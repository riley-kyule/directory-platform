<?php

namespace App\Jobs;

use App\Models\ProfileImage;
use GdImage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProcessProfileImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly int $profileImageId)
    {
        $this->onQueue('media');
    }

    public function handle(): void
    {
        $imageRecord = ProfileImage::query()->findOrFail($this->profileImageId);
        if ($imageRecord->status !== 'quarantined') {
            return;
        }

        $imageRecord->update(['status' => 'processing', 'processing_error' => null]);
        $quarantineDisk = Storage::disk('quarantine');
        $quarantinePath = $imageRecord->storage_directory;
        $sourcePath = $quarantineDisk->path($quarantinePath);
        $bytes = file_get_contents($sourcePath);
        if ($bytes === false) {
            throw new RuntimeException('Quarantined image could not be read.');
        }

        $this->validateEncodedInput($bytes, $sourcePath);
        $source = @imagecreatefromstring($bytes);
        if (! $source instanceof GdImage) {
            throw new RuntimeException('The image decoder rejected the uploaded file.');
        }

        $stagingDirectory = $imageRecord->public_id.'-'.Str::lower(Str::random(10));
        $finalDirectory = substr($imageRecord->public_id, 0, 2).'/'.substr($imageRecord->public_id, 2, 2).'/'.$imageRecord->public_id;
        $stagingDisk = Storage::disk('media_staging');
        $publicDisk = Storage::disk('profile_media');
        $stagingDisk->makeDirectory($stagingDirectory);

        try {
            $derivatives = [];
            $slots = ['thumb' => 320, 'card' => 640, 'profile' => 960, 'full' => 1280];
            foreach ($slots as $slot => $maximumWidth) {
                $derivatives[$slot] = $this->writeDerivative(
                    $source,
                    $stagingDisk->path($stagingDirectory.'/'.$slot.'-'.$maximumWidth.'.webp'),
                    $maximumWidth,
                );
                $derivatives[$slot]['file'] = $slot.'-'.$maximumWidth.'.webp';
            }

            $finalPath = $publicDisk->path($finalDirectory);
            if (is_dir($finalPath)) {
                throw new RuntimeException('A published derivative directory already exists.');
            }
            if (! is_dir(dirname($finalPath)) && ! mkdir(dirname($finalPath), 0755, true) && ! is_dir(dirname($finalPath))) {
                throw new RuntimeException('The public media parent directory could not be created.');
            }
            if (! rename($stagingDisk->path($stagingDirectory), $finalPath)) {
                throw new RuntimeException('The derivative set could not be published atomically.');
            }

            try {
                $imageRecord->update([
                    'storage_directory' => $finalDirectory,
                    'status' => 'pending_review',
                    'mime_type' => 'image/webp',
                    'perceptual_hash' => $this->differenceHash($source),
                    'derivatives' => $derivatives,
                    'processing_error' => null,
                ]);
                $quarantineDisk->delete($quarantinePath);
            } catch (Throwable $exception) {
                $publicDisk->deleteDirectory($finalDirectory);
                throw $exception;
            }
        } finally {
            imagedestroy($source);
            $stagingDisk->deleteDirectory($stagingDirectory);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $image = ProfileImage::query()->find($this->profileImageId);
        if (! $image) {
            return;
        }

        if ($image->status === 'quarantined' || $image->status === 'processing') {
            Storage::disk('quarantine')->delete($image->storage_directory);
        }

        $image->update([
            'status' => 'rejected',
            'processing_error' => Str::limit($exception?->getMessage() ?? 'Image processing failed.', 1000),
        ]);
    }

    private function validateEncodedInput(string $bytes, string $path): void
    {
        $maximumBytes = config('directory.media.maximum_file_kilobytes') * 1024;
        if (strlen($bytes) > $maximumBytes) {
            throw new RuntimeException('The encoded image exceeds the file-size limit.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (! in_array($mime, config('directory.media.accepted_mime_types'), true)) {
            throw new RuntimeException('The actual image format is not allowed.');
        }

        if (($mime === 'image/png' && str_contains($bytes, 'acTL'))
            || ($mime === 'image/webp' && (str_contains($bytes, 'ANIM') || str_contains($bytes, 'ANMF')))) {
            throw new RuntimeException('Animated images are not accepted.');
        }

        $dimensions = @getimagesizefromstring($bytes);
        if (! $dimensions) {
            throw new RuntimeException('Image dimensions could not be read.');
        }

        [$width, $height] = $dimensions;
        if ($width < config('directory.media.minimum_width') || $height < config('directory.media.minimum_height')) {
            throw new RuntimeException('The image dimensions are below the minimum.');
        }
        if ($width > config('directory.media.maximum_dimension') || $height > config('directory.media.maximum_dimension')) {
            throw new RuntimeException('The image dimensions exceed the maximum.');
        }
        if ($width * $height > config('directory.media.maximum_pixels')) {
            throw new RuntimeException('The decoded image contains too many pixels.');
        }

        $ratio = $width / $height;
        if ($ratio < config('directory.media.minimum_aspect_ratio') || $ratio > config('directory.media.maximum_aspect_ratio')) {
            throw new RuntimeException('The image aspect ratio is outside the allowed range.');
        }
    }

    /** @return array{width: int, height: int, size: int} */
    private function writeDerivative(GdImage $source, string $destination, int $maximumWidth): array
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $width = min($maximumWidth, $sourceWidth);
        $height = max(1, (int) round($sourceHeight * ($width / $sourceWidth)));
        $derivative = imagecreatetruecolor($width, $height);
        imagealphablending($derivative, false);
        imagesavealpha($derivative, true);
        $transparent = imagecolorallocatealpha($derivative, 0, 0, 0, 127);
        imagefill($derivative, 0, 0, $transparent);

        if (! imagecopyresampled($derivative, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight)
            || ! imagewebp($derivative, $destination, config('directory.media.webp_quality'))) {
            imagedestroy($derivative);
            throw new RuntimeException('A required WebP derivative could not be encoded.');
        }
        imagedestroy($derivative);

        return ['width' => $width, 'height' => $height, 'size' => filesize($destination) ?: 0];
    }

    private function differenceHash(GdImage $source): string
    {
        $sample = imagecreatetruecolor(9, 8);
        imagecopyresampled($sample, $source, 0, 0, 0, 0, 9, 8, imagesx($source), imagesy($source));
        $bits = '';

        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $left = imagecolorsforindex($sample, imagecolorat($sample, $x, $y));
                $right = imagecolorsforindex($sample, imagecolorat($sample, $x + 1, $y));
                $leftBrightness = $left['red'] + $left['green'] + $left['blue'];
                $rightBrightness = $right['red'] + $right['green'] + $right['blue'];
                $bits .= $leftBrightness > $rightBrightness ? '1' : '0';
            }
        }
        imagedestroy($sample);

        return implode('', array_map(fn (string $chunk) => dechex(bindec($chunk)), str_split($bits, 4)));
    }
}
