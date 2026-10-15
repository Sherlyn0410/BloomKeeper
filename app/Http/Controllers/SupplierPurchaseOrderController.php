<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupplierPurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = PurchaseOrder::query()->where('supplier_id', $request->user()->id);
        $status = in_array($request->query('status'), PurchaseOrder::STATUSES, true) ? $request->query('status') : null;

        return view('supplier.purchase-orders', [
            'purchaseOrders' => (clone $orders)->with(['items', 'creator', 'receivedBatch'])
                ->when($status, fn ($query) => $query->where('status', $status))
                ->orderByRaw("CASE WHEN status = 'sent' THEN 0 ELSE 1 END")
                ->orderBy('requested_date')
                ->latest('id')
                ->get(),
            'statusCounts' => (clone $orders)->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status'),
            'activeStatus' => $status,
        ]);
    }

    public function fulfill(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $data = $request->validate(['supplier_note' => ['nullable', 'string', 'max:1000']]);

        $purchaseOrder->update([
            'status' => 'fulfilled',
            'fulfilled_at' => now(),
            'supplier_note' => $data['supplier_note'] ?? null,
        ]);

        return back()->with('status', "Purchase order {$purchaseOrder->reference()} marked as fulfilled.");
    }

    public function decline(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $data = $request->validate(
            ['supplier_note' => ['required', 'string', 'max:1000']],
            ['supplier_note.required' => 'Please give the shop a reason for declining.'],
        );

        $purchaseOrder->update(['status' => 'declined', 'supplier_note' => $data['supplier_note']]);

        return back()->with('status', "Purchase order {$purchaseOrder->reference()} declined.");
    }
}
