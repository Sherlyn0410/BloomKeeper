<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminOperationsController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

Route::redirect('/', '/login');

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

Route::middleware('auth')->group(function (): void {
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
    Route::get('/dashboard', function (): Response|RedirectResponse {
        if (! auth()->user()->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if (auth()->user()->approval_status !== 'approved') {
            Auth::logout();

            return redirect()->route('login')->withErrors(['email' => 'Your account still needs administrator approval.']);
        }

        if (auth()->user()->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        return response()
            ->view('dashboard')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    })->name('dashboard');
    Route::get('/workspace', function (): Response|RedirectResponse {
        return redirect()->route('dashboard');
    })->name('dashboard.user');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('can:manage-users')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
        Route::get('/users', [AdminUserController::class, 'users'])->name('users');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('users.toggle-status');
        Route::patch('/users/{user}/approve', [AdminUserController::class, 'approve'])->name('users.approve');
        Route::patch('/users/{user}/decline', [AdminUserController::class, 'decline'])->name('users.decline');
        Route::patch('/users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
        Route::patch('/users/{user}/restore', [AdminUserController::class, 'restore'])->name('users.restore');
        Route::get('/operations', [AdminOperationsController::class, 'index'])->name('operations');
        Route::get('/inventory', [AdminOperationsController::class, 'inventory'])->name('inventory');
        Route::get('/orders', [AdminOperationsController::class, 'orders'])->name('orders');
        Route::get('/purchase-orders', [AdminOperationsController::class, 'purchaseOrders'])->name('purchase-orders');
        Route::get('/reports', [AdminOperationsController::class, 'reports'])->name('reports');
        Route::post('/inventory', [AdminOperationsController::class, 'storeInventory'])->name('inventory.store');
        Route::put('/inventory/{inventoryItem}', [AdminOperationsController::class, 'updateInventory'])->name('inventory.update');
        Route::delete('/inventory/{inventoryItem}', [AdminOperationsController::class, 'deleteInventory'])->name('inventory.destroy');
        Route::patch('/orders/{customerOrder}/status', [AdminOperationsController::class, 'updateOrderStatus'])->name('orders.status');
        Route::post('/purchase-orders', [AdminOperationsController::class, 'storePurchaseOrder'])->name('purchase-orders.store');
        Route::patch('/purchase-orders/{purchaseOrder}/status', [AdminOperationsController::class, 'updatePurchaseOrderStatus'])->name('purchase-orders.status');
        Route::get('/reports/export', [AdminOperationsController::class, 'exportReport'])->name('reports.export');
    });
});
