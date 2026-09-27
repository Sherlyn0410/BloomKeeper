<?php

namespace App\Http\Controllers;

use App\Models\CustomerOrder;
use App\Models\InventoryItem;
use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminOperationsController extends Controller
{
    public function index(Request $request): View
    {
        return $this->inventory($request);
    }

    public function inventory(Request $request): View
    {
        return view('admin.inventory', [
            'inventory' => InventoryItem::query()->orderBy('flower_type')->get(),
        ]);
    }

    public function orders(Request $request): View
    {
        $orders = CustomerOrder::query()->with(['items', 'creator'])->latest();
        $orders->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')));
        $orders->when($request->filled('customer'), fn ($query) => $query->where('customer_name', 'like', '%'.$request->string('customer').'%'));
        $orders->when($request->filled('date'), fn ($query) => $query->whereDate('collection_date', $request->date('date')));

        return view('admin.orders', [
            'orders' => $orders->get(),
        ]);
    }

    public function purchaseOrders(Request $request): View
    {
        return view('admin.purchase-orders', [
            'purchaseOrders' => PurchaseOrder::query()->with(['items', 'creator'])->latest()->get(),
            'suppliers' => User::query()->where('role', 'supplier')->where('approval_status', 'approved')->orderBy('name')->get(),
        ]);
    }

    public function reports(Request $request): View
    {
        return view('admin.reports', [
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

    public function exportReport(Request $request): StreamedResponse
    {
        $rows = $this->report($request);
        $unitPrices = InventoryItem::query()->orderBy('date_received')->pluck('unit_price', 'flower_type');
        $totalQuantity = (int) $rows->sum('quantity');

        $from = $request->filled('from') ? $request->date('from')->format('d M Y') : null;
        $to = $request->filled('to') ? $request->date('to')->format('d M Y') : null;
        $reportPeriod = match (true) {
            $from && $to => "{$from} to {$to}",
            (bool) $from => "From {$from}",
            (bool) $to => "Up to {$to}",
            default => 'All dates',
        };

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setSize(14);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sales Report');

        $sheet->setCellValue('A1', 'BloomKeeper Flower Demand Report');
        $sheet->setCellValue('A2', "Report Period: {$reportPeriod}");
        $sheet->setCellValue('A3', 'Generated on: '.now('Asia/Kuala_Lumpur')->format('d M Y, h:i A').' (MYT)');
        $sheet->mergeCells('A1:F1');
        $sheet->mergeCells('A2:F2');
        $sheet->mergeCells('A3:F3');
        $sheet->getStyle('A1')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->fromArray(['No.', 'Flower Type', 'Quantity Ordered', 'Unit Price (RM)', 'Total Sales (RM)', '% of Total Qty'], null, 'A5');
        $sheet->getStyle('A5:F5')->getFont()->setBold(true);
        $sheet->getStyle('A5:F5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D5E4E1');

        $rowNumber = 6;
        $totalSales = 0.0;

        foreach ($rows->values() as $index => $row) {
            $quantity = (int) $row->quantity;
            $unitPrice = (float) ($unitPrices[$row->flower_type] ?? 0);
            $sales = round($quantity * $unitPrice, 2);
            $totalSales += $sales;

            $sheet->fromArray([
                $index + 1,
                $row->flower_type,
                $quantity,
                $unitPrice,
                $sales,
                $totalQuantity > 0 ? $quantity / $totalQuantity : 0,
            ], null, "A{$rowNumber}");
            $rowNumber++;
        }

        $sheet->fromArray([null, 'Total', $totalQuantity, null, round($totalSales, 2), $totalQuantity > 0 ? 1 : 0], null, "A{$rowNumber}");
        $sheet->getStyle("A{$rowNumber}:F{$rowNumber}")->getFont()->setBold(true);

        $sheet->getStyle("D6:E{$rowNumber}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("F6:F{$rowNumber}")->getNumberFormat()->setFormatCode('0.00%');

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'bloomkeeper-report.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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
