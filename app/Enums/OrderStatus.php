<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** Position in the normal flow; used to refuse moving a paid order backwards. */
    public function rank(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::Processing => 1,
            self::Completed => 2,
            self::Cancelled => 3,
        };
    }

    /** Cancelled orders never count toward order value. */
    public function countsTowardOrderValue(): bool
    {
        return $this !== self::Cancelled;
    }
}
