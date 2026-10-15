<?php

namespace App\Http\Controllers;

use App\Models\StockReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupplierStockReturnController extends Controller
{
    public function index(Request $request): View
    {
        $returns = StockReturn::query()->where('supplier_id', $request->user()->id);
        $status = in_array($request->query('status'), StockReturn::STATUSES, true) ? $request->query('status') : null;

        return view('supplier.stock-returns', [
            'stockReturns' => (clone $returns)->with('creator')
                ->when($status, fn ($query) => $query->where('status', $status))
                ->orderByRaw("CASE WHEN status = 'requested' THEN 0 ELSE 1 END")
                ->latest()->latest('id')
                ->get(),
            'statusCounts' => (clone $returns)->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status'),
            'activeStatus' => $status,
        ]);
    }

    public function accept(Request $request, StockReturn $stockReturn): RedirectResponse
    {
        $data = $request->validate(['supplier_note' => ['nullable', 'string', 'max:1000']]);

        $stockReturn->accept($data['supplier_note'] ?? null);

        return back()->with('status', "Return {$stockReturn->reference()} accepted.");
    }

    public function reject(Request $request, StockReturn $stockReturn): RedirectResponse
    {
        $data = $request->validate(
            ['supplier_note' => ['required', 'string', 'max:1000']],
            ['supplier_note.required' => 'Please give the shop a reason for rejecting the return.'],
        );

        $stockReturn->reject($data['supplier_note']);

        return back()->with('status', "Return {$stockReturn->reference()} rejected.");
    }
}
