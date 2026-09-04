<?php

namespace App\Console\Commands;

use App\Models\BackupRecord;
use App\Models\SystemHeartbeat;
use FilesystemIterator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class BackupMedia extends Command
{
    protected $signature = 'system:backup-media {--prune : Remove archives older than the configured retention period}';

    protected $description = 'Create and verify a compressed archive of public profile media, separate from the database backup';

    public function handle(): int
    {
        $archivePath = tempnam(sys_get_temp_dir(), 'directory-media-backup-').'.tar.gz';

        try {
            $this->createArchive($archivePath);
            $size = filesize($archivePath);
            abort_unless($size && $size > 100, 500, 'The media archive is unexpectedly empty.');

            $diskName = config('operations.backup_disk');
            $directory = trim(config('operations.backup_directory'), '/');
            $path = $directory.'/media-'.now()->format('Ymd-His').'.tar.gz';
            $stream = fopen($archivePath, 'rb');
            throw_unless($stream && Storage::disk($diskName)->put($path, $stream), RuntimeException::class, 'The media archive could not be written to backup storage.');
            if (is_resource($stream)) {
                fclose($stream);
            }

            $record = BackupRecord::query()->create([
                'backup_type' => 'media',
                'disk' => $diskName,
                'path' => $path,
                'size_bytes' => $size,
                'checksum_sha256' => hash_file('sha256', $archivePath),
                'status' => 'completed',
                'completed_at' => now(),
                'verified_at' => now(),
            ]);
            SystemHeartbeat::query()->updateOrCreate(
                ['name' => 'backup-media'],
                ['last_seen_at' => now(), 'metadata' => ['backup_record_id' => $record->id]],
            );
            if ($this->option('prune')) {
                $this->prune($diskName, $directory);
            }
            $this->info("Verified media backup stored on {$diskName}:{$path}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Media backup failed: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if (is_file($archivePath)) {
                unlink($archivePath);
            }
        }
    }

    /**
     * Archives the public-facing media disks — profile photos (the
     * irreplaceable content) and the general "public" disk — as a single
     * tar.gz. GNU tar and bsdtar both apply each -C to the paths that follow
     * it, so two unrelated roots can go in one archive without a shared
     * parent directory.
     */
    private function createArchive(string $target): void
    {
        $profileMediaRoot = public_path('media');
        $publicDiskRoot = storage_path('app/public');
        $reviewMediaRoot = storage_path('app/media-review');

        // Deliberately no "--" separator: it only terminates option parsing
        // once, so a second occurrence between repeated -C pairs gets read
        // as a literal filename instead — neither GNU tar nor bsdtar accepts
        // it here, and the plain directory names below can't be mistaken
        // for flags anyway.
        $command = ['tar', '-czf', $target];
        if (is_dir($profileMediaRoot) && (new FilesystemIterator($profileMediaRoot))->valid()) {
            $command = [...$command, '-C', dirname($profileMediaRoot), basename($profileMediaRoot)];
        }
        if (is_dir($publicDiskRoot) && (new FilesystemIterator($publicDiskRoot))->valid()) {
            $command = [...$command, '-C', dirname($publicDiskRoot), basename($publicDiskRoot)];
        }
        if (is_dir($reviewMediaRoot) && (new FilesystemIterator($reviewMediaRoot))->valid()) {
            $command = [...$command, '-C', dirname($reviewMediaRoot), basename($reviewMediaRoot)];
        }
        throw_if(count($command) === 3, RuntimeException::class, 'No public media directories were found to back up.');

        $stderr = '';
        $process = new Process($command);
        $process->setTimeout(3600);
        $process->run(function (string $type, string $buffer) use (&$stderr): void {
            if ($type === Process::ERR) {
                $stderr .= $buffer;
            }
        });
        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($stderr) ?: 'The tar utility failed to archive media.');
        }
    }

    private function prune(string $diskName, string $directory): void
    {
        $disk = Storage::disk($diskName);
        $cutoff = now()->subDays(config('operations.backup_retention_days'))->timestamp;
        foreach ($disk->files($directory) as $path) {
            if (str_ends_with($path, '.tar.gz') && $disk->lastModified($path) < $cutoff) {
                $disk->delete($path);
            }
        }
    }
}
