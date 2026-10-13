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

test('cancelling returns the stock', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = placeOrder([['flower_type' => 'Roses', 'quantity' => 4]]);

    $this->actingAs($admin)->patch(route('admin.orders.status', $order), ['status' => 'cancelled']);

    expect($this->roses->refresh()->quantity)->toBe(10)
        ->and($order->heldStock()->all())->toBe([]);
});

test('cancelled orders cannot be edited or reopened, only deleted', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = placeOrder([['flower_type' => 'Roses', 'quantity' => 4]]);
    $this->actingAs($admin)->patch(route('admin.orders.status', $order), ['status' => 'cancelled']);

    $this->actingAs($admin)->get(route('staff.orders.edit', $order))->assertForbidden();
    $this->actingAs($admin)
        ->put(route('staff.orders.update', $order), orderPayload([['flower_type' => 'Roses', 'quantity' => 4]], ['status' => 'pending']))
        ->assertForbidden();
    $this->actingAs($admin)->get(route('admin.orders'))
        ->assertDontSee(route('staff.orders.edit', $order))
        ->assertSee('action="'.route('staff.orders.destroy', $order).'"', false);

    $this->actingAs($admin)->delete(route('staff.orders.destroy', $order))->assertRedirect();

    expect(CustomerOrder::find($order->id))->toBeNull()
        ->and($this->roses->refresh()->quantity)->toBe(10);
});

test('deleting a pending order returns its stock', function () {
    $pending = placeOrder([['flower_type' => 'Roses', 'quantity' => 3]]);

    $this->actingAs($this->staff)->delete(route('staff.orders.destroy', $pending));

    expect(CustomerOrder::find($pending->id))->toBeNull()
        ->and($this->roses->refresh()->quantity)->toBe(10);
});

test('cancelling a ready order records its flowers as wastage instead of returning them', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = placeOrder([['flower_type' => 'Lilies', 'quantity' => 2], ['flower_type' => 'Roses', 'quantity' => 3]]);
    $this->actingAs($admin)->patch(route('admin.orders.status', $order), ['status' => 'ready']);

    $this->actingAs($admin)->patch(route('admin.orders.status', $order), ['status' => 'cancelled'])->assertSessionHasNoErrors();

    $wastage = StockAdjustment::where('type', 'wastage')->get();
    expect($order->refresh()->status)->toBe('cancelled')
        ->and($this->lilies->refresh()->quantity)->toBe(3)
        ->and($this->roses->refresh()->quantity)->toBe(7)
        ->and($order->heldStock()->all())->toBe([])
        ->and($wastage)->toHaveCount(2)
        ->and($wastage->sum('quantity_change'))->toEqual(-5)
        ->and($wastage->pluck('reason')->unique()->all())->toBe(['other'])
        ->and($wastage->first()->notes)->toContain($order->order_number);

    $this->actingAs($admin)->get(route('admin.reports.wastage'))->assertSee('RM 7.00');
});

test('cancelling a ready order through the edit form also records wastage', function () {
    $order = placeOrder([['flower_type' => 'Roses', 'quantity' => 4]]);
    $order->update(['status' => 'ready']);

    $this->actingAs($this->staff)
        ->put(route('staff.orders.update', $order), orderPayload([['flower_type' => 'Roses', 'quantity' => 4]], ['status' => 'cancelled']))
        ->assertSessionHasNoErrors();

    expect($this->roses->refresh()->quantity)->toBe(6)
        ->and(StockAdjustment::where('type', 'wastage')->sum('quantity_change'))->toEqual(-4);
});

test('cancelling a ready order placed before stock tracking takes its flowers from stock as wastage', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $legacyOrder = CustomerOrder::create(['created_by' => $admin->id, 'customer_name' => 'Old Order', 'collection_date' => today(), 'status' => 'ready']);
    $legacyOrder->items()->create(['flower_type' => 'Roses', 'quantity' => 4, 'unit_price' => 2.50]);
    $legacyOrder->items()->create(['flower_type' => 'Lilies', 'quantity' => 7, 'unit_price' => 3.25]);

    $this->actingAs($admin)->patch(route('admin.orders.status', $legacyOrder), ['status' => 'cancelled'])->assertSessionHasNoErrors();

    $wastage = StockAdjustment::with('inventoryItem')->where('type', 'wastage')->get()->keyBy(fn ($adjustment) => $adjustment->inventoryItem->flower_type);
    expect($this->roses->refresh()->quantity)->toBe(6)
        ->and($this->lilies->refresh()->quantity)->toBe(0)
        ->and($wastage['Roses']->quantity_change)->toBe(-4)
        ->and($wastage['Roses']->notes)->toBe("Order {$legacyOrder->order_number} was cancelled after it was prepared.")
        ->and($wastage['Lilies']->quantity_change)->toBe(-5)
        ->and($wastage['Lilies']->notes)->toContain('2 more Lilies were used but not in stock records.');
});

test('cancelling a pending order still returns its stock', function () {
    $order = placeOrder([['flower_type' => 'Roses', 'quantity' => 4]]);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.orders.status', $order), ['status' => 'cancelled']);

    expect($this->roses->refresh()->quantity)->toBe(10)
        ->and(StockAdjustment::where('type', 'wastage')->count())->toBe(0);
});

test('the status dropdown is disabled for cancelled orders and the server refuses changes', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = placeOrder([['flower_type' => 'Roses', 'quantity' => 4]]);
    $this->actingAs($admin)->patch(route('admin.orders.status', $order), ['status' => 'cancelled']);

    $this->actingAs($admin)->get(route('admin.orders'))
        ->assertSee('id="order-status-'.$order->id.'" disabled', false)
        ->assertDontSee('action="'.route('admin.orders.status', $order).'"', false);

    $this->actingAs($admin)
        ->patch(route('admin.orders.status', $order), ['status' => 'pending'])
        ->assertForbidden();

    expect($order->refresh()->status)->toBe('cancelled')
        ->and($this->roses->refresh()->quantity)->toBe(10);
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
