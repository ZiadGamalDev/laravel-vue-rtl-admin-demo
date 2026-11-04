<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->withHeader('Origin', 'http://localhost');
});

it('serves the SPA shell in RTL Arabic by default', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('<html lang="ar" dir="rtl">', false)
        ->assertSee('demo@example.com');
});

it('logs in with the seeded demo credentials', function () {
    $this->postJson('/api/login', ['email' => 'demo@example.com', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.email', 'demo@example.com');

    $this->assertAuthenticated('web');
    $this->getJson('/api/user')->assertOk()->assertJsonPath('data.email', 'demo@example.com');
});

it('rejects wrong credentials with an Arabic message', function () {
    $this->postJson('/api/login', ['email' => 'demo@example.com', 'password' => 'wrong'])
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'بيانات الدخول غير صحيحة.');

    $this->assertGuest('web');
});

it('returns English messages when the client asks for English', function () {
    $this->withHeader('X-Locale', 'en')
        ->postJson('/api/login', ['email' => '', 'password' => ''])
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'The email field is required.');
});

it('rate limits login attempts per email and IP', function () {
    RateLimiter::clear('login');

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/login', ['email' => 'demo@example.com', 'password' => 'wrong'])->assertStatus(422);
    }

    $this->postJson('/api/login', ['email' => 'demo@example.com', 'password' => 'password'])
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'محاولات دخول كثيرة'));

    $this->assertGuest('web');
});

it('logs out', function () {
    signIn(User::firstWhere('email', 'demo@example.com'));

    $this->postJson('/api/logout')->assertNoContent();
    $this->assertGuest('web');
});

it('blocks guests from the API', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});
