<?php

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\ErrorEvent;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

/*
 * Regression tests for findings from the self-review (see DECISIONS.md).
 */

describe('search', function () {
    beforeEach(fn () => signIn());

    it('treats % and _ in the search term literally', function () {
        Customer::factory()->create(['name' => 'Sale 50% off', 'email' => 'a@example.com']);
        Customer::factory()->create(['name' => 'Sale 500 off', 'email' => 'b@example.com']);
        Customer::factory()->create(['name' => 'code a_b', 'email' => 'c@example.com']);
        Customer::factory()->create(['name' => 'code axb', 'email' => 'd@example.com']);

        $this->getJson('/api/customers?search='.urlencode('50%'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Sale 50% off');
        $this->getJson('/api/customers?search=a_b')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'code a_b');
    });
});

describe('login', function () {
    beforeEach(function () {
        User::factory()->create(['email' => 'demo@example.com', 'password' => 'password']);
        $this->withHeader('Origin', 'http://localhost');
    });

    it('accepts the email in any letter case', function () {
        $this->postJson('/api/login', ['email' => 'DEMO@Example.com', 'password' => 'password'])->assertOk();
    });

    it('limits one IP spraying many different emails', function () {
        foreach (range(1, 20) as $i) {
            $this->postJson('/api/login', ['email' => "user$i@example.com", 'password' => 'x'])->assertStatus(422);
        }

        $this->postJson('/api/login', ['email' => 'demo@example.com', 'password' => 'password'])->assertStatus(429);
    });

    // Guards against a framework upgrade silently dropping SessionGuard's timebox.
    it('makes a failed login for an unknown email take the minimum delay', function () {
        $start = microtime(true);
        $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'x'])->assertStatus(422);

        expect(microtime(true) - $start)->toBeGreaterThanOrEqual(0.19);
    });
});

it('rate limits the API per user', function () {
    signIn();

    foreach (range(1, 120) as $i) {
        $this->getJson('/api/user')->assertOk();
    }

    $this->getJson('/api/user')->assertStatus(429);
});

describe('data rules', function () {
    beforeEach(fn () => signIn());

    it('stores customer emails lower-cased and keeps them unique regardless of case', function () {
        $this->postJson('/api/customers', ['name' => 'Case Test', 'email' => '  Mixed@Example.COM '])
            ->assertCreated()
            ->assertJsonPath('data.email', 'mixed@example.com');

        $this->postJson('/api/customers', ['name' => 'Again', 'email' => 'MIXED@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    });

    it('rejects unit prices with more than two decimals', function () {
        $errors = $this->postJson('/api/orders', [
            'customer_id' => Customer::factory()->create()->id,
            'status' => 'pending',
            'ordered_at' => '2026-10-01',
            'items' => [['product_name' => 'Tea', 'quantity' => 3, 'unit_price' => 10.555]],
        ])->assertStatus(422)->json('errors');

        expect($errors['items.0.unit_price'][0])->toContain('سعر الوحدة');
    });

    it('locks the customer of an order with a paid invoice and refuses to delete it', function () {
        $order = Order::factory()->withItems([['product_name' => 'Tea', 'quantity' => 1, 'unit_price' => 10]])->create();
        Invoice::factory()->for($order)->create(['customer_id' => $order->customer_id, 'status' => InvoiceStatus::Paid]);

        $this->putJson("/api/orders/{$order->id}", [
            'customer_id' => Customer::factory()->create()->id,
            'status' => 'completed',
            'ordered_at' => $order->ordered_at->toDateString(),
            'items' => [['product_name' => 'Tea', 'quantity' => 1, 'unit_price' => 10]],
        ])->assertStatus(409);

        $this->deleteJson("/api/orders/{$order->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'لا يمكن حذف طلب صدرت له فاتورة مدفوعة.');

        expect(Order::count())->toBe(1)->and(Invoice::count())->toBe(1);
    });

    it('neutralises spreadsheet formulas in the CSV export', function () {
        $evil = Customer::factory()->create(['name' => '=HYPERLINK("http://example.test","x")']);
        Order::factory()->for($evil)->withItems()->create();

        $csv = $this->get('/api/orders/export')->assertOk()->streamedContent();

        expect($csv)->toContain(",\"'=HYPERLINK(")->not->toContain(',"=HYPERLINK(');
    });

    it('caps the dashboard range at one year', function () {
        $this->getJson('/api/dashboard?from=2000-01-01&to=2026-01-01')
            ->assertStatus(422)
            ->assertJsonPath('errors.to.0', 'يجب ألا تتجاوز الفترة سنة واحدة.');

        $this->getJson('/api/dashboard?from=2025-11-01&to=2026-10-31')->assertOk();
    });
});

it('stores only the request path of an error, never the query string', function () {
    Route::get('/api/boom-with-query', fn () => throw new LogicException('boom'))->middleware('api');

    $this->getJson('/api/boom-with-query?search=Layla+Mansour&token=abc123&page=2')->assertStatus(500);

    expect(ErrorEvent::first()->last_url)->toBe('/api/boom-with-query');
});

it('marks draft invoices past their due date as overdue', function () {
    Carbon::setTestNow('2026-11-01 12:00:00');
    $late = Invoice::factory()->create(['status' => InvoiceStatus::Draft, 'due_at' => '2026-10-20']);
    $notYet = Invoice::factory()->create(['status' => InvoiceStatus::Draft, 'due_at' => '2026-11-15']);
    $paid = Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'due_at' => '2026-10-01']);

    $this->artisan('invoices:mark-overdue')->expectsOutputToContain('1 invoice(s) marked overdue')->assertSuccessful();

    expect($late->fresh()->status)->toBe(InvoiceStatus::Overdue)
        ->and($notYet->fresh()->status)->toBe(InvoiceStatus::Draft)
        ->and($paid->fresh()->status)->toBe(InvoiceStatus::Paid);
    Carbon::setTestNow();
});

describe('arabic-aware search', function () {
    beforeEach(fn () => signIn());

    it('finds customers regardless of alef, hamza, taa marbuta, ya and tashkeel', function () {
        Customer::factory()->create(['name' => 'أحمد مصطفى', 'email' => 'a1@example.com']);
        Customer::factory()->create(['name' => 'فاطمة الزهراء', 'email' => 'a2@example.com']);
        Customer::factory()->create(['name' => 'Other Person', 'email' => 'a3@example.com', 'city' => 'Nowhere']);

        foreach (['احمد', 'إحمد', 'مصطفي', 'أَحْمَد'] as $term) {
            $this->getJson('/api/customers?search='.urlencode($term))->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'a1@example.com');
        }
        $this->getJson('/api/customers?search='.urlencode('فاطمه'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'a2@example.com');
        $this->getJson('/api/customers?search='.urlencode('الزهرا'))->assertJsonCount(1, 'data');
    });

    it('matches phone numbers typed with Arabic-Indic digits', function () {
        Customer::factory()->create(['name' => 'Phone Test', 'email' => 'p@example.com', 'phone' => '555-0177']);

        $this->getJson('/api/customers?search='.urlencode('٠١٧٧'))->assertJsonCount(1, 'data');
    });

    it('finds orders and invoices by a normalised customer name', function () {
        $customer = Customer::factory()->create(['name' => 'سلمى الحدّاد', 'email' => 's@example.com']);
        $order = Order::factory()->for($customer)->withItems()->create();
        Invoice::factory()->for($order)->create(['customer_id' => $customer->id]);
        Order::factory()->for(Customer::factory()->create(['name' => 'Zulu Market']))->withItems()->create();

        $this->getJson('/api/orders?search='.urlencode('سلمي الحداد'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $order->id);
        $this->getJson('/api/invoices?search='.urlencode('سلمي'))->assertJsonCount(1, 'data');
    });

    it('keeps the search text in sync on update and never exposes it', function () {
        $customer = Customer::factory()->create(['name' => 'Old Name', 'email' => 'u@example.com']);
        $customer->update(['name' => 'يوسف']);

        $this->getJson('/api/customers?search='.urlencode('يوسف'))->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.search_text');
    });
});
