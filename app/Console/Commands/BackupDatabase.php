<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Writes a timestamped backup of the database to storage/app/backups.
 *
 * SQLite uses "VACUUM INTO", which produces a consistent copy even while the
 * app is running. MySQL/MariaDB and PostgreSQL shell out to mysqldump/pg_dump.
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup
        {--connection= : Database connection (defaults to the app default)}
        {--path= : Directory to write to (defaults to storage/app/backups)}
        {--keep=7 : Number of backups to keep for this connection, 0 keeps all}';

    protected $description = 'Back up the database to a timestamped file';

    public function handle(): int
    {
        $name = $this->option('connection') ?: config('database.default');
        $config = config("database.connections.$name");

        if (! $config) {
            $this->components->error("Unknown database connection [$name].");

            return self::FAILURE;
        }

        $directory = $this->option('path') ?: storage_path('app/backups');
        File::ensureDirectoryExists($directory);

        $prefix = "$name-";
        $target = $directory.DIRECTORY_SEPARATOR.$prefix.now()->format('Y-m-d_His').'.'.$this->extension($config['driver']);

        try {
            match ($config['driver']) {
                'sqlite' => $this->sqlite($name, $target),
                'mysql', 'mariadb' => $this->mysql($config, $target),
                'pgsql' => $this->pgsql($config, $target),
                default => throw new RuntimeException("Backups are not supported for the [{$config['driver']}] driver."),
            };

            clearstatcache(true, $target);
            if (! is_file($target) || filesize($target) === 0) {
                throw new RuntimeException('the backup file is missing or empty');
            }
        } catch (\Throwable $e) {
            File::delete($target);
            $this->components->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Backup written to %s (%s KB).', $target, number_format(filesize($target) / 1024, 1)));
        $this->prune($directory, $prefix, (int) $this->option('keep'));

        return self::SUCCESS;
    }

    private function sqlite(string $connection, string $target): void
    {
        $database = config("database.connections.$connection.database");
        if ($database === ':memory:') {
            throw new RuntimeException('An in-memory SQLite database cannot be backed up.');
        }

        DB::connection($connection)->statement('VACUUM INTO ?', [$target]);
    }

    private function mysql(array $config, string $target): void
    {
        $this->dump([
            'mysqldump', '--single-transaction', '--routines', '--no-tablespaces',
            '--host='.$config['host'], '--port='.$config['port'], '--user='.$config['username'],
            '--result-file='.$target, $config['database'],
        ], ['MYSQL_PWD' => (string) $config['password']]);
    }

    private function pgsql(array $config, string $target): void
    {
        $this->dump([
            'pg_dump', '--format=custom', '--no-owner',
            '--host='.$config['host'], '--port='.$config['port'], '--username='.$config['username'],
            '--file='.$target, $config['database'],
        ], ['PGPASSWORD' => (string) $config['password']]);
    }

    /** Passwords go through the environment, never the command line. */
    private function dump(array $command, array $env): void
    {
        $result = Process::env($env)->timeout(600)->run($command);

        if ($result->failed()) {
            throw new RuntimeException(trim($result->errorOutput()) ?: 'the dump command failed');
        }
    }

    private function extension(string $driver): string
    {
        return match ($driver) {
            'sqlite' => 'sqlite',
            'pgsql' => 'dump',
            default => 'sql',
        };
    }

    private function prune(string $directory, string $prefix, int $keep): void
    {
        if ($keep <= 0) {
            return;
        }

        $old = collect(File::files($directory))
            ->filter(fn ($file) => str_starts_with($file->getFilename(), $prefix))
            ->sortByDesc(fn ($file) => $file->getFilename())
            ->slice($keep);

        $old->each(fn ($file) => File::delete($file->getPathname()));

        if ($old->isNotEmpty()) {
            $this->components->info("Removed {$old->count()} old backup(s).");
        }
    }
}
