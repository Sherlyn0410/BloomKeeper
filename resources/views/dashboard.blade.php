<x-staff.layout
    :title="auth()->user()->greeting()"
    :description="auth()->user()->can('supply-orders') ? 'Here are the purchase orders the shop has sent you.' : 'Here\'s what needs attention in the shop today.'"
>
    @can('manage-customers')
        <x-dashboard.stats :stats="[
            ['label' => 'Flowers in stock', 'value' => $totalStockItems, 'note' => 'Flower types with stock on hand', 'tone' => 'text-[#203d37]'],
            ['label' => 'Low-stock alerts', 'value' => $lowStockAlerts, 'note' => 'Flowers at or below threshold', 'tone' => 'text-[#d47f2a]'],
            ['label' => 'Spoilage alerts', 'value' => $spoilageAlerts, 'note' => 'Flowers spoiling within 2 days', 'tone' => 'text-[#d5533d]'],
            ['label' => 'Pending orders', 'value' => $pendingOrders, 'note' => 'Awaiting preparation', 'tone' => 'text-[#285443]'],
            ['label' => 'Collections today', 'value' => $collectionsToday, 'note' => 'Pending or ready for today', 'tone' => 'text-[#1c9b5f]'],
        ]" />

        <div class="grid gap-6 xl:grid-cols-[1.5fr_0.9fr]">
            <x-dashboard.recent-orders :orders="$recentOrders" :view-all-url="route('staff.orders.index')" />
            <div class="min-w-0 space-y-6">
                <x-dashboard.quick-alerts title="Low stock" type="low-stock" :alerts="$lowStockItems" :view-all-url="route('staff.inventory', ['alert' => 'low_stock'])" empty-message="Every flower is above its low-stock threshold." />
                <x-dashboard.quick-alerts title="Spoilage alerts" type="spoilage" :alerts="$spoilageItems" :view-all-url="route('staff.inventory', ['alert' => 'spoilage'])" empty-message="Nothing is close to spoiling." />
            </div>
        </div>
    @elsecan('supply-orders')
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            @foreach ([
                ['label' => 'Awaiting you', 'value' => $openPurchaseOrders, 'note' => 'Orders to fulfil or decline', 'tone' => 'text-[#a76513]'],
                ['label' => 'Due within 3 days', 'value' => $duePurchaseOrders, 'note' => 'Requested soon or overdue', 'tone' => 'text-[#d5533d]'],
                ['label' => 'Fulfilled this month', 'value' => $fulfilledThisMonth, 'note' => 'Delivered to the shop', 'tone' => 'text-[#1c9b5f]'],
                ['label' => 'Returns to review', 'value' => $pendingReturnCount, 'note' => 'Returns to accept or reject', 'tone' => 'text-[#b84331]'],
            ] as $stat)
                <div class="bk-card">
                    <p class="text-sm text-[#49665f]">{{ $stat['label'] }}</p>
                    <p class="mt-3 truncate text-2xl font-bold sm:text-3xl {{ $stat['tone'] }}">{{ $stat['value'] }}</p>
                    <p class="mt-2 text-sm text-[#607a72]">{{ $stat['note'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="bk-card">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="bk-card-title">Next to deliver</h2>
                        <p class="bk-card-subtitle">Open purchase orders, soonest first.</p>
                    </div>
                    <a href="{{ route('supplier.purchase-orders.index') }}" class="text-sm font-semibold text-[#285443] underline underline-offset-4">View all</a>
                </div>
                <div class="mt-5 space-y-3">
                    @forelse ($upcomingPurchaseOrders as $purchaseOrder)
                        <div class="bk-tile flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold tracking-wide text-[#8aa39c]">{{ $purchaseOrder->reference() }}</p>
                                <p class="font-semibold text-[#1c2e28]">{{ $purchaseOrder->items->map(fn ($item) => $item->quantity.' × '.$item->flower_type)->join(', ') }}</p>
                            </div>
                            <span @class([
                                'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                'bg-[#f5dcd7] text-[#b84331]' => $purchaseOrder->requested_date->lte(today()->addDays(3)),
                                'bg-[#edf7f5] text-[#285443]' => $purchaseOrder->requested_date->gt(today()->addDays(3)),
                            ])>Needed {{ $purchaseOrder->requested_date->format('d M Y') }}</span>
                        </div>
                    @empty
                        <p class="bk-tile text-center text-sm text-[#607a72]">No open purchase orders right now.</p>
                    @endforelse
                </div>
            </section>
            <section class="bk-card">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="bk-card-title">Returns to review</h2>
                        <p class="bk-card-subtitle">Stock the shop is returning, oldest first.</p>
                    </div>
                    <a href="{{ route('supplier.stock-returns.index') }}" class="text-sm font-semibold text-[#285443] underline underline-offset-4">View all</a>
                </div>
                <div class="mt-5 space-y-3">
                    @forelse ($pendingReturns as $stockReturn)
                        <div class="bk-tile flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold tracking-wide text-[#8aa39c]">{{ $stockReturn->reference() }}</p>
                                <p class="font-semibold text-[#1c2e28]">{{ $stockReturn->quantity }} × {{ $stockReturn->flower_type }}</p>
                            </div>
                            <span class="rounded-full bg-[#f5dcd7] px-2.5 py-0.5 text-xs font-semibold text-[#b84331]">{{ $stockReturn->reasonLabel() }}</span>
                        </div>
                    @empty
                        <p class="bk-tile text-center text-sm text-[#607a72]">No returns to review.</p>
                    @endforelse
                </div>
            </section>
        </div>
    @else
        <section class="bk-card">
            <span class="inline-flex rounded-full bg-[#d6eee9] px-3 py-1 text-sm font-medium capitalize text-[#285443]">{{ auth()->user()->role }} account</span>
            <h2 class="bk-card-title mt-4">Your account is ready</h2>
            <p class="bk-card-subtitle">Use My profile in the sidebar to keep your name, email, and password up to date.</p>
        </section>
    @endcan
</x-staff.layout>
