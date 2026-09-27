<?php

use App\Models\CustomerOrder;
use App\Models\InventoryItem;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

    $this->actingAs($admin)->get(route('admin.reports.export'))->assertDownload('bloomkeeper-report.xlsx');
    expect($order->refresh()->status)->toBe('completed');
});

test('the exported sales report includes a header, sales figures, and a total row', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    InventoryItem::create(['flower_type' => 'Tulips', 'quantity' => 20, 'unit_price' => 1.50, 'date_received' => '2026-09-20', 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);
    InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 20, 'unit_price' => 2.50, 'date_received' => '2026-09-20', 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);
    $order = CustomerOrder::create([
        'created_by' => $admin->id, 'customer_name' => 'Mina Flores', 'collection_date' => '2026-09-23', 'status' => 'pending',
    ]);
    OrderItem::create(['customer_order_id' => $order->id, 'flower_type' => 'Tulips', 'quantity' => 12, 'unit_price' => 1.25]);
    OrderItem::create(['customer_order_id' => $order->id, 'flower_type' => 'Roses', 'quantity' => 4, 'unit_price' => 2.00]);

    $this->travelTo(Carbon::parse('2026-09-27 06:30:00', 'UTC'));

    $content = $this->actingAs($admin)
        ->get(route('admin.reports.export', ['from' => '2026-09-01', 'to' => '2026-09-30']))
        ->streamedContent();

    $path = tempnam(sys_get_temp_dir(), 'report');
    file_put_contents($path, $content);
    $sheet = IOFactory::load($path)->getActiveSheet();
    unlink($path);

    expect($sheet->getCell('A1')->getValue())->toBe('BloomKeeper Flower Demand Report')
        ->and($sheet->getMergeCells())->toContain('A1:F1')
        ->and($sheet->getStyle('A1')->getFont()->getSize())->toEqual(14)
        ->and($sheet->getStyle('C6')->getFont()->getSize())->toEqual(14)
        ->and($sheet->getCell('A2')->getValue())->toBe('Report Period: 01 Sep 2026 to 30 Sep 2026')
        ->and($sheet->getCell('A3')->getValue())->toBe('Generated on: 27 Sep 2026, 02:30 PM (MYT)')
        ->and($sheet->rangeToArray('A5:F5')[0])->toBe(['No.', 'Flower Type', 'Quantity Ordered', 'Unit Price (RM)', 'Total Sales (RM)', '% of Total Qty'])
        ->and($sheet->rangeToArray('A6:F8'))->toBe([
            ['1', 'Roses', '4', '2.50', '10.00', '25.00%'],
            ['2', 'Tulips', '12', '1.50', '18.00', '75.00%'],
            [null, 'Total', '16', null, '28.00', '100.00%'],
        ])
        ->and($sheet->getStyle('B8')->getFont()->getBold())->toBeTrue();
});

test('non administrators cannot access operations', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff']))
        ->get(route('admin.operations'))
        ->assertForbidden();
});
