<?php

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;

beforeEach(fn () => signIn());

function orderPayload(array $overrides = []): array
{
    return array_merge([
        'customer_id' => Customer::factory()->create()->id,
        'status' => 'pending',
        'ordered_at' => '2026-10-15',
        'notes' => null,
        'items' => [
            ['product_name' => 'قهوة عربية', 'quantity' => 2, 'unit_price' => 18.5],
            ['product_name' => 'كوب سيراميك', 'quantity' => 3, 'unit_price' => 7.25],
        ],
    ], $overrides);
}

it('creates an order with items and computes the total', function () {
    $response = $this->postJson('/api/orders', orderPayload())->assertCreated();

    $response->assertJsonPath('data.total', 58.75)
        ->assertJsonPath('data.items_count', 2)
        ->assertJsonPath('data.items.1.line_total', 21.75)
        ->assertJsonPath('data.number', fn (string $n) => str_starts_with($n, 'ORD-'));
});

it('validates the order and its items in Arabic', function () {
    $errors = $this->postJson('/api/orders', orderPayload([
        'customer_id' => 999,
        'status' => 'shipped',
        'ordered_at' => 'yesterday',
        'items' => [['product_name' => '', 'quantity' => 0, 'unit_price' => 'abc']],
    ]))
        ->assertStatus(422)
        ->assertJsonPath('errors.customer_id.0', 'قيمة العميل المحددة غير موجودة.')
        ->assertJsonPath('errors.status.0', 'قيمة الحالة غير صالحة.')
        ->assertJsonPath('errors.ordered_at.0', 'لا يتوافق تاريخ الطلب مع الشكل Y-m-d.')
        ->json('errors');

    expect($errors['items.0.product_name'][0])->toBe('حقل اسم المنتج مطلوب.')
        ->and($errors['items.0.quantity'][0])->toBe('يجب أن تكون قيمة الكمية 1 على الأقل.')
        ->and($errors['items.0.unit_price'][0])->toBe('يجب أن يكون سعر الوحدة رقمًا.');

    $this->postJson('/api/orders', orderPayload(['items' => []]))
        ->assertStatus(422)
        ->assertJsonPath('errors.items.0', 'يجب أن يحتوي الطلب على صنف واحد على الأقل.');
});

it('replaces items on update and keeps an unpaid invoice in sync', function () {
    $order = Order::factory()->withItems()->create();
    $invoice = Invoice::factory()->for($order)->create(['customer_id' => $order->customer_id, 'amount' => 100]);

    $this->putJson("/api/orders/{$order->id}", orderPayload([
        'customer_id' => $order->customer_id,
        'items' => [['product_name' => 'دفتر', 'quantity' => 4, 'unit_price' => 5]],
    ]))->assertOk()->assertJsonPath('data.total', 20)->assertJsonCount(1, 'data.items');

    expect((float) $invoice->fresh()->amount)->toBe(20.0);
});

it('blocks item changes once the invoice is paid', function () {
    $order = Order::factory()->withItems()->create();
    Invoice::factory()->for($order)->create(['customer_id' => $order->customer_id, 'status' => InvoiceStatus::Paid]);

    $this->putJson("/api/orders/{$order->id}", orderPayload([
        'customer_id' => $order->customer_id,
        'items' => [['product_name' => 'دفتر', 'quantity' => 1, 'unit_price' => 5]],
    ]))->assertStatus(409);
});

it('lists orders with search, status filter, date range and sort', function () {
    $alpha = Customer::factory()->create(['name' => 'Alpha Shop']);
    Order::factory()->for($alpha)->withItems()->create(['status' => 'pending', 'ordered_at' => '2026-10-01']);
    // Fixed name: a random Faker name could sort before "Alpha Shop" and make the sort assertion flaky.
    $zulu = Customer::factory()->create(['name' => 'Zulu Market']);
    Order::factory()->for($zulu)->withItems([['product_name' => 'x', 'quantity' => 1, 'unit_price' => 500]])->create(['status' => 'completed', 'ordered_at' => '2026-10-20']);

    $this->getJson('/api/orders?search=Alpha')->assertJsonCount(1, 'data')->assertJsonPath('data.0.customer.name', 'Alpha Shop');
    $this->getJson('/api/orders?status=completed')->assertJsonCount(1, 'data');
    $this->getJson('/api/orders?from=2026-10-10&to=2026-10-31')->assertJsonCount(1, 'data');
    $this->getJson('/api/orders?sort=total&direction=desc')->assertJsonPath('data.0.total', 500);
    $this->getJson('/api/orders?sort=customer&direction=asc')->assertJsonPath('data.0.customer.name', 'Alpha Shop');
});

it('deletes an order with its items and invoice', function () {
    $order = Order::factory()->withItems()->create();
    Invoice::factory()->for($order)->create(['customer_id' => $order->customer_id]);

    $this->deleteJson("/api/orders/{$order->id}")->assertNoContent();

    expect(Order::count())->toBe(0)->and(Invoice::count())->toBe(0)->and(OrderItem::count())->toBe(0);
});

it('exports the filtered orders as CSV', function () {
    $customer = Customer::factory()->create(['name' => 'عميل تجريبي']);
    Order::factory()->for($customer)->withItems()->create(['status' => 'completed']);
    Order::factory()->withItems()->create(['status' => 'pending']);

    $response = $this->get('/api/orders/export?status=completed')->assertOk();
    $csv = $response->streamedContent();

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($csv)->toStartWith("\xEF\xBB\xBF")
        ->and(substr_count($csv, "\n"))->toBe(2)
        ->and($csv)->toContain('عميل تجريبي')
        ->and($csv)->toContain(',completed,');
});
