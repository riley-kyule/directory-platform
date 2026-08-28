<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Filesystem moves for the media pipeline that survive a cPanel layout where
 * storage/ and public/ sit on different devices. A plain rename() throws
 * "Invalid cross-device link" there, so every move falls back to a recursive
 * copy + delete when rename() fails.
 */
class MediaFilesystem
{
    /**
     * Move a directory from one absolute path to another. The destination must
     * not already exist. Tries rename() first, then copy + delete.
     */
    public static function moveDirectory(string $from, string $to): void
    {
        if (! is_dir($from)) {
            throw new RuntimeException("Source directory is missing: {$from}");
        }
        if (is_dir($to) || is_file($to)) {
            throw new RuntimeException("Destination already exists: {$to}");
        }

        self::ensureParentDirectory($to);

        if (@rename($from, $to)) {
            return;
        }

        if (! File::copyDirectory($from, $to)) {
            throw new RuntimeException("Directory could not be copied to {$to}");
        }
        File::deleteDirectory($from);
    }

    /**
     * Move a single file from one absolute path to another, creating the
     * destination directory as needed. Tries rename() first, then copy + delete.
     */
    public static function moveFile(string $from, string $to): void
    {
        if (! is_file($from)) {
            throw new RuntimeException("Source file is missing: {$from}");
        }

        self::ensureParentDirectory($to);

        if (@rename($from, $to)) {
            return;
        }

        if (! @copy($from, $to)) {
            throw new RuntimeException("File could not be copied to {$to}");
        }
        @unlink($from);
    }

    public static function ensureParentDirectory(string $absolutePath): void
    {
        $parent = dirname($absolutePath);
        if (! is_dir($parent) && ! mkdir($parent, 0755, true) && ! is_dir($parent)) {
            throw new RuntimeException("Directory could not be created: {$parent}");
        }
    }

    public static function ensureDirectory(string $absolutePath): void
    {
        if (! is_dir($absolutePath) && ! mkdir($absolutePath, 0755, true) && ! is_dir($absolutePath)) {
            throw new RuntimeException("Directory could not be created: {$absolutePath}");
        }
    }
}
