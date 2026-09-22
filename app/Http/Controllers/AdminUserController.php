<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()->whereIn('role', ['staff', 'supplier', 'admin']);
        $users->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')));
        $users->when($request->filled('status'), fn ($query) => $query->where('approval_status', $request->string('status')));

        return view('admin.users.index', [
            'users' => $users->latest()->paginate(10)->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', 'in:staff,supplier'], 'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        User::create([...$data, 'password' => Hash::make($data['password']), 'approval_status' => 'approved']);

        return back()->with('status', 'Account created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'role' => ['required', 'in:staff,supplier,admin'], 'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);
        if ($data['password'] ?? false) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $user->update($data);

        return back()->with('status', 'Account updated.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        abort_if($user->is(auth()->user()), 422, 'You cannot deactivate your own account.');
        $user->update(['approval_status' => $user->approval_status === 'approved' ? 'suspended' : 'approved']);

        return back()->with('status', 'Account status updated.');
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
