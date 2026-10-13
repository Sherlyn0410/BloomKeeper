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

test('ready and completed orders cannot be deleted', function (string $status) {
    $order = makeOrder(['status' => $status]);

    $this->actingAs($this->staff)->delete(route('staff.orders.destroy', $order))->assertForbidden();

    expect($order->refresh()->status)->toBe($status);
})->with(['ready', 'completed']);

test('completed orders are locked against editing and status changes', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = makeOrder(['status' => 'completed']);

    $this->actingAs($this->staff)->get(route('staff.orders.edit', $order))->assertForbidden();
    $this->actingAs($this->staff)->put(route('staff.orders.update', $order), [])->assertForbidden();
    $this->actingAs($admin)->patch(route('admin.orders.status', $order), ['status' => 'cancelled'])->assertForbidden();

    $this->actingAs($admin)->get(route('admin.orders'))
        ->assertSee('Locked')
        ->assertDontSee(route('staff.orders.edit', $order))
        ->assertDontSee(route('admin.orders.status', $order));

    expect($order->refresh()->status)->toBe('completed');
});

test('order cards only offer delete for pending and cancelled orders', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $pending = makeOrder(['status' => 'pending']);
    $ready = makeOrder(['status' => 'ready']);
    $cancelled = makeOrder(['status' => 'cancelled']);

    $this->actingAs($admin)->get(route('admin.orders'))
        ->assertSee('action="'.route('staff.orders.destroy', $pending).'"', false)
        ->assertSee('action="'.route('staff.orders.destroy', $cancelled).'"', false)
        ->assertDontSee('action="'.route('staff.orders.destroy', $ready).'"', false)
        ->assertSee(route('staff.orders.edit', $ready))
        ->assertSee(route('staff.orders.edit', $pending))
        ->assertDontSee(route('staff.orders.edit', $cancelled))
        ->assertDontSee('Cancel order');
});

test('admins can mark unpaid orders as paid, including completed ones', function (string $status) {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = makeOrder(['status' => $status, 'payment_status' => 'unpaid']);

    $this->actingAs($admin)->get(route('admin.orders'))->assertSee('action="'.route('admin.orders.paid', $order).'"', false);

    $this->actingAs($admin)->patch(route('admin.orders.paid', $order))->assertRedirect();

    expect($order->refresh())->payment_status->toBe('paid')->status->toBe($status);
})->with(['pending', 'completed']);

test('cancelled or already paid orders cannot be marked as paid', function (array $attributes) {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = makeOrder($attributes);

    $this->actingAs($admin)->get(route('admin.orders'))->assertDontSee('action="'.route('admin.orders.paid', $order).'"', false);
    $this->actingAs($admin)->patch(route('admin.orders.paid', $order))->assertForbidden();
})->with([
    'cancelled' => [['status' => 'cancelled', 'payment_status' => 'unpaid']],
    'already paid' => [['payment_status' => 'paid']],
]);

test('staff cannot use the admin mark as paid action', function () {
    $order = makeOrder(['payment_status' => 'unpaid']);

    $this->actingAs($this->staff)->patch(route('admin.orders.paid', $order))->assertForbidden();

    expect($order->refresh()->payment_status)->toBe('unpaid');
});

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

test('new orders need a collection date after today', function (string $date, bool $allowed) {
    $response = $this->actingAs($this->staff)->post(route('staff.orders.store'), [
        'customer_id' => $this->customer->id,
        'collection_date' => $date,
        'payment_status' => 'unpaid',
        'items' => [['flower_type' => 'Roses', 'quantity' => 1]],
    ]);

    $allowed
        ? $response->assertSessionHasNoErrors()
        : $response->assertSessionHasErrors(['collection_date' => 'The collection date must be after today.']);
})->with([
    'yesterday' => ['2026-10-11', false],
    'today' => ['2026-10-12', false],
    'tomorrow' => ['2026-10-13', true],
]);

test('editing an overdue order can keep its date but cannot move it to another past date', function () {
    $order = makeOrder(['collection_date' => '2026-10-10']);
    $payload = fn (string $date): array => [
        'customer_id' => $this->customer->id,
        'collection_date' => $date,
        'payment_status' => 'paid',
        'status' => 'pending',
        'items' => [['flower_type' => 'Roses', 'quantity' => 6]],
    ];

    $this->actingAs($this->staff)->get(route('staff.orders.edit', $order))->assertSee('min="2026-10-10"', false);

    $this->actingAs($this->staff)->put(route('staff.orders.update', $order), $payload('2026-10-10'))->assertSessionHasNoErrors();
    expect($order->refresh()->payment_status)->toBe('paid');

    $this->actingAs($this->staff)
        ->put(route('staff.orders.update', $order), $payload('2026-10-09'))
        ->assertSessionHasErrors(['collection_date' => 'The collection date must be after today.']);
});
