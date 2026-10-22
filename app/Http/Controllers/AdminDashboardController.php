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
        $stock = InventoryItem::dashboardSummary();

        $recentOrders = CustomerOrder::query()->with('items')->latest()->limit(5)->get();

        return view('admin.dashboard', [
            'totalStockItems' => $stock['flowersInStock'],
            'lowStockAlerts' => $stock['lowStockAlerts'],
            'spoilageAlerts' => $stock['spoilageAlerts'],
            'pendingOrders' => CustomerOrder::query()->where('status', 'pending')->count(),
            'pendingPurchaseOrders' => PurchaseOrder::query()->where('status', 'sent')->count(),
            'recentOrders' => $recentOrders,
            'lowStockItems' => $stock['lowStockItems'],
            'spoilageItems' => $stock['spoilageItems'],
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
