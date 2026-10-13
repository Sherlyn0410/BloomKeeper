<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\StockAdjustment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WastageController extends Controller
{
    public function create(Request $request): View
    {
        [$sort, $direction] = StockAdjustment::resolveWastageSort($request->query('sort'), $request->query('direction'));
        $recentIds = StockAdjustment::query()->where('type', 'wastage')->latest()->latest('id')->limit(10)->pluck('id');

        return view('staff.wastage', [
            'inventory' => InventoryItem::query()->orderBy('flower_type')->orderBy('date_received')->get(),
            'selectedItemId' => $request->integer('item') ?: null,
            'recentWastage' => StockAdjustment::query()->with(['inventoryItem', 'user'])
                ->whereIn('id', $recentIds)
                ->sortedForWastage($sort, $direction)
                ->get(),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateWastage($request);

        $item = InventoryItem::query()->findOrFail($data['inventory_item_id']);
        $item->recordWastage($request->user(), (int) $data['quantity'], $data['reason'], $data['notes'] ?? null);

        return redirect()->route('staff.wastage.create')
            ->with('status', "Removed {$data['quantity']} {$item->flower_type} as {$data['reason']}. {$item->quantity} left in stock.");
    }

    public function edit(StockAdjustment $stockAdjustment): View
    {
        return view('staff.wastage-edit', [
            'adjustment' => $stockAdjustment->load('inventoryItem'),
            'inventory' => InventoryItem::query()->orderBy('flower_type')->orderBy('date_received')->get(),
        ]);
    }

    public function update(Request $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        $data = $this->validateWastage($request);
        $batch = InventoryItem::query()->findOrFail($data['inventory_item_id']);

        $stockAdjustment->updateWastage($batch, (int) $data['quantity'], $data['reason'], $data['notes'] ?? null);

        return $this->redirectToWastageList($request)->with('status', "Wastage record updated: {$data['quantity']} {$batch->flower_type} as {$data['reason']}.");
    }

    public function destroy(Request $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        $quantity = abs($stockAdjustment->quantity_change);
        $flowerType = $stockAdjustment->inventoryItem?->flower_type;

        $stockAdjustment->reverseWastage();

        return $this->redirectToWastageList($request)->with('status', "Wastage record deleted. {$quantity} {$flowerType} returned to stock.");
    }

    /**
     * @return array{inventory_item_id: int|string, quantity: int|string, reason: string, notes?: string|null}
     */
    private function validateWastage(Request $request): array
    {
        return $request->validate([
            'inventory_item_id' => ['required', Rule::exists('inventory_items', 'id')],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::in(InventoryItem::WASTAGE_REASONS)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function redirectToWastageList(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->role === 'admin' ? 'admin.wastage' : 'staff.wastage.create');
    }
}
