<?php

namespace App\Providers;

use App\Models\CustomerOrder;
use App\Models\PurchaseOrder;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-users', fn (User $user): bool => $user->role === 'admin');
        Gate::define('use-workspace', fn (User $user): bool => $user->approval_status === 'approved');
        Gate::define('update-order', fn (User $user, CustomerOrder $order): bool => $order->canBeEdited());
        Gate::define('delete-order', fn (User $user, CustomerOrder $order): bool => $order->canBeDeleted());
        Gate::define('mark-order-paid', fn (User $user, CustomerOrder $order): bool => $order->canBeMarkedPaid());
        Gate::define('supply-orders', fn (User $user): bool => $user->role === 'supplier' && $user->approval_status === 'approved');
        Gate::define('edit-purchase-order', fn (User $user, PurchaseOrder $order): bool => $order->canBeEdited());
        Gate::define('update-purchase-order', fn (User $user, PurchaseOrder $order): bool => ! $order->isLocked());
        Gate::define('respond-purchase-order', fn (User $user, PurchaseOrder $order): bool => $order->supplier_id === $user->id && $order->isAwaitingSupplier());
        Gate::define('edit-wastage', fn (User $user, StockAdjustment $adjustment): bool => $adjustment->isWastageEditableBy($user));
        Gate::define('manage-customers', fn (User $user): bool => in_array($user->role, ['admin', 'staff'], true) && $user->approval_status === 'approved');

        RateLimiter::for('auth', function (Request $request): Limit {
            return Limit::perMinute(5)->by(
                Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip()),
            );
        });
    }
}
