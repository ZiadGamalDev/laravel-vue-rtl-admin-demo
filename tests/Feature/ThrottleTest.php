<?php

use App\Enums\Role;
use App\Models\Invoice;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

afterEach(fn () => TrustProxies::flushState());

it('limits invoice PDF downloads and email previews per user', function () {
    signIn(Role::Viewer);
    $invoice = Invoice::factory()->create();

    foreach (range(1, 20) as $i) {
        $this->get("/documents/invoices/{$invoice->id}/email")->assertOk();
    }

    $this->get("/documents/invoices/{$invoice->id}.pdf")->assertStatus(429);
});

it('limits sending invoices to 5 per minute per user', function () {
    Mail::fake();
    signIn(Role::Staff);
    $invoice = Invoice::factory()->create();

    foreach (range(1, 5) as $i) {
        $this->postJson("/api/invoices/{$invoice->id}/email")->assertOk();
    }

    $this->postJson("/api/invoices/{$invoice->id}/email")
        ->assertStatus(429)
        ->assertJsonPath('message', fn (string $m) => str_contains($m, 'محاولات'));
});

/** 21 failed logins from different clients behind the same proxy (10.0.0.2). */
function loginsBehindProxy(TestCase $test): int
{
    User::factory()->create(['email' => 'demo@example.com', 'password' => 'password']);
    $test->withHeader('Origin', 'http://localhost');

    foreach (range(1, 20) as $i) {
        $test->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->withHeader('X-Forwarded-For', "203.0.113.$i")
            ->postJson('/api/login', ['email' => "user$i@example.com", 'password' => 'x']);
    }

    $response = $test->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->withHeader('X-Forwarded-For', '198.51.100.7')
        ->postJson('/api/login', ['email' => 'demo@example.com', 'password' => 'password']);

    return $response->getStatusCode();
}

it('without TRUSTED_PROXIES, everyone behind a proxy shares one rate-limit bucket', function () {
    expect(loginsBehindProxy($this))->toBe(429);
});

it('with TRUSTED_PROXIES, rate limits use the real client IP', function () {
    config(['app.trusted_proxies' => '10.0.0.0/8']);
    (new AppServiceProvider(app()))->boot();

    expect(loginsBehindProxy($this))->toBe(200);
});
