<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $fillable = ['number', 'order_id', 'customer_id', 'status', 'amount', 'issued_at', 'due_at', 'notes'];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'amount' => 'decimal:2',
            'issued_at' => DateOnly::class,
            'due_at' => DateOnly::class,
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Invoice $invoice) {
            if (! $invoice->number) {
                $invoice->forceFill(['number' => sprintf('INV-%05d', 1000 + $invoice->id)])->saveQuietly();
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
