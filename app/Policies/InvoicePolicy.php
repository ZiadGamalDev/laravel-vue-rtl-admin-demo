<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class InvoicePolicy extends RecordPolicy
{
    /** Taking an invoice out of "paid" changes the books: admins only. */
    public function reopen(User $user): bool
    {
        return $user->role === Role::Admin;
    }

    /** Sending an invoice to the customer is a write-level action. */
    public function email(User $user): bool
    {
        return $user->role->canWrite();
    }
}
