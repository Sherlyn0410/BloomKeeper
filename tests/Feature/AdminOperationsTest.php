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

test('inventory cannot be received on a future date', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.inventory.store'), [
        'flower_type' => 'Roses', 'quantity' => 20, 'unit_price' => 2.50, 'cost_price' => 1.20,
        'date_received' => today()->addDay()->format('Y-m-d'), 'shelf_life_days' => 7, 'low_stock_threshold' => 5,
    ])->assertSessionHasErrors(['date_received' => 'The date received cannot be in the future.']);

    expect(InventoryItem::count())->toBe(0);
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
        ->assertSee('Receive into stock')
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

test('admins can edit a purchase order before the supplier fulfils it', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $supplier = User::factory()->create(['role' => 'supplier']);
    $otherSupplier = User::factory()->create(['role' => 'supplier', 'name' => 'Petal Wholesale']);
    $purchaseOrder = PurchaseOrder::create(['created_by' => $admin->id, 'supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'requested_date' => today()->addDays(2), 'status' => 'sent']);
    $purchaseOrder->items()->create(['flower_type' => 'Roses', 'quantity' => 40]);

    $this->actingAs($admin)->put(route('admin.purchase-orders.update', $purchaseOrder), [
        'supplier_id' => $otherSupplier->id, 'requested_date' => today()->addDays(5)->format('Y-m-d'),
        'flower_type' => 'Tulips', 'quantity' => 25, 'notes' => 'Yellow only',
    ])->assertSessionHasNoErrors();

    $purchaseOrder->refresh();
    expect($purchaseOrder)
        ->status->toBe('sent')
        ->supplier_id->toBe($otherSupplier->id)
        ->supplier_name->toBe('Petal Wholesale')
        ->notes->toBe('Yellow only')
        ->and($purchaseOrder->requested_date->format('Y-m-d'))->toBe(today()->addDays(5)->format('Y-m-d'))
        ->and($purchaseOrder->items()->sole()->only(['flower_type', 'quantity']))->toBe(['flower_type' => 'Tulips', 'quantity' => 25]);
});

test('editing a declined purchase order sends it to the supplier again', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $supplier = User::factory()->create(['role' => 'supplier']);
    $purchaseOrder = PurchaseOrder::create(['created_by' => $admin->id, 'supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'requested_date' => today()->addDays(2), 'status' => 'declined', 'supplier_note' => 'Too many']);

    $this->actingAs($admin)->put(route('admin.purchase-orders.update', $purchaseOrder), [
        'supplier_id' => $supplier->id, 'requested_date' => today()->addDays(2)->format('Y-m-d'), 'flower_type' => 'Roses', 'quantity' => 10,
    ])->assertSessionHasNoErrors();

    expect($purchaseOrder->refresh())
        ->status->toBe('sent')
        ->supplier_note->toBeNull();
});

test('fulfilled and cancelled purchase orders cannot be edited', function (string $status) {
    $admin = User::factory()->create(['role' => 'admin']);
    $supplier = User::factory()->create(['role' => 'supplier']);
    $purchaseOrder = PurchaseOrder::create(['created_by' => $admin->id, 'supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'requested_date' => today()->addDays(2), 'status' => $status]);

    $this->actingAs($admin)->get(route('admin.purchase-orders'))->assertDontSee('edit-po-'.$purchaseOrder->id);
    $this->actingAs($admin)->put(route('admin.purchase-orders.update', $purchaseOrder), [
        'supplier_id' => $supplier->id, 'requested_date' => today()->addDays(2)->format('Y-m-d'), 'flower_type' => 'Roses', 'quantity' => 10,
    ])->assertForbidden();
})->with(['fulfilled', 'cancelled']);

test('an edited purchase order can keep a past requested date but not move to another one', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $supplier = User::factory()->create(['role' => 'supplier']);
    $purchaseOrder = PurchaseOrder::create(['created_by' => $admin->id, 'supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'requested_date' => today()->subDays(2), 'status' => 'sent']);
    $payload = fn ($date): array => ['supplier_id' => $supplier->id, 'requested_date' => $date->format('Y-m-d'), 'flower_type' => 'Roses', 'quantity' => 10];

    $this->actingAs($admin)->put(route('admin.purchase-orders.update', $purchaseOrder), $payload(today()->subDays(2)))->assertSessionHasNoErrors();
    $this->actingAs($admin)->put(route('admin.purchase-orders.update', $purchaseOrder), $payload(today()->subDays(3)))
        ->assertSessionHasErrors(['requested_date' => 'The requested date must be today or later.']);
});

test('purchase order history can be searched and filtered', function (array $query, array $visible, array $hidden) {
    $admin = User::factory()->create(['role' => 'admin']);
    $highland = User::factory()->create(['role' => 'supplier', 'name' => 'Highland Florals']);
    $petal = User::factory()->create(['role' => 'supplier', 'name' => 'Petal Wholesale']);
    $orders = [
        ['supplier' => $highland, 'flower' => 'Roses', 'date' => '2026-10-20', 'status' => 'sent', 'note' => 'first-order'],
        ['supplier' => $petal, 'flower' => 'Tulips', 'date' => '2026-10-21', 'status' => 'fulfilled', 'note' => 'second-order'],
        ['supplier' => $petal, 'flower' => 'Lilies', 'date' => '2026-10-20', 'status' => 'declined', 'note' => 'third-order'],
    ];
    foreach ($orders as $order) {
        PurchaseOrder::create(['created_by' => $admin->id, 'supplier_id' => $order['supplier']->id, 'supplier_name' => $order['supplier']->name, 'requested_date' => $order['date'], 'status' => $order['status'], 'notes' => $order['note']])
            ->items()->create(['flower_type' => $order['flower'], 'quantity' => 10]);
    }

    $response = $this->actingAs($admin)->get(route('admin.purchase-orders', $query))->assertOk();

    foreach ($visible as $note) {
        $response->assertSee($note);
    }
    foreach ($hidden as $note) {
        $response->assertDontSee($note);
    }
})->with([
    'PO number' => [['search' => 'PO-0002'], ['second-order'], ['first-order', 'third-order']],
    'supplier' => [['search' => 'petal'], ['second-order', 'third-order'], ['first-order']],
    'flower' => [['search' => 'rose'], ['first-order'], ['second-order', 'third-order']],
    'requested date' => [['date' => '2026-10-20'], ['first-order', 'third-order'], ['second-order']],
    'status' => [['status' => 'declined'], ['third-order'], ['first-order', 'second-order']],
    'combined' => [['search' => 'petal', 'date' => '2026-10-20'], ['third-order'], ['first-order', 'second-order']],
]);

test('inventory groups batches by flower with a total and where each batch came from', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $supplier = User::factory()->create(['role' => 'supplier', 'name' => 'Abu']);
    $purchaseOrder = PurchaseOrder::create(['created_by' => $admin->id, 'supplier_id' => $supplier->id, 'supplier_name' => 'Abu', 'requested_date' => today(), 'status' => 'fulfilled']);
    $manual = InventoryItem::create(['flower_type' => 'Black Roses', 'quantity' => 40, 'unit_price' => 3.50, 'cost_price' => 2.50, 'date_received' => today(), 'shelf_life_days' => 5, 'low_stock_threshold' => 50, 'supplier_id' => $supplier->id]);
    $received = InventoryItem::create(['flower_type' => 'Black Roses', 'quantity' => 90, 'unit_price' => 3.50, 'cost_price' => 2.50, 'date_received' => today(), 'shelf_life_days' => 5, 'low_stock_threshold' => 50, 'supplier_id' => $supplier->id, 'purchase_order_id' => $purchaseOrder->id]);

    $this->actingAs($admin)->get(route('admin.inventory'))
        ->assertOk()
        ->assertSeeInOrder(['Black Roses', '>130</td>', '2 batches', "Batch #{$manual->id}", 'added manually', "Batch #{$received->id}", 'from PO-0001'], false)
        ->assertDontSee('Low stock</span>', false);
});

test('the sell first batch is the one customer orders take from', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $later = InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 30, 'unit_price' => 2.50, 'cost_price' => 1.20, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);
    $sooner = InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 30, 'unit_price' => 2.50, 'cost_price' => 1.20, 'date_received' => today()->subDays(2), 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);

    $this->actingAs($staff)->get(route('staff.inventory'))
        ->assertSeeInOrder(["Batch #{$sooner->id}", 'Sell first', "Batch #{$later->id}"]);

    $order = CustomerOrder::create(['created_by' => $staff->id, 'customer_name' => 'Priya', 'collection_date' => today()->addDay(), 'status' => 'pending']);
    OrderItem::create(['customer_order_id' => $order->id, 'flower_type' => 'Roses', 'quantity' => 5, 'unit_price' => 2.50]);
    $order->syncStock($staff);

    expect($sooner->refresh()->quantity)->toBe(25)
        ->and($later->refresh()->quantity)->toBe(30);
});

test('low stock is judged on the flower total, not each batch', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    InventoryItem::create(['flower_type' => 'Tulips', 'quantity' => 4, 'unit_price' => 1.80, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 8]);
    InventoryItem::create(['flower_type' => 'Tulips', 'quantity' => 3, 'unit_price' => 1.80, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 8]);

    $this->actingAs($admin)->get(route('admin.inventory'))->assertSeeInOrder(['Tulips', 'Low stock', '>7</td>'], false);

    InventoryItem::create(['flower_type' => 'Tulips', 'quantity' => 5, 'unit_price' => 1.80, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 8]);

    $this->actingAs($admin)->get(route('admin.inventory'))->assertSee('>12</td>', false)->assertDontSee('Low stock</span>', false);
});

test('the flower summary shows its margin', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 10, 'unit_price' => 3.50, 'cost_price' => 2.50, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 2]);
    InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 10, 'unit_price' => 3.50, 'cost_price' => 2.50, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 2]);

    $this->actingAs($admin)->get(route('admin.inventory'))->assertSeeInOrder(['Roses', 'RM 3.50', 'RM 1.00', '2 batches']);
});

test('batch rows only repeat pricing when the batches are priced differently', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $batch = ['flower_type' => 'Roses', 'quantity' => 10, 'unit_price' => 3.50, 'cost_price' => 2.50, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 2];
    InventoryItem::create($batch);
    InventoryItem::create($batch);

    $this->actingAs($admin)->get(route('admin.inventory'))->assertDontSee('cost RM 2.50');

    InventoryItem::create([...$batch, 'cost_price' => 2.80]);

    $this->actingAs($admin)->get(route('admin.inventory'))
        ->assertSee('RM 0.70 – 1.00')
        ->assertSee('cost RM 2.50')
        ->assertSee('cost RM 2.80');
});

test('an emptied batch with history cannot be topped up again', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $roses = InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 5, 'unit_price' => 2.50, 'cost_price' => 1.20, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 2]);
    $roses->recordWastage($admin, 5, 'damaged');
    $payload = ['flower_type' => 'Roses', 'unit_price' => 2.50, 'cost_price' => 1.20, 'date_received' => today()->format('Y-m-d'), 'shelf_life_days' => 7, 'low_stock_threshold' => 2];

    $this->actingAs($admin)->get(route('admin.inventory', ['show_empty' => 1]))
        ->assertSeeInOrder(["Batch #{$roses->id}", 'title="This batch is empty.">Edit', 'title="This batch is empty.">Wastage', 'title="This batch is empty.">Delete'], false)
        ->assertDontSee("document.getElementById('edit-stock-{$roses->id}').showModal()", false);

    $this->actingAs($admin)->put(route('admin.inventory.update', $roses), [...$payload, 'quantity' => 50])
        ->assertSessionHasErrors(['quantity' => "Batch #{$roses->id} is empty. To add new Roses, use Add stock item or Receive into stock."]);
    $this->actingAs($admin)->put(route('admin.inventory.update', $roses), [...$payload, 'quantity' => 0, 'notes' => 'Sold out'])->assertSessionHasNoErrors();

    expect($roses->refresh())->quantity->toBe(0)->notes->toBe('Sold out');
});

test('an empty batch without history can still be corrected', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $roses = InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 0, 'unit_price' => 2.50, 'cost_price' => 1.20, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 2]);

    $this->actingAs($admin)->put(route('admin.inventory.update', $roses), [
        'flower_type' => 'Roses', 'quantity' => 30, 'unit_price' => 2.50, 'cost_price' => 1.20, 'date_received' => today()->format('Y-m-d'), 'shelf_life_days' => 7, 'low_stock_threshold' => 2,
    ])->assertSessionHasNoErrors();

    expect($roses->refresh()->quantity)->toBe(30);
});

test('sold out batches are hidden until asked for', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $soldOut = InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 0, 'unit_price' => 2.00, 'cost_price' => 1.80, 'date_received' => today()->subDays(5), 'shelf_life_days' => 7, 'low_stock_threshold' => 2]);
    $inStock = InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 30, 'unit_price' => 2.50, 'cost_price' => 1.20, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 2]);

    $this->actingAs($admin)->get(route('admin.inventory'))
        ->assertSee("Batch #{$inStock->id}")
        ->assertDontSee("Batch #{$soldOut->id}")
        ->assertSee('1 batch')
        ->assertSee('Show empty batches (1)')
        ->assertDontSee('name="show_empty" value="1" checked', false)
        ->assertSee('RM 1.30')
        ->assertDontSee('RM 0.20');

    $this->actingAs($admin)->get(route('admin.inventory', ['show_empty' => 1]))
        ->assertSee("Batch #{$soldOut->id}")
        ->assertSee('name="show_empty" value="1" checked', false);
});

test('a flower with every batch sold out stays listed as out of stock with a reorder button', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    InventoryItem::create(['flower_type' => 'Lilies', 'quantity' => 0, 'unit_price' => 3.25, 'cost_price' => 1.70, 'date_received' => today()->subDays(3), 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);

    $this->actingAs($admin)->get(route('admin.inventory'))
        ->assertSeeInOrder(['Lilies', 'Out of stock', '>0</td>', 'No batches in stock'], false)
        ->assertSee('>Reorder</a>', false);
});

test('the sales report offers daily, weekly, and monthly periods', function () {
    $this->travelTo(Carbon::parse('2026-10-15 10:00:00', 'Asia/Kuala_Lumpur'));
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.reports'))
        ->assertOk()
        ->assertSee(route('admin.reports', ['from' => '2026-10-15', 'to' => '2026-10-15', 'sort' => 'flower_type', 'direction' => 'asc']))
        ->assertSee(route('admin.reports', ['from' => '2026-10-12', 'to' => '2026-10-18', 'sort' => 'flower_type', 'direction' => 'asc']))
        ->assertSee(route('admin.reports', ['from' => '2026-10-01', 'to' => '2026-10-31', 'sort' => 'flower_type', 'direction' => 'asc']));

    $this->actingAs($admin)->get(route('admin.reports', ['from' => '2026-10-12', 'to' => '2026-10-18']))
        ->assertSeeInOrder(['aria-current="page"', 'Weekly'], false)
        ->assertSee('12 Oct 2026');
});

test('the sales report steps back and forward by the chosen period', function (array $current, string $label, array $previous, ?array $next) {
    $this->travelTo(Carbon::parse('2026-10-15 10:00:00', 'Asia/Kuala_Lumpur'));
    $admin = User::factory()->create(['role' => 'admin']);
    $url = fn (array $range): string => route('admin.reports', [...$range, 'sort' => 'flower_type', 'direction' => 'asc']);

    $response = $this->actingAs($admin)->get(route('admin.reports', $current))
        ->assertSee($label)
        ->assertSee($url($previous));

    $next ? $response->assertSee($url($next)) : $response->assertDontSee('aria-label="Next period"', false);
})->with([
    'previous month' => [['from' => '2026-09-01', 'to' => '2026-09-30'], 'September 2026', ['from' => '2026-08-01', 'to' => '2026-08-31'], ['from' => '2026-10-01', 'to' => '2026-10-31']],
    'current month' => [['from' => '2026-10-01', 'to' => '2026-10-31'], 'October 2026', ['from' => '2026-09-01', 'to' => '2026-09-30'], null],
    'week across months' => [['from' => '2026-09-28', 'to' => '2026-10-04'], '28 Sep – 04 Oct 2026', ['from' => '2026-09-21', 'to' => '2026-09-27'], ['from' => '2026-10-05', 'to' => '2026-10-11']],
    'day' => [['from' => '2026-10-01', 'to' => '2026-10-01'], '01 Oct 2026', ['from' => '2026-09-30', 'to' => '2026-09-30'], ['from' => '2026-10-02', 'to' => '2026-10-02']],
]);

test('custom date ranges have no period arrows', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.reports', ['from' => '2026-09-03', 'to' => '2026-09-17']))
        ->assertDontSee('aria-label="Previous period"', false);
});

test('the wastage report has the same period buttons and arrows', function () {
    $this->travelTo(Carbon::parse('2026-10-15 10:00:00', 'Asia/Kuala_Lumpur'));
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.reports.wastage'))
        ->assertOk()
        ->assertSeeInOrder(['Daily', 'Weekly', 'Monthly', 'All time'])
        ->assertSee('/admin/reports/wastage?from=2026-10-01&amp;to=2026-10-31', false);

    $this->actingAs($admin)->get(route('admin.reports.wastage', ['from' => '2026-09-01', 'to' => '2026-09-30']))
        ->assertSee('September 2026')
        ->assertSee('/admin/reports/wastage?from=2026-08-01&amp;to=2026-08-31', false)
        ->assertSee('/admin/reports/wastage?from=2026-10-01&amp;to=2026-10-31', false);
});

test('inventory can be filtered to low stock or spoilage alerts', function (?string $alert, array $visible, array $hidden) {
    $admin = User::factory()->create(['role' => 'admin']);
    InventoryItem::create(['flower_type' => 'Tulips', 'quantity' => 3, 'unit_price' => 1.80, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 8]);
    InventoryItem::create(['flower_type' => 'Lilies', 'quantity' => 30, 'unit_price' => 3.25, 'date_received' => today()->subDays(6), 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);
    InventoryItem::create(['flower_type' => 'Orchids', 'quantity' => 30, 'unit_price' => 4.75, 'date_received' => today(), 'shelf_life_days' => 30, 'low_stock_threshold' => 5]);

    $response = $this->actingAs($admin)->get(route('admin.inventory', array_filter(['alert' => $alert])))->assertOk();

    foreach ($visible as $flower) {
        $response->assertSee('>'.$flower.'</span>', false);
    }
    foreach ($hidden as $flower) {
        $response->assertDontSee('>'.$flower.'</span>', false);
    }
})->with([
    'all' => [null, ['Tulips', 'Lilies', 'Orchids'], []],
    'low stock' => ['low_stock', ['Tulips'], ['Lilies', 'Orchids']],
    'spoilage' => ['spoilage', ['Lilies'], ['Tulips', 'Orchids']],
]);

test('the alert filters show counts and toggle off when clicked again', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    InventoryItem::create(['flower_type' => 'Tulips', 'quantity' => 3, 'unit_price' => 1.80, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 8]);

    $this->actingAs($admin)->get(route('admin.inventory'))
        ->assertSeeInOrder(['Low stock', '1'])
        ->assertSee(route('admin.inventory', ['alert' => 'low_stock']));

    $this->actingAs($admin)->get(route('admin.inventory', ['alert' => 'low_stock']))
        ->assertSee('aria-current="page"', false)
        ->assertSee('title="Show all flowers"', false);

    $this->actingAs($admin)->get(route('admin.inventory', ['alert' => 'spoilage']))
        ->assertSee('No flowers are close to spoiling.');
});
