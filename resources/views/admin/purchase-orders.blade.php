<x-admin.layout
    title="Purchase Orders"
    description="Create supplier requests and track which flower deliveries are sent, fulfilled, or cancelled."
>
    <section class="bk-card">
        <div>
            <h2 class="bk-card-title">Create purchase order</h2>
            <p class="mt-1 text-sm text-[#607a72]">Send a request to an approved supplier. They see it in their account and mark it fulfilled.</p>
        </div>
        @if ($suppliers->isEmpty())
            <p class="mt-4 rounded-xl bg-[#fff0d5] px-3 py-2 text-sm text-[#a76513]">No approved suppliers yet. Approve one under <a href="{{ route('admin.users', ['role' => 'supplier']) }}" class="font-semibold underline">Manage Users → Suppliers</a>.</p>
        @endif
        <form method="POST" action="{{ route('admin.purchase-orders.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-[2fr_2fr_1fr_1.5fr]">
            @csrf
            <label class="bk-label">
                Supplier
                <select name="supplier_id" required class="bk-input">
                    <option value="">Select a supplier</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="bk-label">
                Flower type
                <input name="flower_type" value="{{ old('flower_type') }}" required class="bk-input">
            </label>
            <label class="bk-label">
                Quantity
                <input name="quantity" value="{{ old('quantity') }}" type="number" min="1" required class="bk-input">
            </label>
            <label class="bk-label">
                Requested date
                <input name="requested_date" value="{{ old('requested_date') }}" type="date" min="{{ today()->format('Y-m-d') }}" required class="bk-input">
            </label>
            <div class="flex flex-col gap-3 sm:col-span-2 sm:flex-row sm:items-end lg:col-span-4">
                <label class="bk-label flex-1">
                    <span>Notes for the supplier <span class="font-normal text-[#8aa39c]">(optional)</span></span>
                    <input name="notes" value="{{ old('notes') }}" maxlength="1000" placeholder="e.g. Deep red, long stems" class="bk-input">
                </label>
                <button class="bk-btn bk-btn-primary shrink-0">Send purchase order</button>
            </div>
        </form>
    </section>

    <section class="bk-card">
        <div>
            <h2 class="bk-card-title">Purchase order history</h2>
            <p class="mt-1 text-sm text-[#607a72]">Monitor supplier requests and update their current status.</p>
        </div>
        <div class="mt-4 grid gap-3 lg:grid-cols-2">
            @forelse ($purchaseOrders as $purchaseOrder)
                @php
                    $statusBadge = [
                        'sent' => 'bg-[#fff0d5] text-[#a76513]',
                        'fulfilled' => 'bg-[#d7f0df] text-[#187849]',
                        'declined' => 'bg-[#f5dcd7] text-[#b84331]',
                        'cancelled' => 'bg-[#dce9e6] text-[#49665f]',
                    ][$purchaseOrder->status] ?? 'bg-[#dce9e6] text-[#49665f]';
                @endphp
                <article class="bk-tile flex flex-col gap-2 py-3">
                    <div class="flex items-center justify-between gap-3">
                        <p class="min-w-0 truncate">
                            <span class="text-xs font-semibold tracking-wide text-[#8aa39c]">{{ $purchaseOrder->reference() }}</span>
                            <span class="ml-1.5 font-semibold text-[#1c2e28]">{{ $purchaseOrder->supplier_name }}</span>
                        </p>
                        <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadge }}">{{ ucfirst($purchaseOrder->status) }}</span>
                    </div>

                    <div class="flex items-end justify-between gap-3">
                        <div class="min-w-0 text-sm">
                            <p class="font-medium text-[#1c2e28]">{{ $purchaseOrder->items->map(fn ($item) => $item->quantity.' × '.$item->flower_type)->join(', ') }}</p>
                            <p class="text-xs text-[#607a72]">
                                For {{ $purchaseOrder->requested_date->format('d M Y') }} · by {{ $purchaseOrder->creator->name }}
                                @if ($purchaseOrder->fulfilled_at)
                                    · <span class="text-[#187849]">fulfilled {{ $purchaseOrder->fulfilled_at->timezone('Asia/Kuala_Lumpur')->format('d M Y') }}</span>
                                @endif
                            </p>
                        </div>
                        @if ($purchaseOrder->isLocked())
                            <span class="inline-flex h-8 shrink-0 items-center gap-1 text-xs font-semibold text-[#8aa39c]" title="Fulfilled orders have been delivered and can't be changed">
                                <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" /></svg>
                                Locked
                            </span>
                        @else
                            <form method="POST" action="{{ route('admin.purchase-orders.status', $purchaseOrder) }}" class="shrink-0">
                                @csrf
                                @method('PATCH')
                                <label class="sr-only" for="po-status-{{ $purchaseOrder->id }}">Status</label>
                                <select id="po-status-{{ $purchaseOrder->id }}" name="status" onchange="this.form.submit()" class="bk-input bk-input-sm h-8 w-28 cursor-pointer py-1 text-[#285443]">
                                    @if ($purchaseOrder->status === 'declined')
                                        <option value="declined" selected disabled>Declined</option>
                                    @endif
                                    <option value="sent" @selected($purchaseOrder->status === 'sent')>{{ $purchaseOrder->status === 'declined' ? 'Re-send' : 'Sent' }}</option>
                                    <option value="cancelled" @selected($purchaseOrder->status === 'cancelled')>Cancelled</option>
                                </select>
                            </form>
                        @endif
                    </div>

                    @if ($purchaseOrder->notes)
                        <p class="rounded-lg bg-[#fdf6ea] px-3 py-1.5 text-xs text-[#7a5a2b]"><span class="font-semibold">Your note:</span> {{ $purchaseOrder->notes }}</p>
                    @endif
                    @if ($purchaseOrder->supplier_note)
                        <p @class(['rounded-lg px-3 py-1.5 text-xs', 'bg-[#fbeae7] text-[#b84331]' => $purchaseOrder->status === 'declined', 'bg-[#edf7f5] text-[#285443]' => $purchaseOrder->status !== 'declined'])>
                            <span class="font-semibold">Supplier {{ $purchaseOrder->status === 'declined' ? 'declined' : 'replied' }}:</span> {{ $purchaseOrder->supplier_note }}
                        </p>
                    @endif
                </article>
            @empty
                <p class="bk-tile text-sm text-[#607a72] lg:col-span-2">No purchase orders have been created yet.</p>
            @endforelse
        </div>
    </section>

</x-admin.layout>
