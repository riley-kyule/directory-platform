<?php

namespace Tests\Unit;

use App\Support\MediaFilesystem;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MediaFilesystemTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/media-fs-'.uniqid();
        mkdir($this->base, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->base);
        parent::tearDown();
    }

    public function test_move_directory_relocates_contents_and_removes_the_source(): void
    {
        $from = $this->base.'/from';
        mkdir($from.'/nested', 0755, true);
        file_put_contents($from.'/a.txt', 'one');
        file_put_contents($from.'/nested/b.txt', 'two');

        MediaFilesystem::moveDirectory($from, $this->base.'/deep/to');

        $this->assertDirectoryDoesNotExist($from);
        $this->assertSame('one', file_get_contents($this->base.'/deep/to/a.txt'));
        $this->assertSame('two', file_get_contents($this->base.'/deep/to/nested/b.txt'));
    }

    public function test_move_directory_refuses_an_existing_destination(): void
    {
        mkdir($this->base.'/from', 0755, true);
        mkdir($this->base.'/to', 0755, true);

        $this->expectException(RuntimeException::class);
        MediaFilesystem::moveDirectory($this->base.'/from', $this->base.'/to');
    }

    public function test_move_file_creates_missing_parent_directories(): void
    {
        file_put_contents($this->base.'/source.bin', 'payload');

        MediaFilesystem::moveFile($this->base.'/source.bin', $this->base.'/x/y/z/dest.bin');

        $this->assertFileDoesNotExist($this->base.'/source.bin');
        $this->assertSame('payload', file_get_contents($this->base.'/x/y/z/dest.bin'));
    }

    private function deleteTree(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        foreach (scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path.'/'.$entry;
            is_dir($full) ? $this->deleteTree($full) : @unlink($full);
        }
        @rmdir($path);
    }
}
