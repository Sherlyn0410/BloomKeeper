<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'totalUsers' => User::query()->count(),
            'pendingUsers' => User::query()->where('approval_status', 'pending')->count(),
            'activeUsers' => User::query()->where('approval_status', 'approved')->count(),
            'suspendedUsers' => User::query()->where('approval_status', 'suspended')->count(),
            'recentUsers' => User::query()->latest()->limit(6)->get(),
        ]);
    }
}
