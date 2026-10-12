<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffCustomerController extends Controller
{
    public function index(): View
    {
        return view('staff.customers', [
            'customers' => Customer::query()->withCount('orders')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = Customer::create([
            ...$request->validate([
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['required', 'string', 'max:30', 'regex:/^(?:[\s-]*\d){9,11}[\s-]*$/'],
                'email' => ['nullable', 'email', 'max:255', 'unique:customers,email'],
            ], ['phone.regex' => 'The phone number must have 9 to 11 digits.']),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', "{$customer->name} has been added.");
    }
}
