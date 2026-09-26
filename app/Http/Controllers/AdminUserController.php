<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function users(Request $request): View
    {
        $accountRole = $request->string('role')->toString();
        $accountRole = in_array($accountRole, ['staff', 'supplier'], true) ? $accountRole : 'staff';

        return view('admin.users.index', [
            'accountRole' => $accountRole,
            'accountLabel' => $accountRole === 'supplier' ? 'Supplier' : 'Staff',
            'users' => User::query()->where('role', $accountRole)
                ->when($request->filled('status'), fn ($query) => $query->where('approval_status', $request->string('status')))
                ->when($request->filled('search'), function ($query) use ($request): void {
                    $search = '%'.$request->string('search')->toString().'%';
                    $query->where(fn ($query) => $query->where('name', 'like', $search)->orWhere('email', 'like', $search));
                })
                ->latest()->paginate(10)->withQueryString(),
        ]);
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

    public function decline(User $user): RedirectResponse
    {
        abort_if($user->is(auth()->user()), 422, 'You cannot decline your own account.');
        $user->update(['approval_status' => 'declined']);

        return back()->with('status', "{$user->name}'s account was declined.");
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
