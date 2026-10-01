@php
    $statusStyles = [
        'pending' => ['badge' => 'bg-[#fff0d5] text-[#a76513]', 'dot' => 'bg-[#a76513]'],
        'ready' => ['badge' => 'bg-[#d7f0df] text-[#187849]', 'dot' => 'bg-[#187849]'],
        'completed' => ['badge' => 'bg-[#d5e4e1] text-[#285443]', 'dot' => 'bg-[#285443]'],
        'cancelled' => ['badge' => 'bg-[#f5dcd7] text-[#b84331]', 'dot' => 'bg-[#b84331]'],
    ];
    $activeStatus = request('status', '');
    $today = today();
@endphp

<x-admin.layout
    title="Customer Orders"
    description="Review customer collections, inspect order items, and move each order through fulfilment."
>
    <section class="rounded-[28px] bg-[#edf7f5]/90 p-5 shadow-[0_12px_30px_rgba(36,73,64,0.1)] sm:p-7">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="font-serif text-2xl font-bold">Order queue</h2>
                <p class="mt-1 text-sm text-[#607a72]">Filter the queue by customer, collection date, or status.</p>
            </div>
            <form class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="status" value="{{ $activeStatus }}">
                <label class="relative">
                    <span class="sr-only">Customer</span>
                    <svg class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-[#8aa39c]" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.45 4.39l3.08 3.08a.75.75 0 1 1-1.06 1.06l-3.08-3.08A7 7 0 0 1 2 9Z" clip-rule="evenodd" /></svg>
                    <input name="customer" value="{{ request('customer') }}" placeholder="Search customer" class="w-48 rounded-lg border-0 py-2 pr-3 pl-9 text-sm">
                </label>
                <label>
                    <span class="sr-only">Collection date</span>
                    <input name="date" value="{{ request('date') }}" type="date" class="rounded-lg border-0 p-2 text-sm">
                </label>
                <button class="rounded-lg bg-[#285443] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#1f4435]">Filter</button>
                @if (request()->filled('customer') || request()->filled('date'))
                    <a href="{{ route('admin.orders', array_filter(['status' => $activeStatus])) }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-[#49665f] transition hover:bg-[#dce9e6]">Clear</a>
                @endif
            </form>
        </div>

        <nav class="mt-6 flex flex-wrap gap-2" aria-label="Filter by status">
            @foreach (['' => 'All', 'pending' => 'Pending', 'ready' => 'Ready', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $status => $label)
                @php
                    $isActive = $activeStatus === $status;
                    $count = $status === '' ? $statusCounts->sum() : ($statusCounts[$status] ?? 0);
                @endphp
                <a
                    href="{{ route('admin.orders', array_filter([...request()->only(['customer', 'date']), 'status' => $status])) }}"
                    @if ($isActive) aria-current="page" @endif
                    @class([
                        'flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold transition',
                        'bg-[#285443] text-white' => $isActive,
                        'bg-white/70 text-[#49665f] hover:bg-white' => ! $isActive,
                    ])
                >
                    @if ($status !== '')
                        <span @class(['size-2 rounded-full', $isActive ? 'bg-white' : $statusStyles[$status]['dot']])></span>
                    @endif
                    {{ $label }}
                    <span @class(['rounded-full px-2 text-xs', 'bg-white/20' => $isActive, 'bg-[#dce9e6]' => ! $isActive])>{{ $count }}</span>
                </a>
            @endforeach
        </nav>

        <div class="mt-5 space-y-3">
            @forelse ($orders as $order)
                @php
                    $orderTotal = $order->items->sum(fn ($item) => $item->quantity * $item->unit_price);
                    $isOpen = in_array($order->status, ['pending', 'ready'], true);
                    $collectionLabel = match (true) {
                        ! $isOpen => null,
                        $order->collection_date->lt($today) => ['text' => 'Overdue', 'class' => 'bg-[#f5dcd7] text-[#b84331]'],
                        $order->collection_date->isSameDay($today) => ['text' => 'Today', 'class' => 'bg-[#e3953d] text-white'],
                        $order->collection_date->isSameDay($today->copy()->addDay()) => ['text' => 'Tomorrow', 'class' => 'bg-[#fff0d5] text-[#a76513]'],
                        default => null,
                    };
                @endphp
                <article @class([
                    'rounded-2xl bg-white/70 p-4 transition hover:bg-white sm:p-5',
                    'opacity-70' => $order->status === 'cancelled',
                ])>
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-semibold text-[#8aa39c]">#{{ str_pad($order->id, 4, '0', STR_PAD_LEFT) }}</span>
                                <h3 class="font-semibold text-[#1c2e28]">{{ $order->customer_name }}</h3>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusStyles[$order->status]['badge'] ?? 'bg-[#dce9e6] text-[#49665f]' }}">{{ ucfirst($order->status) }}</span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-[#607a72]">
                                @if ($order->customer_email)
                                    <span class="flex items-center gap-1.5">
                                        <svg class="size-4 text-[#8aa39c]" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 4a2 2 0 0 0-2 2v1.16l8.55 4.27a1 1 0 0 0 .9 0L19 7.16V6a2 2 0 0 0-2-2H3Z" /><path d="m19 8.84-7.66 3.83a3 3 0 0 1-2.68 0L1 8.84V14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.84Z" /></svg>
                                        {{ $order->customer_email }}
                                    </span>
                                @endif
                                @if ($order->customer_phone)
                                    <span class="flex items-center gap-1.5">
                                        <svg class="size-4 text-[#8aa39c]" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M2 3.5A1.5 1.5 0 0 1 3.5 2h1.15c.86 0 1.6.58 1.82 1.41l.66 2.49a1.88 1.88 0 0 1-.7 1.97l-.8.6a.5.5 0 0 0-.17.57 9.02 9.02 0 0 0 5.5 5.5.5.5 0 0 0 .57-.17l.6-.8a1.88 1.88 0 0 1 1.97-.7l2.49.66c.83.22 1.41.96 1.41 1.82v1.15A1.5 1.5 0 0 1 16.5 18H15C7.82 18 2 12.18 2 5V3.5Z" clip-rule="evenodd" /></svg>
                                        {{ $order->customer_phone }}
                                    </span>
                                @endif
                                <span class="flex items-center gap-1.5">
                                    <svg class="size-4 text-[#8aa39c]" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd" /></svg>
                                    Collection {{ $order->collection_date->format('d M Y') }}
                                    @if ($collectionLabel)
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $collectionLabel['class'] }}">{{ $collectionLabel['text'] }}</span>
                                    @endif
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <p class="text-xs text-[#8aa39c]">Order total</p>
                                <p class="font-bold text-[#1c2e28]">RM {{ number_format($orderTotal, 2) }}</p>
                            </div>
                            <form method="POST" action="{{ route('admin.orders.status', $order) }}">
                                @csrf
                                @method('PATCH')
                                <label class="sr-only" for="order-status-{{ $order->id }}">Update status</label>
                                <select id="order-status-{{ $order->id }}" name="status" onchange="this.form.submit()" class="rounded-lg border border-[#d4e4e0] bg-white py-2 pr-8 pl-3 text-sm font-semibold text-[#285443] focus:border-[#285443] focus:outline-none">
                                    @foreach (['pending' => 'Pending', 'ready' => 'Ready', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label)
                                        <option value="{{ $value }}" @selected($order->status === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </div>
                    </div>

                    @if ($order->items->isNotEmpty())
                        <ul class="mt-4 flex flex-wrap gap-2 border-t border-[#e3eeeb] pt-4">
                            @foreach ($order->items as $item)
                                <li class="rounded-full bg-[#edf7f5] px-3 py-1 text-sm text-[#285443]">
                                    <span class="font-semibold">{{ $item->quantity }}×</span> {{ $item->flower_type }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </article>
            @empty
                <div class="rounded-2xl bg-white/60 p-10 text-center">
                    <p class="font-semibold text-[#49665f]">No orders found</p>
                    <p class="mt-1 text-sm text-[#607a72]">No orders match the current filters.</p>
                </div>
            @endforelse
        </div>
    </section>
</x-admin.layout>
