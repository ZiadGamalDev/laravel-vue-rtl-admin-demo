<?php

use App\Enums\Role;
use App\Models\Customer;
use App\Models\ErrorEvent;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

/**
 * Permission matrix: [method, uri builder, payload builder, admin, staff, viewer].
 * "ok" = any 2xx, 403 = forbidden by policy.
 */
function matrixFixtures(): array
{
    $order = Order::factory()->withItems([['product_name' => 'Tea', 'quantity' => 1, 'unit_price' => 10]])->create();
    $invoice = Invoice::factory()->create();
    $spareOrder = Order::factory()->withItems()->create();
    $error = ErrorEvent::create([
        'fingerprint' => sha1(uniqid('', true)), 'exception_class' => 'E', 'message' => 'm', 'file' => 'f', 'line' => 1,
        'first_seen_at' => now(), 'last_seen_at' => now(),
    ]);

    return compact('order', 'invoice', 'spareOrder', 'error');
}

dataset('matrix', [
    'list customers' => ['GET', fn ($f) => '/api/customers', null, 'ok', 'ok', 'ok'],
    'create customer' => ['POST', fn ($f) => '/api/customers', fn ($f) => ['name' => 'New One', 'email' => uniqid().'@example.com'], 'ok', 'ok', 403],
    'update customer' => ['PUT', fn ($f) => '/api/customers/'.$f['order']->customer_id, fn ($f) => ['name' => 'Renamed', 'email' => uniqid().'@example.com'], 'ok', 'ok', 403],
    'delete customer' => ['DELETE', fn ($f) => '/api/customers/'.Customer::factory()->create()->id, null, 'ok', 403, 403],
    'list orders' => ['GET', fn ($f) => '/api/orders', null, 'ok', 'ok', 'ok'],
    'export orders' => ['GET', fn ($f) => '/api/orders/export', null, 'ok', 'ok', 'ok'],
    'create order' => ['POST', fn ($f) => '/api/orders', fn ($f) => ['customer_id' => $f['order']->customer_id, 'status' => 'pending', 'ordered_at' => '2026-10-01', 'items' => [['product_name' => 'Tea', 'quantity' => 1, 'unit_price' => 5]]], 'ok', 'ok', 403],
    'delete order' => ['DELETE', fn ($f) => '/api/orders/'.$f['spareOrder']->id, null, 'ok', 403, 403],
    'show invoice' => ['GET', fn ($f) => '/api/invoices/'.$f['invoice']->id, null, 'ok', 'ok', 'ok'],
    'update invoice' => ['PUT', fn ($f) => '/api/invoices/'.$f['invoice']->id, fn ($f) => ['order_id' => $f['invoice']->order_id, 'status' => 'paid', 'issued_at' => '2026-10-01', 'due_at' => '2026-10-15'], 'ok', 'ok', 403],
    'delete invoice' => ['DELETE', fn ($f) => '/api/invoices/'.$f['invoice']->id, null, 'ok', 403, 403],
    'error log list' => ['GET', fn ($f) => '/api/error-events', null, 'ok', 403, 403],
    'error log detail' => ['GET', fn ($f) => '/api/error-events/'.$f['error']->id, null, 'ok', 403, 403],
    'resolve error' => ['PATCH', fn ($f) => '/api/error-events/'.$f['error']->id, fn ($f) => ['resolved' => true], 'ok', 403, 403],
    'list users' => ['GET', fn ($f) => '/api/users', null, 'ok', 403, 403],
]);

it('enforces the permission matrix', function (string $method, Closure $uri, ?Closure $payload, $admin, $staff, $viewer) {
    foreach (['admin' => $admin, 'staff' => $staff, 'viewer' => $viewer] as $role => $expected) {
        $fixtures = matrixFixtures();
        signIn(Role::from($role));

        $response = $this->json($method, $uri($fixtures), $payload ? $payload($fixtures) : []);

        if ($expected === 'ok') {
            expect($response->getStatusCode())->toBeLessThan(300, "$role $method should succeed, got {$response->getStatusCode()}");
        } else {
            expect($response->getStatusCode())->toBe(403, "$role $method should be forbidden");
            expect($response->json('message'))->toBe('ليست لديك صلاحية لهذا الإجراء.');
        }
    }
})->with('matrix');

it('tells the UI what the signed-in user may do', function () {
    signIn(Role::Staff);

    $can = $this->getJson('/api/user')->assertJsonPath('data.role', 'staff')->json('data.can');

    expect($can['orders.create'])->toBeTrue()
        ->and($can['orders.delete'])->toBeFalse()
        ->and($can['invoices.email'])->toBeTrue()
        ->and($can['errors.view'])->toBeFalse()
        ->and($can['users.manage'])->toBeFalse();
});

it('lets an admin change another user\'s role but not their own', function () {
    $admin = signIn(Role::Admin);
    $other = User::factory()->create();

    $this->patchJson("/api/users/{$other->id}", ['role' => 'staff'])->assertOk()->assertJsonPath('data.role', 'staff');
    $this->patchJson("/api/users/{$other->id}", ['role' => 'owner'])->assertStatus(422)->assertJsonValidationErrors('role');
    $this->patchJson("/api/users/{$admin->id}", ['role' => 'viewer'])->assertStatus(403)->assertJsonPath('message', 'لا يمكنك تغيير دورك بنفسك.');

    expect($admin->fresh()->role)->toBe(Role::Admin);
});

it('does not let staff promote anyone', function () {
    signIn(Role::Staff);
    $other = User::factory()->create();

    $this->patchJson("/api/users/{$other->id}", ['role' => 'admin'])->assertStatus(403);
    expect($other->fresh()->role)->toBe(Role::Viewer);
});

it('keeps the test-error trigger admin-only even locally', function () {
    app()->detectEnvironment(fn () => 'local');
    signIn(Role::Staff);

    $this->postJson('/api/error-events/trigger')->assertStatus(403);
    expect(ErrorEvent::count())->toBe(0);
});

it('seeds one demo account per role and new accounts default to viewer', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'demo@example.com')->value('role'))->toBe(Role::Admin)
        ->and(User::where('email', 'staff@example.com')->value('role'))->toBe(Role::Staff)
        ->and(User::where('email', 'viewer@example.com')->value('role'))->toBe(Role::Viewer);

    $fresh = User::create(['name' => 'X', 'email' => 'x@example.com', 'password' => 'secret123']);
    expect($fresh->fresh()->role)->toBe(Role::Viewer);
});
