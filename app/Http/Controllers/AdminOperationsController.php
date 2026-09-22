<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\CustomerOrder;
use App\Models\InventoryItem;
use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminOperationsController extends Controller
{
    public function index(Request $request): View
    {
        $orders = CustomerOrder::query()->with(['items', 'creator'])->latest();
        $orders->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')));
        $orders->when($request->filled('customer'), fn ($query) => $query->where('customer_name', 'like', '%'.$request->string('customer').'%'));
        $orders->when($request->filled('date'), fn ($query) => $query->whereDate('collection_date', $request->date('date')));

        return view('admin.operations', [
            'inventory' => InventoryItem::query()->orderBy('flower_type')->get(),
            'orders' => $orders->get(),
            'purchaseOrders' => PurchaseOrder::query()->with(['items', 'creator'])->latest()->get(),
            'suppliers' => User::query()->where('role', 'supplier')->where('approval_status', 'approved')->orderBy('name')->get(),
            'settings' => BusinessSetting::query()->pluck('value', 'key'),
            'report' => $this->report($request),
        ]);
    }

    public function storeInventory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'flower_type' => ['required', 'string', 'max:100'], 'quantity' => ['required', 'integer', 'min:0'],
            'unit_price' => ['required', 'numeric', 'min:0'], 'date_received' => ['required', 'date'],
            'shelf_life_days' => ['required', 'integer', 'min:1'], 'low_stock_threshold' => ['required', 'integer', 'min:0'],
        ]);
        InventoryItem::create($data);

        return back()->with('status', 'Inventory item added.');
    }

    public function updateInventory(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $inventoryItem->update($request->validate([
            'flower_type' => ['required', 'string', 'max:100'], 'quantity' => ['required', 'integer', 'min:0'],
            'unit_price' => ['required', 'numeric', 'min:0'], 'date_received' => ['required', 'date'],
            'shelf_life_days' => ['required', 'integer', 'min:1'], 'low_stock_threshold' => ['required', 'integer', 'min:0'],
        ]));

        return back()->with('status', 'Inventory item updated.');
    }

    public function deleteInventory(InventoryItem $inventoryItem): RedirectResponse
    {
        $inventoryItem->delete();

        return back()->with('status', 'Inventory item deleted.');
    }

    public function adjustInventory(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $data = $request->validate(['quantity_change' => ['required', 'integer', 'not_in:0'], 'reason' => ['required', 'string', 'max:255']]);
        abort_if($data['quantity_change'] < 0 && $inventoryItem->quantity < abs($data['quantity_change']), 422, 'Adjustment cannot reduce stock below zero.');
        DB::transaction(function () use ($data, $inventoryItem): void {
            $inventoryItem->increment('quantity', $data['quantity_change']);
            StockAdjustment::create([...$data, 'inventory_item_id' => $inventoryItem->id, 'user_id' => auth()->id()]);
        });

        return back()->with('status', 'Stock adjusted.');
    }

    public function updateOrderStatus(Request $request, CustomerOrder $customerOrder): RedirectResponse
    {
        $customerOrder->update($request->validate(['status' => ['required', 'in:pending,ready,completed,cancelled']]));

        return back()->with('status', 'Order status updated.');
    }

    public function storePurchaseOrder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'exists:users,id'], 'supplier_name' => ['required', 'string', 'max:255'],
            'requested_date' => ['required', 'date'], 'flower_type' => ['required', 'string', 'max:100'], 'quantity' => ['required', 'integer', 'min:1'],
        ]);
        DB::transaction(function () use ($data): void {
            $purchaseOrder = PurchaseOrder::create([...$data, 'created_by' => auth()->id(), 'status' => 'sent']);
            $purchaseOrder->items()->create(['flower_type' => $data['flower_type'], 'quantity' => $data['quantity']]);
        });

        return back()->with('status', 'Purchase order sent.');
    }

    public function updatePurchaseOrderStatus(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $purchaseOrder->update($request->validate(['status' => ['required', 'in:sent,fulfilled,cancelled']]));

        return back()->with('status', 'Purchase order status updated.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'], 'business_contact' => ['nullable', 'string', 'max:255'],
            'spoilage_alert_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);
        foreach ($data as $key => $value) {
            BusinessSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return back()->with('status', 'Settings saved.');
    }

    public function exportReport(Request $request): StreamedResponse
    {
        $rows = $this->report($request);

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Flower type', 'Quantity ordered']);
            foreach ($rows as $row) {
                fputcsv($handle, [$row->flower_type, $row->quantity]);
            }
            fclose($handle);
        }, 'bloomkeeper-report.csv', [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="bloomkeeper-report.csv"',
        ]);
    }

    private function report(Request $request): Collection
    {
        return OrderItem::query()->select('flower_type', DB::raw('SUM(quantity) as quantity'))
            ->when($request->filled('from'), fn ($query) => $query->whereHas('order', fn ($order) => $order->whereDate('collection_date', '>=', $request->date('from'))))
            ->when($request->filled('to'), fn ($query) => $query->whereHas('order', fn ($order) => $order->whereDate('collection_date', '<=', $request->date('to'))))
            ->groupBy('flower_type')->orderBy('flower_type')->get();
    }
}
