<?php

use App\Models\ErrorEvent;
use App\Support\ErrorRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

function recordSameError(): void
{
    // Same file and line every call, so the fingerprint is identical.
    try {
        throw new RuntimeException('Something broke');
    } catch (RuntimeException $e) {
        report($e);
    }
}

it('groups repeated exceptions into one issue with a counter', function () {
    recordSameError();
    recordSameError();
    recordSameError();

    expect(ErrorEvent::count())->toBe(1);

    $event = ErrorEvent::first();
    expect($event->count)->toBe(3)
        ->and($event->exception_class)->toBe(RuntimeException::class)
        ->and($event->file)->toBe('tests/Feature/ErrorLogTest.php')
        ->and($event->line)->toBeInt()
        ->and($event->trace[0]['function'])->toBe(RuntimeException::class)
        ->and($event->first_seen_at)->not->toBeNull();
});

it('records exceptions thrown during HTTP requests through the real handler', function () {
    Route::get('/api/boom', fn () => throw new LogicException('Boom in a request'))->middleware('api');

    $this->getJson('/api/boom')->assertStatus(500);

    $event = ErrorEvent::firstWhere('exception_class', LogicException::class);
    expect($event->message)->toBe('Boom in a request')
        ->and($event->last_method)->toBe('GET')
        ->and($event->last_url)->toEndWith('/api/boom');
});

it('does not record exceptions Laravel does not report', function () {
    signIn();
    $this->postJson('/api/customers', [])->assertStatus(422);
    $this->getJson('/api/customers/999')->assertNotFound();

    expect(ErrorEvent::count())->toBe(0);
});

it('reopens a resolved issue when it happens again', function () {
    recordSameError();
    ErrorEvent::first()->update(['resolved_at' => now()]);

    recordSameError();

    expect(ErrorEvent::first()->resolved_at)->toBeNull()->and(ErrorEvent::first()->count)->toBe(2);
});

it('never throws when the table is missing', function () {
    Schema::drop('error_events');

    app(ErrorRecorder::class)->record(new RuntimeException('no table'));
})->throwsNoExceptions();

it('lists, filters, shows and resolves issues', function () {
    signIn();
    recordSameError();
    $other = ErrorEvent::create([
        'fingerprint' => sha1('other'), 'exception_class' => 'TypeError', 'message' => 'Other', 'file' => 'app/X.php', 'line' => 1,
        'count' => 9, 'first_seen_at' => now()->subDay(), 'last_seen_at' => now()->subHour(), 'resolved_at' => now(),
    ]);

    $this->getJson('/api/error-events')->assertJsonCount(2, 'data')->assertJsonMissingPath('data.0.trace');
    $this->getJson('/api/error-events?status=unresolved')->assertJsonCount(1, 'data');
    $this->getJson('/api/error-events?status=resolved')->assertJsonPath('data.0.id', $other->id);
    $this->getJson('/api/error-events?sort=count&direction=desc')->assertJsonPath('data.0.count', 9);
    $this->getJson('/api/error-events?search=broke')->assertJsonCount(1, 'data');

    $id = ErrorEvent::firstWhere('message', 'Something broke')->id;
    $this->getJson("/api/error-events/$id")->assertOk()->assertJsonStructure(['data' => ['trace' => [['file', 'line', 'function']], 'url', 'method']]);

    $this->patchJson("/api/error-events/$id", ['resolved' => true])->assertOk()->assertJsonPath('data.resolved_at', fn ($v) => $v !== null);
    $this->patchJson("/api/error-events/$id", ['resolved' => false])->assertOk()->assertJsonPath('data.resolved_at', null);
});

it('hides the test error route outside the local environment', function () {
    signIn();

    $this->postJson('/api/error-events/trigger')->assertNotFound();
    expect(ErrorEvent::count())->toBe(0);
});

it('records a test error when running locally', function () {
    signIn();
    app()->detectEnvironment(fn () => 'local');

    $this->postJson('/api/error-events/trigger')->assertStatus(500);

    expect(ErrorEvent::first()->message)->toBe('خطأ تجريبي أُطلق من سجل الأخطاء.');
});

it('requires authentication for the error log', function () {
    $this->getJson('/api/error-events')->assertUnauthorized();
});

it('handles first-fingerprint race without dropping concurrent occurrences', function () {
    $e = new RuntimeException('Concurrent race exception');
    $fingerprint = ErrorRecorder::fingerprint($e);
    $recorder = app(ErrorRecorder::class);

    $active = true;
    $simulatedRace = false;
    DB::listen(function ($query) use (&$active, &$simulatedRace, $fingerprint) {
        if ($active && ! $simulatedRace && str_contains($query->sql, 'update') && str_contains($query->sql, 'error_events')) {
            $simulatedRace = true;
            DB::table('error_events')->insert([
                'fingerprint' => $fingerprint,
                'exception_class' => RuntimeException::class,
                'message' => 'Concurrent race exception',
                'file' => 'test.php',
                'line' => 1,
                'trace' => '[]',
                'count' => 1,
                'first_seen_at' => now()->subMinute(),
                'last_seen_at' => now()->subMinute(),
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ]);
        }
    });

    try {
        $recorder->record($e);

        expect(ErrorEvent::count())->toBe(1);
        $event = ErrorEvent::first();
        expect($event->count)->toBe(2)
            ->and($event->first_seen_at->timestamp)->toBeLessThan(now()->timestamp);
    } finally {
        $active = false;
    }
});
