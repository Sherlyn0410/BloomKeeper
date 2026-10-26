<?php

use App\Models\CustomerOrder;
use App\Models\InventoryItem;
use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->staff = User::factory()->create(['role' => 'staff']);
    $this->supplier = User::factory()->create(['role' => 'supplier', 'name' => 'Highland Florals']);
    $this->purchaseOrder = PurchaseOrder::create(['created_by' => $this->admin->id, 'supplier_id' => $this->supplier->id, 'supplier_name' => $this->supplier->name, 'requested_date' => today(), 'status' => 'fulfilled', 'fulfilled_at' => now()]);
    $this->purchaseOrder->items()->create(['flower_type' => 'Roses', 'quantity' => 50]);
});

function receivePayload(array $overrides = []): array
{
    return [
        'flower_type' => 'Roses', 'quantity' => 48, 'unit_price' => 2.50, 'cost_price' => 1.20,
        'date_received' => today()->format('Y-m-d'), 'shelf_life_days' => 7, 'low_stock_threshold' => 10, 'notes' => '2 stems short',
        ...$overrides,
    ];
}

test('fulfilling a purchase order does not add stock by itself', function () {
    expect(InventoryItem::count())->toBe(0);

    $this->actingAs($this->staff)->get(route('staff.purchase-orders'))
        ->assertSee('Receive into stock')
        ->assertSee('This is the first Roses delivery, so enter its prices and shelf life.');
});

test('admins and staff can receive a fulfilled delivery into stock', function (string $role) {
    $user = $role === 'admin' ? $this->admin : $this->staff;

    $this->actingAs($user)->post(route("{$role}.purchase-orders.receive", $this->purchaseOrder), receivePayload())->assertSessionHasNoErrors();

    expect(InventoryItem::sole())
        ->quantity->toBe(48)
        ->supplier_id->toBe($this->supplier->id)
        ->purchase_order_id->toBe($this->purchaseOrder->id)
        ->notes->toBe('2 stems short');

    $this->actingAs($user)->get(route("{$role}.purchase-orders"))
        ->assertSee('Received into stock: 48 Roses (2 short).')
        ->assertDontSee('Receive into stock');
})->with(['admin', 'staff']);

test('the receive form copies prices and shelf life from the last batch of that flower', function () {
    InventoryItem::create(['flower_type' => 'roses', 'quantity' => 5, 'unit_price' => 2.10, 'cost_price' => 0.95, 'date_received' => today()->subDays(9), 'shelf_life_days' => 6, 'low_stock_threshold' => 4]);
    InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 5, 'unit_price' => 2.80, 'cost_price' => 1.30, 'date_received' => today()->subDays(2), 'shelf_life_days' => 8, 'low_stock_threshold' => 12]);

    $this->actingAs($this->staff)->get(route('staff.purchase-orders'))
        ->assertSee('name="unit_price" value="2.80"', false)
        ->assertSee('name="cost_price" value="1.30"', false)
        ->assertSee('name="shelf_life_days" value="8"', false)
        ->assertSee('name="low_stock_threshold" value="12"', false)
        ->assertSee('name="quantity" value="50"', false);
});

test('a delivery can only be received once', function () {
    $this->actingAs($this->staff)->post(route('staff.purchase-orders.receive', $this->purchaseOrder), receivePayload())->assertSessionHasNoErrors();
    $this->actingAs($this->staff)->post(route('staff.purchase-orders.receive', $this->purchaseOrder), receivePayload())->assertForbidden();

    expect(InventoryItem::count())->toBe(1);
});

test('only fulfilled purchase orders can be received', function (string $status) {
    $this->purchaseOrder->update(['status' => $status]);

    $this->actingAs($this->staff)->post(route('staff.purchase-orders.receive', $this->purchaseOrder), receivePayload())->assertForbidden();

    expect(InventoryItem::count())->toBe(0);
})->with(['sent', 'declined', 'cancelled']);

test('received stock follows the usual stock rules', function () {
    $this->actingAs($this->staff)->post(route('staff.purchase-orders.receive', $this->purchaseOrder), receivePayload(['quantity' => 0, 'date_received' => today()->addDay()->format('Y-m-d')]))
        ->assertSessionHasErrors(['quantity', 'date_received' => 'The date received cannot be in the future.']);

    expect(InventoryItem::count())->toBe(0);
});

test('suppliers cannot receive deliveries and guests are sent to login', function () {
    $this->actingAs($this->supplier)->post(route('staff.purchase-orders.receive', $this->purchaseOrder), receivePayload())->assertForbidden();
    auth()->logout();
    $this->post(route('staff.purchase-orders.receive', $this->purchaseOrder), receivePayload())->assertRedirect(route('login'));

    expect(InventoryItem::count())->toBe(0);
});

test('a short delivery is flagged for the shop and the supplier', function () {
    $this->actingAs($this->staff)->post(route('staff.purchase-orders.receive', $this->purchaseOrder), receivePayload(['quantity' => 40, 'notes' => 'Internal: check fridge']))->assertSessionHasNoErrors();

    expect($this->purchaseOrder->refresh()->receivedDifference())->toBe(-10);

    $this->actingAs($this->staff)->get(route('staff.purchase-orders'))
        ->assertSee('10 short')
        ->assertSee('40 of 50 on')
        ->assertSee('Received into stock: 40 Roses (10 short).');

    $this->actingAs($this->supplier)->get(route('supplier.purchase-orders.index'))
        ->assertSee('Shop received 40 of 50')
        ->assertSee('10 short.')
        ->assertDontSee('Internal: check fridge');
});

test('extra and complete deliveries are shown too', function (int $received, string $shopSees, string $supplierSees) {
    $this->actingAs($this->staff)->post(route('staff.purchase-orders.receive', $this->purchaseOrder), receivePayload(['quantity' => $received]))->assertSessionHasNoErrors();

    $this->actingAs($this->staff)->get(route('staff.purchase-orders'))->assertSee($shopSees);
    $this->actingAs($this->supplier)->get(route('supplier.purchase-orders.index'))->assertSee($supplierSees);
})->with([
    'extra' => [55, 'Received into stock: 55 Roses (5 extra).', '5 extra.'],
    'complete' => [50, 'Received into stock: 50 Roses.', 'everything arrived.'],
]);

test('nothing is flagged before the delivery is received', function () {
    expect($this->purchaseOrder->receivedDifference())->toBeNull();

    $this->actingAs($this->supplier)->get(route('supplier.purchase-orders.index'))->assertDontSee('Shop received');
});

test('selling flowers from a received batch does not change what the delivery shows', function () {
    $this->actingAs($this->staff)->post(route('staff.purchase-orders.receive', $this->purchaseOrder), receivePayload(['quantity' => 50]))->assertSessionHasNoErrors();

    $order = CustomerOrder::create(['created_by' => $this->staff->id, 'customer_name' => 'Priya', 'collection_date' => today()->addDay(), 'status' => 'pending']);
    OrderItem::create(['customer_order_id' => $order->id, 'flower_type' => 'Roses', 'quantity' => 45, 'unit_price' => 2.50]);
    $order->syncStock($this->staff);

    expect(InventoryItem::sole()->quantity)->toBe(5)
        ->and($this->purchaseOrder->refresh())->received_quantity->toBe(50)
        ->and($this->purchaseOrder->receivedDifference())->toBe(0);

    $this->actingAs($this->staff)->get(route('staff.purchase-orders'))
        ->assertSee('50 of 50 on')
        ->assertDontSee('45 short');
    $this->actingAs($this->supplier)->get(route('supplier.purchase-orders.index'))
        ->assertSee('Shop received 50 of 50');
});

test('a short delivery offers a pre-filled re-order for the missing flowers', function () {
    $this->actingAs($this->staff)->post(route('staff.purchase-orders.receive', $this->purchaseOrder), receivePayload(['quantity' => 40]));
    $followUpUrl = route('staff.purchase-orders', ['follow_up' => $this->purchaseOrder->id]);

    $this->actingAs($this->staff)->get(route('staff.purchase-orders'))
        ->assertSee($followUpUrl.'#create-purchase-order', false)
        ->assertSee('Re-order missing 10');

    $this->actingAs($this->staff)->get($followUpUrl)
        ->assertSee('Re-ordering the 10 Roses missing from PO-0001. Please review the details and send.')
        ->assertSee('<option value="'.$this->supplier->id.'" selected>', false)
        ->assertSee('name="flower_type" value="Roses"', false)
        ->assertSee('name="quantity" value="10"', false)
        ->assertSee('value="Follow-up to PO-0001 (10 short)"', false);

    $this->actingAs($this->supplier)->get(route('supplier.purchase-orders.index'))
        ->assertSee('The shop may send a follow-up order for the missing flowers.');
});

test('complete deliveries have no re-order button and cannot be followed up', function () {
    $this->actingAs($this->staff)->post(route('staff.purchase-orders.receive', $this->purchaseOrder), receivePayload(['quantity' => 50]));

    $this->actingAs($this->staff)->get(route('staff.purchase-orders'))->assertDontSee('Re-order missing');
    $this->actingAs($this->staff)->get(route('staff.purchase-orders', ['follow_up' => $this->purchaseOrder->id]))
        ->assertDontSee('Re-ordering the')
        ->assertDontSee('name="quantity" value="50"', false);
});

function followUpPayload(PurchaseOrder $shortDelivery, int $quantity): array
{
    return [
        'supplier_id' => $shortDelivery->supplier_id, 'flower_type' => 'Roses', 'quantity' => $quantity,
        'requested_date' => today()->addDay()->format('Y-m-d'), 'follow_up_of_id' => $shortDelivery->id,
    ];
}

test('missing stock can only be re-ordered once', function () {
    $this->actingAs($this->staff)->post(route('staff.purchase-orders.receive', $this->purchaseOrder), receivePayload(['quantity' => 40]));
    $this->actingAs($this->staff)->post(route('staff.purchase-orders.store'), followUpPayload($this->purchaseOrder, 10))->assertSessionHasNoErrors();
    $followUp = PurchaseOrder::query()->latest('id')->first();

    expect($followUp->follow_up_of_id)->toBe($this->purchaseOrder->id);
    $this->actingAs($this->staff)->get(route('staff.purchase-orders'))
        ->assertDontSee('Re-order missing')
        ->assertSee('Missing stock re-ordered in '.$followUp->reference());
    $this->actingAs($this->staff)->get(route('staff.purchase-orders', ['follow_up' => $this->purchaseOrder->id]))->assertDontSee('Re-ordering the');
    $this->actingAs($this->staff)->post(route('staff.purchase-orders.store'), followUpPayload($this->purchaseOrder, 10))
        ->assertSessionHasErrors(['quantity' => 'The missing stock from PO-0001 has already been re-ordered.']);
});

test('a re-order cannot ask for more than the missing quantity', function () {
    $this->actingAs($this->staff)->post(route('staff.purchase-orders.receive', $this->purchaseOrder), receivePayload(['quantity' => 40]));

    $this->actingAs($this->staff)->post(route('staff.purchase-orders.store'), followUpPayload($this->purchaseOrder, 200))
        ->assertSessionHasErrors(['quantity' => 'Only 10 Roses are missing from PO-0001.']);
    $this->actingAs($this->staff)->post(route('staff.purchase-orders.store'), [...followUpPayload($this->purchaseOrder, 5), 'flower_type' => 'Tulips'])
        ->assertSessionHasErrors(['flower_type' => 'This re-order must be for Roses.']);

    $this->actingAs($this->staff)->post(route('staff.purchase-orders.store'), followUpPayload($this->purchaseOrder, 6))->assertSessionHasNoErrors();
    $this->actingAs($this->staff)->get(route('staff.purchase-orders'))->assertSee('Re-order missing 4');

    $followUp = PurchaseOrder::query()->latest('id')->first();
    $this->actingAs($this->staff)->put(route('staff.purchase-orders.update', $followUp), [...followUpPayload($this->purchaseOrder, 11), 'follow_up_of_id' => null])
        ->assertSessionHasErrors(['quantity' => 'Only 10 Roses are missing from PO-0001.']);
    $this->actingAs($this->staff)->put(route('staff.purchase-orders.update', $followUp), followUpPayload($this->purchaseOrder, 10))->assertSessionHasNoErrors();
});

test('cancelling a re-order lets the missing stock be re-ordered again', function () {
    $this->actingAs($this->staff)->post(route('staff.purchase-orders.receive', $this->purchaseOrder), receivePayload(['quantity' => 40]));
    $this->actingAs($this->staff)->post(route('staff.purchase-orders.store'), followUpPayload($this->purchaseOrder, 10));
    $followUp = PurchaseOrder::query()->latest('id')->first();

    $this->actingAs($this->staff)->patch(route('staff.purchase-orders.status', $followUp), ['status' => 'cancelled'])->assertSessionHasNoErrors();

    $this->actingAs($this->staff)->get(route('staff.purchase-orders'))->assertSee('Re-order missing 10');
});
