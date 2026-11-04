<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;

beforeEach(fn () => signIn());

it('creates an invoice whose customer and amount come from the order', function () {
    $order = Order::factory()->withItems([['product_name' => 'A', 'quantity' => 3, 'unit_price' => 10]])->create();

    $this->postJson('/api/invoices', [
        'order_id' => $order->id,
        'status' => 'draft',
        'issued_at' => '2026-10-01',
        'due_at' => '2026-10-15',
        'amount' => 1, // ignored
        'customer_id' => 999, // ignored
    ])
        ->assertCreated()
        ->assertJsonPath('data.amount', 30)
        ->assertJsonPath('data.customer.id', $order->customer_id)
        ->assertJsonPath('data.order.items.0.product_name', 'A')
        ->assertJsonPath('data.number', fn (string $n) => str_starts_with($n, 'INV-'));
});

it('validates invoices in Arabic', function () {
    $order = Order::factory()->withItems()->create();
    Invoice::factory()->for($order)->create(['customer_id' => $order->customer_id]);

    $this->postJson('/api/invoices', [
        'order_id' => $order->id,
        'status' => 'sent',
        'issued_at' => '2026-10-10',
        'due_at' => '2026-10-01',
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.order_id.0', 'هذا الطلب لديه فاتورة بالفعل.')
        ->assertJsonPath('errors.status.0', 'قيمة الحالة غير صالحة.')
        ->assertJsonPath('errors.due_at.0', 'يجب أن يكون تاريخ الاستحقاق تاريخًا مساويًا أو بعد تاريخ الإصدار.');
});

it('updates the status of an invoice', function () {
    $invoice = Invoice::factory()->create();

    $this->putJson("/api/invoices/{$invoice->id}", [
        'order_id' => $invoice->order_id,
        'status' => 'paid',
        'issued_at' => '2026-10-01',
        'due_at' => '2026-10-15',
    ])->assertOk()->assertJsonPath('data.status', 'paid');
});

it('lists invoices with search, status filter and sort', function () {
    $customer = Customer::factory()->create(['name' => 'Beta Store']);
    Invoice::factory()->for(Order::factory()->for($customer)->withItems())->create(['customer_id' => $customer->id, 'status' => 'overdue']);
    $other = Customer::factory()->create(['name' => 'Zulu Market']);
    Invoice::factory()->for(Order::factory()->for($other)->withItems([['product_name' => 'x', 'quantity' => 1, 'unit_price' => 900]]))->create(['customer_id' => $other->id, 'status' => 'paid']);

    $this->getJson('/api/invoices?search=Beta')->assertJsonCount(1, 'data')->assertJsonPath('data.0.customer.name', 'Beta Store');
    $this->getJson('/api/invoices?status=paid')->assertJsonCount(1, 'data');
    $this->getJson('/api/invoices?sort=amount&direction=desc')->assertJsonPath('data.0.amount', 900);
});

it('deletes an invoice but keeps the order', function () {
    $invoice = Invoice::factory()->create();

    $this->deleteJson("/api/invoices/{$invoice->id}")->assertNoContent();

    expect(Invoice::count())->toBe(0)->and(Order::count())->toBe(1);
});

it('refuses to invoice a cancelled order', function () {
    $order = Order::factory()->withItems()->create(['status' => 'cancelled']);

    $this->postJson('/api/invoices', ['order_id' => $order->id, 'status' => 'draft', 'issued_at' => '2026-10-01', 'due_at' => '2026-10-15'])
        ->assertStatus(422)
        ->assertJsonPath('errors.order_id.0', 'لا يمكن إصدار فاتورة لطلب ملغي.');
});

it('offers only invoiceable orders for new invoices', function () {
    Invoice::factory()->create();
    Order::factory()->withItems()->create(['status' => 'cancelled']);
    $free = Order::factory()->withItems()->create();

    $this->getJson('/api/orders?without_invoice=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $free->id);
});
