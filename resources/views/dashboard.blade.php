<x-staff.layout
    :title="auth()->user()->greeting()"
    description="Here's what needs attention in the shop today."
>
    @can('manage-customers')
        <x-dashboard.stats :stats="[
            ['label' => 'Total stock items', 'value' => $totalStockItems, 'note' => 'Flower stock on hand', 'tone' => 'text-[#203d37]'],
            ['label' => 'Low-stock alerts', 'value' => $lowStockAlerts, 'note' => 'Items below threshold', 'tone' => 'text-[#d47f2a]'],
            ['label' => 'Spoilage alerts', 'value' => $spoilageAlerts, 'note' => 'Approaching expiry', 'tone' => 'text-[#d5533d]'],
            ['label' => 'Pending orders', 'value' => $pendingOrders, 'note' => 'Awaiting preparation', 'tone' => 'text-[#285443]'],
            ['label' => 'Collections today', 'value' => $collectionsToday, 'note' => 'Pending or ready for today', 'tone' => 'text-[#1c9b5f]'],
        ]" />

        <div class="grid gap-6 xl:grid-cols-[1.5fr_0.9fr]">
            <x-dashboard.recent-orders :orders="$recentOrders" :view-all-url="route('staff.orders.index')" />
            <x-dashboard.quick-alerts :alerts="$inventoryAlerts" />
        </div>

        <section class="bk-card">
            <h2 class="bk-card-title">Quick actions</h2>
            <p class="bk-card-subtitle">Jump straight into today's work.</p>
            <div class="mt-5 flex flex-wrap gap-3">
                <a href="{{ route('staff.orders.create') }}" class="bk-btn bk-btn-primary"><x-icons.plus /> Create order</a>
                <a href="{{ route('staff.customers.index') }}" class="bk-btn bk-btn-secondary">Customers</a>
                <a href="{{ route('staff.wastage.create') }}" class="bk-btn bk-btn-secondary">Record wastage</a>
            </div>
        </section>
    @else
        <section class="bk-card">
            <span class="inline-flex rounded-full bg-[#d6eee9] px-3 py-1 text-sm font-medium capitalize text-[#285443]">{{ auth()->user()->role }} account</span>
            <h2 class="bk-card-title mt-4">Your account is ready</h2>
            <p class="bk-card-subtitle">Use My profile in the sidebar to keep your name, email, and password up to date.</p>
        </section>
    @endcan
</x-staff.layout>
