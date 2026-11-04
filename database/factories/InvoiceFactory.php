<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->withItems(),
            'customer_id' => fn (array $attributes) => Order::find($attributes['order_id'])->customer_id,
            'status' => InvoiceStatus::Draft,
            'amount' => fn (array $attributes) => Order::find($attributes['order_id'])->fresh()->total,
            'issued_at' => now()->toDateString(),
            'due_at' => now()->addDays(14)->toDateString(),
        ];
    }
}
