<?php

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\InventoryItem;
use App\Models\StockAdjustment;
use App\Models\User;

beforeEach(function () {
    $this->staff = User::factory()->create(['role' => 'staff']);
    $this->customer = Customer::factory()->create(['created_by' => $this->staff->id]);
    $this->roses = InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 10, 'unit_price' => 2.50, 'cost_price' => 1.20, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 2]);
    $this->lilies = InventoryItem::create(['flower_type' => 'Lilies', 'quantity' => 5, 'unit_price' => 3.25, 'cost_price' => 1.70, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 2]);
});

function orderPayload(array $items, array $overrides = []): array
{
    return [
        'customer_id' => test()->customer->id,
        'collection_date' => today()->addDay()->format('Y-m-d'),
        'payment_status' => 'unpaid',
        'items' => $items,
        ...$overrides,
    ];
}

function placeOrder(array $items): CustomerOrder
{
    test()->actingAs(test()->staff)->post(route('staff.orders.store'), orderPayload($items))->assertSessionHasNoErrors();

    return CustomerOrder::latest('id')->firstOrFail();
}

test('placing an order deducts stock and logs the movement against the order', function () {
    $order = placeOrder([['flower_type' => 'Roses', 'quantity' => 4], ['flower_type' => 'Lilies', 'quantity' => 2]]);

    expect($this->roses->refresh()->quantity)->toBe(6)
        ->and($this->lilies->refresh()->quantity)->toBe(3)
        ->and($order->heldStock()->all())->toEqualCanonicalizing(['Roses' => 4, 'Lilies' => 2])
        ->and(StockAdjustment::where('type', 'order')->where('customer_order_id', $order->id)->sum('quantity_change'))->toEqual(-6);
});

test('an order cannot take more stock than is available', function () {
    $this->actingAs($this->staff)
        ->post(route('staff.orders.store'), orderPayload([['flower_type' => 'Roses', 'quantity' => 11], ['flower_type' => 'Lilies', 'quantity' => 1]]))
        ->assertSessionHasErrors(['items' => 'Only 10 Roses left in stock, but this order needs 11.']);

    expect(CustomerOrder::count())->toBe(0)
        ->and($this->roses->refresh()->quantity)->toBe(10)
        ->and($this->lilies->refresh()->quantity)->toBe(5)
        ->and(StockAdjustment::count())->toBe(0);
});

test('the same flower on two lines is checked against stock as one total', function () {
    $this->actingAs($this->staff)
        ->post(route('staff.orders.store'), orderPayload([['flower_type' => 'Roses', 'quantity' => 6], ['flower_type' => 'Roses', 'quantity' => 6]]))
        ->assertSessionHasErrors('items');

    expect($this->roses->refresh()->quantity)->toBe(10);
});

test('stock is taken from the batch that spoils soonest first', function () {
    $this->roses->update(['quantity' => 3, 'date_received' => today()->subDays(5)]);
    $freshRoses = InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 10, 'unit_price' => 2.50, 'cost_price' => 1.20, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 2]);

    placeOrder([['flower_type' => 'Roses', 'quantity' => 5]]);

    expect($this->roses->refresh()->quantity)->toBe(0)
        ->and($freshRoses->refresh()->quantity)->toBe(8);
});

test('editing an order adjusts stock by the difference', function () {
    $order = placeOrder([['flower_type' => 'Roses', 'quantity' => 4]]);

    $this->actingAs($this->staff)
        ->put(route('staff.orders.update', $order), orderPayload([['flower_type' => 'Roses', 'quantity' => 7], ['flower_type' => 'Lilies', 'quantity' => 1]], ['status' => 'pending']))
        ->assertSessionHasNoErrors();

    expect($this->roses->refresh()->quantity)->toBe(3)
        ->and($this->lilies->refresh()->quantity)->toBe(4);

    $this->actingAs($this->staff)
        ->put(route('staff.orders.update', $order), orderPayload([['flower_type' => 'Roses', 'quantity' => 12]], ['status' => 'pending']))
        ->assertSessionHasErrors('items');

    expect($this->roses->refresh()->quantity)->toBe(3)
        ->and($this->lilies->refresh()->quantity)->toBe(4)
        ->and($order->refresh()->items()->sum('quantity'))->toEqual(8);
});

test('changing only the status does not move stock', function () {
    $order = placeOrder([['flower_type' => 'Roses', 'quantity' => 4]]);
    $movements = StockAdjustment::count();

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.orders.status', $order), ['status' => 'ready']);

    expect(StockAdjustment::count())->toBe($movements)
        ->and($this->roses->refresh()->quantity)->toBe(6);
});

test('cancelling returns the stock and reopening takes it again', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = placeOrder([['flower_type' => 'Roses', 'quantity' => 4]]);

    $this->actingAs($admin)->patch(route('admin.orders.status', $order), ['status' => 'cancelled']);
    expect($this->roses->refresh()->quantity)->toBe(10)
        ->and($order->heldStock()->all())->toBe([]);

    $this->actingAs($admin)->patch(route('admin.orders.status', $order), ['status' => 'pending']);
    expect($this->roses->refresh()->quantity)->toBe(6);
});

test('a cancelled order cannot be reopened when its flowers have sold out', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = placeOrder([['flower_type' => 'Roses', 'quantity' => 4]]);
    $this->actingAs($admin)->patch(route('admin.orders.status', $order), ['status' => 'cancelled']);
    placeOrder([['flower_type' => 'Roses', 'quantity' => 8]]);

    $this->actingAs($admin)
        ->patch(route('admin.orders.status', $order), ['status' => 'pending'])
        ->assertSessionHasErrors('items');

    expect($order->refresh()->status)->toBe('cancelled')
        ->and($this->roses->refresh()->quantity)->toBe(2);
});

test('deleting a pending order and cancelling a ready order both return stock', function () {
    $pending = placeOrder([['flower_type' => 'Roses', 'quantity' => 3]]);
    $ready = placeOrder([['flower_type' => 'Lilies', 'quantity' => 2]]);
    $ready->update(['status' => 'ready']);

    $this->actingAs($this->staff)->delete(route('staff.orders.destroy', $pending));
    $this->actingAs($this->staff)->delete(route('staff.orders.destroy', $ready));

    expect(CustomerOrder::find($pending->id))->toBeNull()
        ->and($ready->refresh()->status)->toBe('cancelled')
        ->and($this->roses->refresh()->quantity)->toBe(10)
        ->and($this->lilies->refresh()->quantity)->toBe(5);
});

test('orders placed before stock tracking can still change status without touching stock', function () {
    $legacyOrder = CustomerOrder::create(['created_by' => $this->staff->id, 'customer_name' => 'Old Order', 'collection_date' => today(), 'status' => 'pending']);
    $legacyOrder->items()->create(['flower_type' => 'Tulips', 'quantity' => 12, 'unit_price' => 1.80]);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.orders.status', $legacyOrder), ['status' => 'completed'])
        ->assertSessionHasNoErrors();

    expect($legacyOrder->refresh()->status)->toBe('completed')
        ->and(StockAdjustment::count())->toBe(0);
});

test('the order form shows how much of each flower is in stock', function () {
    $this->actingAs($this->staff)
        ->get(route('staff.orders.create'))
        ->assertSee('Roses (10 in stock)')
        ->assertSee('Lilies (5 in stock)');
});
