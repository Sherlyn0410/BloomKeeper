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
        return view('staff.wastage', [
            'inventory' => InventoryItem::query()->orderBy('flower_type')->orderBy('date_received')->get(),
            'selectedItemId' => $request->integer('item') ?: null,
            'recentWastage' => StockAdjustment::query()->with(['inventoryItem', 'user'])
                ->where('type', 'wastage')
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'inventory_item_id' => ['required', Rule::exists('inventory_items', 'id')],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::in(InventoryItem::WASTAGE_REASONS)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $item = InventoryItem::query()->findOrFail($data['inventory_item_id']);
        $item->recordWastage($request->user(), (int) $data['quantity'], $data['reason'], $data['notes'] ?? null);

        return redirect()->route('staff.wastage.create')
            ->with('status', "Removed {$data['quantity']} {$item->flower_type} as {$data['reason']}. {$item->quantity} left in stock.");
    }
}
