<?php

use App\Models\CustomerOrder;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\StockReturn;
use App\Models\User;
use Illuminate\Support\Carbon;

test('staff see the workspace dashboard with stock alerts, collections, and recent orders', function () {
    $this->travelTo(Carbon::parse('2026-10-13 02:00:00', 'UTC'));
    $staff = User::factory()->create(['role' => 'staff', 'name' => 'Siti Aminah']);
    InventoryItem::create(['flower_type' => 'Tulips', 'quantity' => 3, 'unit_price' => 1.80, 'cost_price' => 0.90, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);
    InventoryItem::create(['flower_type' => 'Lilies', 'quantity' => 20, 'unit_price' => 3.25, 'cost_price' => 1.70, 'date_received' => today()->subDays(6), 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);
    CustomerOrder::create(['created_by' => $staff->id, 'customer_name' => 'Aisha Rahman', 'collection_date' => today(), 'status' => 'ready']);
    CustomerOrder::create(['created_by' => $staff->id, 'customer_name' => 'Daniel Tan', 'collection_date' => today()->addDay(), 'status' => 'pending']);

    $this->actingAs($staff)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Good morning, Siti.')
        ->assertSeeInOrder(['Flowers in stock', '2', 'Low-stock alerts', '1', 'Spoilage alerts', '1', 'Pending orders', '1', 'Collections today', '1'])
        ->assertSee('Aisha Rahman')
        ->assertSee('Daniel Tan')
        ->assertSeeInOrder(['Low stock', 'Tulips', '3 remaining', 'Spoilage alerts', 'Lilies', 'Check by'])
        ->assertSee(route('staff.inventory', ['alert' => 'low_stock']))
        ->assertSee(route('staff.inventory', ['alert' => 'spoilage']))
        ->assertSee(route('staff.orders.index'))
        ->assertSee(route('staff.wastage.create'))
        ->assertDontSee(route('admin.dashboard'));
});

test('the greeting follows the time of day in Malaysia', function (string $utcTime, string $greeting) {
    $this->travelTo(Carbon::parse("2026-10-13 {$utcTime}", 'UTC'));

    $this->actingAs(User::factory()->create(['role' => 'staff', 'name' => 'Siti Aminah']))
        ->get(route('dashboard'))
        ->assertSee("{$greeting}, Siti.");
})->with([
    'morning' => ['01:00:00', 'Good morning'],
    'afternoon' => ['07:00:00', 'Good afternoon'],
    'evening' => ['12:00:00', 'Good evening'],
]);

test('staff pages use the sidebar layout', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff']))
        ->get(route('staff.orders.index'))
        ->assertOk()
        ->assertSee('Staff workspace')
        ->assertSee('Back to dashboard')
        ->assertSee('All orders')
        ->assertSee('Create order')
        ->assertSee('Record wastage');
});

test('suppliers see their purchase order summary and no staff tools', function () {
    $this->travelTo(Carbon::parse('2026-10-13 02:00:00', 'UTC'));
    $admin = User::factory()->create(['role' => 'admin']);
    $supplier = User::factory()->create(['role' => 'supplier']);
    $otherSupplier = User::factory()->create(['role' => 'supplier']);
    $dueSoon = PurchaseOrder::create(['created_by' => $admin->id, 'supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'requested_date' => '2026-10-14', 'status' => 'sent']);
    $dueSoon->items()->create(['flower_type' => 'Roses', 'quantity' => 40]);
    PurchaseOrder::create(['created_by' => $admin->id, 'supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'requested_date' => '2026-10-30', 'status' => 'sent']);
    PurchaseOrder::create(['created_by' => $admin->id, 'supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'requested_date' => '2026-10-05', 'status' => 'fulfilled', 'fulfilled_at' => '2026-10-05 03:00:00']);
    PurchaseOrder::create(['created_by' => $admin->id, 'supplier_id' => $otherSupplier->id, 'supplier_name' => $otherSupplier->name, 'requested_date' => '2026-10-14', 'status' => 'sent']);

    $this->actingAs($supplier)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Supplier workspace')
        ->assertSeeInOrder(['Awaiting you', '2', 'Due within 3 days', '1', 'Fulfilled this month', '1'])
        ->assertSee('40 × Roses')
        ->assertSee(route('supplier.purchase-orders.index'))
        ->assertDontSee('Collections today')
        ->assertDontSee(route('staff.orders.index'));
});

test('suppliers see returns waiting for them on the dashboard', function () {
    $supplier = User::factory()->create(['role' => 'supplier']);
    $otherSupplier = User::factory()->create(['role' => 'supplier']);
    $pending = StockReturn::factory()->create(['supplier_id' => $supplier->id, 'quantity' => 4, 'flower_type' => 'Roses', 'reason' => 'damaged']);
    StockReturn::factory()->accepted()->create(['supplier_id' => $supplier->id, 'flower_type' => 'Lilies']);
    StockReturn::factory()->create(['supplier_id' => $otherSupplier->id, 'flower_type' => 'Tulips']);

    $this->actingAs($supplier)->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Returns to review', '1', 'Returns to accept or reject'])
        ->assertSee($pending->reference())
        ->assertSee('4 × Roses')
        ->assertDontSee('Lilies')
        ->assertDontSee('Tulips')
        ->assertSee(route('supplier.stock-returns.index'));
});

test('dashboards count stock per flower like the inventory page and ignore empty batches', function (string $role, string $page) {
    $user = User::factory()->create(['role' => $role]);
    // Roses: two batches totalling 30 (above threshold 10); an emptied old batch must not count as low stock or spoiling.
    InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 0, 'unit_price' => 2.50, 'date_received' => today()->subDays(6), 'shelf_life_days' => 7, 'low_stock_threshold' => 10]);
    InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 8, 'unit_price' => 2.50, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 10]);
    InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 22, 'unit_price' => 2.50, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 10]);
    // Tulips: total 4, below threshold 8.
    InventoryItem::create(['flower_type' => 'Tulips', 'quantity' => 4, 'unit_price' => 1.80, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 8]);

    $this->actingAs($user)->get(route($page))
        ->assertSeeInOrder(['Flowers in stock', '2', 'Low-stock alerts', '1', 'Spoilage alerts', '0'])
        ->assertSeeInOrder(['Low stock', 'Tulips', '4 remaining'])
        ->assertSee('Nothing is close to spoiling.');
})->with([
    'staff' => ['staff', 'dashboard'],
    'admin' => ['admin', 'admin.dashboard'],
]);
