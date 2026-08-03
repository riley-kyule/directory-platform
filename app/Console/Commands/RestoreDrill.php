<?php

namespace App\Console\Commands;

use App\Models\BackupRecord;
use App\Models\SystemHeartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Restores the latest verified backup into an isolated scratch database and
 * checks it, closing the loop on "a backup exists" vs "a backup is actually
 * restorable" — the distinction the project's own runbook calls out.
 *
 * Deliberately does not run the PHPUnit suite against the restored data (the
 * README's manual runbook step for a human-supervised isolated environment);
 * this instead does a bounded, safe integrity check — schema present via a
 * dry-run migration check, and row counts on a handful of core tables.
 */
class RestoreDrill extends Command
{
    protected $signature = 'system:restore-drill';

    protected $description = 'Restore the latest verified backup into an isolated connection and verify it';

    private const CORE_TABLES = ['users', 'profiles', 'locations', 'audit_logs'];

    public function handle(): int
    {
        $connectionName = 'restore_drill';
        $target = config("database.connections.{$connectionName}");

        if (empty($target['database'])) {
            $this->error('Configure OPS_RESTORE_DRILL_DB_* environment variables (a database distinct from production) before running a restore drill.');

            return self::FAILURE;
        }

        if ($this->targetsSameDatabase($target)) {
            $this->error('The restore_drill connection resolves to the same host and database as the default connection. Refusing to run.');

            return self::FAILURE;
        }

        $backup = BackupRecord::query()->whereNotNull('verified_at')->latest('completed_at')->first();
        if (! $backup) {
            $this->error('No verified backup is available to restore.');

            return self::FAILURE;
        }

        $archivePath = tempnam(sys_get_temp_dir(), 'directory-restore-').'.sql.gz';
        $sqlPath = substr($archivePath, 0, -3);

        try {
            $this->download($backup, $archivePath);
            $this->verifyChecksum($archivePath, $backup->checksum_sha256);
            $this->decompress($archivePath, $sqlPath);
            $this->resetTarget($connectionName, $target);
            $this->import($target, $sqlPath);

            $pendingMigrations = $this->pendingMigrations($connectionName);
            $tableCounts = $this->tableCounts($connectionName);

            SystemHeartbeat::query()->updateOrCreate(['name' => 'restore_drill'], [
                'last_seen_at' => now(),
                'metadata' => [
                    'backup_record_id' => $backup->id,
                    'backup_completed_at' => $backup->completed_at->toIso8601String(),
                    'pending_migrations' => $pendingMigrations,
                    'table_counts' => $tableCounts,
                ],
            ]);

            $this->info("Restored backup #{$backup->id} into [{$connectionName}] and verified it.");
            foreach ($tableCounts as $table => $count) {
                $this->line("  {$table}: {$count} rows");
            }
            if ($pendingMigrations > 0) {
                $this->warn("{$pendingMigrations} migration(s) are pending against the restored schema — review before trusting this backup for a real recovery.");
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Restore drill failed: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            DB::purge($connectionName);
            foreach ([$archivePath, $sqlPath] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    /** @param  array<string, mixed>  $target */
    private function targetsSameDatabase(array $target): bool
    {
        $default = config('database.connections.'.config('database.default'));

        if (($default['driver'] ?? null) === 'sqlite') {
            // Compare canonical paths, not raw strings: a relative
            // "database/database.sqlite" and the default connection's
            // absolute path to that same file must be recognised as
            // identical, or this guard is trivially bypassed by accident.
            return ($target['driver'] ?? null) === 'sqlite'
                && $this->resolveSqlitePath($target['database']) === $this->resolveSqlitePath($default['database']);
        }

        return ($target['host'] ?? null) === ($default['host'] ?? null)
            && ($target['port'] ?? null) === ($default['port'] ?? null)
            && $target['database'] === ($default['database'] ?? null);
    }

    private function resolveSqlitePath(string $path): string
    {
        return realpath($path) ?: (str_starts_with($path, '/') ? $path : base_path($path));
    }

    private function download(BackupRecord $backup, string $target): void
    {
        $disk = Storage::disk($backup->disk);
        throw_unless($disk->exists($backup->path), RuntimeException::class, 'The recorded backup archive no longer exists on its disk.');

        $stream = $disk->readStream($backup->path);
        $destination = fopen($target, 'wb');
        throw_unless($stream && $destination, RuntimeException::class, 'The backup archive could not be downloaded for verification.');
        stream_copy_to_stream($stream, $destination);
        fclose($stream);
        fclose($destination);
    }

    private function verifyChecksum(string $archivePath, string $expected): void
    {
        throw_unless(hash_equals($expected, hash_file('sha256', $archivePath)), RuntimeException::class, 'The downloaded archive does not match its recorded checksum.');
    }

    private function decompress(string $source, string $target): void
    {
        $input = gzopen($source, 'rb');
        $output = fopen($target, 'wb');
        throw_unless($input && $output, RuntimeException::class, 'The backup archive could not be decompressed.');
        while (! gzeof($input)) {
            fwrite($output, gzread($input, 1024 * 1024));
        }
        gzclose($input);
        fclose($output);
    }

    /** @param  array<string, mixed>  $target */
    private function resetTarget(string $connectionName, array $target): void
    {
        match ($target['driver']) {
            'sqlite' => $this->resetSqlite($target['database']),
            'pgsql' => $this->runNativeCommand([
                'psql', '--host='.$target['host'], '--port='.(string) $target['port'],
                '--username='.$target['username'], '--dbname='.$target['database'],
                '-v', 'ON_ERROR_STOP=1', '-c', 'DROP SCHEMA public CASCADE; CREATE SCHEMA public;',
            ], ['PGPASSWORD' => $target['password']]),
            'mysql', 'mariadb' => $this->resetMysql($connectionName, $target),
            default => throw new RuntimeException("Unsupported restore_drill driver: {$target['driver']}"),
        };
    }

    private function resetSqlite(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
        touch($path);
    }

    /** @param  array<string, mixed>  $target */
    private function resetMysql(string $connectionName, array $target): void
    {
        $tables = DB::connection($connectionName)->select('show tables');
        if ($tables === []) {
            return;
        }
        $column = 'Tables_in_'.$target['database'];
        $dropStatements = collect($tables)->map(fn ($row) => 'DROP TABLE IF EXISTS `'.$row->$column.'`;')->implode(' ');
        DB::connection($connectionName)->statement('SET FOREIGN_KEY_CHECKS=0; '.$dropStatements.' SET FOREIGN_KEY_CHECKS=1;');
    }

    /** @param  array<string, mixed>  $target */
    private function import(array $target, string $sqlPath): void
    {
        match ($target['driver']) {
            'sqlite' => $this->runNativeCommand(['sqlite3', $target['database']], [], $sqlPath),
            'pgsql' => $this->runNativeCommand([
                'psql', '--host='.$target['host'], '--port='.(string) $target['port'],
                '--username='.$target['username'], '--dbname='.$target['database'], '-v', 'ON_ERROR_STOP=1',
            ], ['PGPASSWORD' => $target['password']], $sqlPath),
            'mysql', 'mariadb' => $this->runNativeCommand([
                'mysql', '--host='.$target['host'], '--port='.(string) $target['port'],
                '--user='.$target['username'], $target['database'],
            ], ['MYSQL_PWD' => $target['password']], $sqlPath),
            default => throw new RuntimeException("Unsupported restore_drill driver: {$target['driver']}"),
        };
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $environment
     */
    private function runNativeCommand(array $command, array $environment = [], ?string $inputPath = null): void
    {
        $process = new Process($command, null, $environment);
        $process->setTimeout(3600);
        if ($inputPath) {
            $handle = fopen($inputPath, 'rb');
            throw_unless($handle, RuntimeException::class, 'The import input file could not be opened.');
            $process->setInput($handle);
        }
        $process->run();
        if (isset($handle) && is_resource($handle)) {
            fclose($handle);
        }
        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'A native database utility failed during the restore drill.');
        }
    }

    /**
     * Counts migration files not yet recorded against the restored database,
     * rather than parsing `migrate --pretend` text output. Still runs the
     * pretend migration for its human-readable console output.
     */
    private function pendingMigrations(string $connectionName): int
    {
        Artisan::call('migrate', ['--database' => $connectionName, '--pretend' => true, '--force' => true]);
        $this->line(Artisan::output());

        $ran = DB::connection($connectionName)->table('migrations')->pluck('migration')->all();

        return collect(glob(database_path('migrations/*.php')) ?: [])
            ->map(fn (string $path) => basename($path, '.php'))
            ->reject(fn (string $migration) => in_array($migration, $ran, true))
            ->count();
    }

    /** @return array<string, int> */
    private function tableCounts(string $connectionName): array
    {
        return collect(self::CORE_TABLES)
            ->mapWithKeys(fn (string $table) => [$table => DB::connection($connectionName)->table($table)->count()])
            ->all();
    }
}
