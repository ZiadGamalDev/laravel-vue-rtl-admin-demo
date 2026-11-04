<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/** Stack traces and request URLs are operational data: admins only. */
class ErrorEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::Admin;
    }

    public function view(User $user): bool
    {
        return $user->role === Role::Admin;
    }

    public function update(User $user): bool
    {
        return $user->role === Role::Admin;
    }

    public function trigger(User $user): bool
    {
        return $user->role === Role::Admin;
    }
}
