<x-admin.layout
    title="Customer orders"
    description="Review customer collections, inspect order items, and move each order through fulfilment."
>
    <section class="rounded-[28px] bg-[#edf7f5]/90 p-5 shadow-[0_12px_30px_rgba(36,73,64,0.1)] sm:p-7">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="font-serif text-2xl font-bold">Order queue</h2>
                <p class="mt-1 text-sm text-[#607a72]">Filter the queue by customer, collection date, or status.</p>
            </div>
            <form class="flex flex-wrap gap-2">
                <input name="customer" value="{{ request('customer') }}" placeholder="Customer" class="rounded-lg border-0 p-2">
                <input name="date" value="{{ request('date') }}" type="date" class="rounded-lg border-0 p-2">
                <select name="status" class="rounded-lg border-0 p-2"><option value="">All statuses</option>@foreach (['pending', 'ready', 'completed', 'cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
                <button class="rounded-lg bg-[#285443] px-3 py-2 font-semibold text-white">Filter</button>
            </form>
        </div>

        <div class="mt-6 space-y-3">
            @forelse ($orders as $order)
                <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white/60 p-4">
                    <div>
                        <p class="font-semibold">#{{ $order->id }} · {{ $order->customer_name }}</p>
                        <p class="mt-1 text-sm text-[#607a72]">{{ $order->customer_email }} · Collection {{ $order->collection_date->format('d M Y') }}</p>
                        <p class="mt-1 text-sm text-[#607a72]">{{ $order->items->map(fn ($item) => $item->quantity.' '.$item->flower_type)->join(', ') }}</p>
                    </div>
                    <form method="POST" action="{{ route('admin.orders.status', $order) }}">@csrf @method('PATCH')<select name="status" onchange="this.form.submit()" class="rounded-lg border-0 p-2"><option value="pending" @selected($order->status === 'pending')>Pending</option><option value="ready" @selected($order->status === 'ready')>Ready</option><option value="completed" @selected($order->status === 'completed')>Completed</option><option value="cancelled" @selected($order->status === 'cancelled')>Cancelled</option></select></form>
                </div>
            @empty
                <p class="rounded-2xl bg-white/60 p-6 text-sm text-[#607a72]">No orders match the current filters.</p>
            @endforelse
        </div>
    </section>
</x-admin.layout>
