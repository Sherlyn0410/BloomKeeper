<?php

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\InventoryItem;
use App\Models\User;

beforeEach(function () {
    $this->staff = User::factory()->create(['role' => 'staff']);
    InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 40, 'unit_price' => 2.50, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);
});

test('staff can view the customer list', function () {
    Customer::factory()->create(['name' => 'Aisha Rahman', 'created_by' => $this->staff->id]);

    $this->actingAs($this->staff)
        ->get(route('staff.customers.index'))
        ->assertOk()
        ->assertSee('Aisha Rahman');
});

test('staff can add a new customer', function () {
    $this->actingAs($this->staff)
        ->post(route('staff.customers.store'), ['name' => 'Daniel Tan', 'phone' => '016-222 3344', 'email' => 'daniel@example.com'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Customer::sole())
        ->name->toBe('Daniel Tan')
        ->phone->toBe('0162223344')
        ->formatted_phone->toBe('016-222 3344')
        ->created_by->toBe($this->staff->id);
});

test('adding a customer validates the details', function (array $data, array $errors) {
    Customer::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($this->staff)
        ->post(route('staff.customers.store'), $data)
        ->assertSessionHasErrors($errors);
})->with([
    'missing name and phone' => [['email' => 'new@example.com'], ['name', 'phone']],
    'invalid email' => [['name' => 'Leo', 'phone' => '0123456789', 'email' => 'not-an-email'], ['email']],
    'duplicate email' => [['name' => 'Leo', 'phone' => '0123456789', 'email' => 'taken@example.com'], ['email']],
    'phone too short' => [['name' => 'Leo', 'phone' => '012-345'], ['phone']],
    'phone too long' => [['name' => 'Leo', 'phone' => '012-345 678901'], ['phone']],
    'phone with letters' => [['name' => 'Leo', 'phone' => '012-345 abcd'], ['phone']],
]);

test('phone numbers are stored as digits and displayed by prefix', function (string $typed, string $stored, string $displayed) {
    $this->actingAs($this->staff)
        ->post(route('staff.customers.store'), ['name' => 'Aisha Rahman', 'phone' => $typed])
        ->assertSessionHasNoErrors();

    $customer = Customer::sole();
    expect($customer->phone)->toBe($stored)
        ->and($customer->formatted_phone)->toBe($displayed);

    $this->actingAs($this->staff)->get(route('staff.customers.index'))->assertSee($displayed);
})->with([
    '011 number' => ['011-1234 5678', '01112345678', '011-1234 5678'],
    'other mobile number' => ['012-345 6789', '0123456789', '012-345 6789'],
    'typed without separators' => ['0198765432', '0198765432', '019-876 5432'],
    'eleven digits, not 011' => ['01523456789', '01523456789', '015-234 56789'],
]);

test('staff can create an order for a saved customer and the details are copied onto the order', function () {
    $customer = Customer::factory()->create(['name' => 'Priya Nair', 'email' => 'priya@example.com', 'phone' => '017-889 1020']);

    $this->actingAs($this->staff)
        ->post(route('staff.orders.store'), [
            'customer_id' => $customer->id,
            'collection_date' => today()->addDay()->format('Y-m-d'),
            'payment_status' => 'unpaid',
            'items' => [['flower_type' => 'Roses', 'quantity' => 6]],
        ])
        ->assertRedirect(route('staff.orders.create'))
        ->assertSessionHasNoErrors();

    $order = CustomerOrder::with('items')->sole();
    expect($order)
        ->customer_id->toBe($customer->id)
        ->customer_name->toBe('Priya Nair')
        ->customer_email->toBe('priya@example.com')
        ->customer_phone->toBe('0178891020')
        ->status->toBe('pending')
        ->and($order->items->sole())
        ->flower_type->toBe('Roses')
        ->quantity->toBe(6)
        ->unit_price->toBe('2.50');
});

test('staff can add a new customer while creating an order', function () {
    $this->actingAs($this->staff)
        ->post(route('staff.orders.store'), [
            'customer_id' => 'new',
            'new_customer' => ['name' => 'Wong Mei Ling', 'phone' => '019-456 7788', 'email' => ''],
            'collection_date' => today()->addDay()->format('Y-m-d'),
            'payment_status' => 'paid',
            'items' => [['flower_type' => 'Roses', 'quantity' => 3]],
        ])
        ->assertSessionHasNoErrors();

    $customer = Customer::sole();
    expect($customer->name)->toBe('Wong Mei Ling')
        ->and(CustomerOrder::sole()->customer_id)->toBe($customer->id);
});

test('creating an order validates the customer, date, and flowers', function () {
    $this->actingAs($this->staff)
        ->post(route('staff.orders.store'), [
            'customer_id' => 'new',
            'new_customer' => ['name' => '', 'phone' => ''],
            'collection_date' => today()->subDay()->format('Y-m-d'),
            'items' => [['flower_type' => 'Cactus', 'quantity' => 0]],
        ])
        ->assertSessionHasErrors(['new_customer.name', 'new_customer.phone', 'collection_date', 'items.0.flower_type', 'items.0.quantity']);

    expect(Customer::count())->toBe(0)
        ->and(CustomerOrder::count())->toBe(0);
});

test('suppliers and unapproved staff cannot manage customers', function (array $attributes) {
    $this->actingAs(User::factory()->create($attributes))
        ->get(route('staff.customers.index'))
        ->assertForbidden();
})->with([
    'supplier' => [['role' => 'supplier']],
    'pending staff' => [['role' => 'staff', 'approval_status' => 'pending']],
]);
