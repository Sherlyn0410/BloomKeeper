<?php

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\InventoryItem;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-12 09:00:00'));
    $this->staff = User::factory()->create(['role' => 'staff']);
    $this->customer = Customer::factory()->create(['name' => 'Priya Nair', 'created_by' => $this->staff->id]);
    InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 40, 'unit_price' => 2.50, 'cost_price' => 1.20, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);
    InventoryItem::create(['flower_type' => 'Lilies', 'quantity' => 20, 'unit_price' => 3.25, 'cost_price' => 1.70, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);
});

function makeOrder(array $attributes = []): CustomerOrder
{
    $order = CustomerOrder::create([
        'created_by' => test()->staff->id,
        'customer_id' => test()->customer->id,
        'customer_name' => test()->customer->name,
        'collection_date' => '2026-10-14',
        'status' => 'pending',
        ...$attributes,
    ]);
    OrderItem::create(['customer_order_id' => $order->id, 'flower_type' => 'Roses', 'quantity' => 6, 'unit_price' => 2.00]);

    return $order;
}

test('new orders get sequential order numbers per year', function () {
    expect(makeOrder()->order_number)->toBe('BK-2026-0001')
        ->and(makeOrder()->order_number)->toBe('BK-2026-0002');
});

test('orders store collection time, notes, and payment status', function () {
    $this->actingAs($this->staff)
        ->post(route('staff.orders.store'), [
            'customer_id' => $this->customer->id,
            'collection_date' => '2026-10-13',
            'collection_time' => '14:30',
            'notes' => 'Card: Happy birthday!',
            'payment_status' => 'paid',
            'items' => [['flower_type' => 'Roses', 'quantity' => 3]],
        ])
        ->assertSessionHasNoErrors();

    expect(CustomerOrder::sole())
        ->order_number->toBe('BK-2026-0001')
        ->collection_time->toStartWith('14:30')
        ->notes->toBe('Card: Happy birthday!')
        ->payment_status->toBe('paid');
});

test('staff can edit an order and existing item prices are kept', function () {
    $order = makeOrder();

    $this->actingAs($this->staff)->get(route('staff.orders.edit', $order))->assertOk()->assertSee($order->order_number);

    $this->actingAs($this->staff)
        ->put(route('staff.orders.update', $order), [
            'customer_id' => $this->customer->id,
            'collection_date' => '2026-10-15',
            'collection_time' => '09:15',
            'notes' => 'Changed to white wrapping.',
            'payment_status' => 'paid',
            'status' => 'ready',
            'items' => [['flower_type' => 'Roses', 'quantity' => 10], ['flower_type' => 'Lilies', 'quantity' => 2]],
        ])
        ->assertRedirect(route('staff.orders.index'))
        ->assertSessionHasNoErrors();

    $order->refresh()->load('items');
    expect($order)
        ->status->toBe('ready')
        ->payment_status->toBe('paid')
        ->notes->toBe('Changed to white wrapping.')
        ->and($order->collection_date->format('Y-m-d'))->toBe('2026-10-15')
        ->and($order->items->pluck('unit_price', 'flower_type')->all())->toBe(['Roses' => '2.00', 'Lilies' => '3.25'])
        ->and($order->items->pluck('quantity', 'flower_type')->all())->toBe(['Roses' => 10, 'Lilies' => 2]);
});

test('pending orders are deleted', function () {
    $order = makeOrder();

    $this->actingAs($this->staff)->delete(route('staff.orders.destroy', $order))->assertRedirect();

    expect(CustomerOrder::find($order->id))->toBeNull()
        ->and(OrderItem::count())->toBe(0);
});

test('ready and completed orders are cancelled instead of deleted', function (string $status) {
    $order = makeOrder(['status' => $status]);

    $this->actingAs($this->staff)->delete(route('staff.orders.destroy', $order))->assertRedirect();

    expect($order->refresh()->status)->toBe('cancelled');
})->with(['ready', 'completed']);

test('admins are sent back to the admin orders page after editing', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = makeOrder();

    $this->actingAs($admin)->delete(route('staff.orders.destroy', $order))->assertRedirect(route('admin.orders'));
});

test('admin orders can be searched by order number', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    makeOrder();
    makeOrder(['customer_name' => 'Leo Tan']);

    $this->actingAs($admin)
        ->get(route('admin.orders', ['customer' => 'BK-2026-0002']))
        ->assertOk()
        ->assertSee('Leo Tan')
        ->assertDontSee('Priya Nair');
});

test('the staff order list can be searched by order number', function () {
    makeOrder();
    makeOrder(['customer_name' => 'Leo Tan']);

    $this->actingAs($this->staff)
        ->get(route('staff.orders.index', ['search' => '0001']))
        ->assertOk()
        ->assertSee('Priya Nair')
        ->assertDontSee('Leo Tan');
});

test('suppliers cannot edit or delete orders', function () {
    $order = makeOrder();
    $supplier = User::factory()->create(['role' => 'supplier']);

    $this->actingAs($supplier)->get(route('staff.orders.edit', $order))->assertForbidden();
    $this->actingAs($supplier)->delete(route('staff.orders.destroy', $order))->assertForbidden();

    expect(CustomerOrder::find($order->id))->not->toBeNull();
});
