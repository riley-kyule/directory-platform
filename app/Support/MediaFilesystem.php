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
            self::normalizePermissions($to);

            return;
        }

        if (! File::copyDirectory($from, $to)) {
            throw new RuntimeException("Directory could not be copied to {$to}");
        }
        File::deleteDirectory($from);
        self::normalizePermissions($to);
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
            @chmod($to, 0o644);

            return;
        }

        if (! @copy($from, $to)) {
            throw new RuntimeException("File could not be copied to {$to}");
        }
        @unlink($from);
        @chmod($to, 0o644);
    }

    public static function ensureParentDirectory(string $absolutePath): void
    {
        $parent = dirname($absolutePath);
        if (! is_dir($parent) && ! mkdir($parent, 0755, true) && ! is_dir($parent)) {
            throw new RuntimeException("Directory could not be created: {$parent}");
        }
        // mkdir() is subject to the process umask (077 on the queue worker), so
        // the shard dirs it just made can be 0700 and block the web server.
        // Re-open the two shard levels ("ab/cd/") that the media layout uses.
        for ($dir = $parent, $i = 0; $i < 2 && is_dir($dir); $dir = dirname($dir), $i++) {
            @chmod($dir, 0o755);
        }
    }

    /**
     * Force the whole tree to owner-write / world-read+traverse. rename()
     * carries the source directory's mode into public/, and the queue worker
     * often runs with a 077 umask, so without this the media folders land as
     * 0700 and the web server 404s every file inside them.
     */
    public static function normalizePermissions(string $absoluteDir): void
    {
        if (! is_dir($absoluteDir)) {
            return;
        }

        @chmod($absoluteDir, 0o755);
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absoluteDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($items as $item) {
            @chmod($item->getPathname(), $item->isDir() ? 0o755 : 0o644);
        }
    }

    public static function ensureDirectory(string $absolutePath): void
    {
        if (! is_dir($absolutePath) && ! mkdir($absolutePath, 0755, true) && ! is_dir($absolutePath)) {
            throw new RuntimeException("Directory could not be created: {$absolutePath}");
        }
    }
}
