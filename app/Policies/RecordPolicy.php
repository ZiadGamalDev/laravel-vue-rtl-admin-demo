<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/**
 * Shared rules for the business records (customers, orders, invoices):
 * everyone signed in can read, admin and staff can create/edit, only admin deletes.
 */
abstract class RecordPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role->canWrite();
    }

    public function update(User $user): bool
    {
        return $user->role->canWrite();
    }

    public function delete(User $user): bool
    {
        return $user->role === Role::Admin;
    }
}
