<?php

namespace App\Http\Controllers;

use App\Models\CustomerOrder;
use App\Models\InventoryItem;
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
            ->view('dashboard', $user->can('manage-customers') ? $this->workspaceSummary() : [])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
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
            'inventoryAlerts' => $inventoryItems->filter(fn (InventoryItem $item): bool => $item->isLowStock() || $item->isNearSpoilage())->take(6)->values(),
        ];
    }
}
