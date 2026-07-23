<?php

namespace App\Console\Commands;

use App\Models\BackupRecord;
use App\Models\SystemHeartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'system:backup {--prune : Remove archives older than the configured retention period}';

    protected $description = 'Create and verify a compressed private database backup using the native database utility';

    public function handle(): int
    {
        $dumpPath = tempnam(sys_get_temp_dir(), 'directory-backup-');
        $archivePath = $dumpPath.'.sql.gz';

        try {
            $this->createDump($dumpPath);
            $this->compress($dumpPath, $archivePath);
            $size = filesize($archivePath);
            abort_unless($size && $size > 100, 500, 'The backup archive is unexpectedly empty.');
            $this->verifyArchive($archivePath);

            $diskName = config('operations.backup_disk');
            $directory = trim(config('operations.backup_directory'), '/');
            $path = $directory.'/database-'.now()->format('Ymd-His').'.sql.gz';
            $stream = fopen($archivePath, 'rb');
            throw_unless($stream && Storage::disk($diskName)->put($path, $stream), RuntimeException::class, 'The archive could not be written to backup storage.');
            if (is_resource($stream)) {
                fclose($stream);
            }

            $record = BackupRecord::query()->create([
                'disk' => $diskName,
                'path' => $path,
                'size_bytes' => $size,
                'checksum_sha256' => hash_file('sha256', $archivePath),
                'status' => 'completed',
                'completed_at' => now(),
                'verified_at' => now(),
            ]);
            SystemHeartbeat::query()->updateOrCreate(
                ['name' => 'backup'],
                ['last_seen_at' => now(), 'metadata' => ['backup_record_id' => $record->id]],
            );
            if ($this->option('prune')) {
                $this->prune($diskName, $directory);
            }
            $this->info("Verified backup stored on {$diskName}:{$path}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Backup failed: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if (is_file($dumpPath)) {
                unlink($dumpPath);
            }
            if (is_file($archivePath)) {
                unlink($archivePath);
            }
        }
    }

    private function createDump(string $target): void
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");
        $driver = $config['driver'];
        $environment = [];

        $command = match ($driver) {
            'sqlite' => ['sqlite3', $config['database'], '.dump'],
            'mysql', 'mariadb' => [
                'mysqldump', '--single-transaction', '--quick', '--skip-lock-tables',
                '--host='.$config['host'], '--port='.(string) $config['port'],
                '--user='.$config['username'], $config['database'],
            ],
            'pgsql' => [
                'pg_dump', '--no-owner', '--no-privileges',
                '--host='.$config['host'], '--port='.(string) $config['port'],
                '--username='.$config['username'], $config['database'],
            ],
            default => throw new RuntimeException("Unsupported database driver: {$driver}"),
        };
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $environment['MYSQL_PWD'] = $config['password'];
        } elseif ($driver === 'pgsql') {
            $environment['PGPASSWORD'] = $config['password'];
        }

        $handle = fopen($target, 'wb');
        throw_unless($handle, RuntimeException::class, 'A temporary dump file could not be created.');
        $stderr = '';
        $process = new Process($command, null, $environment);
        $process->setTimeout(3600);
        $process->run(function (string $type, string $buffer) use ($handle, &$stderr): void {
            if ($type === Process::OUT) {
                fwrite($handle, $buffer);
            } else {
                $stderr .= $buffer;
            }
        });
        fclose($handle);
        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($stderr) ?: 'The native database dump utility failed.');
        }
    }

    private function compress(string $source, string $target): void
    {
        $input = fopen($source, 'rb');
        $output = gzopen($target, 'wb9');
        throw_unless($input && $output, RuntimeException::class, 'Backup compression could not start.');
        while (! feof($input)) {
            gzwrite($output, fread($input, 1024 * 1024));
        }
        fclose($input);
        gzclose($output);
    }

    private function verifyArchive(string $path): void
    {
        $handle = gzopen($path, 'rb');
        throw_unless($handle, RuntimeException::class, 'The compressed archive cannot be opened.');
        $bytes = 0;
        while (! gzeof($handle)) {
            $bytes += strlen(gzread($handle, 1024 * 1024));
        }
        gzclose($handle);
        throw_unless($bytes > 100, RuntimeException::class, 'The compressed archive failed its content check.');
    }

    private function prune(string $diskName, string $directory): void
    {
        $disk = Storage::disk($diskName);
        $cutoff = now()->subDays(config('operations.backup_retention_days'))->timestamp;
        foreach ($disk->files($directory) as $path) {
            if (str_ends_with($path, '.sql.gz') && $disk->lastModified($path) < $cutoff) {
                $disk->delete($path);
            }
        }
    }
}
