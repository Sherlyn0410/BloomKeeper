<?php

use App\Models\CustomerOrder;
use App\Models\InventoryItem;
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
        ->assertSeeInOrder(['Total stock items', '2', 'Low-stock alerts', '1', 'Spoilage alerts', '1', 'Pending orders', '1', 'Collections today', '1'])
        ->assertSee('Aisha Rahman')
        ->assertSee('Daniel Tan')
        ->assertSee('Quick alerts')
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

test('suppliers see a simple dashboard without staff tools', function () {
    $this->actingAs(User::factory()->create(['role' => 'supplier']))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Supplier workspace')
        ->assertSee('Your account is ready')
        ->assertDontSee('Collections today')
        ->assertDontSee(route('staff.orders.index'));
});
