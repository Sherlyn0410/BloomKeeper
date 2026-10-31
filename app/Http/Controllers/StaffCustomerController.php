<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            ...$this->validateCustomer($request),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', "{$customer->name} has been added.");
    }

    public function edit(Customer $customer): View
    {
        return view('staff.customer-edit', ['customer' => $customer]);
    }

    /**
     * Past orders keep the details they were placed with; only the saved customer changes.
     */
    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->validateCustomer($request, $customer));

        return redirect()->route('staff.customers.index')->with('status', "{$customer->name} has been updated.");
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->orders()->exists()) {
            return back()->withErrors(['customer' => "{$customer->name} can't be deleted because the customer has orders. Edit the details instead."]);
        }

        $customer->delete();

        return back()->with('status', "{$customer->name} has been deleted.");
    }

    /**
     * @return array{name: string, phone: string, email: string|null}
     */
    private function validateCustomer(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^(?:[\s-]*\d){9,11}[\s-]*$/'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customer)],
        ], ['phone.regex' => 'The phone number must have 9 to 11 digits.']);
    }
}
