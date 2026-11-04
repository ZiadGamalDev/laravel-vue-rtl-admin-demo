<?php

use App\Models\ErrorEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/backup-test-'.uniqid();
    File::ensureDirectoryExists($this->dir);

    // A file-based SQLite connection: VACUUM INTO cannot run inside the test transaction.
    $this->dbFile = $this->dir.'/app.sqlite';
    touch($this->dbFile);
    config(['database.connections.filedb' => [
        'driver' => 'sqlite',
        'database' => $this->dbFile,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);
});

afterEach(function () {
    DB::purge('filedb');
    File::deleteDirectory($this->dir);
});

it('backs up a SQLite database to a readable copy', function () {
    Schema::connection('filedb')->create('notes', fn ($t) => $t->string('body'));
    DB::connection('filedb')->table('notes')->insert(['body' => 'ملاحظة']);

    $this->artisan('db:backup', ['--connection' => 'filedb', '--path' => $this->dir.'/backups'])
        ->expectsOutputToContain('Backup written to')
        ->assertSuccessful();

    $files = File::files($this->dir.'/backups');
    expect($files)->toHaveCount(1)->and($files[0]->getFilename())->toStartWith('filedb-');

    $copy = new PDO('sqlite:'.$files[0]->getPathname());
    expect($copy->query('select body from notes')->fetchColumn())->toBe('ملاحظة');
});

it('keeps only the newest backups', function () {
    $backups = $this->dir.'/backups';
    File::ensureDirectoryExists($backups);
    foreach (['2026-01-01_000000', '2026-01-02_000000', '2026-01-03_000000'] as $stamp) {
        touch("$backups/filedb-$stamp.sqlite");
    }

    $this->artisan('db:backup', ['--connection' => 'filedb', '--path' => $backups, '--keep' => 2])->assertSuccessful();

    $names = collect(File::files($backups))->map->getFilename()->sort()->values();
    expect($names)->toHaveCount(2)->and($names[0])->toBe('filedb-2026-01-03_000000.sqlite');
});

it('passes the MySQL password through the environment, not the command line', function () {
    // Pretend to be mysqldump: write the --result-file it was given.
    Process::fake(function ($process) {
        $file = collect($process->command)->first(fn ($arg) => str_starts_with($arg, '--result-file='));
        file_put_contents(substr($file, strlen('--result-file=')), '-- dump');

        return Process::result();
    });
    config(['database.connections.fake_mysql' => [
        'driver' => 'mysql', 'host' => 'db.internal', 'port' => 3306,
        'database' => 'shop', 'username' => 'app', 'password' => 's3cret',
    ]]);

    $this->artisan('db:backup', ['--connection' => 'fake_mysql', '--path' => $this->dir])->assertSuccessful();

    Process::assertRan(function ($process) {
        $command = implode(' ', (array) $process->command);

        return str_contains($command, 'mysqldump')
            && str_contains($command, '--single-transaction')
            && ! str_contains($command, 's3cret')
            && $process->environment['MYSQL_PWD'] === 's3cret';
    });
});

it('fails when the dump tool produces no file', function () {
    Process::fake(fn () => Process::result());
    config(['database.connections.fake_pgsql' => [
        'driver' => 'pgsql', 'host' => 'db.internal', 'port' => 5432,
        'database' => 'shop', 'username' => 'app', 'password' => 'x',
    ]]);

    $this->artisan('db:backup', ['--connection' => 'fake_pgsql', '--path' => $this->dir])
        ->expectsOutputToContain('missing or empty')
        ->assertFailed();
});

it('fails cleanly for an in-memory database', function () {
    config(['database.connections.mem' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);

    $this->artisan('db:backup', ['--connection' => 'mem', '--path' => $this->dir])
        ->expectsOutputToContain('Backup failed')
        ->assertFailed();
});

it('backs up before migrating and skips both when nothing is pending', function () {
    $originalStorage = app()->storagePath();
    $isolatedStorage = $this->dir.'/storage';
    app()->useStoragePath($isolatedStorage);

    try {
        $this->artisan('migrate:safe', ['--database' => 'filedb', '--force' => true])
            ->expectsOutputToContain('pending migration')
            ->assertSuccessful();

        expect(Schema::connection('filedb')->hasTable('orders'))->toBeTrue()
            ->and(File::files(storage_path('app/backups')))->not->toBeEmpty();

        $this->artisan('migrate:safe', ['--database' => 'filedb', '--force' => true])
            ->expectsOutputToContain('Nothing to migrate')
            ->assertSuccessful();
    } finally {
        app()->useStoragePath($originalStorage);
    }
});

it('does not migrate when the backup fails', function () {
    config(['database.connections.mem' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);

    $this->artisan('migrate:safe', ['--database' => 'mem', '--force' => true])
        ->expectsOutputToContain('migrations were NOT run')
        ->assertFailed();

    expect(Schema::connection('mem')->hasTable('orders'))->toBeFalse();
});

it('prunes resolved issues that have been quiet for 30 days', function () {
    $make = fn (string $key, $resolved, $lastSeen) => ErrorEvent::create([
        'fingerprint' => sha1($key), 'exception_class' => 'E', 'message' => $key, 'file' => 'f', 'line' => 1,
        'first_seen_at' => now()->subDays(90), 'last_seen_at' => $lastSeen, 'resolved_at' => $resolved,
    ]);
    $make('old-resolved', now()->subDays(40), now()->subDays(40));
    $make('old-open', null, now()->subDays(40));
    $make('recent-resolved', now(), now()->subDays(2));

    $this->artisan('model:prune', ['--model' => ErrorEvent::class])->assertSuccessful();

    expect(ErrorEvent::pluck('message')->sort()->values()->all())->toBe(['old-open', 'recent-resolved']);
});
