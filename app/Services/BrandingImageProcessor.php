<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class BrandingImageProcessor
{
    private const MAXIMUM_PIXELS = 20_000_000;

    /** @return array{contents: string, extension: string} */
    public function process(UploadedFile $file, string $type): array
    {
        [$targetWidth, $targetHeight] = match ($type) {
            'logo' => [600, 180],
            'favicon' => [512, 512],
            default => throw new \InvalidArgumentException("Unknown branding image type: {$type}"),
        };

        $path = $file->getRealPath();
        $bytes = $path !== false ? file_get_contents($path) : false;
        $dimensions = $bytes !== false ? @getimagesizefromstring($bytes) : false;
        $mime = $path !== false ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : false;

        if ($bytes === false || $dimensions === false || ! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            throw ValidationException::withMessages([$type => 'Upload a valid PNG, JPEG, or WebP image.']);
        }

        [$sourceWidth, $sourceHeight] = $dimensions;
        if ($sourceWidth < 1 || $sourceHeight < 1 || $sourceWidth * $sourceHeight > self::MAXIMUM_PIXELS) {
            throw ValidationException::withMessages([$type => 'The image dimensions are invalid or too large to process safely.']);
        }

        $source = @imagecreatefromstring($bytes);
        if (! $source instanceof GdImage) {
            throw ValidationException::withMessages([$type => 'The uploaded image could not be decoded.']);
        }

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if (! $canvas instanceof GdImage) {
            imagedestroy($source);
            throw ValidationException::withMessages([$type => 'The image could not be prepared.']);
        }

        try {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefill($canvas, 0, 0, $transparent);

            $scale = min($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
            $width = max(1, (int) round($sourceWidth * $scale));
            $height = max(1, (int) round($sourceHeight * $scale));
            $x = intdiv($targetWidth - $width, 2);
            $y = intdiv($targetHeight - $height, 2);

            if (! imagecopyresampled($canvas, $source, $x, $y, 0, 0, $width, $height, $sourceWidth, $sourceHeight)) {
                throw ValidationException::withMessages([$type => 'The image could not be resized.']);
            }

            ob_start();
            $encoded = imagepng($canvas, null, 8);
            $contents = ob_get_clean();
            if (! $encoded || ! is_string($contents) || $contents === '') {
                throw ValidationException::withMessages([$type => 'The processed image could not be encoded.']);
            }

            return ['contents' => $contents, 'extension' => 'png'];
        } finally {
            imagedestroy($canvas);
            imagedestroy($source);
        }
    }
}
