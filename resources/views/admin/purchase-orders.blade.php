<x-admin.layout
    title="Purchase Orders"
    description="Create supplier requests and track which flower deliveries are sent, fulfilled, or cancelled."
>
    <section class="bk-card">
        <div>
            <h2 class="bk-card-title">Create purchase order</h2>
            <p class="mt-1 text-sm text-[#607a72]">Send a request to one of your approved suppliers.</p>
        </div>
        <form method="POST" action="{{ route('admin.purchase-orders.store') }}" class="mt-5 grid gap-3 sm:grid-cols-2">
            @csrf
            <label class="bk-label">
                Supplier
                <select name="supplier_id" required class="bk-input">
                    <option value="">Select a supplier</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
                @if ($suppliers->isEmpty())
                    <span class="text-xs font-normal text-[#a76513]">No approved suppliers yet. Approve one under <a href="{{ route('admin.users', ['role' => 'supplier']) }}" class="underline">Manage Users → Suppliers</a>.</span>
                @else
                    <span class="text-xs font-normal text-[#8aa39c]">The supplier sees this order in their account and marks it fulfilled.</span>
                @endif
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
                <input name="requested_date" value="{{ old('requested_date') }}" type="date" min="{{ today()->format('Y-m-d') }}" required class="bk-input">
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
                @php
                    $statusBadge = [
                        'sent' => 'bg-[#fff0d5] text-[#a76513]',
                        'fulfilled' => 'bg-[#d7f0df] text-[#187849]',
                        'declined' => 'bg-[#f5dcd7] text-[#b84331]',
                        'cancelled' => 'bg-[#dce9e6] text-[#49665f]',
                    ][$purchaseOrder->status] ?? 'bg-[#dce9e6] text-[#49665f]';
                @endphp
                <article class="bk-tile">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold tracking-wide text-[#8aa39c]">{{ $purchaseOrder->reference() }}</p>
                            <h3 class="mt-0.5 truncate text-lg font-semibold text-[#1c2e28]">{{ $purchaseOrder->supplier_name }}</h3>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadge }}">{{ ucfirst($purchaseOrder->status) }}</span>
                    </div>
                    <p class="mt-2 text-sm text-[#1c2e28]">{{ $purchaseOrder->items->map(fn ($item) => $item->quantity.' × '.$item->flower_type)->join(', ') }}</p>
                    <p class="mt-1 text-sm text-[#607a72]">
                        Requested for {{ $purchaseOrder->requested_date->format('d M Y') }} · by {{ $purchaseOrder->creator->name }}
                        @if ($purchaseOrder->fulfilled_at)
                            · <span class="text-[#187849]">fulfilled {{ $purchaseOrder->fulfilled_at->timezone('Asia/Kuala_Lumpur')->format('d M Y') }}</span>
                        @endif
                    </p>
                    @if ($purchaseOrder->notes)
                        <p class="mt-2 rounded-lg bg-[#fdf6ea] px-3 py-2 text-sm text-[#7a5a2b]"><span class="font-semibold">Your note:</span> {{ $purchaseOrder->notes }}</p>
                    @endif
                    @if ($purchaseOrder->supplier_note)
                        <p @class(['mt-2 rounded-lg px-3 py-2 text-sm', 'bg-[#fbeae7] text-[#b84331]' => $purchaseOrder->status === 'declined', 'bg-[#edf7f5] text-[#285443]' => $purchaseOrder->status !== 'declined'])>
                            <span class="font-semibold">Supplier {{ $purchaseOrder->status === 'declined' ? 'declined' : 'replied' }}:</span> {{ $purchaseOrder->supplier_note }}
                        </p>
                    @endif
                    <div class="mt-3 flex items-center justify-end border-t border-[#e3eeeb] pt-3">
                        @if ($purchaseOrder->isLocked())
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-[#8aa39c]" title="Fulfilled orders have been delivered and can't be changed">
                                <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" /></svg>
                                Locked
                            </span>
                        @else
                        <form method="POST" action="{{ route('admin.purchase-orders.status', $purchaseOrder) }}" class="min-w-0 max-w-36 flex-1">
                            @csrf
                            @method('PATCH')
                            <label class="sr-only" for="po-status-{{ $purchaseOrder->id }}">Status</label>
                            <select id="po-status-{{ $purchaseOrder->id }}" name="status" onchange="this.form.submit()" class="bk-input bk-input-sm w-full text-[#285443]">
                                @if ($purchaseOrder->status === 'declined')
                                    <option value="declined" selected disabled>Declined</option>
                                @endif
                                <option value="sent" @selected($purchaseOrder->status === 'sent')>{{ $purchaseOrder->status === 'declined' ? 'Re-send' : 'Sent' }}</option>
                                <option value="cancelled" @selected($purchaseOrder->status === 'cancelled')>Cancelled</option>
                            </select>
                        </form>
                        @endif
                    </div>
                </article>
            @empty
                <p class="bk-tile text-sm text-[#607a72]">No purchase orders have been created yet.</p>
            @endforelse
        </div>
    </section>

</x-admin.layout>
