<?php

namespace App\Services;

use GdImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PwaIconService
{
    public function __construct(private readonly DirectorySettings $settings) {}

    public function render(int $size, bool $maskable = false): string
    {
        if (! in_array($size, [180, 192, 512], true)) {
            throw new \InvalidArgumentException('Unsupported PWA icon size.');
        }

        $canvas = imagecreatetruecolor($size, $size);
        if (! $canvas instanceof GdImage) {
            throw new RuntimeException('The PWA icon canvas could not be created.');
        }

        try {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $background = imagecolorallocate($canvas, 23, 23, 23);
            imagefill($canvas, 0, 0, $background);
            imagealphablending($canvas, true);

            $source = $this->brandingSource();
            if ($source instanceof GdImage) {
                try {
                    $sourceWidth = imagesx($source);
                    $sourceHeight = imagesy($source);
                    $target = (int) round($size * ($maskable ? 0.64 : 0.86));
                    $scale = min($target / $sourceWidth, $target / $sourceHeight);
                    $width = max(1, (int) round($sourceWidth * $scale));
                    $height = max(1, (int) round($sourceHeight * $scale));
                    imagecopyresampled(
                        $canvas,
                        $source,
                        intdiv($size - $width, 2),
                        intdiv($size - $height, 2),
                        0,
                        0,
                        $width,
                        $height,
                        $sourceWidth,
                        $sourceHeight,
                    );
                } finally {
                    imagedestroy($source);
                }
            } else {
                $this->drawFallbackMark($canvas, $size, $maskable);
            }

            ob_start();
            $encoded = imagepng($canvas, null, 8);
            $contents = ob_get_clean();
            if (! $encoded || ! is_string($contents) || $contents === '') {
                throw new RuntimeException('The PWA icon could not be encoded.');
            }

            return $contents;
        } finally {
            imagedestroy($canvas);
        }
    }

    private function brandingSource(): ?GdImage
    {
        $path = $this->settings->string('site.favicon_path');
        if ($path === '' || ! Storage::disk('branding')->exists($path)) {
            return null;
        }

        $bytes = Storage::disk('branding')->get($path);
        $image = @imagecreatefromstring($bytes);

        return $image instanceof GdImage ? $image : null;
    }

    private function drawFallbackMark(GdImage $canvas, int $size, bool $maskable): void
    {
        $rose = imagecolorallocate($canvas, 244, 63, 94);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $diameter = (int) round($size * ($maskable ? 0.58 : 0.72));
        $center = intdiv($size, 2);
        imagefilledellipse($canvas, $center, $center, $diameter, $diameter, $rose);
        imagefilledellipse($canvas, $center, $center, (int) round($diameter * 0.34), (int) round($diameter * 0.34), $white);
        imagefilledellipse($canvas, $center, $center, (int) round($diameter * 0.16), (int) round($diameter * 0.16), $rose);
    }
}
