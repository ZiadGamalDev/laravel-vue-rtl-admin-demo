<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::Admin;
    }

    /** Admins change other people's roles, never their own (no accidental lock-out). */
    public function updateRole(User $user, User $target): bool
    {
        return $user->role === Role::Admin && $user->isNot($target);
    }
}
