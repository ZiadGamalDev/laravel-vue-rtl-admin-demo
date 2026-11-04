<?php

use App\Models\Customer;
use App\Models\Order;

beforeEach(fn () => signIn());

it('lists customers with pagination meta', function () {
    Customer::factory()->count(15)->create();

    $this->getJson('/api/customers?per_page=10')
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.total', 15)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonStructure(['data' => [['id', 'name', 'email', 'phone', 'city', 'orders_count', 'created_at']]]);
});

it('searches by name, email, phone and city', function () {
    Customer::factory()->create(['name' => 'ليلى منصور', 'email' => 'layla@example.com', 'city' => 'عمّان']);
    Customer::factory()->create(['name' => 'Other Person', 'email' => 'other@example.com', 'city' => 'Elsewhere']);

    $this->getJson('/api/customers?search='.urlencode('ليلى'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'layla@example.com');
    $this->getJson('/api/customers?search=other@')->assertJsonCount(1, 'data');
    $this->getJson('/api/customers?search='.urlencode('عمّان'))->assertJsonCount(1, 'data');
    $this->getJson('/api/customers?search=%25')->assertJsonCount(0, 'data');
});

it('sorts by whitelisted columns only', function () {
    Customer::factory()->create(['name' => 'Bravo']);
    Customer::factory()->create(['name' => 'Alpha']);

    $this->getJson('/api/customers?sort=name&direction=asc')->assertJsonPath('data.0.name', 'Alpha');
    $this->getJson('/api/customers?sort=name&direction=desc')->assertJsonPath('data.0.name', 'Bravo');
    $this->getJson('/api/customers?sort=password')->assertStatus(422)->assertJsonValidationErrors('sort');
});

it('creates, shows, updates and deletes a customer', function () {
    $id = $this->postJson('/api/customers', [
        'name' => 'سارة النجار',
        'email' => 'sara@example.com',
        'phone' => '555-0199',
        'city' => 'الدوحة',
    ])->assertCreated()->json('data.id');

    $this->getJson("/api/customers/$id")->assertOk()->assertJsonPath('data.city', 'الدوحة');

    $this->putJson("/api/customers/$id", ['name' => 'سارة', 'email' => 'sara@example.com'])
        ->assertOk()
        ->assertJsonPath('data.name', 'سارة');

    $this->deleteJson("/api/customers/$id")->assertNoContent();
    expect(Customer::find($id))->toBeNull();
});

it('returns Arabic validation errors', function () {
    Customer::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/customers', ['name' => '', 'email' => 'taken@example.com', 'phone' => 'abc'])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'حقل الاسم مطلوب.')
        ->assertJsonPath('errors.email.0', 'قيمة البريد الإلكتروني مستخدمة من قبل.')
        ->assertJsonPath('errors.phone.0', 'صيغة الهاتف غير صحيحة.');
});

it('refuses to delete a customer who has orders', function () {
    $order = Order::factory()->withItems()->create();

    $this->deleteJson("/api/customers/{$order->customer_id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'لا يمكن حذف عميل لديه طلبات. احذف طلباته أولًا.');
});
