<?php

use App\Models\InventoryItem;
use App\Models\StockAdjustment;
use App\Models\StockReturn;
use App\Models\User;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->staff = User::factory()->create(['role' => 'staff']);
    $this->supplier = User::factory()->create(['role' => 'supplier', 'name' => 'Highland Florals']);
    $this->roses = InventoryItem::create(['flower_type' => 'Roses', 'quantity' => 20, 'unit_price' => 2.50, 'cost_price' => 1.20, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 5, 'supplier_id' => $this->supplier->id]);
});

function sendReturn(User $user, array $overrides = []): TestResponse
{
    $area = $user->role === 'admin' ? 'admin' : 'staff';

    return test()->actingAs($user)->post(route("{$area}.stock-returns.store"), [
        'inventory_item_id' => test()->roses->id, 'quantity' => 5, 'reason' => 'damaged', 'notes' => 'Bruised petals',
        ...$overrides,
    ]);
}

test('admins and staff can return stock, which leaves stock straight away', function (string $role) {
    $user = $role === 'staff' ? $this->staff : User::factory()->create(['role' => 'admin']);

    sendReturn($user)->assertSessionHasNoErrors();

    $stockReturn = StockReturn::sole();
    expect($stockReturn)
        ->reference()->toBe('RT-0001')
        ->supplier_id->toBe($this->supplier->id)
        ->status->toBe('requested')
        ->value()->toBe(6.0)
        ->and($this->roses->refresh()->quantity)->toBe(15)
        ->and($stockReturn->stockAdjustment)->type->toBe('return')->quantity_change->toBe(-5);
})->with(['staff', 'admin']);

test('a return cannot exceed the stock on hand or come from a batch without a supplier', function () {
    sendReturn($this->staff, ['quantity' => 21])->assertSessionHasErrors(['quantity' => 'Only 20 Roses in stock. Unable to return 21.']);

    $noSupplier = InventoryItem::create(['flower_type' => 'Tulips', 'quantity' => 10, 'unit_price' => 1.80, 'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 5]);
    sendReturn($this->staff, ['inventory_item_id' => $noSupplier->id])
        ->assertSessionHasErrors(['inventory_item_id' => 'This Tulips batch has no recorded supplier and cannot be returned. Record it as wastage instead.']);

    expect(StockReturn::count())->toBe(0)
        ->and($this->roses->refresh()->quantity)->toBe(20);
});

test('the supplier sees their returns and can accept one', function () {
    sendReturn($this->staff);
    $stockReturn = StockReturn::sole();

    $this->actingAs($this->supplier)->get(route('supplier.stock-returns.index'))
        ->assertOk()
        ->assertSee(['RT-0001', '5 × Roses', 'Bruised petals']);

    $this->actingAs($this->supplier)->patch(route('supplier.stock-returns.accept', $stockReturn), ['supplier_note' => 'Credit issued'])->assertSessionHasNoErrors();

    expect($stockReturn->refresh())->status->toBe('accepted')->supplier_note->toBe('Credit issued')
        ->and($this->roses->refresh()->quantity)->toBe(15)
        ->and(StockAdjustment::where('type', 'wastage')->count())->toBe(0);

    $this->actingAs($this->staff)->get(route('staff.stock-returns'))->assertSee('RM 6.00');
});

test('a rejected return is recorded as wastage', function () {
    sendReturn($this->staff);
    $stockReturn = StockReturn::sole();

    $this->actingAs($this->supplier)->patch(route('supplier.stock-returns.reject', $stockReturn), [])
        ->assertSessionHasErrors(['supplier_note' => 'Please give the shop a reason for rejecting the return.']);
    $this->actingAs($this->supplier)->patch(route('supplier.stock-returns.reject', $stockReturn), ['supplier_note' => 'They left us in good condition'])->assertSessionHasNoErrors();

    $wastage = StockAdjustment::where('type', 'wastage')->sole();
    expect($stockReturn->refresh()->status)->toBe('rejected')
        ->and($wastage)->quantity_change->toBe(-5)->reason->toBe('damaged')
        ->and($wastage->notes)->toContain('RT-0001')
        ->and($this->roses->refresh()->quantity)->toBe(15);
});

test('suppliers can only answer their own returns while they are waiting', function () {
    sendReturn($this->staff);
    $stockReturn = StockReturn::sole();
    $otherSupplier = User::factory()->create(['role' => 'supplier']);

    $this->actingAs($otherSupplier)->patch(route('supplier.stock-returns.accept', $stockReturn))->assertForbidden();
    $this->actingAs($otherSupplier)->get(route('supplier.stock-returns.index'))->assertDontSee('RT-0001');

    $stockReturn->accept(null);
    $this->actingAs($this->supplier)->patch(route('supplier.stock-returns.reject', $stockReturn), ['supplier_note' => 'Changed my mind'])->assertForbidden();
});

test('cancelling a waiting return puts the flowers back into stock', function () {
    sendReturn($this->staff);
    $stockReturn = StockReturn::sole();

    $this->actingAs($this->staff)->patch(route('staff.stock-returns.cancel', $stockReturn))->assertSessionHasNoErrors();

    expect($stockReturn->refresh()->status)->toBe('cancelled')
        ->and($this->roses->refresh()->quantity)->toBe(20)
        ->and(StockAdjustment::count())->toBe(0);
});

test('staff can only cancel their own waiting returns, admins any', function () {
    $otherStaff = User::factory()->create(['role' => 'staff']);
    $admin = User::factory()->create(['role' => 'admin']);
    sendReturn($otherStaff);
    $stockReturn = StockReturn::sole();

    $this->actingAs($this->staff)->patch(route('staff.stock-returns.cancel', $stockReturn))->assertForbidden();
    $this->actingAs($admin)->patch(route('admin.stock-returns.cancel', $stockReturn))->assertSessionHasNoErrors();

    $this->actingAs($admin)->patch(route('admin.stock-returns.cancel', $stockReturn->refresh()))->assertForbidden();
});

test('a returned batch has stock history and cannot be deleted', function () {
    sendReturn($this->staff);

    $this->actingAs($this->staff)->delete(route('staff.inventory.destroy', $this->roses))->assertSessionHasErrors('inventory');

    expect(InventoryItem::find($this->roses->id))->not->toBeNull();
});

test('the inventory links returnable batches to the return form', function () {
    $this->actingAs($this->staff)->get(route('staff.inventory'))->assertSee(route('staff.stock-returns', ['item' => $this->roses->id]));
    $this->actingAs($this->staff)->get(route('staff.stock-returns', ['item' => $this->roses->id]))
        ->assertOk()
        ->assertSee('<option value="'.$this->roses->id.'" selected>', false);
});

test('suppliers are blocked from the shop return pages and guests are sent to login', function (string $method, string $route) {
    $stockReturn = StockReturn::factory()->create();
    $url = str_ends_with($route, 'cancel') ? route($route, $stockReturn) : route($route);

    $this->actingAs($this->supplier)->{$method}($url)->assertForbidden();
    auth()->logout();
    $this->{$method}($url)->assertRedirect(route('login'));
})->with([
    ['get', 'staff.stock-returns'], ['post', 'staff.stock-returns.store'], ['patch', 'staff.stock-returns.cancel'],
    ['get', 'admin.stock-returns'], ['post', 'admin.stock-returns.store'],
]);

test('staff cannot use the supplier return pages and guests are sent to login', function () {
    $stockReturn = StockReturn::factory()->create();

    $this->actingAs($this->staff)->get(route('supplier.stock-returns.index'))->assertForbidden();
    $this->actingAs($this->staff)->patch(route('supplier.stock-returns.accept', $stockReturn))->assertForbidden();
    auth()->logout();
    $this->get(route('supplier.stock-returns.index'))->assertRedirect(route('login'));
});

test('flowers can only be returned within the return window after arriving', function (int $daysAgo, bool $allowed) {
    $this->roses->update(['date_received' => today()->subDays($daysAgo)]);

    $response = sendReturn($this->staff);

    $allowed
        ? $response->assertSessionHasNoErrors()
        : $response->assertSessionHasErrors(['inventory_item_id' => 'This Roses batch was received on '.today()->subDays($daysAgo)->format('d M Y').'. Returns are accepted within 2 days of receipt. Record it as wastage instead.']);

    expect($this->roses->refresh()->quantity)->toBe($allowed ? 15 : 20);
})->with([
    'today' => [0, true],
    'two days ago' => [2, true],
    'three days ago' => [3, false],
]);

test('older batches are left out of the return form and lose the return link', function () {
    $this->roses->update(['date_received' => today()->subDays(3)]);

    $this->actingAs($this->staff)->get(route('staff.stock-returns'))->assertDontSee('<option value="'.$this->roses->id.'"', false);
    $this->actingAs($this->staff)->get(route('staff.inventory'))->assertDontSee(route('staff.stock-returns', ['item' => $this->roses->id]));
});
