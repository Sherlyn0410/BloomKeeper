<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\StockReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockReturnController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), StockReturn::STATUSES, true) ? $request->query('status') : null;

        return view('admin.stock-returns', [
            'returnableBatches' => InventoryItem::query()->with('supplier')->whereNotNull('supplier_id')->where('quantity', '>', 0)
                ->whereDate('date_received', '>=', today()->subDays(StockReturn::RETURN_WINDOW_DAYS))
                ->orderBy('flower_type')->orderBy('date_received')->get(),
            'selectedItemId' => $request->integer('item') ?: null,
            'stockReturns' => StockReturn::query()->with(['supplier', 'creator', 'inventoryItem'])
                ->when($status, fn ($query) => $query->where('status', $status))
                ->latest()->latest('id')
                ->get(),
            'statusCounts' => StockReturn::query()->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status'),
            'acceptedValue' => (float) StockReturn::query()->where('status', 'accepted')->sum(DB::raw('quantity * unit_cost')),
            'activeStatus' => $status,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'inventory_item_id' => ['required', Rule::exists('inventory_items', 'id')],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::in(array_keys(StockReturn::REASONS))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'inventory_item_id.required' => 'Please select a stock batch.',
        ]);

        $stockReturn = StockReturn::request(
            $request->user(),
            InventoryItem::query()->findOrFail($data['inventory_item_id']),
            (int) $data['quantity'],
            $data['reason'],
            $data['notes'] ?? null,
        );

        return back()->with('status', "Return {$stockReturn->reference()} submitted to {$stockReturn->supplier->name}. Stock has been deducted.");
    }

    public function cancel(StockReturn $stockReturn): RedirectResponse
    {
        $stockReturn->cancel();

        return back()->with('status', "Return {$stockReturn->reference()} cancelled. Stock has been restored.");
    }
}
