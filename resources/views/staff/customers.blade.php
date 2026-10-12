<x-workspace.layout
    title="Customers"
    description="Save customer details once and reuse them for future orders."
>
    <section class="bk-card">
        <h2 class="bk-card-title">Add new customer</h2>
        <form method="POST" action="{{ route('staff.customers.store') }}" class="mt-5 grid gap-3 sm:grid-cols-3">
            @csrf
            <label class="bk-label">
                Name
                <input name="name" value="{{ old('name') }}" required maxlength="255" class="bk-input">
            </label>
            <label class="bk-label">
                Phone
                <input name="phone" value="{{ old('phone') }}" type="tel" inputmode="numeric" required maxlength="13" placeholder="012-345 6789" data-phone-input class="bk-input">
            </label>
            <label class="bk-label">
                <span>Email <span class="font-normal text-[#8aa39c]">(optional)</span></span>
                <input name="email" value="{{ old('email') }}" type="email" maxlength="255" class="bk-input">
            </label>
            <div class="bk-form-actions">
                <button class="bk-btn bk-btn-primary">Save customer</button>
            </div>
        </form>
    </section>

    <section class="bk-card">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="bk-card-title">Saved customers</h2>
            <span class="rounded-full bg-[#d6eee9] px-3 py-1 text-sm font-medium text-[#285443]">{{ $customers->count() }} total</span>
        </div>
        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[560px] text-left text-sm">
                <thead class="border-b border-[#d4e4e0] text-xs uppercase tracking-wider text-[#607a72]">
                    <tr>
                        <th class="pb-3">Name</th>
                        <th class="pb-3">Phone</th>
                        <th class="pb-3">Email</th>
                        <th class="pb-3 text-right">Orders</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#dce9e6]">
                    @forelse ($customers as $customer)
                        <tr>
                            <td class="py-3 font-semibold">{{ $customer->name }}</td>
                            <td class="py-3 text-[#607a72]">{{ $customer->formatted_phone ?? '-' }}</td>
                            <td class="py-3 text-[#607a72]">{{ $customer->email ?? '-' }}</td>
                            <td class="py-3 text-right font-semibold">{{ $customer->orders_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-[#607a72]">No customers saved yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-workspace.layout>
