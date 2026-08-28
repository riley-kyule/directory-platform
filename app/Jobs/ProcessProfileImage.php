<?php

namespace App\Jobs;

use App\Models\ProfileImage;
use App\Services\DirectorySettings;
use App\Services\ProfileImageVisibility;
use App\Support\MediaFilesystem;
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
        $imageRecord = ProfileImage::withTrashed()->with('profile')->find($this->profileImageId);
        // A retry re-dispatches this job for an image that previously failed; pick
        // those up too, as long as the quarantine upload is still on disk.
        if (! $imageRecord || $imageRecord->trashed() || ! in_array($imageRecord->status, ['quarantined', 'rejected'], true)) {
            return;
        }

        $quarantineDisk = Storage::disk('quarantine');
        $quarantinePath = $this->quarantinePath($imageRecord);
        if (! $quarantineDisk->exists($quarantinePath)) {
            $imageRecord->update([
                'status' => 'rejected',
                'processing_error' => 'The uploaded file is no longer available. Please upload it again.',
            ]);

            return;
        }

        $imageRecord->update(['status' => 'processing', 'processing_error' => null]);
        $sourcePath = $quarantineDisk->path($quarantinePath);
        $bytes = file_get_contents($sourcePath);
        if ($bytes === false) {
            throw new RuntimeException('Quarantined image could not be read.');
        }

        $this->validateEncodedInput($bytes, $sourcePath);
        $this->guardDecodeMemory($imageRecord);
        $source = @imagecreatefromstring($bytes);
        if (! $source instanceof GdImage) {
            throw new RuntimeException('The uploaded image could not be decoded. Try re-saving it as a standard JPEG or PNG.');
        }

        $stagingDirectory = $imageRecord->public_id.'-'.Str::lower(Str::random(10));
        $finalDirectory = substr($imageRecord->public_id, 0, 2).'/'.substr($imageRecord->public_id, 2, 2).'/'.$imageRecord->public_id;
        $stagingDisk = Storage::disk('media_staging');
        $reviewDisk = Storage::disk('media_review');
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

            $finalPath = $reviewDisk->path($finalDirectory);
            if (is_dir($finalPath)) {
                // Left over from an earlier run that failed before the DB update.
                // A genuine pending/approved image never reaches this job again.
                $reviewDisk->deleteDirectory($finalDirectory);
            }
            MediaFilesystem::moveDirectory($stagingDisk->path($stagingDirectory), $finalPath);

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
                $reviewDisk->deleteDirectory($finalDirectory);
                throw $exception;
            }
        } finally {
            imagedestroy($source);
            $stagingDisk->deleteDirectory($stagingDirectory);
        }

        // No moderation hold: on a live profile the photo goes public as soon as
        // it is processed. On a draft it waits in pending_review until the
        // profile is activated (PublishProfileImages handles that batch).
        $profile = $imageRecord->profile;
        if ($profile && $profile->status->isPublic()) {
            app(ProfileImageVisibility::class)->publishImages($profile);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $image = ProfileImage::query()->with('profile')->find($this->profileImageId);
        if (! $image) {
            return;
        }

        // The last successful run renames storage_directory to the published path,
        // so never derive the quarantine location from it — recompute it.
        Storage::disk('quarantine')->delete($this->quarantinePath($image));

        $image->update([
            'status' => 'rejected',
            'processing_error' => Str::limit($exception?->getMessage() ?? 'Image processing failed.', 1000),
        ]);
    }

    private function quarantinePath(ProfileImage $image): string
    {
        return ($image->profile?->public_id ?? 'orphan').'/'.$image->public_id.'.upload';
    }

    /**
     * GD decodes the whole bitmap into RAM. Bump the memory ceiling to cover the
     * decoded pixels plus resample headroom, and fail with a clear message rather
     * than let the worker hit a fatal OOM (which surfaces as a silent rejection).
     */
    private function guardDecodeMemory(ProfileImage $image): void
    {
        $pixels = max(1, (int) $image->width * (int) $image->height);
        // ~4 bytes/pixel for the source truecolor image plus a full-size resample copy.
        $requiredBytes = (int) ($pixels * 4 * 2.4) + 16 * 1024 * 1024;
        $ceilingBytes = app(DirectorySettings::class)->integer('media.processing_memory_limit_mb') * 1024 * 1024;
        $target = min($ceilingBytes, $requiredBytes + memory_get_usage(true));

        if ($this->memoryLimitBytes() < $target) {
            @ini_set('memory_limit', (string) $target);
        }

        if ($requiredBytes + memory_get_usage(true) > $this->memoryLimitBytes()) {
            throw new RuntimeException('This image is too large to process on the server. Please upload a smaller version (fewer megapixels).');
        }
    }

    private function memoryLimitBytes(): int
    {
        $limit = trim((string) ini_get('memory_limit'));
        if ($limit === '' || $limit === '-1') {
            return PHP_INT_MAX;
        }

        $unit = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    private function validateEncodedInput(string $bytes, string $path): void
    {
        $settings = app(DirectorySettings::class);
        $maximumBytes = $settings->integer('media.maximum_file_kilobytes') * 1024;
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
        $minWidth = $settings->integer('media.minimum_width');
        $minHeight = $settings->integer('media.minimum_height');
        if ($width < $minWidth || $height < $minHeight) {
            throw new RuntimeException("This photo is {$width}×{$height}px, smaller than the {$minWidth}×{$minHeight}px minimum. Please upload a larger version.");
        }
        $maxDimension = $settings->integer('media.maximum_dimension');
        if ($width > $maxDimension || $height > $maxDimension) {
            throw new RuntimeException("This photo is {$width}×{$height}px, larger than the {$maxDimension}px limit on a side. Please resize it and upload again.");
        }
        if ($width * $height > $settings->integer('media.maximum_pixels')) {
            throw new RuntimeException('The decoded image contains too many pixels.');
        }

        $ratio = $width / $height;
        if ($ratio < $settings->float('media.minimum_aspect_ratio') || $ratio > $settings->float('media.maximum_aspect_ratio')) {
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
            || ! imagewebp($derivative, $destination, app(DirectorySettings::class)->integer('media.webp_quality'))) {
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
