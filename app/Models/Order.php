<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = ['number', 'customer_id', 'status', 'ordered_at', 'total', 'notes'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'ordered_at' => DateOnly::class,
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Order $order) {
            if (! $order->number) {
                $order->forceFill(['number' => sprintf('ORD-%05d', 10000 + $order->id)])->saveQuietly();
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /** Recalculate the stored total from the items. */
    public function refreshTotal(): void
    {
        $this->forceFill(['total' => round((float) $this->items()->sum('line_total'), 2)])->save();
    }
}
