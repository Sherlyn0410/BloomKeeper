<?php

use App\Models\InventoryItem;
use App\Models\User;

beforeEach(function () {
    $this->staff = User::factory()->create(['role' => 'staff']);
    $this->supplier = User::factory()->create(['role' => 'supplier', 'name' => 'Highland Florals']);
    $this->tulips = InventoryItem::create(['flower_type' => 'Tulips', 'quantity' => 3, 'unit_price' => 1.80, 'cost_price' => 0.90, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 8, 'supplier_id' => $this->supplier->id]);
});

test('low stock flowers get a reorder button for the same flower and supplier', function () {
    $reorderUrl = route('staff.purchase-orders', ['flower' => 'Tulips', 'supplier' => $this->supplier->id]);

    $this->actingAs($this->staff)->get(route('staff.inventory'))->assertSee($reorderUrl.'#create-purchase-order');
});

test('flowers with enough stock have no reorder button', function () {
    $this->tulips->update(['quantity' => 20]);

    $this->actingAs($this->staff)->get(route('staff.inventory'))->assertDontSee('>Reorder</a>', false);
});

test('the reorder link pre-fills the purchase order form', function () {
    $this->actingAs($this->staff)
        ->get(route('staff.purchase-orders', ['flower' => 'Tulips', 'supplier' => $this->supplier->id]))
        ->assertOk()
        ->assertSee('Tulips stock is low. The previous supplier has been selected. Please enter the quantity required.')
        ->assertSee('<option value="'.$this->supplier->id.'" selected>', false)
        ->assertSee('name="flower_type" value="Tulips"', false)
        ->assertSee('name="quantity" value="" type="number"', false);
});

test('the dashboard low stock list links to a reorder', function (string $role, string $dashboard, string $area) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route($dashboard))
        ->assertSee(route("{$area}.purchase-orders", ['flower' => 'Tulips', 'supplier' => $this->supplier->id]).'#create-purchase-order');
})->with([
    'staff' => ['staff', 'dashboard', 'staff'],
    'admin' => ['admin', 'admin.dashboard', 'admin'],
]);

test('the reorder note asks for a supplier when the previous one cannot be selected', function () {
    $this->supplier->update(['approval_status' => 'suspended']);

    $this->actingAs($this->staff)
        ->get(route('staff.purchase-orders', ['flower' => 'Tulips', 'supplier' => $this->supplier->id]))
        ->assertSee('Tulips stock is low. Please select a supplier and enter the quantity required.');
});
