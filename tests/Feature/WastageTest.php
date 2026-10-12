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

test('reports and the excel export include wastage by flower valued at cost price', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->travelTo('2026-10-05 10:00:00');
    $this->item->recordWastage($this->staff, 4, 'expired');

    $this->actingAs($admin)
        ->get(route('admin.reports', ['from' => '2026-10-01', 'to' => '2026-10-31']))
        ->assertOk()
        ->assertSee('Wastage by flower')
        ->assertSeeInOrder(['Lilies', '4', '6.00']);

    $content = $this->actingAs($admin)
        ->get(route('admin.reports.export', ['from' => '2026-10-01', 'to' => '2026-10-31']))
        ->streamedContent();

    $path = tempnam(sys_get_temp_dir(), 'report');
    file_put_contents($path, $content);
    $sheet = IOFactory::load($path)->getSheetByName('Wastage');
    unlink($path);

    expect($sheet)->not->toBeNull()
        ->and($sheet->getCell('B5')->getValue())->toBe('Lilies')
        ->and($sheet->getCell('C5')->getValue())->toBe(4)
        ->and($sheet->getCell('D5')->getValue())->toEqual(6.0);
});
