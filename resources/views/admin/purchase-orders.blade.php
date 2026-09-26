<x-admin.layout
    title="Purchase orders"
    description="Create supplier requests and track which flower deliveries are sent, fulfilled, or cancelled."
>
    <section class="rounded-[28px] bg-[#edf7f5]/90 p-5 shadow-[0_12px_30px_rgba(36,73,64,0.1)] sm:p-7">
        <div>
            <h2 class="font-serif text-2xl font-bold">Create purchase order</h2>
            <p class="mt-1 text-sm text-[#607a72]">Send a request to an approved supplier or record an external supplier.</p>
        </div>
        <form method="POST" action="{{ route('admin.purchase-orders.store') }}" class="mt-5 grid gap-3 sm:grid-cols-2">
            @csrf
            <select name="supplier_id" class="rounded-xl border-0 p-3"><option value="">Select approved supplier</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select>
            <input name="supplier_name" placeholder="Supplier / company" required class="rounded-xl border-0 p-3">
            <input name="flower_type" placeholder="Flower type" required class="rounded-xl border-0 p-3">
            <input name="quantity" type="number" min="1" placeholder="Quantity" required class="rounded-xl border-0 p-3">
            <input name="requested_date" type="date" required class="rounded-xl border-0 p-3">
            <button class="rounded-xl bg-[#285443] px-4 py-3 font-semibold text-white transition hover:bg-[#1f4435] sm:col-span-2">Send purchase order</button>
        </form>
    </section>

    <section class="rounded-[28px] bg-[#edf7f5]/90 p-5 shadow-[0_12px_30px_rgba(36,73,64,0.1)] sm:p-7">
        <div>
            <h2 class="font-serif text-2xl font-bold">Purchase order history</h2>
            <p class="mt-1 text-sm text-[#607a72]">Monitor supplier requests and update their current status.</p>
        </div>
        <div class="mt-5 space-y-3">
            @forelse ($purchaseOrders as $purchaseOrder)
                <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white/60 p-4">
                    <div>
                        <p class="font-semibold">{{ $purchaseOrder->supplier_name }}</p>
                        <p class="mt-1 text-sm text-[#607a72]">{{ $purchaseOrder->items->map(fn ($item) => $item->quantity.' '.$item->flower_type)->join(', ') }} · by {{ $purchaseOrder->creator->name }}</p>
                        <p class="mt-1 text-xs uppercase tracking-wider text-[#607a72]">Requested {{ $purchaseOrder->requested_date->format('d M Y') }}</p>
                    </div>
                    <form method="POST" action="{{ route('admin.purchase-orders.status', $purchaseOrder) }}">@csrf @method('PATCH')<select name="status" onchange="this.form.submit()" class="rounded-lg border-0 p-2"><option value="sent" @selected($purchaseOrder->status === 'sent')>Sent</option><option value="fulfilled" @selected($purchaseOrder->status === 'fulfilled')>Fulfilled</option><option value="cancelled" @selected($purchaseOrder->status === 'cancelled')>Cancelled</option></select></form>
                </div>
            @empty
                <p class="rounded-2xl bg-white/60 p-6 text-sm text-[#607a72]">No purchase orders have been created yet.</p>
            @endforelse
        </div>
    </section>
</x-admin.layout>
