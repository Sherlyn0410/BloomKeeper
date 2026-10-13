<?php

namespace App\Http\Controllers;

use App\Models\CustomerOrder;
use App\Models\InventoryItem;
use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
        $sortableColumns = [
            'flower_type' => fn (InventoryItem $item): string => strtolower($item->flower_type),
            'quantity' => fn (InventoryItem $item): int => $item->quantity,
            'date_received' => fn (InventoryItem $item): int => $item->date_received->timestamp,
            'spoilage_date' => fn (InventoryItem $item): int => $item->spoilageDate()->timestamp,
        ];
        $sort = array_key_exists($request->query('sort'), $sortableColumns) ? $request->query('sort') : 'flower_type';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        return view('admin.inventory', [
            'inventory' => InventoryItem::query()->with('supplier')
                ->when($request->filled('search'), fn ($query) => $query->where('flower_type', 'like', '%'.$request->string('search').'%'))
                ->orderBy('flower_type')->get()
                ->sortBy($sortableColumns[$sort], SORT_REGULAR, $direction === 'desc')->values(),
            'suppliers' => $this->approvedSuppliers(),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function orders(Request $request): View
    {
        $orders = CustomerOrder::query()
            ->when($request->filled('customer'), fn ($query) => $query->where(fn ($query) => $query
                ->where('customer_name', 'like', '%'.$request->string('customer').'%')
                ->orWhere('order_number', 'like', '%'.$request->string('customer').'%')))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('collection_date', $request->date('date')));

        $statusCounts = (clone $orders)->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');

        return view('admin.orders', [
            'orders' => $orders->with(['items', 'creator'])
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
                ->latest()
                ->get(),
            'statusCounts' => $statusCounts,
        ]);
    }

    public function purchaseOrders(Request $request): View
    {
        return view('admin.purchase-orders', [
            'purchaseOrders' => PurchaseOrder::query()->with(['items', 'creator'])->latest()->get(),
            'suppliers' => $this->approvedSuppliers(),
        ]);
    }

    public function wastage(Request $request): View
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        [$sort, $direction] = StockAdjustment::resolveWastageSort($request->query('sort'), $request->query('direction'));

        return view('admin.wastage', [
            'adjustments' => StockAdjustment::query()->with(['inventoryItem', 'user'])
                ->where('type', 'wastage')
                ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
                ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
                ->sortedForWastage($sort, $direction)
                ->paginate(20)
                ->withQueryString(),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function reports(Request $request): View
    {
        return view('admin.reports', $this->salesReport($request));
    }

    public function wastageReports(Request $request): View
    {
        return view('admin.reports-wastage', $this->wastageReport($request));
    }

    public function storeInventory(Request $request): RedirectResponse
    {
        InventoryItem::create($request->validate($this->inventoryRules(), ['date_received.before_or_equal' => 'The date received cannot be in the future.']));

        return back()->with('status', 'Inventory item added.');
    }

    public function updateInventory(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $inventoryItem->update($request->validate($this->inventoryRules(), ['date_received.before_or_equal' => 'The date received cannot be in the future.']));

        return back()->with('status', 'Inventory item updated.');
    }

    public function deleteInventory(InventoryItem $inventoryItem): RedirectResponse
    {
        $inventoryItem->delete();

        return back()->with('status', 'Inventory item deleted.');
    }

    public function updateOrderStatus(Request $request, CustomerOrder $customerOrder): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:pending,ready,completed,cancelled']]);

        DB::transaction(function () use ($customerOrder, $data, $request): void {
            $previousStatus = $customerOrder->status;
            $customerOrder->update($data);
            $customerOrder->applyStatusChangeToStock($request->user(), $previousStatus);
        });

        return back()->with('status', 'Order status updated.');
    }

    public function markOrderPaid(CustomerOrder $customerOrder): RedirectResponse
    {
        $customerOrder->update(['payment_status' => 'paid']);

        return back()->with('status', "Order {$customerOrder->order_number} marked as paid.");
    }

    public function storePurchaseOrder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', Rule::exists('users', 'id')->where('role', 'supplier')->where('approval_status', 'approved')],
            'requested_date' => ['required', 'date', 'after_or_equal:today'], 'flower_type' => ['required', 'string', 'max:100'], 'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'supplier_id.required' => 'Choose the supplier to send this purchase order to.',
            'requested_date.after_or_equal' => 'The requested date must be today or later.',
        ]);

        $data['supplier_name'] = User::query()->findOrFail($data['supplier_id'])->name;

        DB::transaction(function () use ($data): void {
            $purchaseOrder = PurchaseOrder::create([...$data, 'created_by' => auth()->id(), 'status' => 'sent']);
            $purchaseOrder->items()->create(['flower_type' => $data['flower_type'], 'quantity' => $data['quantity']]);
        });

        return back()->with('status', 'Purchase order sent.');
    }

    public function updatePurchaseOrderStatus(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $data = $request->validate(
            ['status' => ['required', 'in:sent,cancelled']],
            ['status.in' => 'Only the supplier can mark a purchase order as fulfilled.'],
        );

        $purchaseOrder->update([
            ...$data,
            'supplier_note' => $data['status'] === 'sent' ? null : $purchaseOrder->supplier_note,
        ]);

        return back()->with('status', 'Purchase order status updated.');
    }

    public function exportReport(Request $request): StreamedResponse
    {
        ['rows' => $rows, 'totalQuantity' => $totalQuantity, 'totalSales' => $totalSales, 'totalCost' => $totalCost, 'totalProfit' => $totalProfit, 'reportPeriod' => $reportPeriod] = $this->salesReport($request);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setSize(14);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sales Report');

        $sheet->setCellValue('A1', 'BloomKeeper Flower Demand Report');
        $sheet->setCellValue('A2', "Report Period: {$reportPeriod}");
        $sheet->setCellValue('A3', 'Generated on: '.now('Asia/Kuala_Lumpur')->format('d M Y, h:i A').' (MYT)');
        $sheet->mergeCells('A1:H1');
        $sheet->mergeCells('A2:H2');
        $sheet->mergeCells('A3:H3');
        $sheet->getStyle('A1')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->fromArray(['No.', 'Flower Type', 'Quantity Ordered', 'Total Sales (RM)', 'Total Cost (RM)', 'Gross Profit (RM)', 'Margin', '% of Total Qty'], null, 'A5');
        $sheet->getStyle('A5:H5')->getFont()->setBold(true);
        $sheet->getStyle('A5:H5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D5E4E1');

        $rowNumber = 6;

        foreach ($rows as $index => $row) {
            $sheet->fromArray([
                $index + 1,
                $row['flower_type'],
                $row['quantity'],
                $row['total_sales'],
                $row['total_cost'],
                $row['profit'],
                $row['margin'],
                $row['share'],
            ], null, "A{$rowNumber}");
            $rowNumber++;
        }

        $sheet->fromArray([null, 'Total', $totalQuantity, $totalSales, $totalCost, $totalProfit, $totalSales > 0 ? $totalProfit / $totalSales : 0, $totalQuantity > 0 ? 1 : 0], null, "A{$rowNumber}");
        $sheet->getStyle("A{$rowNumber}:H{$rowNumber}")->getFont()->setBold(true);

        $sheet->getStyle("D6:F{$rowNumber}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("G6:H{$rowNumber}")->getNumberFormat()->setFormatCode('0.00%');

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'bloomkeeper-report.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportWastageReport(Request $request): StreamedResponse
    {
        ['rows' => $rows, 'totalQuantity' => $totalQuantity, 'totalValueLost' => $totalValueLost, 'reportPeriod' => $reportPeriod] = $this->wastageReport($request);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setSize(14);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Wastage Report');

        $sheet->setCellValue('A1', 'BloomKeeper Wastage Report');
        $sheet->setCellValue('A2', "Report Period: {$reportPeriod}");
        $sheet->setCellValue('A3', 'Generated on: '.now('Asia/Kuala_Lumpur')->format('d M Y, h:i A').' (MYT)');
        $sheet->mergeCells('A1:H1');
        $sheet->mergeCells('A2:H2');
        $sheet->mergeCells('A3:H3');
        $sheet->getStyle('A1')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->fromArray(['No.', 'Flower Type', 'Expired', 'Damaged', 'Other', 'Total Removed', 'Value Lost (RM)', '% of Wastage'], null, 'A5');
        $sheet->getStyle('A5:H5')->getFont()->setBold(true);
        $sheet->getStyle('A5:H5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D5E4E1');

        $rowNumber = 6;

        foreach ($rows as $index => $row) {
            $sheet->fromArray([
                $index + 1,
                $row['flower_type'],
                $row['reasons']['expired'],
                $row['reasons']['damaged'],
                $row['reasons']['other'],
                $row['quantity'],
                $row['value_lost'],
                $row['share'],
            ], null, "A{$rowNumber}");
            $rowNumber++;
        }

        $reasonTotals = array_map(fn (string $reason): int => array_sum(array_map(fn (array $row): int => $row['reasons'][$reason], $rows)), InventoryItem::WASTAGE_REASONS);
        $sheet->fromArray([null, 'Total', ...$reasonTotals, $totalQuantity, $totalValueLost, $totalQuantity > 0 ? 1 : 0], null, "A{$rowNumber}");
        $sheet->getStyle("A{$rowNumber}:H{$rowNumber}")->getFont()->setBold(true);

        $sheet->getStyle("G6:G{$rowNumber}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("H6:H{$rowNumber}")->getNumberFormat()->setFormatCode('0.00%');

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'bloomkeeper-wastage-report.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Validation rules shared by the add and edit stock forms.
     *
     * @return array<string, array<int, mixed>>
     */
    private function inventoryRules(): array
    {
        return [
            'flower_type' => ['required', 'string', 'max:100'], 'quantity' => ['required', 'integer', 'min:0'],
            'unit_price' => ['required', 'numeric', 'min:0'], 'cost_price' => ['required', 'numeric', 'min:0'],
            'date_received' => ['required', 'date', 'before_or_equal:today'], 'shelf_life_days' => ['required', 'integer', 'min:1'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'supplier_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'supplier')],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return Collection<int, User>
     */
    private function approvedSuppliers(): Collection
    {
        return User::query()->where('role', 'supplier')->where('approval_status', 'approved')->orderBy('name')->get();
    }

    /**
     * Wastage per flower for the requested date range (by date recorded), split by reason and valued at the cost saved when it was recorded.
     *
     * @return array{rows: array<int, array{flower_type: string, quantity: int, reasons: array<string, int>, value_lost: float, share: float}>, totalQuantity: int, totalValueLost: float, recordCount: int, reportPeriod: string, sort: string, direction: string}
     */
    private function wastageReport(Request $request): array
    {
        $adjustments = StockAdjustment::query()->with('inventoryItem')
            ->where('type', 'wastage')
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->get();

        $totalQuantity = (int) $adjustments->sum(fn (StockAdjustment $adjustment): int => abs($adjustment->quantity_change));

        $rows = $adjustments
            ->groupBy(fn (StockAdjustment $adjustment): string => $adjustment->inventoryItem->flower_type)
            ->map(function ($flowerAdjustments, string $flowerType) use ($totalQuantity): array {
                $quantity = (int) $flowerAdjustments->sum(fn (StockAdjustment $adjustment): int => abs($adjustment->quantity_change));

                return [
                    'flower_type' => $flowerType,
                    'quantity' => $quantity,
                    'reasons' => collect(InventoryItem::WASTAGE_REASONS)->mapWithKeys(fn (string $reason): array => [
                        $reason => (int) $flowerAdjustments->where('reason', $reason)->sum(fn (StockAdjustment $adjustment): int => abs($adjustment->quantity_change)),
                    ])->all(),
                    'value_lost' => round($flowerAdjustments->sum(fn (StockAdjustment $adjustment): float => abs($adjustment->quantity_change) * (float) ($adjustment->unit_cost ?? $adjustment->inventoryItem->cost_price)), 2),
                    'share' => $totalQuantity > 0 ? $quantity / $totalQuantity : 0.0,
                ];
            });

        $sort = in_array($request->query('sort'), ['flower_type', 'quantity', 'value_lost'], true) ? $request->query('sort') : 'flower_type';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $rows = $rows->sortBy($sort, SORT_NATURAL | SORT_FLAG_CASE, $direction === 'desc')->values()->all();

        return [
            'rows' => $rows,
            'totalQuantity' => $totalQuantity,
            'totalValueLost' => round(array_sum(array_column($rows, 'value_lost')), 2),
            'recordCount' => $adjustments->count(),
            'reportPeriod' => $this->reportPeriod($request),
            'sort' => $sort,
            'direction' => $direction,
        ];
    }

    private function reportPeriod(Request $request): string
    {
        $from = $request->filled('from') ? $request->date('from')->format('d M Y') : null;
        $to = $request->filled('to') ? $request->date('to')->format('d M Y') : null;

        return match (true) {
            $from && $to => "{$from} to {$to}",
            (bool) $from => "From {$from}",
            (bool) $to => "Up to {$to}",
            default => 'All dates',
        };
    }

    /**
     * Build the sales report for the requested collection-date range, excluding cancelled orders.
     * Sales and cost use the price and cost saved on each order line, so later inventory changes do not alter past figures.
     *
     * @return array{rows: array<int, array{flower_type: string, quantity: int, total_sales: float, total_cost: float, profit: float, margin: float, share: float}>, totalQuantity: int, totalSales: float, totalCost: float, totalProfit: float, reportPeriod: string, sort: string, direction: string}
     */
    private function salesReport(Request $request): array
    {
        $sales = OrderItem::query()
            ->select('flower_type', DB::raw('SUM(quantity) as quantity'), DB::raw('SUM(quantity * unit_price) as total_sales'), DB::raw('SUM(quantity * COALESCE(unit_cost, 0)) as total_cost'))
            ->whereHas('order', fn ($order) => $order
                ->where('status', '!=', 'cancelled')
                ->when($request->filled('from'), fn ($query) => $query->whereDate('collection_date', '>=', $request->date('from')))
                ->when($request->filled('to'), fn ($query) => $query->whereDate('collection_date', '<=', $request->date('to'))))
            ->groupBy('flower_type')->orderBy('flower_type')->get();

        $totalQuantity = (int) $sales->sum('quantity');

        $rows = $sales->map(function (OrderItem $item) use ($totalQuantity): array {
            $quantity = (int) $item->quantity;
            $totalSales = round((float) $item->total_sales, 2);
            $totalCost = round((float) $item->total_cost, 2);
            $profit = round($totalSales - $totalCost, 2);

            return [
                'flower_type' => $item->flower_type,
                'quantity' => $quantity,
                'total_sales' => $totalSales,
                'total_cost' => $totalCost,
                'profit' => $profit,
                'margin' => $totalSales > 0 ? $profit / $totalSales : 0.0,
                'share' => $totalQuantity > 0 ? $quantity / $totalQuantity : 0.0,
            ];
        });

        $sort = in_array($request->query('sort'), ['flower_type', 'quantity', 'total_sales', 'profit'], true) ? $request->query('sort') : 'flower_type';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $rows = $rows->sortBy($sort, SORT_NATURAL | SORT_FLAG_CASE, $direction === 'desc')->values()->all();

        return [
            'rows' => $rows,
            'totalQuantity' => $totalQuantity,
            'totalSales' => round(array_sum(array_column($rows, 'total_sales')), 2),
            'totalCost' => round(array_sum(array_column($rows, 'total_cost')), 2),
            'totalProfit' => round(array_sum(array_column($rows, 'profit')), 2),
            'reportPeriod' => $this->reportPeriod($request),
            'sort' => $sort,
            'direction' => $direction,
        ];
    }
}
