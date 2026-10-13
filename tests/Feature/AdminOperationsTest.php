<?php

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\InventoryItem;
use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

test('an administrator can add inventory stock', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.inventory.store'), [
        'flower_type' => 'Roses', 'quantity' => 20, 'unit_price' => 2.50, 'cost_price' => 1.20,
        'date_received' => '2026-09-22', 'shelf_life_days' => 7, 'low_stock_threshold' => 5,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $item = InventoryItem::firstOrFail();
    expect($item->refresh()->quantity)->toBe(20)
        ->and($item->cost_price)->toBe('1.20');
});

test('an administrator can update orders, purchasing, and export reports', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = CustomerOrder::create([
        'created_by' => $admin->id, 'customer_name' => 'Mina Flores', 'customer_email' => 'mina@example.com',
        'collection_date' => '2026-09-23', 'status' => 'pending',
    ]);
    OrderItem::create(['customer_order_id' => $order->id, 'flower_type' => 'Tulips', 'quantity' => 12, 'unit_price' => 1.25]);

    $this->actingAs($admin)->patch(route('admin.orders.status', $order), ['status' => 'completed'])->assertRedirect();
    $supplier = User::factory()->create(['role' => 'supplier', 'name' => 'Greenhouse Co']);
    $this->actingAs($admin)->post(route('admin.purchase-orders.store'), [
        'supplier_id' => $supplier->id, 'requested_date' => today()->addDays(3)->format('Y-m-d'), 'flower_type' => 'Tulips', 'quantity' => 30,
    ])->assertRedirect()->assertSessionHasNoErrors();

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
    OrderItem::create(['customer_order_id' => $order->id, 'flower_type' => 'Tulips', 'quantity' => 12, 'unit_price' => 1.25, 'unit_cost' => 0.75]);
    OrderItem::create(['customer_order_id' => $order->id, 'flower_type' => 'Roses', 'quantity' => 4, 'unit_price' => 2.00, 'unit_cost' => 1.00]);

    $this->travelTo(Carbon::parse('2026-09-27 06:30:00', 'UTC'));

    $content = $this->actingAs($admin)
        ->get(route('admin.reports.export', ['from' => '2026-09-01', 'to' => '2026-09-30']))
        ->streamedContent();

    $path = tempnam(sys_get_temp_dir(), 'report');
    file_put_contents($path, $content);
    $sheet = IOFactory::load($path)->getActiveSheet();
    unlink($path);

    expect($sheet->getCell('A1')->getValue())->toBe('BloomKeeper Flower Demand Report')
        ->and($sheet->getMergeCells())->toContain('A1:H1')
        ->and($sheet->getStyle('A1')->getFont()->getSize())->toEqual(14)
        ->and($sheet->getStyle('C6')->getFont()->getSize())->toEqual(14)
        ->and($sheet->getCell('A2')->getValue())->toBe('Report Period: 01 Sep 2026 to 30 Sep 2026')
        ->and($sheet->getCell('A3')->getValue())->toBe('Generated on: 27 Sep 2026, 02:30 PM (MYT)')
        ->and($sheet->rangeToArray('A5:H5')[0])->toBe(['No.', 'Flower Type', 'Quantity Ordered', 'Total Sales (RM)', 'Total Cost (RM)', 'Gross Profit (RM)', 'Margin', '% of Total Qty'])
        ->and($sheet->rangeToArray('A6:H8'))->toBe([
            ['1', 'Roses', '4', '8.00', '4.00', '4.00', '50.00%', '25.00%'],
            ['2', 'Tulips', '12', '15.00', '9.00', '6.00', '40.00%', '75.00%'],
            [null, 'Total', '16', '23.00', '13.00', '10.00', '43.48%', '100.00%'],
        ])
        ->and($sheet->getStyle('B8')->getFont()->getBold())->toBeTrue();
});

test('the sales report page shows sales figures and excludes cancelled orders', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 20, 'unit_price' => 2.50, 'date_received' => '2026-09-20', 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);
    $order = CustomerOrder::create([
        'created_by' => $admin->id, 'customer_name' => 'Mina Flores', 'collection_date' => '2026-09-23', 'status' => 'pending',
    ]);
    OrderItem::create(['customer_order_id' => $order->id, 'flower_type' => 'Roses', 'quantity' => 4, 'unit_price' => 2.50]);
    $cancelledOrder = CustomerOrder::create([
        'created_by' => $admin->id, 'customer_name' => 'Leo Tan', 'collection_date' => '2026-09-24', 'status' => 'cancelled',
    ]);
    OrderItem::create(['customer_order_id' => $cancelledOrder->id, 'flower_type' => 'Lilies', 'quantity' => 9, 'unit_price' => 3.00]);

    $this->actingAs($admin)
        ->get(route('admin.reports', ['from' => '2026-09-01', 'to' => '2026-09-30']))
        ->assertOk()
        ->assertSee('01 Sep 2026 to 30 Sep 2026')
        ->assertSee('RM 10.00')
        ->assertSee('Roses')
        ->assertDontSee('Lilies');
});

test('the sales report can be sorted by the selected column', function (array $query, array $expectedOrder) {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = CustomerOrder::create([
        'created_by' => $admin->id, 'customer_name' => 'Mina Flores', 'collection_date' => '2026-09-23', 'status' => 'pending',
    ]);

    foreach (['Lilies' => [5, 3.00], 'Roses' => [12, 1.00], 'Tulips' => [2, 10.00]] as $flowerType => [$quantity, $unitPrice]) {
        InventoryItem::create(['flower_type' => $flowerType, 'quantity' => 20, 'unit_price' => $unitPrice, 'date_received' => '2026-09-20', 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);
        OrderItem::create(['customer_order_id' => $order->id, 'flower_type' => $flowerType, 'quantity' => $quantity, 'unit_price' => $unitPrice]);
    }

    $this->actingAs($admin)
        ->get(route('admin.reports', $query))
        ->assertOk()
        ->assertSeeInOrder($expectedOrder);
})->with([
    'flower type by default' => [[], ['Lilies', 'Roses', 'Tulips']],
    'quantity descending' => [['sort' => 'quantity', 'direction' => 'desc'], ['Roses', 'Lilies', 'Tulips']],
    'total sales ascending' => [['sort' => 'total_sales', 'direction' => 'asc'], ['Roses', 'Lilies', 'Tulips']],
    'total sales descending' => [['sort' => 'total_sales', 'direction' => 'desc'], ['Tulips', 'Lilies', 'Roses']],
    'unknown column falls back to flower type' => [['sort' => 'unit_price'], ['Lilies', 'Roses', 'Tulips']],
]);

test('the exported sales report follows the selected sort', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = CustomerOrder::create([
        'created_by' => $admin->id, 'customer_name' => 'Mina Flores', 'collection_date' => '2026-09-23', 'status' => 'pending',
    ]);
    OrderItem::create(['customer_order_id' => $order->id, 'flower_type' => 'Lilies', 'quantity' => 5, 'unit_price' => 3.00]);
    OrderItem::create(['customer_order_id' => $order->id, 'flower_type' => 'Roses', 'quantity' => 12, 'unit_price' => 1.00]);

    $content = $this->actingAs($admin)
        ->get(route('admin.reports.export', ['sort' => 'quantity', 'direction' => 'desc']))
        ->streamedContent();

    $path = tempnam(sys_get_temp_dir(), 'report');
    file_put_contents($path, $content);
    $sheet = IOFactory::load($path)->getActiveSheet();
    unlink($path);

    expect($sheet->getCell('B6')->getValue())->toBe('Roses')
        ->and($sheet->getCell('B7')->getValue())->toBe('Lilies');
});

test('the inventory can be sorted by the selected column', function (array $query, array $expectedOrder) {
    $admin = User::factory()->create(['role' => 'admin']);
    InventoryItem::create(['flower_type' => 'Lilies', 'quantity' => 18, 'unit_price' => 3.25, 'date_received' => '2026-09-20', 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);
    InventoryItem::create(['flower_type' => 'Orchids', 'quantity' => 4, 'unit_price' => 4.75, 'date_received' => '2026-09-10', 'shelf_life_days' => 30, 'low_stock_threshold' => 3]);
    InventoryItem::create(['flower_type' => 'Tulips', 'quantity' => 9, 'unit_price' => 1.80, 'date_received' => '2026-09-25', 'shelf_life_days' => 3, 'low_stock_threshold' => 8]);

    $this->actingAs($admin)
        ->get(route('admin.inventory', $query))
        ->assertOk()
        ->assertSeeInOrder($expectedOrder);
})->with([
    'flower by default' => [[], ['Lilies', 'Orchids', 'Tulips']],
    'quantity descending' => [['sort' => 'quantity', 'direction' => 'desc'], ['Lilies', 'Tulips', 'Orchids']],
    'received newest first' => [['sort' => 'date_received', 'direction' => 'desc'], ['Tulips', 'Lilies', 'Orchids']],
    'spoiling soonest first' => [['sort' => 'spoilage_date', 'direction' => 'asc'], ['Lilies', 'Tulips', 'Orchids']],
    'unknown column falls back to flower' => [['sort' => 'unit_price'], ['Lilies', 'Orchids', 'Tulips']],
]);

test('the orders page shows status counts, order totals, and filters by status', function () {
    $this->travelTo(Carbon::parse('2026-09-27 09:00:00'));
    $admin = User::factory()->create(['role' => 'admin']);
    $pendingOrder = CustomerOrder::create([
        'created_by' => $admin->id, 'customer_name' => 'Mina Flores', 'collection_date' => '2026-09-27', 'status' => 'pending',
    ]);
    OrderItem::create(['customer_order_id' => $pendingOrder->id, 'flower_type' => 'Roses', 'quantity' => 4, 'unit_price' => 2.50]);
    CustomerOrder::create([
        'created_by' => $admin->id, 'customer_name' => 'Leo Tan', 'collection_date' => '2026-09-20', 'status' => 'completed',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.orders'))
        ->assertOk()
        ->assertSeeInOrder(['All', '2', 'Pending', '1', 'Ready', '0', 'Completed', '1'])
        ->assertSee('RM 10.00')
        ->assertSee('Today')
        ->assertSee('Leo Tan');

    $this->actingAs($admin)
        ->get(route('admin.orders', ['status' => 'pending']))
        ->assertOk()
        ->assertSee('Mina Flores')
        ->assertDontSee('Leo Tan');
});

test('the inventory page searches flowers and shows supplier, margin, and days left', function () {
    $this->travelTo(Carbon::parse('2026-10-12 09:00:00'));
    $admin = User::factory()->create(['role' => 'admin']);
    $supplier = User::factory()->create(['role' => 'supplier', 'name' => 'Highland Florals']);
    InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 20, 'unit_price' => 2.50, 'cost_price' => 1.20, 'date_received' => '2026-10-10', 'shelf_life_days' => 7, 'low_stock_threshold' => 5, 'supplier_id' => $supplier->id]);
    InventoryItem::create(['flower_type' => 'Tulips', 'quantity' => 20, 'unit_price' => 1.80, 'cost_price' => 0.90, 'date_received' => '2026-10-10', 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);

    $this->actingAs($admin)
        ->get(route('admin.inventory', ['search' => 'ros']))
        ->assertOk()
        ->assertSee('Highland Florals')
        ->assertSee('RM 1.30')
        ->assertSee('17 Oct 2026')
        ->assertSee('5 days left')
        ->assertDontSee('Tulips');
});

test('inventory suppliers must be supplier accounts', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.inventory.store'), [
        'flower_type' => 'Roses', 'quantity' => 20, 'unit_price' => 2.50, 'cost_price' => 1.20,
        'date_received' => '2026-09-22', 'shelf_life_days' => 7, 'low_stock_threshold' => 5, 'supplier_id' => $admin->id,
    ])->assertSessionHasErrors('supplier_id');
});

test('the supplier marking a purchase order fulfilled records when it happened and locks it', function () {
    $this->travelTo(Carbon::parse('2026-10-12 09:00:00'));
    $admin = User::factory()->create(['role' => 'admin']);

    $supplier = User::factory()->create(['role' => 'supplier']);
    $this->actingAs($admin)->post(route('admin.purchase-orders.store'), [
        'supplier_id' => $supplier->id, 'requested_date' => today()->addDays(3)->format('Y-m-d'), 'flower_type' => 'Tulips', 'quantity' => 30, 'notes' => 'Deliver before 9am.',
    ])->assertSessionHasNoErrors();

    $purchaseOrder = PurchaseOrder::sole();
    expect($purchaseOrder->notes)->toBe('Deliver before 9am.')
        ->and($purchaseOrder->fulfilled_at)->toBeNull();

    $this->actingAs($supplier)->patch(route('supplier.purchase-orders.fulfill', $purchaseOrder))->assertSessionHasNoErrors();
    expect($purchaseOrder->refresh()->fulfilled_at->toDateTimeString())->toBe('2026-10-12 09:00:00');

    $this->actingAs($admin)->patch(route('admin.purchase-orders.status', $purchaseOrder), ['status' => 'cancelled'])->assertForbidden();
    expect($purchaseOrder->refresh()->status)->toBe('fulfilled')
        ->and($purchaseOrder->fulfilled_at)->not->toBeNull();
});

test('sales use the price saved on each order, not the current inventory price', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $roses = InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 50, 'unit_price' => 2.50, 'cost_price' => 1.20, 'date_received' => '2026-09-20', 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);
    $firstOrder = CustomerOrder::create(['created_by' => $admin->id, 'customer_name' => 'Mina Flores', 'collection_date' => '2026-09-23', 'status' => 'completed']);
    OrderItem::create(['customer_order_id' => $firstOrder->id, 'flower_type' => 'Roses', 'quantity' => 4, 'unit_price' => 2.00]);
    $secondOrder = CustomerOrder::create(['created_by' => $admin->id, 'customer_name' => 'Leo Tan', 'collection_date' => '2026-09-24', 'status' => 'pending']);
    OrderItem::create(['customer_order_id' => $secondOrder->id, 'flower_type' => 'Roses', 'quantity' => 6, 'unit_price' => 3.00]);

    $roses->update(['unit_price' => 9.99]);

    $this->actingAs($admin)
        ->get(route('admin.reports'))
        ->assertOk()
        ->assertSee('RM 26.00')
        ->assertSeeInOrder(['Roses', '10', '26.00'])
        ->assertDontSee('9.99');
});

test('the sales report shows cost and profit from the cost saved on each order', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $roses = InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 50, 'unit_price' => 2.50, 'cost_price' => 1.00, 'date_received' => '2026-09-20', 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);
    $order = CustomerOrder::create(['created_by' => $admin->id, 'customer_name' => 'Mina Flores', 'collection_date' => '2026-09-23', 'status' => 'completed']);
    OrderItem::create(['customer_order_id' => $order->id, 'flower_type' => 'Roses', 'quantity' => 10, 'unit_price' => 2.50, 'unit_cost' => 1.00]);

    $roses->update(['cost_price' => 2.40]);

    $this->actingAs($admin)
        ->get(route('admin.reports'))
        ->assertOk()
        ->assertSee('Gross profit')
        ->assertSee('RM 15.00')
        ->assertSee('60.0% margin')
        ->assertSeeInOrder(['Roses', '10', '25.00', '10.00', '15.00', '60.0%']);
});

test('the sales report can be sorted by profit', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = CustomerOrder::create(['created_by' => $admin->id, 'customer_name' => 'Mina Flores', 'collection_date' => '2026-09-23', 'status' => 'completed']);
    OrderItem::create(['customer_order_id' => $order->id, 'flower_type' => 'Lilies', 'quantity' => 2, 'unit_price' => 3.00, 'unit_cost' => 1.00]);
    OrderItem::create(['customer_order_id' => $order->id, 'flower_type' => 'Roses', 'quantity' => 10, 'unit_price' => 2.50, 'unit_cost' => 1.00]);

    $this->actingAs($admin)
        ->get(route('admin.reports', ['sort' => 'profit', 'direction' => 'desc']))
        ->assertSeeInOrder(['Roses', 'Lilies']);
});

test('placing an order saves the current cost price on each line', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $customer = Customer::factory()->create(['created_by' => $staff->id]);
    InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 50, 'unit_price' => 2.50, 'cost_price' => 1.20, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);

    $this->actingAs($staff)->post(route('staff.orders.store'), [
        'customer_id' => $customer->id, 'collection_date' => today()->addDay()->format('Y-m-d'), 'payment_status' => 'paid',
        'items' => [['flower_type' => 'Roses', 'quantity' => 3]],
    ])->assertSessionHasNoErrors();

    expect(OrderItem::sole()->unit_cost)->toBe('1.20');
});

test('choosing an approved supplier fills in the supplier name automatically', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $supplier = User::factory()->create(['role' => 'supplier', 'name' => 'Highland Florals']);

    $this->actingAs($admin)->post(route('admin.purchase-orders.store'), [
        'supplier_id' => $supplier->id, 'requested_date' => today()->addDays(3)->format('Y-m-d'), 'flower_type' => 'Roses', 'quantity' => 40,
    ])->assertSessionHasNoErrors();

    expect(PurchaseOrder::sole())
        ->supplier_id->toBe($supplier->id)
        ->supplier_name->toBe('Highland Florals');
});

test('a purchase order must be sent to a supplier account', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.purchase-orders.store'), [
        'supplier_name' => 'Someone by phone', 'requested_date' => today()->addDays(3)->format('Y-m-d'), 'flower_type' => 'Roses', 'quantity' => 40,
    ])->assertSessionHasErrors(['supplier_id' => 'Choose the supplier to send this purchase order to.']);

    expect(PurchaseOrder::count())->toBe(0);
});

test('re-sending a declined purchase order clears the supplier\'s reason', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $supplier = User::factory()->create(['role' => 'supplier']);
    $purchaseOrder = PurchaseOrder::create(['created_by' => $admin->id, 'supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'requested_date' => '2026-10-15', 'status' => 'declined', 'supplier_note' => 'Out of season']);

    $this->actingAs($admin)->patch(route('admin.purchase-orders.status', $purchaseOrder), ['status' => 'sent']);

    expect($purchaseOrder->refresh())
        ->status->toBe('sent')
        ->supplier_note->toBeNull();
});

test('only approved supplier accounts can be chosen as the supplier', function (array $attributes) {
    $admin = User::factory()->create(['role' => 'admin']);
    $notASupplier = User::factory()->create($attributes);

    $this->actingAs($admin)->post(route('admin.purchase-orders.store'), [
        'supplier_id' => $notASupplier->id, 'requested_date' => today()->addDays(3)->format('Y-m-d'), 'flower_type' => 'Roses', 'quantity' => 40,
    ])->assertSessionHasErrors('supplier_id');
})->with([
    'staff account' => [['role' => 'staff']],
    'unapproved supplier' => [['role' => 'supplier', 'approval_status' => 'pending']],
]);

test('fulfilled purchase orders are locked and cannot go back to sent or cancelled', function (string $status) {
    $admin = User::factory()->create(['role' => 'admin']);
    $supplier = User::factory()->create(['role' => 'supplier']);
    $purchaseOrder = PurchaseOrder::create(['created_by' => $admin->id, 'supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'requested_date' => '2026-10-15', 'status' => 'fulfilled', 'fulfilled_at' => now()]);

    $this->actingAs($admin)->patch(route('admin.purchase-orders.status', $purchaseOrder), ['status' => $status])->assertForbidden();
    $this->actingAs($admin)->get(route('admin.purchase-orders'))
        ->assertSee('Locked')
        ->assertDontSee(route('admin.purchase-orders.status', $purchaseOrder));

    expect($purchaseOrder->refresh()->status)->toBe('fulfilled');
})->with(['sent', 'cancelled']);

test('admins cannot mark a purchase order fulfilled on the supplier\'s behalf', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $supplier = User::factory()->create(['role' => 'supplier']);
    $purchaseOrder = PurchaseOrder::create(['created_by' => $admin->id, 'supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'requested_date' => '2026-10-15', 'status' => 'sent']);

    $this->actingAs($admin)
        ->patch(route('admin.purchase-orders.status', $purchaseOrder), ['status' => 'fulfilled'])
        ->assertSessionHasErrors(['status' => 'Only the supplier can mark a purchase order as fulfilled.']);
    $this->actingAs($admin)->get(route('admin.purchase-orders'))->assertDontSee('<option value="fulfilled"', false);

    expect($purchaseOrder->refresh())
        ->status->toBe('sent')
        ->fulfilled_at->toBeNull();
});

test('purchase orders cannot be requested for a past date', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $supplier = User::factory()->create(['role' => 'supplier']);

    $this->actingAs($admin)->post(route('admin.purchase-orders.store'), [
        'supplier_id' => $supplier->id, 'requested_date' => today()->subDay()->format('Y-m-d'), 'flower_type' => 'Roses', 'quantity' => 40,
    ])->assertSessionHasErrors(['requested_date' => 'The requested date must be today or later.']);

    $this->actingAs($admin)->get(route('admin.purchase-orders'))->assertSee('min="'.today()->format('Y-m-d').'"', false);

    expect(PurchaseOrder::count())->toBe(0);
});

test('non administrators cannot access operations', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff']))
        ->get(route('admin.operations'))
        ->assertForbidden();
});
