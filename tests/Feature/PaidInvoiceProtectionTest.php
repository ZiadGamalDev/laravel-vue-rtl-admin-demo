<?php

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Http\Requests\InvoiceRequest;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

/*
 * A paid invoice is a closed document. Regression tests for the review finding
 * "staff can change order_id or move a paid invoice back to draft".
 */

function paidInvoice(array $attributes = []): Invoice
{
    $order = Order::factory()->withItems([['product_name' => 'Tea', 'quantity' => 2, 'unit_price' => 10]])->create(['ordered_at' => '2026-10-01']);

    return Invoice::factory()->for($order)->create([
        'customer_id' => $order->customer_id,
        'status' => InvoiceStatus::Paid,
        'issued_at' => '2026-10-02',
        'due_at' => '2026-10-16',
        ...$attributes,
    ]);
}

function invoicePayload(Invoice $invoice, array $overrides = []): array
{
    return [
        'order_id' => $invoice->order_id,
        'status' => $invoice->status->value,
        'issued_at' => $invoice->issued_at->toDateString(),
        'due_at' => $invoice->due_at->toDateString(),
        'notes' => $invoice->notes,
        ...$overrides,
    ];
}

it('never lets any role move an invoice to another order', function (Role $role) {
    signIn($role);
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Draft]);
    $other = Order::factory()->withItems([['product_name' => 'Big', 'quantity' => 1, 'unit_price' => 999]])->create();

    $this->putJson("/api/invoices/{$invoice->id}", invoicePayload($invoice, ['order_id' => $other->id]))
        ->assertStatus(422)
        ->assertJsonPath('errors.order_id.0', 'لا يمكن نقل الفاتورة إلى طلب آخر.');

    expect($invoice->fresh()->order_id)->toBe($invoice->order_id)
        ->and((float) $invoice->fresh()->amount)->toBe((float) $invoice->amount);
})->with([Role::Admin, Role::Staff]);

it('refuses to take a paid invoice out of paid through a normal edit, for every role', function (Role $role, string $status) {
    signIn($role);
    $invoice = paidInvoice();

    $this->putJson("/api/invoices/{$invoice->id}", invoicePayload($invoice, ['status' => $status]))
        ->assertStatus(409)
        ->assertJsonPath('message', 'الفاتورة مدفوعة: يمكن تعديل الملاحظات فقط. يستطيع المدير إعادة فتحها.');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
})->with([Role::Admin, Role::Staff])->with(['draft', 'overdue']);

it('refuses to re-date a paid invoice but still allows a note', function () {
    signIn(Role::Staff);
    $invoice = paidInvoice();

    $this->putJson("/api/invoices/{$invoice->id}", invoicePayload($invoice, ['due_at' => '2026-12-31']))->assertStatus(409);
    $this->putJson("/api/invoices/{$invoice->id}", invoicePayload($invoice, ['notes' => 'Paid by transfer']))
        ->assertOk()
        ->assertJsonPath('data.notes', 'Paid by transfer')
        ->assertJsonPath('data.status', 'paid');
});

it('lets only an admin reopen a paid invoice, explicitly', function () {
    Carbon::setTestNow('2026-11-01 12:00:00');
    $invoice = paidInvoice(); // due 2026-10-16, so reopening makes it overdue

    signIn(Role::Staff);
    $this->postJson("/api/invoices/{$invoice->id}/reopen")->assertStatus(403);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);

    signIn(Role::Admin);
    $this->postJson("/api/invoices/{$invoice->id}/reopen")->assertOk()->assertJsonPath('data.status', 'overdue');
    $this->postJson("/api/invoices/{$invoice->id}/reopen")->assertStatus(409);

    $future = paidInvoice(['due_at' => '2026-12-01']);
    $this->postJson("/api/invoices/{$future->id}/reopen")->assertOk()->assertJsonPath('data.status', 'draft');
    Carbon::setTestNow();
});

it('refuses to delete a paid invoice, even for an admin', function () {
    signIn(Role::Admin);
    $invoice = paidInvoice();

    $this->deleteJson("/api/invoices/{$invoice->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'لا يمكن حذف فاتورة مدفوعة. أعد فتحها أولًا.');

    expect(Invoice::find($invoice->id))->not->toBeNull();
});

it('still lets staff mark an unpaid invoice as paid', function () {
    signIn(Role::Staff);
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Draft]);

    $this->putJson("/api/invoices/{$invoice->id}", invoicePayload($invoice, ['status' => 'paid']))
        ->assertOk()
        ->assertJsonPath('data.status', 'paid');
});

describe('order with a paid invoice', function () {
    function paidOrderPayload(Invoice $invoice, array $overrides = []): array
    {
        $order = $invoice->order;

        return [
            'customer_id' => $order->customer_id,
            'status' => $order->status->value,
            'ordered_at' => $order->ordered_at->toDateString(),
            'items' => [['product_name' => 'Tea', 'quantity' => 2, 'unit_price' => 10]],
            ...$overrides,
        ];
    }

    it('cannot be cancelled, re-dated or moved backwards', function (array $change) {
        signIn(Role::Admin);
        $invoice = paidInvoice();
        $invoice->order->update(['status' => 'processing']);

        $this->putJson("/api/orders/{$invoice->order_id}", paidOrderPayload($invoice->fresh(), $change))
            ->assertStatus(409)
            ->assertJsonPath('message', 'الطلب له فاتورة مدفوعة: لا يمكن تغيير تاريخه أو إلغاؤه أو إرجاع حالته.');

        expect($invoice->order->fresh()->status->value)->toBe('processing')
            ->and($invoice->order->fresh()->ordered_at->toDateString())->toBe('2026-10-01');
    })->with([
        'cancel' => [['status' => 'cancelled']],
        're-date' => [['ordered_at' => '2026-09-01']],
        'backwards' => [['status' => 'pending']],
    ]);

    it('can still move forward and take notes', function () {
        signIn(Role::Staff);
        $invoice = paidInvoice();
        $invoice->order->update(['status' => 'processing']);

        $this->putJson("/api/orders/{$invoice->order_id}", paidOrderPayload($invoice->fresh(), ['status' => 'completed', 'notes' => 'Shipped']))
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    });
});

describe('concurrent stale-bound state regressions', function () {
    it('configures sqlite connections for immediate write serialization and bounded busy timeout', function () {
        expect(config('database.connections.sqlite.transaction_mode'))->toBe('IMMEDIATE')
            ->and(config('database.connections.sqlite.busy_timeout'))->toBe(5000);
    });

    it('refuses to downgrade or re-date an invoice that becomes paid after route binding', function () {
        signIn(Role::Admin);
        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::Draft,
            'issued_at' => '2026-10-02',
            'due_at' => '2026-10-16',
        ]);

        Route::bind('invoice', function (string $value) {
            $bound = Invoice::findOrFail($value);
            // Concurrent request B marks the invoice as paid after request A bound the draft model.
            Invoice::whereKey($bound->id)->update(['status' => InvoiceStatus::Paid]);

            return $bound;
        });

        $this->putJson("/api/invoices/{$invoice->id}", invoicePayload($invoice, ['status' => 'draft', 'notes' => 'Late overwrite']))
            ->assertStatus(409)
            ->assertJsonPath('message', 'الفاتورة مدفوعة: يمكن تعديل الملاحظات فقط. يستطيع المدير إعادة فتحها.');

        expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
            ->and($invoice->fresh()->notes)->toBe($invoice->notes);
    });

    it('refuses to delete an invoice that becomes paid after route binding', function () {
        signIn(Role::Admin);
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Draft]);

        Route::bind('invoice', function (string $value) {
            $bound = Invoice::findOrFail($value);
            Invoice::whereKey($bound->id)->update(['status' => InvoiceStatus::Paid]);

            return $bound;
        });

        $this->deleteJson("/api/invoices/{$invoice->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'لا يمكن حذف فاتورة مدفوعة. أعد فتحها أولًا.');

        expect($invoice->fresh()?->status)->toBe(InvoiceStatus::Paid);
    });

    it('refuses to reopen an invoice that becomes unpaid after route binding', function () {
        signIn(Role::Admin);
        $invoice = paidInvoice();

        Route::bind('invoice', function (string $value) {
            $bound = Invoice::findOrFail($value);
            Invoice::whereKey($bound->id)->update(['status' => InvoiceStatus::Draft]);

            return $bound;
        });

        $this->postJson("/api/invoices/{$invoice->id}/reopen")
            ->assertStatus(409)
            ->assertJsonPath('message', __('messages.invoice_not_paid'));
    });

    it('refuses to change items or cancel an order when its cached draft invoice becomes paid after route binding', function () {
        signIn(Role::Admin);
        $order = Order::factory()
            ->withItems([['product_name' => 'Tea', 'quantity' => 2, 'unit_price' => 10]])
            ->create(['ordered_at' => '2026-10-01', 'status' => OrderStatus::Processing]);
        $invoice = Invoice::factory()->for($order)->create([
            'customer_id' => $order->customer_id,
            'status' => InvoiceStatus::Draft,
            'amount' => 20,
        ]);

        Route::bind('order', function (string $value) {
            $bound = Order::with(['invoice', 'items'])->findOrFail($value);
            // Concurrent payment lands after $bound already cached $bound->invoice as Draft.
            Invoice::where('order_id', $bound->id)->update(['status' => InvoiceStatus::Paid]);

            return $bound;
        });

        $this->putJson("/api/orders/{$order->id}", [
            'customer_id' => $order->customer_id,
            'status' => OrderStatus::Processing->value,
            'ordered_at' => '2026-10-01',
            'items' => [['product_name' => 'Tea', 'quantity' => 5, 'unit_price' => 10]],
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', __('messages.order_has_invoice'));

        $this->putJson("/api/orders/{$order->id}", [
            'customer_id' => $order->customer_id,
            'status' => OrderStatus::Cancelled->value,
            'ordered_at' => '2026-10-01',
            'items' => [['product_name' => 'Tea', 'quantity' => 2, 'unit_price' => 10]],
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', __('messages.order_paid_locked'));

        expect((float) $order->fresh()->total)->toBe(20.0)
            ->and($order->fresh()->status)->toBe(OrderStatus::Processing)
            ->and((float) $invoice->fresh()->amount)->toBe(20.0)
            ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
    });

    it('refuses to delete an order whose cached draft invoice becomes paid after route binding', function () {
        signIn(Role::Admin);
        $order = Order::factory()
            ->withItems([['product_name' => 'Tea', 'quantity' => 2, 'unit_price' => 10]])
            ->create(['ordered_at' => '2026-10-01']);
        $invoice = Invoice::factory()->for($order)->create([
            'customer_id' => $order->customer_id,
            'status' => InvoiceStatus::Draft,
        ]);

        Route::bind('order', function (string $value) {
            $bound = Order::with('invoice')->findOrFail($value);
            Invoice::where('order_id', $bound->id)->update(['status' => InvoiceStatus::Paid]);

            return $bound;
        });

        $this->deleteJson("/api/orders/{$order->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', __('messages.order_paid_delete'));

        expect(Order::find($order->id))->not->toBeNull()
            ->and($invoice->fresh()?->status)->toBe(InvoiceStatus::Paid);
    });

    it('refuses to create an invoice when the order is cancelled after request validation', function () {
        signIn(Role::Staff);
        $order = Order::factory()
            ->withItems([['product_name' => 'Tea', 'quantity' => 2, 'unit_price' => 10]])
            ->create(['status' => OrderStatus::Pending]);

        $this->app->afterResolving(InvoiceRequest::class, function () use ($order) {
            Order::whereKey($order->id)->update(['status' => OrderStatus::Cancelled]);
        });

        $this->postJson('/api/invoices', [
            'order_id' => $order->id,
            'status' => InvoiceStatus::Draft->value,
            'issued_at' => '2026-10-02',
            'due_at' => '2026-10-16',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.order_id.0', __('messages.order_cancelled'));

        expect(Invoice::where('order_id', $order->id)->exists())->toBeFalse();
    });

    it('refuses to create a duplicate invoice when another invoice is created after request validation', function () {
        signIn(Role::Staff);
        $order = Order::factory()
            ->withItems([['product_name' => 'Tea', 'quantity' => 2, 'unit_price' => 10]])
            ->create(['status' => OrderStatus::Pending]);

        $this->app->afterResolving(InvoiceRequest::class, function () use ($order) {
            Invoice::factory()->for($order)->create([
                'customer_id' => $order->customer_id,
                'status' => InvoiceStatus::Draft,
            ]);
        });

        $this->postJson('/api/invoices', [
            'order_id' => $order->id,
            'status' => InvoiceStatus::Draft->value,
            'issued_at' => '2026-10-02',
            'due_at' => '2026-10-16',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['order_id']);

        expect(Invoice::where('order_id', $order->id)->count())->toBe(1);
    });
});
