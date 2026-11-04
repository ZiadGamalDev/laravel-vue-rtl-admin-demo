<?php

namespace App\Enums;

/**
 * admin  - everything, including the error log and user roles
 * staff  - day-to-day work: create and edit customers, orders and invoices, email invoices
 * viewer - read-only: dashboard, lists, invoice print/PDF, CSV export
 */
enum Role: string
{
    case Admin = 'admin';
    case Staff = 'staff';
    case Viewer = 'viewer';

    public function canWrite(): bool
    {
        return $this === self::Admin || $this === self::Staff;
    }
}
