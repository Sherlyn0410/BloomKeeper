<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminOperationsController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StaffCustomerController;
use App\Http\Controllers\StaffDashboardController;
use App\Http\Controllers\StaffOrderController;
use App\Http\Controllers\StockReturnController;
use App\Http\Controllers\SupplierPurchaseOrderController;
use App\Http\Controllers\SupplierStockReturnController;
use App\Http\Controllers\WastageController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

Route::redirect('/', '/login');

/**
 * Stock, purchasing, and order-status pages shared by admins and staff. They are registered under both
 * the admin and staff areas so each role keeps its own URLs, route names, and layout.
 */
$sharedOperationsRoutes = function (): void {
    Route::get('/inventory', [AdminOperationsController::class, 'inventory'])->name('inventory');
    Route::get('/purchase-orders', [AdminOperationsController::class, 'purchaseOrders'])->name('purchase-orders');
    Route::post('/inventory', [AdminOperationsController::class, 'storeInventory'])->name('inventory.store');
    Route::put('/inventory/{inventoryItem}', [AdminOperationsController::class, 'updateInventory'])->name('inventory.update');
    Route::delete('/inventory/{inventoryItem}', [AdminOperationsController::class, 'deleteInventory'])->name('inventory.destroy');
    Route::post('/purchase-orders/{purchaseOrder}/receive', [AdminOperationsController::class, 'receivePurchaseOrder'])->middleware('can:receive-purchase-order,purchaseOrder')->name('purchase-orders.receive');
    Route::get('/stock-returns', [StockReturnController::class, 'index'])->name('stock-returns');
    Route::post('/stock-returns', [StockReturnController::class, 'store'])->name('stock-returns.store');
    Route::patch('/stock-returns/{stockReturn}/cancel', [StockReturnController::class, 'cancel'])->middleware('can:cancel-stock-return,stockReturn')->name('stock-returns.cancel');
    Route::put('/stock-returns/{stockReturn}', [StockReturnController::class, 'update'])->middleware('can:edit-stock-return,stockReturn')->name('stock-returns.update');
    Route::patch('/orders/{customerOrder}/status', [AdminOperationsController::class, 'updateOrderStatus'])->middleware('can:update-order,customerOrder')->name('orders.status');
    Route::patch('/orders/{customerOrder}/paid', [AdminOperationsController::class, 'markOrderPaid'])->middleware('can:mark-order-paid,customerOrder')->name('orders.paid');
    Route::post('/purchase-orders', [AdminOperationsController::class, 'storePurchaseOrder'])->name('purchase-orders.store');
    Route::patch('/purchase-orders/{purchaseOrder}/status', [AdminOperationsController::class, 'updatePurchaseOrderStatus'])->middleware('can:update-purchase-order,purchaseOrder')->name('purchase-orders.status');
};

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth')->name('login.store');
    Route::get('/signup', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/signup', [AuthController::class, 'register'])->middleware('throttle:auth')->name('register.store');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:auth')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth')->name('password.update');
});

Route::middleware('auth')->group(function () use ($sharedOperationsRoutes): void {
    Route::get('/email/verify', function (): View {
        return view('auth.verify-email');
    })->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request): RedirectResponse {
        $request->fulfill();

        return redirect()->route('dashboard');
    })->middleware('signed')->name('verification.verify');
    Route::post('/email/verification-notification', function (Request $request): RedirectResponse {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'A verification link has been sent to your email address.');
    })->middleware('throttle:6,1')->name('verification.send');
    Route::get('/dashboard', StaffDashboardController::class)->name('dashboard');
    Route::get('/workspace', function (): Response|RedirectResponse {
        return redirect()->route('dashboard');
    })->name('dashboard.user');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware(['verified', 'can:manage-customers'])->prefix('staff')->name('staff.')->group(function () use ($sharedOperationsRoutes): void {
        Route::get('/customers', [StaffCustomerController::class, 'index'])->name('customers.index');
        Route::post('/customers', [StaffCustomerController::class, 'store'])->name('customers.store');
        Route::get('/orders', [AdminOperationsController::class, 'orders'])->name('orders.index');
        Route::get('/orders/create', [StaffOrderController::class, 'create'])->name('orders.create');
        Route::post('/orders', [StaffOrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{customerOrder}/edit', [StaffOrderController::class, 'edit'])->middleware('can:update-order,customerOrder')->name('orders.edit');
        Route::put('/orders/{customerOrder}', [StaffOrderController::class, 'update'])->middleware('can:update-order,customerOrder')->name('orders.update');
        Route::delete('/orders/{customerOrder}', [StaffOrderController::class, 'destroy'])->middleware('can:delete-order,customerOrder')->name('orders.destroy');
        Route::get('/wastage', [WastageController::class, 'create'])->name('wastage.create');
        Route::post('/wastage', [WastageController::class, 'store'])->name('wastage.store');
        Route::middleware('can:edit-wastage,stockAdjustment')->group(function (): void {
            Route::get('/wastage/{stockAdjustment}/edit', [WastageController::class, 'edit'])->name('wastage.edit');
            Route::put('/wastage/{stockAdjustment}', [WastageController::class, 'update'])->name('wastage.update');
            Route::delete('/wastage/{stockAdjustment}', [WastageController::class, 'destroy'])->name('wastage.destroy');
        });
        $sharedOperationsRoutes();
    });

    Route::middleware(['verified', 'can:supply-orders'])->prefix('supplier')->name('supplier.')->group(function (): void {
        Route::get('/purchase-orders', [SupplierPurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::middleware('can:respond-purchase-order,purchaseOrder')->group(function (): void {
            Route::patch('/purchase-orders/{purchaseOrder}/fulfill', [SupplierPurchaseOrderController::class, 'fulfill'])->name('purchase-orders.fulfill');
            Route::patch('/purchase-orders/{purchaseOrder}/decline', [SupplierPurchaseOrderController::class, 'decline'])->name('purchase-orders.decline');
        });
        Route::get('/returns', [SupplierStockReturnController::class, 'index'])->name('stock-returns.index');
        Route::middleware('can:respond-stock-return,stockReturn')->group(function (): void {
            Route::patch('/returns/{stockReturn}/accept', [SupplierStockReturnController::class, 'accept'])->name('stock-returns.accept');
            Route::patch('/returns/{stockReturn}/reject', [SupplierStockReturnController::class, 'reject'])->name('stock-returns.reject');
        });
    });

    Route::middleware(['verified', 'can:use-workspace'])->group(function (): void {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::post('/messages', [MessageController::class, 'start'])->name('messages.start');
        Route::middleware('can:view-conversation,conversation')->group(function (): void {
            Route::get('/messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
            Route::post('/messages/{conversation}', [MessageController::class, 'store'])->name('messages.store');
            Route::get('/messages/{conversation}/poll', [MessageController::class, 'poll'])->name('messages.poll');
            Route::get('/messages/{conversation}/images/{message}', [MessageController::class, 'image'])->name('messages.image');
        });
    });

    Route::middleware('can:manage-users')->prefix('admin')->name('admin.')->group(function () use ($sharedOperationsRoutes): void {
        $sharedOperationsRoutes();
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
        Route::get('/users', [AdminUserController::class, 'users'])->name('users');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('users.toggle-status');
        Route::patch('/users/{user}/approve', [AdminUserController::class, 'approve'])->name('users.approve');
        Route::patch('/users/{user}/decline', [AdminUserController::class, 'decline'])->name('users.decline');
        Route::patch('/users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
        Route::patch('/users/{user}/restore', [AdminUserController::class, 'restore'])->name('users.restore');
        Route::get('/operations', [AdminOperationsController::class, 'index'])->name('operations');
        Route::get('/orders', [AdminOperationsController::class, 'orders'])->name('orders');
        Route::get('/reports', [AdminOperationsController::class, 'reports'])->name('reports');
        Route::get('/wastage', [AdminOperationsController::class, 'wastage'])->name('wastage');
        Route::put('/purchase-orders/{purchaseOrder}', [AdminOperationsController::class, 'updatePurchaseOrder'])->middleware('can:edit-purchase-order,purchaseOrder')->name('purchase-orders.update');
        Route::get('/reports/export', [AdminOperationsController::class, 'exportReport'])->name('reports.export');
        Route::get('/reports/wastage', [AdminOperationsController::class, 'wastageReports'])->name('reports.wastage');
        Route::get('/reports/wastage/export', [AdminOperationsController::class, 'exportWastageReport'])->name('reports.wastage.export');
    });
});
