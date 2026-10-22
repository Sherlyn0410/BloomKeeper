<?php

use App\Models\CustomerOrder;
use App\Models\InventoryItem;
use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\StockAdjustment;
use App\Models\User;

beforeEach(function () {
    $this->staff = User::factory()->create(['role' => 'staff']);
    $this->supplier = User::factory()->create(['role' => 'supplier', 'name' => 'Highland Florals']);
    $this->roses = InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 20, 'unit_price' => 2.50, 'cost_price' => 1.20, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);
});

function stockPayload(array $overrides = []): array
{
    return [
        'flower_type' => 'Tulips', 'quantity' => 30, 'unit_price' => 1.80, 'cost_price' => 0.90,
        'date_received' => today()->format('Y-m-d'), 'shelf_life_days' => 5, 'low_stock_threshold' => 8,
        ...$overrides,
    ];
}

function staffPurchaseOrder(User $creator, User $supplier, string $status = 'sent'): PurchaseOrder
{
    return PurchaseOrder::create(['created_by' => $creator->id, 'supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'requested_date' => today()->addDays(2), 'status' => $status]);
}

test('staff can view, search, and sort the inventory without the delete action', function () {
    InventoryItem::create(stockPayload(['quantity' => 2]));

    $this->actingAs($this->staff)
        ->get(route('staff.inventory', ['search' => 'tul', 'sort' => 'quantity']))
        ->assertOk()
        ->assertSee('Tulips')
        ->assertDontSee('Roses')
        ->assertSee(route('staff.inventory.store'))
        ->assertDontSee('/admin/inventory')
        ->assertDontSee(route('admin.dashboard'));
});

test('staff can add and edit stock batches', function () {
    $this->actingAs($this->staff)->post(route('staff.inventory.store'), stockPayload())->assertSessionHasNoErrors();
    $this->actingAs($this->staff)->put(route('staff.inventory.update', $this->roses), stockPayload(['flower_type' => 'Roses', 'quantity' => 12]))->assertSessionHasNoErrors();

    expect(InventoryItem::where('flower_type', 'Tulips')->sole()->quantity)->toBe(30)
        ->and($this->roses->refresh()->quantity)->toBe(12);
});

test('admins and staff can delete a stock batch that has no history', function (string $role) {
    $user = $role === 'staff' ? $this->staff : User::factory()->create(['role' => 'admin']);

    $this->actingAs($user)->get(route("{$role}.inventory"))->assertSee('Delete Roses? This cannot be undone.');
    $this->actingAs($user)->delete(route("{$role}.inventory.destroy", $this->roses))->assertSessionHasNoErrors();

    expect(InventoryItem::find($this->roses->id))->toBeNull();
})->with(['staff', 'admin']);

test('a stock batch with history cannot be deleted by admins or staff', function (string $role, string $history) {
    $user = $role === 'staff' ? $this->staff : User::factory()->create(['role' => 'admin']);

    if ($history === 'wastage') {
        $this->roses->recordWastage($this->staff, 2, 'damaged');
    } else {
        $order = CustomerOrder::create(['created_by' => $this->staff->id, 'customer_name' => 'Priya Nair', 'collection_date' => today()->addDay(), 'status' => 'pending']);
        OrderItem::create(['customer_order_id' => $order->id, 'flower_type' => 'Roses', 'quantity' => 3, 'unit_price' => 2.50]);
        $order->syncStock($this->staff);
    }

    $this->actingAs($user)->get(route("{$role}.inventory"))
        ->assertDontSee('Delete Roses? This cannot be undone.')
        ->assertSee('Set its quantity to 0 instead.');

    $this->actingAs($user)->delete(route("{$role}.inventory.destroy", $this->roses))
        ->assertSessionHasErrors(['inventory' => "Roses can't be deleted because it has stock history (wastage, order stock, or adjustments). Set its quantity to 0 instead."]);

    expect(InventoryItem::find($this->roses->id))->not->toBeNull()
        ->and(StockAdjustment::where('inventory_item_id', $this->roses->id)->exists())->toBeTrue();
})->with(['staff', 'admin'])->with(['wastage', 'order stock']);

test('staff can create purchase orders and see every status', function () {
    $this->actingAs($this->staff)->post(route('staff.purchase-orders.store'), [
        'supplier_id' => $this->supplier->id, 'requested_date' => today()->addDays(3)->format('Y-m-d'), 'flower_type' => 'Roses', 'quantity' => 50, 'notes' => 'Long stems',
    ])->assertSessionHasNoErrors();

    expect(PurchaseOrder::sole())
        ->created_by->toBe($this->staff->id)
        ->status->toBe('sent');

    staffPurchaseOrder($this->staff, $this->supplier, 'declined');

    $this->actingAs($this->staff)->get(route('staff.purchase-orders'))
        ->assertOk()
        ->assertSee(['Sent', 'Fulfilled', 'Declined', 'Cancelled'])
        ->assertSee(route('staff.purchase-orders.store'))
        ->assertDontSee('/admin/');
});

test('staff can cancel and re-send their own purchase orders', function () {
    $sent = staffPurchaseOrder($this->staff, $this->supplier);
    $declined = staffPurchaseOrder($this->staff, $this->supplier, 'declined');

    $this->actingAs($this->staff)->patch(route('staff.purchase-orders.status', $sent), ['status' => 'cancelled'])->assertSessionHasNoErrors();
    $this->actingAs($this->staff)->patch(route('staff.purchase-orders.status', $declined), ['status' => 'sent'])->assertSessionHasNoErrors();

    expect($sent->refresh()->status)->toBe('cancelled')
        ->and($declined->refresh()->status)->toBe('sent');
});

test('staff cannot change purchase orders created by someone else', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $purchaseOrder = staffPurchaseOrder($admin, $this->supplier);

    $this->actingAs($this->staff)->get(route('staff.purchase-orders'))->assertDontSee(route('staff.purchase-orders.status', $purchaseOrder));
    $this->actingAs($this->staff)->patch(route('staff.purchase-orders.status', $purchaseOrder), ['status' => 'cancelled'])->assertForbidden();

    expect($purchaseOrder->refresh()->status)->toBe('sent');
});

test('fulfilling and declining stay supplier only', function () {
    $purchaseOrder = staffPurchaseOrder($this->staff, $this->supplier);

    $this->actingAs($this->staff)->patch(route('supplier.purchase-orders.fulfill', $purchaseOrder))->assertForbidden();
    $this->actingAs($this->staff)->patch(route('supplier.purchase-orders.decline', $purchaseOrder), ['supplier_note' => 'No'])->assertForbidden();
    $this->actingAs($this->staff)->patch(route('staff.purchase-orders.status', $purchaseOrder), ['status' => 'fulfilled'])->assertSessionHasErrors('status');

    expect($purchaseOrder->refresh()->status)->toBe('sent');
});

test('staff can move customer orders through their statuses and mark them paid', function () {
    $order = CustomerOrder::create(['created_by' => $this->staff->id, 'customer_name' => 'Priya Nair', 'collection_date' => today()->addDay(), 'status' => 'pending', 'payment_status' => 'unpaid']);
    OrderItem::create(['customer_order_id' => $order->id, 'flower_type' => 'Roses', 'quantity' => 4, 'unit_price' => 2.50]);
    $order->syncStock($this->staff);

    $this->actingAs($this->staff)->get(route('staff.orders.index'))
        ->assertSee(route('staff.orders.status', $order))
        ->assertSee(route('staff.orders.paid', $order));

    $this->actingAs($this->staff)->patch(route('staff.orders.status', $order), ['status' => 'ready'])->assertSessionHasNoErrors();
    $this->actingAs($this->staff)->patch(route('staff.orders.paid', $order))->assertSessionHasNoErrors();
    $this->actingAs($this->staff)->patch(route('staff.orders.status', $order), ['status' => 'cancelled'])->assertSessionHasNoErrors();

    expect($order->refresh())->status->toBe('cancelled')->payment_status->toBe('paid')
        ->and(StockAdjustment::where('type', 'wastage')->sum('quantity_change'))->toEqual(-4);
});

test('staff cannot change completed orders', function () {
    $order = CustomerOrder::create(['created_by' => $this->staff->id, 'customer_name' => 'Priya Nair', 'collection_date' => today()->addDay(), 'status' => 'completed']);

    $this->actingAs($this->staff)->patch(route('staff.orders.status', $order), ['status' => 'cancelled'])->assertForbidden();

    expect($order->refresh()->status)->toBe('completed');
});

test('the staff sidebar links to every staff page', function () {
    $this->actingAs($this->staff)->get(route('dashboard'))
        ->assertSee([
            route('dashboard'), route('staff.inventory'), route('staff.orders.index'), route('staff.orders.create'),
            route('staff.customers.index'), route('staff.purchase-orders'), route('staff.wastage.create'), route('profile.edit'),
        ]);
});

dataset('staff operation requests', [
    'inventory' => ['get', 'staff.inventory', null],
    'add stock' => ['post', 'staff.inventory.store', null],
    'edit stock' => ['put', 'staff.inventory.update', 'stock'],
    'delete stock' => ['delete', 'staff.inventory.destroy', 'stock'],
    'purchase orders' => ['get', 'staff.purchase-orders', null],
    'create purchase order' => ['post', 'staff.purchase-orders.store', null],
    'purchase order status' => ['patch', 'staff.purchase-orders.status', 'purchase-order'],
    'order status' => ['patch', 'staff.orders.status', 'order'],
    'mark order paid' => ['patch', 'staff.orders.paid', 'order'],
]);

function staffOperationUrl(string $route, ?string $model): string
{
    return route($route, match ($model) {
        'stock' => test()->roses,
        'purchase-order' => staffPurchaseOrder(test()->staff, test()->supplier),
        'order' => CustomerOrder::create(['created_by' => test()->staff->id, 'customer_name' => 'Priya Nair', 'collection_date' => today()->addDay(), 'status' => 'pending']),
        default => [],
    });
}

test('suppliers are blocked from the staff operations', function (string $method, string $route, ?string $model) {
    $this->actingAs($this->supplier)->{$method}(staffOperationUrl($route, $model))->assertForbidden();
})->with('staff operation requests');

test('guests are sent to the login page from the staff operations', function (string $method, string $route, ?string $model) {
    $this->{$method}(staffOperationUrl($route, $model))->assertRedirect(route('login'));
})->with('staff operation requests');

test('suppliers are blocked from the admin pages', function (string $route) {
    $this->actingAs($this->supplier)->get(route($route))->assertForbidden();
})->with(['admin.dashboard', 'admin.inventory', 'admin.orders', 'admin.purchase-orders', 'admin.reports', 'admin.wastage', 'admin.users']);

test('filtered lists are marked so the page opens at them', function (string $route, string $filters) {
    $this->actingAs($this->staff)->get(route($route))
        ->assertSee('data-scroll-on-filter="'.$filters.'"', false)
        ->assertSee("querySelectorAll('[data-scroll-on-filter]')", false);
})->with([
    'inventory' => ['staff.inventory', 'search,sort,direction,show_empty,alert'],
    'orders' => ['staff.orders.index', 'customer,date,status'],
    'purchase orders' => ['staff.purchase-orders', 'search,date,status'],
    'stock returns' => ['staff.stock-returns', 'status'],
    'wastage' => ['staff.wastage.create', 'sort,direction'],
]);
