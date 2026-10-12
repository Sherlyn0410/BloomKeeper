<?php

use App\Models\InventoryItem;
use App\Models\StockAdjustment;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->staff = User::factory()->create(['role' => 'staff']);
    $this->item = InventoryItem::create(['flower_type' => 'Lilies', 'quantity' => 10, 'unit_price' => 3.25, 'cost_price' => 1.50, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);
});

test('staff can record wastage, which reduces stock and logs a negative adjustment', function () {
    $this->actingAs($this->staff)->get(route('staff.wastage.create'))->assertOk();

    $this->actingAs($this->staff)
        ->post(route('staff.wastage.store'), ['inventory_item_id' => $this->item->id, 'quantity' => 4, 'reason' => 'expired', 'notes' => 'Browning petals'])
        ->assertRedirect(route('staff.wastage.create'))
        ->assertSessionHasNoErrors();

    expect($this->item->refresh()->quantity)->toBe(6)
        ->and(StockAdjustment::sole())
        ->type->toBe('wastage')
        ->quantity_change->toBe(-4)
        ->reason->toBe('expired')
        ->notes->toBe('Browning petals')
        ->user_id->toBe($this->staff->id);
});

test('wastage cannot exceed the stock on hand', function () {
    $this->actingAs($this->staff)
        ->post(route('staff.wastage.store'), ['inventory_item_id' => $this->item->id, 'quantity' => 11, 'reason' => 'damaged'])
        ->assertSessionHasErrors('quantity');

    expect($this->item->refresh()->quantity)->toBe(10)
        ->and(StockAdjustment::count())->toBe(0);
});

test('wastage validates the reason and quantity', function () {
    $this->actingAs($this->staff)
        ->post(route('staff.wastage.store'), ['inventory_item_id' => $this->item->id, 'quantity' => 0, 'reason' => 'stolen'])
        ->assertSessionHasErrors(['quantity', 'reason']);
});

test('suppliers cannot record wastage', function () {
    $this->actingAs(User::factory()->create(['role' => 'supplier']))
        ->post(route('staff.wastage.store'), ['inventory_item_id' => $this->item->id, 'quantity' => 1, 'reason' => 'expired'])
        ->assertForbidden();
});

test('the admin wastage history filters by date range', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $roses = InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 10, 'unit_price' => 2.50, 'cost_price' => 1.00, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);

    $this->travelTo('2026-09-05 10:00:00');
    $roses->recordWastage($this->staff, 2, 'damaged', 'Old roses note');
    $this->travelTo('2026-10-05 10:00:00');
    $this->item->recordWastage($this->staff, 3, 'expired', 'October lilies note');

    $this->actingAs($admin)
        ->get(route('admin.wastage', ['from' => '2026-10-01', 'to' => '2026-10-31']))
        ->assertOk()
        ->assertSee('October lilies note')
        ->assertDontSee('Old roses note');

    $this->actingAs($this->staff)->get(route('admin.wastage'))->assertForbidden();
});

test('the wastage report shows wastage by flower and reason valued at cost price', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->travelTo('2026-10-05 10:00:00');
    $this->item->recordWastage($this->staff, 4, 'expired');
    $this->item->recordWastage($this->staff, 2, 'damaged');
    $this->travelTo('2026-09-05 10:00:00');
    $this->item->recordWastage($this->staff, 1, 'other', 'Outside the period');

    $this->actingAs($admin)
        ->get(route('admin.reports.wastage', ['from' => '2026-10-01', 'to' => '2026-10-31']))
        ->assertOk()
        ->assertSee('Wastage report')
        ->assertSee('RM 9.00')
        ->assertSeeInOrder(['Lilies', '4', '2', '0', '6', '9.00', '100.00%']);

    $this->actingAs($this->staff)->get(route('admin.reports.wastage'))->assertForbidden();
});

test('wastage value uses the cost saved when it was recorded', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->item->recordWastage($this->staff, 4, 'expired');
    $this->item->update(['cost_price' => 9.00]);

    expect(StockAdjustment::sole()->unit_cost)->toBe('1.50');

    $this->actingAs($admin)->get(route('admin.reports.wastage'))->assertSee('RM 6.00')->assertDontSee('RM 36.00');
});

test('the sales report page no longer includes the wastage table', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.reports'))
        ->assertOk()
        ->assertSee('Sales report')
        ->assertDontSee('Value lost');
});

test('the wastage report exports its own excel file', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->travelTo('2026-10-05 10:00:00');
    $this->item->recordWastage($this->staff, 4, 'expired');

    $response = $this->actingAs($admin)->get(route('admin.reports.wastage.export', ['from' => '2026-10-01', 'to' => '2026-10-31']));
    $response->assertDownload('bloomkeeper-wastage-report.xlsx');

    $path = tempnam(sys_get_temp_dir(), 'report');
    file_put_contents($path, $response->streamedContent());
    $spreadsheet = IOFactory::load($path);
    unlink($path);
    $sheet = $spreadsheet->getActiveSheet();

    expect($spreadsheet->getSheetCount())->toBe(1)
        ->and($sheet->getCell('A1')->getValue())->toBe('BloomKeeper Wastage Report')
        ->and($sheet->getCell('A2')->getValue())->toBe('Report Period: 01 Oct 2026 to 31 Oct 2026')
        ->and($sheet->rangeToArray('A5:H5')[0])->toBe(['No.', 'Flower Type', 'Expired', 'Damaged', 'Other', 'Total Removed', 'Value Lost (RM)', '% of Wastage'])
        ->and($sheet->getCell('B6')->getValue())->toBe('Lilies')
        ->and($sheet->getCell('C6')->getValue())->toBe(4)
        ->and($sheet->getCell('F6')->getValue())->toBe(4)
        ->and($sheet->getCell('G6')->getValue())->toEqual(6.0)
        ->and($sheet->getCell('B7')->getValue())->toBe('Total');
});

test('the sales export only contains the sales sheet', function () {
    $content = $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.reports.export'))
        ->streamedContent();

    $path = tempnam(sys_get_temp_dir(), 'report');
    file_put_contents($path, $content);
    $spreadsheet = IOFactory::load($path);
    unlink($path);

    expect($spreadsheet->getSheetCount())->toBe(1)
        ->and($spreadsheet->getActiveSheet()->getTitle())->toBe('Sales Report');
});
