<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

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
    Route::get('/dashboard', function (): RedirectResponse {
        return redirect()->route(auth()->user()->role === 'admin' ? 'admin.dashboard' : 'dashboard.user');
    })->name('dashboard');
    Route::view('/workspace', 'dashboard')->name('dashboard.user');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('can:manage-users')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::patch('/users/{user}/approve', [AdminUserController::class, 'approve'])->name('users.approve');
        Route::patch('/users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
        Route::patch('/users/{user}/restore', [AdminUserController::class, 'restore'])->name('users.restore');
    });
});
