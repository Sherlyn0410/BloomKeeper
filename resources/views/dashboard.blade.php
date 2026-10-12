<x-staff.layout
    title="Welcome, {{ auth()->user()->name }}."
    description="Your florist inventory workspace is ready for the next bouquet."
>
    <section class="bk-card">
        <span class="inline-flex rounded-full bg-[#d6eee9] px-3 py-1 text-sm font-medium capitalize text-[#285443]">{{ auth()->user()->role }} account</span>
        @can('manage-customers')
            <h2 class="bk-card-title mt-4">Quick actions</h2>
            <p class="bk-card-subtitle">Jump straight into today's work.</p>
            <div class="mt-5 flex flex-wrap gap-3">
                <a href="{{ route('staff.orders.create') }}" class="bk-btn bk-btn-primary">Create customer order</a>
                <a href="{{ route('staff.orders.index') }}" class="bk-btn bk-btn-secondary">Orders</a>
                <a href="{{ route('staff.customers.index') }}" class="bk-btn bk-btn-secondary">Customers</a>
                <a href="{{ route('staff.wastage.create') }}" class="bk-btn bk-btn-secondary">Record wastage</a>
            </div>
        @else
            <p class="mt-4 text-sm text-[#607a72]">Use the Profile tab to keep your account details up to date.</p>
        @endcan
    </section>
</x-staff.layout>
