<?php

use App\Models\Order;

beforeEach(fn () => signIn());

it('accepts open-ended date ranges on the orders list', function () {
    Order::factory()->withItems()->create(['ordered_at' => '2026-10-01']);
    Order::factory()->withItems()->create(['ordered_at' => '2026-10-20']);

    $this->getJson('/api/orders?to=2026-10-10')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/orders?from=2026-10-10')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/orders?from=2026-10-20&to=2026-10-01')->assertStatus(422)->assertJsonValidationErrors('to');
});
