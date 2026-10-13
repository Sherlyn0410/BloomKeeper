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

test('staff can correct their own wastage record and stock is adjusted by the difference', function () {
    $adjustment = $this->item->recordWastage($this->staff, 5, 'expired');

    $this->actingAs($this->staff)->get(route('staff.wastage.edit', $adjustment))->assertOk()->assertSee('Edit wastage record');

    $this->actingAs($this->staff)
        ->put(route('staff.wastage.update', $adjustment), ['inventory_item_id' => $this->item->id, 'quantity' => 3, 'reason' => 'damaged', 'notes' => 'Miscounted'])
        ->assertRedirect(route('staff.wastage.create'))
        ->assertSessionHasNoErrors();

    expect($this->item->refresh()->quantity)->toBe(7)
        ->and($adjustment->refresh())
        ->quantity_change->toBe(-3)
        ->reason->toBe('damaged')
        ->notes->toBe('Miscounted');
});

test('correcting wastage to a different flower moves the stock between batches', function () {
    $roses = InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 8, 'unit_price' => 2.50, 'cost_price' => 1.00, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);
    $adjustment = $this->item->recordWastage($this->staff, 4, 'expired');

    $this->actingAs($this->staff)
        ->put(route('staff.wastage.update', $adjustment), ['inventory_item_id' => $roses->id, 'quantity' => 4, 'reason' => 'expired']);

    expect($this->item->refresh()->quantity)->toBe(10)
        ->and($roses->refresh()->quantity)->toBe(4)
        ->and($adjustment->refresh()->unit_cost)->toBe('1.00');
});

test('a correction cannot remove more than the available stock', function () {
    $adjustment = $this->item->recordWastage($this->staff, 4, 'expired');

    $this->actingAs($this->staff)
        ->put(route('staff.wastage.update', $adjustment), ['inventory_item_id' => $this->item->id, 'quantity' => 11, 'reason' => 'expired'])
        ->assertSessionHasErrors('quantity');

    expect($this->item->refresh()->quantity)->toBe(6)
        ->and($adjustment->refresh()->quantity_change)->toBe(-4);
});

test('deleting a wastage record returns its stems to stock', function () {
    $adjustment = $this->item->recordWastage($this->staff, 4, 'expired');

    $this->actingAs($this->staff)->delete(route('staff.wastage.destroy', $adjustment))->assertRedirect();

    expect(StockAdjustment::find($adjustment->id))->toBeNull()
        ->and($this->item->refresh()->quantity)->toBe(10);
});

test('staff cannot change their wastage records after 24 hours', function () {
    $adjustment = $this->item->recordWastage($this->staff, 4, 'expired');
    $this->travel(25)->hours();

    $this->actingAs($this->staff)->get(route('staff.wastage.edit', $adjustment))->assertForbidden();
    $this->actingAs($this->staff)->delete(route('staff.wastage.destroy', $adjustment))->assertForbidden();
    $this->actingAs($this->staff)->get(route('staff.wastage.create'))->assertSee('Locked');

    expect($this->item->refresh()->quantity)->toBe(6);
});

test('staff cannot change another staff member\'s wastage record', function () {
    $adjustment = $this->item->recordWastage(User::factory()->create(['role' => 'staff']), 4, 'expired');

    $this->actingAs($this->staff)
        ->put(route('staff.wastage.update', $adjustment), ['inventory_item_id' => $this->item->id, 'quantity' => 1, 'reason' => 'expired'])
        ->assertForbidden();
});

test('admins can correct any wastage record at any time', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $adjustment = $this->item->recordWastage($this->staff, 4, 'expired');
    $this->travel(30)->days();

    $this->actingAs($admin)->get(route('admin.wastage'))->assertSee(route('staff.wastage.edit', $adjustment));

    $this->actingAs($admin)
        ->put(route('staff.wastage.update', $adjustment), ['inventory_item_id' => $this->item->id, 'quantity' => 2, 'reason' => 'other'])
        ->assertRedirect(route('admin.wastage'));

    expect($this->item->refresh()->quantity)->toBe(8);
});

test('wastage history can be sorted by each column', function (array $query, array $expectedOrder) {
    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Zara Admin']);
    $staff = User::factory()->create(['role' => 'staff', 'name' => 'Amir Staff']);
    $roses = InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 20, 'unit_price' => 2.50, 'cost_price' => 1.00, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);
    $tulips = InventoryItem::create(['flower_type' => 'Tulips', 'quantity' => 20, 'unit_price' => 1.80, 'cost_price' => 0.90, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);

    $this->travelTo('2026-10-01 09:00:00');
    $tulips->recordWastage($admin, 2, 'other', 'first-note');
    $this->travelTo('2026-10-02 09:00:00');
    $this->item->recordWastage($staff, 7, 'expired', 'second-note');
    $this->travelTo('2026-10-03 09:00:00');
    $roses->recordWastage($admin, 4, 'damaged', 'third-note');

    $this->actingAs($admin)
        ->get(route('admin.wastage', $query))
        ->assertOk()
        ->assertSeeInOrder($expectedOrder);
})->with([
    'newest first by default' => [[], ['third-note', 'second-note', 'first-note']],
    'oldest first' => [['sort' => 'date', 'direction' => 'asc'], ['first-note', 'second-note', 'third-note']],
    'flower A to Z' => [['sort' => 'flower', 'direction' => 'asc'], ['second-note', 'third-note', 'first-note']],
    'largest quantity first' => [['sort' => 'quantity', 'direction' => 'desc'], ['second-note', 'third-note', 'first-note']],
    'smallest quantity first' => [['sort' => 'quantity', 'direction' => 'asc'], ['first-note', 'third-note', 'second-note']],
    'reason A to Z' => [['sort' => 'reason', 'direction' => 'asc'], ['third-note', 'second-note', 'first-note']],
    'recorded by A to Z' => [['sort' => 'recorded_by', 'direction' => 'asc'], ['second-note', 'third-note', 'first-note']],
    'unknown column falls back to newest first' => [['sort' => 'notes'], ['third-note', 'second-note', 'first-note']],
]);

test('recent wastage sorts only the latest ten records', function () {
    foreach (range(1, 11) as $day) {
        $this->travelTo(sprintf('2026-10-%02d 09:00:00', $day));
        $this->item->recordWastage($this->staff, $day === 1 ? 9 : 1, 'expired');
        $this->item->update(['quantity' => 20]);
    }

    $response = $this->actingAs($this->staff)->get(route('staff.wastage.create', ['sort' => 'quantity', 'direction' => 'desc']));

    expect($response->viewData('recentWastage'))->toHaveCount(10)
        ->and($response->viewData('recentWastage')->pluck('quantity_change')->unique()->all())->toBe([-1]);
});

test('the wastage report can be sorted and the export follows the sort', function (array $query, array $expectedOrder) {
    $admin = User::factory()->create(['role' => 'admin']);
    $roses = InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 20, 'unit_price' => 2.50, 'cost_price' => 5.00, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);
    $tulips = InventoryItem::create(['flower_type' => 'Tulips', 'quantity' => 20, 'unit_price' => 1.80, 'cost_price' => 0.10, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);
    $this->item->recordWastage($this->staff, 2, 'expired');
    $roses->recordWastage($this->staff, 1, 'damaged');
    $tulips->recordWastage($this->staff, 9, 'other');

    $this->actingAs($admin)
        ->get(route('admin.reports.wastage', $query))
        ->assertOk()
        ->assertSeeInOrder($expectedOrder);

    $path = tempnam(sys_get_temp_dir(), 'report');
    file_put_contents($path, $this->actingAs($admin)->get(route('admin.reports.wastage.export', $query))->streamedContent());
    $sheet = IOFactory::load($path)->getActiveSheet();
    unlink($path);

    expect([$sheet->getCell('B6')->getValue(), $sheet->getCell('B7')->getValue(), $sheet->getCell('B8')->getValue()])->toBe($expectedOrder);
})->with([
    'flower A to Z by default' => [[], ['Lilies', 'Roses', 'Tulips']],
    'most removed first' => [['sort' => 'quantity', 'direction' => 'desc'], ['Tulips', 'Lilies', 'Roses']],
    'highest value lost first' => [['sort' => 'value_lost', 'direction' => 'desc'], ['Roses', 'Lilies', 'Tulips']],
    'unknown column falls back to flower' => [['sort' => 'expired'], ['Lilies', 'Roses', 'Tulips']],
]);
