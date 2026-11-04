<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'status' => OrderStatus::Completed,
            'ordered_at' => now()->subDays(fake()->numberBetween(0, 20))->toDateString(),
            'total' => 0,
        ];
    }

    /** Attach items and keep the stored total in sync. */
    public function withItems(array $items = [['product_name' => 'Sample item', 'quantity' => 2, 'unit_price' => 50]]): static
    {
        return $this->afterCreating(function (Order $order) use ($items) {
            $order->items()->createMany($items);
            $order->refreshTotal();
        });
    }
}
