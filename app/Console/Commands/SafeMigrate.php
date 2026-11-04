<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;

/**
 * "migrate", but only after a successful backup. Does nothing (and makes no
 * backup) when there are no pending migrations.
 */
class SafeMigrate extends Command
{
    protected $signature = 'migrate:safe
        {--database= : Database connection to migrate}
        {--force : Run in production without asking}
        {--step : Run each migration as its own batch}';

    protected $description = 'Back up the database, then run pending migrations';

    public function handle(Migrator $migrator): int
    {
        $connection = $this->option('database') ?: config('database.default');

        $pending = $this->pendingMigrations($migrator, $connection);
        if ($pending === 0) {
            $this->components->info('Nothing to migrate. No backup was made.');

            return self::SUCCESS;
        }

        $this->components->info("$pending pending migration(s). Backing up first…");

        if ($this->call('db:backup', ['--connection' => $connection]) !== self::SUCCESS) {
            $this->components->error('Backup failed, migrations were NOT run.');

            return self::FAILURE;
        }

        return $this->call('migrate', array_filter([
            '--database' => $connection,
            '--force' => $this->option('force'),
            '--step' => $this->option('step'),
        ]));
    }

    private function pendingMigrations(Migrator $migrator, string $connection): int
    {
        return $migrator->usingConnection($connection, function () use ($migrator) {
            $files = $migrator->getMigrationFiles([database_path('migrations'), ...$migrator->paths()]);

            if (! $migrator->repositoryExists()) {
                return count($files);
            }

            return count(array_diff(array_keys($files), $migrator->getRepository()->getRan()));
        });
    }
}
