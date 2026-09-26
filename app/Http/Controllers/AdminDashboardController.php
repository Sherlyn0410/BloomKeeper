<?php

namespace App\Http\Controllers;

use App\Models\CustomerOrder;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        $inventoryItems = InventoryItem::query()->get();
        $lowStockAlerts = $inventoryItems->filter(fn (InventoryItem $item) => $item->quantity <= $item->low_stock_threshold)->count();
        $spoilageAlerts = $inventoryItems->filter(function (InventoryItem $item): bool {
            if (! $item->date_received) {
                return false;
            }

            return $item->date_received->copy()->addDays($item->shelf_life_days)->lte(now()->addDays(2));
        })->count();

        $recentOrders = CustomerOrder::query()->with('items')->latest()->limit(5)->get();

        return view('admin.dashboard', [
            'totalStockItems' => $inventoryItems->count(),
            'lowStockAlerts' => $lowStockAlerts,
            'spoilageAlerts' => $spoilageAlerts,
            'pendingOrders' => CustomerOrder::query()->where('status', 'pending')->count(),
            'pendingPurchaseOrders' => PurchaseOrder::query()->where('status', 'sent')->count(),
            'recentOrders' => $recentOrders,
            'recentInventoryAlerts' => $inventoryItems->filter(fn (InventoryItem $item) => $item->quantity <= $item->low_stock_threshold || $item->date_received->copy()->addDays($item->shelf_life_days)->lte(now()->addDays(2)))->take(6)->values(),
            'totalUsers' => User::query()->count(),
            'pendingUsers' => User::query()->where('approval_status', 'pending')->count(),
            'activeUsers' => User::query()->where('approval_status', 'approved')->count(),
            'suspendedUsers' => User::query()->where('approval_status', 'suspended')->count(),
            'recentUsers' => User::query()
                ->where('role', '!=', 'admin')
                ->latest('created_at')
                ->limit(6)
                ->get(),
        ]);
    }
}
