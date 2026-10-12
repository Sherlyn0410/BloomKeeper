<x-admin.layout
    title="Purchase Orders"
    description="Create supplier requests and track which flower deliveries are sent, fulfilled, or cancelled."
>
    <section class="bk-card">
        <div>
            <h2 class="bk-card-title">Create purchase order</h2>
            <p class="mt-1 text-sm text-[#607a72]">Send a request to an approved supplier or record an external supplier.</p>
        </div>
        <form method="POST" action="{{ route('admin.purchase-orders.store') }}" class="mt-5 grid gap-3 sm:grid-cols-2">
            @csrf
            <label class="bk-label">
                <span>Approved supplier <span class="font-normal text-[#8aa39c]">(optional)</span></span>
                <select name="supplier_id" class="bk-input"><option value="">None (external supplier)</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select>
            </label>
            <label class="bk-label">
                Supplier / company
                <input name="supplier_name" required class="bk-input">
            </label>
            <label class="bk-label">
                Flower type
                <input name="flower_type" required class="bk-input">
            </label>
            <label class="bk-label">
                Quantity
                <input name="quantity" type="number" min="1" required class="bk-input">
            </label>
            <label class="bk-label">
                Requested date
                <input name="requested_date" type="date" required class="bk-input">
            </label>
            <label class="bk-label sm:col-span-2">
                <span>Notes for the supplier <span class="font-normal text-[#8aa39c]">(optional)</span></span>
                <textarea name="notes" rows="2" maxlength="1000" class="bk-input"></textarea>
            </label>
            <div class="bk-form-actions">
                <button class="bk-btn bk-btn-primary">Send purchase order</button>
            </div>
        </form>
    </section>

    <section class="bk-card">
        <div>
            <h2 class="bk-card-title">Purchase order history</h2>
            <p class="mt-1 text-sm text-[#607a72]">Monitor supplier requests and update their current status.</p>
        </div>
        <div class="mt-5 space-y-3">
            @forelse ($purchaseOrders as $purchaseOrder)
                <div class="bk-tile flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="font-semibold">{{ $purchaseOrder->supplier_name }}</p>
                        <p class="mt-1 text-sm text-[#607a72]">{{ $purchaseOrder->items->map(fn ($item) => $item->quantity.' '.$item->flower_type)->join(', ') }} · by {{ $purchaseOrder->creator->name }}</p>
                        <p class="mt-1 text-xs uppercase tracking-wider text-[#607a72]">
                            Requested {{ $purchaseOrder->requested_date->format('d M Y') }}
                            @if ($purchaseOrder->fulfilled_at)
                                · <span class="text-[#187849]">Fulfilled {{ $purchaseOrder->fulfilled_at->format('d M Y') }}</span>
                            @endif
                        </p>
                        @if ($purchaseOrder->notes)
                            <p class="mt-1 text-sm italic text-[#607a72]">{{ $purchaseOrder->notes }}</p>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('admin.purchase-orders.status', $purchaseOrder) }}">@csrf @method('PATCH')<select name="status" onchange="this.form.submit()" class="bk-input bk-input-sm text-[#285443]"><option value="sent" @selected($purchaseOrder->status === 'sent')>Sent</option><option value="fulfilled" @selected($purchaseOrder->status === 'fulfilled')>Fulfilled</option><option value="cancelled" @selected($purchaseOrder->status === 'cancelled')>Cancelled</option></select></form>
                </div>
            @empty
                <p class="bk-tile text-sm text-[#607a72]">No purchase orders have been created yet.</p>
            @endforelse
        </div>
    </section>
</x-admin.layout>
