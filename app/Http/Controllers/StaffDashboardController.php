<?php

namespace App\Http\Controllers;

use App\Models\CustomerOrder;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\StockReturn;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class StaffDashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if ($user->approval_status !== 'approved') {
            Auth::logout();

            return redirect()->route('login')->withErrors(['email' => 'Your account still needs administrator approval.']);
        }

        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        return response()
            ->view('dashboard', match (true) {
                $user->can('manage-customers') => $this->workspaceSummary(),
                $user->can('supply-orders') => $this->supplierSummary($user),
                default => [],
            })
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /**
     * The supplier's open purchase orders, recent deliveries, and returns waiting on them.
     *
     * @return array<string, mixed>
     */
    private function supplierSummary(User $supplier): array
    {
        $orders = PurchaseOrder::query()->where('supplier_id', $supplier->id);

        return [
            'openPurchaseOrders' => (clone $orders)->where('status', 'sent')->count(),
            'duePurchaseOrders' => (clone $orders)->where('status', 'sent')->whereDate('requested_date', '<=', today()->addDays(3))->count(),
            'fulfilledThisMonth' => (clone $orders)->where('status', 'fulfilled')->where('fulfilled_at', '>=', now()->startOfMonth())->count(),
            'upcomingPurchaseOrders' => (clone $orders)->with('items')->where('status', 'sent')->orderBy('requested_date')->limit(5)->get(),
            'pendingReturnCount' => StockReturn::query()->where('supplier_id', $supplier->id)->where('status', 'requested')->count(),
            'pendingReturns' => StockReturn::query()->where('supplier_id', $supplier->id)->where('status', 'requested')->oldest()->limit(5)->get(),
        ];
    }

    /**
     * Stock alerts, today's collections, and recent orders for staff.
     *
     * @return array<string, mixed>
     */
    private function workspaceSummary(): array
    {
        $inventoryItems = InventoryItem::query()->get();

        return [
            'totalStockItems' => $inventoryItems->count(),
            'lowStockAlerts' => $inventoryItems->filter(fn (InventoryItem $item): bool => $item->isLowStock())->count(),
            'spoilageAlerts' => $inventoryItems->filter(fn (InventoryItem $item): bool => $item->isNearSpoilage())->count(),
            'pendingOrders' => CustomerOrder::query()->where('status', 'pending')->count(),
            'collectionsToday' => CustomerOrder::query()->whereIn('status', ['pending', 'ready'])->whereDate('collection_date', today())->count(),
            'recentOrders' => CustomerOrder::query()->with('items')->latest()->limit(5)->get(),
            'lowStockItems' => $inventoryItems->filter(fn (InventoryItem $item): bool => $item->isLowStock())->sortBy('quantity')->take(5)->values(),
            'spoilageItems' => $inventoryItems->filter(fn (InventoryItem $item): bool => $item->isNearSpoilage())->sortBy(fn (InventoryItem $item): int => $item->spoilageDate()->timestamp)->take(5)->values(),
        ];
    }
}
