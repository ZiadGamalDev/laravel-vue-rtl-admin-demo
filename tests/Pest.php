<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/** Sign in through the Sanctum SPA guard; a fresh admin unless a user or role is given. */
function signIn(User|Role|null $user = null): User
{
    $user = match (true) {
        $user instanceof User => $user,
        $user instanceof Role => User::factory()->create(['role' => $user]),
        default => User::factory()->admin()->create(),
    };
    // Sanctum's request guard caches the first user it resolves; reset it when switching users.
    app('auth')->forgetGuards();
    test()->actingAs($user, 'web');

    return $user;
}
