<?php

use App\Models\PurchaseOrder;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->supplier = User::factory()->create(['role' => 'supplier', 'name' => 'Highland Florals']);
});

function purchaseOrderFor(User $supplier, array $attributes = []): PurchaseOrder
{
    $purchaseOrder = PurchaseOrder::create([
        'created_by' => test()->admin->id,
        'supplier_id' => $supplier->id,
        'supplier_name' => $supplier->name,
        'requested_date' => today()->addDays(2),
        'status' => 'sent',
        ...$attributes,
    ]);
    $purchaseOrder->items()->create(['flower_type' => 'Roses', 'quantity' => 40]);

    return $purchaseOrder;
}

test('suppliers only see purchase orders sent to them', function () {
    $mine = purchaseOrderFor($this->supplier, ['notes' => 'Red only please']);
    $theirs = purchaseOrderFor(User::factory()->create(['role' => 'supplier']), ['notes' => 'Someone else\'s order']);

    $this->actingAs($this->supplier)
        ->get(route('supplier.purchase-orders.index'))
        ->assertOk()
        ->assertSee($mine->reference())
        ->assertSee('Red only please')
        ->assertSee('Due in 2 days')
        ->assertDontSee('Someone else\'s order');
});

test('a supplier can mark their order fulfilled with a note', function () {
    $purchaseOrder = purchaseOrderFor($this->supplier);

    $this->actingAs($this->supplier)
        ->patch(route('supplier.purchase-orders.fulfill', $purchaseOrder), ['supplier_note' => 'Delivered at 10am'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($purchaseOrder->refresh())
        ->status->toBe('fulfilled')
        ->supplier_note->toBe('Delivered at 10am')
        ->fulfilled_at->not->toBeNull();

    $this->actingAs($this->admin)->get(route('admin.purchase-orders'))->assertSee('Supplier replied:')->assertSee('Delivered at 10am');
});

test('declining needs a reason, which the shop can see', function () {
    $purchaseOrder = purchaseOrderFor($this->supplier);

    $this->actingAs($this->supplier)
        ->patch(route('supplier.purchase-orders.decline', $purchaseOrder), ['supplier_note' => ''])
        ->assertSessionHasErrors(['supplier_note' => 'Please give the shop a reason for declining.']);
    expect($purchaseOrder->refresh()->status)->toBe('sent');

    $this->actingAs($this->supplier)
        ->patch(route('supplier.purchase-orders.decline', $purchaseOrder), ['supplier_note' => 'Roses are out of season'])
        ->assertSessionHasNoErrors();

    expect($purchaseOrder->refresh())
        ->status->toBe('declined')
        ->supplier_note->toBe('Roses are out of season');

    $this->actingAs($this->admin)->get(route('admin.purchase-orders'))->assertSee('Supplier declined:')->assertSee('Roses are out of season');
});

test('suppliers cannot respond to another supplier\'s order or to an order that is no longer open', function () {
    $someoneElses = purchaseOrderFor(User::factory()->create(['role' => 'supplier']));
    $alreadyFulfilled = purchaseOrderFor($this->supplier, ['status' => 'fulfilled']);

    $this->actingAs($this->supplier)->patch(route('supplier.purchase-orders.fulfill', $someoneElses))->assertForbidden();
    $this->actingAs($this->supplier)->patch(route('supplier.purchase-orders.decline', $alreadyFulfilled), ['supplier_note' => 'x'])->assertForbidden();

    expect($someoneElses->refresh()->status)->toBe('sent')
        ->and($alreadyFulfilled->refresh()->status)->toBe('fulfilled');
});

test('only approved suppliers can use the supplier pages', function (array $attributes) {
    $this->actingAs(User::factory()->create($attributes))
        ->get(route('supplier.purchase-orders.index'))
        ->assertForbidden();
})->with([
    'staff' => [['role' => 'staff']],
    'admin' => [['role' => 'admin']],
    'pending supplier' => [['role' => 'supplier', 'approval_status' => 'pending']],
]);

test('the supplier list can be filtered by status', function () {
    purchaseOrderFor($this->supplier, ['notes' => 'still-open-order']);
    purchaseOrderFor($this->supplier, ['status' => 'fulfilled', 'notes' => 'already-delivered-order']);

    $this->actingAs($this->supplier)
        ->get(route('supplier.purchase-orders.index', ['status' => 'fulfilled']))
        ->assertSee('already-delivered-order')
        ->assertDontSee('still-open-order');
});
