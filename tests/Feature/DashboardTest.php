<?php

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Carbon::setTestNow('2026-11-01 12:00:00');
});

afterEach(fn () => Carbon::setTestNow());

it('requires authentication', function () {
    $this->getJson('/api/dashboard')->assertUnauthorized();
});

it('computes KPIs, a 30 day chart and recent orders', function () {
    signIn();
    $customer = Customer::factory()->create();

    Order::factory()->for($customer)->withItems([['product_name' => 'A', 'quantity' => 2, 'unit_price' => 50]])
        ->create(['ordered_at' => '2026-11-01']);
    Order::factory()->for($customer)->withItems([['product_name' => 'B', 'quantity' => 1, 'unit_price' => 30]])
        ->create(['ordered_at' => '2026-10-10']);
    // Cancelled and out-of-range orders do not count.
    Order::factory()->for($customer)->withItems()->create(['ordered_at' => '2026-10-20', 'status' => OrderStatus::Cancelled]);
    Order::factory()->for($customer)->withItems([['product_name' => 'C', 'quantity' => 1, 'unit_price' => 65]])
        ->create(['ordered_at' => '2026-09-15']);
    Invoice::factory()
        ->for(Order::factory()->for($customer)->withItems()->state(['ordered_at' => '2026-08-01']))
        ->create(['status' => InvoiceStatus::Overdue]);

    $response = $this->getJson('/api/dashboard')->assertOk();

    $response->assertJsonPath('data.period.from', '2026-10-03')
        ->assertJsonPath('data.period.to', '2026-11-01')
        ->assertJsonPath('data.kpis.order_value.value', 130)
        ->assertJsonPath('data.kpis.order_value.change', 100)
        ->assertJsonPath('data.kpis.orders.value', 2)
        ->assertJsonPath('data.kpis.overdue_invoices.value', 1)
        ->assertJsonCount(30, 'data.chart')
        ->assertJsonPath('data.chart.29', ['date' => '2026-11-01', 'order_value' => 100])
        ->assertJsonPath('data.recent_orders.0.ordered_at', '2026-11-01');
});

it('accepts a custom date range', function () {
    signIn();
    Order::factory()->withItems()->create(['ordered_at' => '2026-10-05']);

    $this->getJson('/api/dashboard?from=2026-10-01&to=2026-10-07')
        ->assertOk()
        ->assertJsonCount(7, 'data.chart')
        ->assertJsonPath('data.kpis.order_value.value', 100);

    $this->getJson('/api/dashboard?from=2026-10-07&to=2026-10-01')
        ->assertStatus(422)
        ->assertJsonValidationErrors('to');
});

it('seeds the same demo data on every run', function () {
    $snapshot = function () {
        return [
            Order::orderBy('number')->get(['number', 'status', 'total', 'ordered_at'])->toArray(),
            Invoice::orderBy('number')->pluck('status', 'number')->all(),
            Customer::orderBy('email')->pluck('name', 'email')->all(),
        ];
    };

    $this->seed(DatabaseSeeder::class);
    $first = $snapshot();

    foreach (['invoices', 'order_items', 'orders', 'customers', 'error_events'] as $table) {
        DB::table($table)->delete();
    }
    $this->seed(DatabaseSeeder::class);

    expect($snapshot())->toEqual($first)
        ->and(count($first[0]))->toBeGreaterThan(100);
});
