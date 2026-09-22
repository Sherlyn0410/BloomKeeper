<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->latest()->paginate(10),
        ]);
    }

    public function approve(User $user): RedirectResponse
    {
        $user->update(['approval_status' => 'approved']);

        return back()->with('status', "{$user->name} can now sign in.");
    }

    public function suspend(User $user): RedirectResponse
    {
        if ($user->is(auth()->user())) {
            return back()->withErrors(['user' => 'You cannot suspend your own administrator account.']);
        }

        $user->update(['approval_status' => 'suspended']);

        return back()->with('status', "{$user->name} has been suspended.");
    }

    public function restore(User $user): RedirectResponse
    {
        $user->update(['approval_status' => 'approved']);

        return back()->with('status', "{$user->name} can sign in again.");
    }
}
