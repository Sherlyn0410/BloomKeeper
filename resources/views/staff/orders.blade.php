@php
    $statusBadges = [
        'pending' => 'bg-[#fff0d5] text-[#a76513]',
        'ready' => 'bg-[#d7f0df] text-[#187849]',
        'completed' => 'bg-[#d5e4e1] text-[#285443]',
        'cancelled' => 'bg-[#f5dcd7] text-[#b84331]',
    ];
@endphp

<x-workspace.layout
    title="Customer orders"
    description="Find an order by number or customer, then edit or remove it."
>
    <section class="bk-card">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <form class="flex flex-wrap gap-2">
                <input name="search" value="{{ request('search') }}" placeholder="Order no. or customer" class="bk-input w-64">
                <button class="bk-btn bk-btn-primary">Search</button>
                @if (request()->filled('search'))
                    <a href="{{ route('staff.orders.index') }}" class="bk-btn bk-btn-ghost">Clear</a>
                @endif
            </form>
            <a href="{{ route('staff.orders.create') }}" class="bk-btn bk-btn-accent">+ New order</a>
        </div>

        <div class="mt-5 space-y-3">
            @forelse ($orders as $order)
                <article class="bk-tile flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-semibold text-[#8aa39c]">{{ $order->order_number }}</span>
                            <h3 class="font-semibold">{{ $order->customer_name }}</h3>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadges[$order->status] ?? '' }}">{{ ucfirst($order->status) }}</span>
                            <span @class(['rounded-full px-2.5 py-0.5 text-xs font-semibold', 'bg-[#d7f0df] text-[#187849]' => $order->payment_status === 'paid', 'bg-[#dce9e6] text-[#49665f]' => $order->payment_status !== 'paid'])>{{ ucfirst($order->payment_status) }}</span>
                        </div>
                        <p class="mt-1 text-sm text-[#607a72]">
                            Collection {{ $order->collection_date->format('d M Y') }}{{ $order->collection_time ? ', '.\Illuminate\Support\Carbon::parse($order->collection_time)->format('h:i A') : '' }}
                            · {{ $order->items->map(fn ($item) => $item->quantity.'× '.$item->flower_type)->join(', ') }}
                        </p>
                        @if ($order->notes)
                            <p class="mt-1 text-sm italic text-[#607a72]">“{{ $order->notes }}”</p>
                        @endif
                    </div>
                    <x-order-actions :order="$order" />
                </article>
            @empty
                <p class="bk-tile text-center text-sm text-[#607a72]">No orders match your search.</p>
            @endforelse
        </div>

        <div class="mt-5">{{ $orders->links() }}</div>
    </section>
</x-workspace.layout>
