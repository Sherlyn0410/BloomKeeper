<?php

use App\Models\CustomerOrder;
use App\Models\InventoryItem;
use App\Models\OrderItem;
use App\Models\User;

test('an administrator can add inventory stock', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.inventory.store'), [
        'flower_type' => 'Roses', 'quantity' => 20, 'unit_price' => 2.50,
        'date_received' => '2026-09-22', 'shelf_life_days' => 7, 'low_stock_threshold' => 5,
    ])->assertRedirect();

    $item = InventoryItem::firstOrFail();
    expect($item->refresh()->quantity)->toBe(20);
});

test('an administrator can update orders, purchasing, and export reports', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = CustomerOrder::create([
        'created_by' => $admin->id, 'customer_name' => 'Mina Flores', 'customer_email' => 'mina@example.com',
        'collection_date' => '2026-09-23', 'status' => 'pending',
    ]);
    OrderItem::create(['customer_order_id' => $order->id, 'flower_type' => 'Tulips', 'quantity' => 12, 'unit_price' => 1.25]);

    $this->actingAs($admin)->patch(route('admin.orders.status', $order), ['status' => 'completed'])->assertRedirect();
    $this->actingAs($admin)->post(route('admin.purchase-orders.store'), [
        'supplier_name' => 'Greenhouse Co', 'requested_date' => '2026-09-25', 'flower_type' => 'Tulips', 'quantity' => 30,
    ])->assertRedirect();

    $this->actingAs($admin)->get(route('admin.reports.export'))->assertDownload('bloomkeeper-report.csv');
    expect($order->refresh()->status)->toBe('completed');
});

test('non administrators cannot access operations', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff']))
        ->get(route('admin.operations'))
        ->assertForbidden();
});
