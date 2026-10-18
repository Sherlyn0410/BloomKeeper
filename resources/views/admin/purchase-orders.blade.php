@php
    // Admins and staff share this page; links and forms stay inside the viewer's own area.
    $area = auth()->user()->can('manage-users') ? 'admin' : 'staff';
    // When an edit or receive dialog fails validation, its old input belongs to that dialog, not to the create form.
    $failedEditId = (int) old('editing_purchase_order');
    $failedReceiveId = (int) old('receiving_purchase_order');
    // A Reorder link from a low-stock alert pre-fills the create form.
    $reorder = array_filter(['supplier_id' => request('supplier'), 'flower_type' => request('flower')]);
    // "Re-order missing" on a short delivery pre-fills the same supplier, flower, and missing quantity.
    if ($followUpOf) {
        $missingQuantity = abs($followUpOf->receivedDifference());
        $reorder = [
            'supplier_id' => $followUpOf->supplier_id,
            'flower_type' => $followUpOf->items->first()?->flower_type,
            'quantity' => $missingQuantity,
            'notes' => "Follow-up to {$followUpOf->reference()} ({$missingQuantity} short)",
        ];
    }
    $createOld = fn (string $key): mixed => $failedEditId || $failedReceiveId ? null : old($key, $reorder[$key] ?? null);
@endphp

<x-workspace.layout
    title="Purchase Orders"
    description="Create supplier requests and track which flower deliveries are sent, fulfilled, or cancelled."
>
    <section class="bk-card scroll-mt-4" id="create-purchase-order">
        <div>
            <h2 class="bk-card-title">Create purchase order</h2>
            <p class="mt-1 text-sm text-[#607a72]">Send a request to an approved supplier. They see it in their account and mark it fulfilled.</p>
        </div>
        @if ($followUpOf && ! $errors->any())
            <p class="mt-4 rounded-xl bg-[#fff0d5] px-3 py-2 text-sm text-[#a76513]">Re-ordering the {{ $reorder['quantity'] }} {{ $reorder['flower_type'] }} missing from {{ $followUpOf->reference() }}. Please review the details and send.</p>
        @elseif (isset($reorder['flower_type']) && ! $errors->any())
            <p class="mt-4 rounded-xl bg-[#fff0d5] px-3 py-2 text-sm text-[#a76513]">{{ $reorder['flower_type'] }} stock is low. {{ $suppliers->contains('id', (int) ($reorder['supplier_id'] ?? 0)) ? 'The previous supplier has been selected. Please enter the quantity required.' : 'Please select a supplier and enter the quantity required.' }}</p>
        @endif
        @if ($suppliers->isEmpty())
            <p class="mt-4 rounded-xl bg-[#fff0d5] px-3 py-2 text-sm text-[#a76513]">No approved suppliers yet. @can('manage-users') Approve one under <a href="{{ route('admin.users', ['role' => 'supplier']) }}" class="font-semibold underline">Manage Users → Suppliers</a>. @else Ask an administrator to approve a supplier account. @endcan</p>
        @endif
        <form method="POST" action="{{ route($area.'.purchase-orders.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-[2fr_2fr_1fr_1.5fr]">
            @csrf
            <label class="bk-label">
                Supplier
                <select name="supplier_id" required class="bk-input">
                    <option value="">Select a supplier</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((string) $createOld('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="bk-label">
                Flower type
                <input name="flower_type" value="{{ $createOld('flower_type') }}" required class="bk-input">
            </label>
            <label class="bk-label">
                Quantity
                <input name="quantity" value="{{ $createOld('quantity') }}" type="number" min="1" required class="bk-input">
            </label>
            <label class="bk-label">
                Requested date
                <input name="requested_date" value="{{ $createOld('requested_date') }}" type="date" min="{{ today()->format('Y-m-d') }}" required class="bk-input">
            </label>
            <div class="flex flex-col gap-3 sm:col-span-2 sm:flex-row sm:items-end lg:col-span-4">
                <label class="bk-label flex-1">
                    <span>Notes for the supplier <span class="font-normal text-[#8aa39c]">(optional)</span></span>
                    <input name="notes" value="{{ $createOld('notes') }}" maxlength="1000" placeholder="e.g. Deep red, long stems" class="bk-input">
                </label>
                <button class="bk-btn bk-btn-primary shrink-0">Send purchase order</button>
            </div>
        </form>
    </section>

    <section data-scroll-on-filter="search,date,status" class="scroll-mt-4 bk-card">
        @php
            $activeStatus = in_array(request('status'), \App\Models\PurchaseOrder::STATUSES, true) ? request('status') : '';
            $isFiltered = request()->filled('search') || request()->filled('date');
            $statusStyles = [
                'sent' => ['label' => 'Sent', 'badge' => 'bg-[#fff0d5] text-[#a76513]', 'dot' => 'bg-[#a76513]'],
                'fulfilled' => ['label' => 'Fulfilled', 'badge' => 'bg-[#d7f0df] text-[#187849]', 'dot' => 'bg-[#187849]'],
                'declined' => ['label' => 'Declined', 'badge' => 'bg-[#f5dcd7] text-[#b84331]', 'dot' => 'bg-[#b84331]'],
                'cancelled' => ['label' => 'Cancelled', 'badge' => 'bg-[#dce9e6] text-[#49665f]', 'dot' => 'bg-[#8aa39c]'],
            ];
        @endphp
        <div>
            <div>
                <h2 class="bk-card-title">Purchase order history</h2>
                <p class="mt-1 text-sm text-[#607a72]">Search by PO number, supplier, or flower, and filter by requested date or status.</p>
            </div>
            <form class="mt-4 flex flex-wrap items-center gap-2">
                <input type="hidden" name="status" value="{{ $activeStatus }}">
                <label class="relative w-full sm:w-auto sm:min-w-64 sm:flex-1 lg:max-w-sm">
                    <span class="sr-only">PO number, supplier, or flower</span>
                    <svg class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-[#8aa39c]" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.45 4.39l3.08 3.08a.75.75 0 1 1-1.06 1.06l-3.08-3.08A7 7 0 0 1 2 9Z" clip-rule="evenodd" /></svg>
                    <input name="search" value="{{ request('search') }}" placeholder="PO no., supplier, or flower" class="bk-input w-full pl-9">
                </label>
                <label>
                    <span class="sr-only">Requested date</span>
                    <input name="date" value="{{ request('date') }}" type="date" class="bk-input">
                </label>
                <button class="bk-btn bk-btn-primary">Filter</button>
                @if ($isFiltered)
                    <a href="{{ route($area.'.purchase-orders', array_filter(['status' => $activeStatus])) }}" class="bk-btn bk-btn-ghost">Clear</a>
                @endif
            </form>
        </div>

        <nav class="mt-5 flex flex-wrap gap-2" aria-label="Filter by status">
            @foreach (['' => 'All', ...collect($statusStyles)->map(fn ($style) => $style['label'])->all()] as $status => $label)
                @php
                    $isActive = $activeStatus === $status;
                    $count = $status === '' ? $statusCounts->sum() : ($statusCounts[$status] ?? 0);
                @endphp
                <a
                    href="{{ route($area.'.purchase-orders', array_filter([...request()->only(['search', 'date']), 'status' => $status])) }}"
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
            @forelse ($purchaseOrders as $purchaseOrder)
                @php
                    $statusStyle = $statusStyles[$purchaseOrder->status] ?? ['label' => ucfirst($purchaseOrder->status), 'badge' => 'bg-[#dce9e6] text-[#49665f]'];
                    $receivedBatch = $purchaseOrder->receivedBatch;
                    $receivedDifference = $purchaseOrder->receivedDifference();
                @endphp
                <article @class(['bk-tile', 'opacity-70' => $purchaseOrder->status === 'cancelled'])>
                    {{-- Header: reference and supplier, what was ordered, and the status on the right --}}
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold tracking-wide text-[#8aa39c]">{{ $purchaseOrder->reference() }} · {{ $purchaseOrder->supplier_name }}</p>
                            <h3 class="mt-0.5 text-lg font-semibold text-[#1c2e28]">{{ $purchaseOrder->items->map(fn ($item) => $item->quantity.' × '.$item->flower_type)->join(', ') }}</h3>
                        </div>
                        <div class="flex shrink-0 flex-wrap justify-end gap-1.5">
                            @if ($receivedDifference)
                                <span @class(['rounded-full px-2.5 py-0.5 text-xs font-semibold', 'bg-[#f5dcd7] text-[#b84331]' => $receivedDifference < 0, 'bg-[#edf7f5] text-[#285443]' => $receivedDifference > 0])>
                                    {{ abs($receivedDifference) }} {{ $receivedDifference < 0 ? 'short' : 'extra' }}
                                </span>
                            @endif
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusStyle['badge'] }}">{{ $statusStyle['label'] }}</span>
                        </div>
                    </div>

                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-4">
                        <div>
                            <dt class="text-xs text-[#8aa39c]">Requested date</dt>
                            <dd class="font-medium text-[#1c2e28]">{{ $purchaseOrder->requested_date->format('d M Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[#8aa39c]">Created</dt>
                            <dd class="font-medium text-[#1c2e28]">{{ $purchaseOrder->created_at->timezone('Asia/Kuala_Lumpur')->format('d M Y') }} <span class="font-normal text-[#607a72]">by {{ $purchaseOrder->creator->name }}</span></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[#8aa39c]">Fulfilled</dt>
                            <dd class="font-medium text-[#1c2e28]">{{ $purchaseOrder->fulfilled_at?->timezone('Asia/Kuala_Lumpur')->format('d M Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[#8aa39c]">Received into stock</dt>
                            <dd class="font-medium text-[#1c2e28]">{{ $receivedBatch ? $purchaseOrder->received_quantity.' of '.$purchaseOrder->orderedQuantity().' on '.$receivedBatch->date_received->format('d M Y') : '—' }}</dd>
                        </div>
                    </dl>

                    @if ($purchaseOrder->notes)
                        <p class="mt-3 rounded-lg bg-[#fdf6ea] px-3 py-2 text-sm text-[#7a5a2b]"><span class="font-semibold">Note to supplier:</span> {{ $purchaseOrder->notes }}</p>
                    @endif
                    @if ($purchaseOrder->supplier_note)
                        <p @class(['mt-2 rounded-lg px-3 py-2 text-sm', 'bg-[#fbeae7] text-[#b84331]' => $purchaseOrder->status === 'declined', 'bg-[#edf7f5] text-[#285443]' => $purchaseOrder->status !== 'declined'])>
                            <span class="font-semibold">Supplier {{ $purchaseOrder->status === 'declined' ? 'declined' : 'replied' }}:</span> {{ $purchaseOrder->supplier_note }}
                        </p>
                    @endif

                    {{-- Footer: what happens next on the left, actions on the right --}}
                    <div class="mt-4 flex flex-col gap-3 border-t border-[#e3eeeb] pt-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs">
                            @if ($receivedBatch)
                                <span @class(['font-semibold', 'text-[#b84331]' => $receivedDifference < 0, 'text-[#187849]' => $receivedDifference >= 0])>
                                    Received into stock: {{ $purchaseOrder->received_quantity }} {{ $receivedBatch->flower_type }}{{ $receivedDifference ? ' ('.abs($receivedDifference).' '.($receivedDifference < 0 ? 'short' : 'extra').')' : '' }}.
                                </span>
                                <a href="{{ route($area.'.inventory', ['search' => $receivedBatch->flower_type]) }}" class="ml-1 font-semibold text-[#285443] underline underline-offset-2">View in inventory</a>
                            @else
                                <span @class(['font-semibold text-[#a76513]' => $purchaseOrder->status === 'fulfilled', 'text-[#607a72]' => $purchaseOrder->status !== 'fulfilled'])>
                                    {{ match ($purchaseOrder->status) {
                                        'sent' => 'Waiting for the supplier to fulfil or decline.',
                                        'fulfilled' => 'Delivered by the supplier. Check what arrived, then receive it into stock.',
                                        'declined' => 'Declined by the supplier. Edit the order or re-send it.',
                                        default => 'This purchase order was cancelled.',
                                    } }}
                                </span>
                            @endif
                        </p>
                        <div class="flex flex-wrap items-center gap-2 sm:ml-auto sm:justify-end">
                            @can('edit-purchase-order', $purchaseOrder)
                                <button type="button" onclick="document.getElementById('edit-po-{{ $purchaseOrder->id }}').showModal()" class="bk-btn bk-btn-secondary bk-btn-sm h-8">
                                    <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.59 3.59a2 2 0 0 1 2.82 2.82l-8.6 8.6a2 2 0 0 1-.9.52l-3.12.89a.5.5 0 0 1-.62-.62l.89-3.12a2 2 0 0 1 .52-.9l8.6-8.6Z" /></svg>
                                    Edit
                                </button>
                            @endcan
                            @can('receive-purchase-order', $purchaseOrder)
                                <button type="button" onclick="document.getElementById('receive-po-{{ $purchaseOrder->id }}').showModal()" class="bk-btn bk-btn-primary bk-btn-sm h-8">
                                    <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z" /><path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z" /></svg>
                                    Receive into stock
                                </button>
                            @elseif ($purchaseOrder->isLocked())
                                @if ($receivedDifference < 0)
                                    <a href="{{ route($area.'.purchase-orders', ['follow_up' => $purchaseOrder->id]) }}#create-purchase-order" class="bk-btn bk-btn-accent bk-btn-sm h-8">Re-order missing {{ abs($receivedDifference) }}</a>
                                @endif
                                <span class="inline-flex h-8 shrink-0 items-center gap-1 text-xs font-semibold text-[#8aa39c]" title="Fulfilled orders have been delivered and can't be changed">
                                    <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" /></svg>
                                    Locked
                                </span>
                            @elseif (auth()->user()->can('update-purchase-order', $purchaseOrder))
                                <form method="POST" action="{{ route($area.'.purchase-orders.status', $purchaseOrder) }}">
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
                    </div>
                    @can('receive-purchase-order', $purchaseOrder)
                        @php
                            $isFailedReceive = $failedReceiveId === $purchaseOrder->id;
                            $orderedItem = $purchaseOrder->items->first();
                            $lastBatch = $latestBatches[strtolower((string) $orderedItem?->flower_type)] ?? null;
                            $receiveValue = fn (string $key, mixed $default): mixed => $isFailedReceive ? old($key) : $default;
                        @endphp
                        <dialog
                            id="receive-po-{{ $purchaseOrder->id }}"
                            aria-labelledby="receive-po-title-{{ $purchaseOrder->id }}"
                            @if ($isFailedReceive) data-open-on-load @endif
                            class="fixed left-1/2 top-1/2 m-0 max-h-[calc(100vh-2rem)] w-[calc(100%-2rem)] max-w-lg -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-[24px] bg-white p-0 backdrop:bg-[#1c2e28]/40"
                        >
                            <form method="POST" action="{{ route($area.'.purchase-orders.receive', $purchaseOrder) }}" class="max-h-[calc(100vh-2rem)] overflow-y-auto p-6 sm:p-8">
                                @csrf
                                <input type="hidden" name="receiving_purchase_order" value="{{ $purchaseOrder->id }}">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 id="receive-po-title-{{ $purchaseOrder->id }}" class="font-serif text-xl font-bold text-[#1c2e28]">Receive {{ $purchaseOrder->reference() }}</h3>
                                        <p class="mt-1 text-sm text-[#607a72]">Adds a new stock batch from {{ $purchaseOrder->supplier_name }}. Enter what actually arrived.</p>
                                    </div>
                                    <button type="button" aria-label="Close receive dialog" class="cursor-pointer rounded-full p-1.5 text-[#607a72] hover:bg-[#edf7f5]" onclick="this.closest('dialog').close()">&#10005;</button>
                                </div>
                                @if ($isFailedReceive && $errors->any())
                                    <ul class="mt-4 list-inside list-disc rounded-xl bg-[#f5dcd7] px-4 py-2 text-sm text-[#b84331]">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if ($lastBatch)
                                    <p class="mt-4 rounded-xl bg-[#edf7f5] px-3 py-2 text-xs text-[#285443]">Prices, shelf life, and threshold are copied from the last {{ $lastBatch->flower_type }} batch (received {{ $lastBatch->date_received->format('d M Y') }}).</p>
                                @else
                                    <p class="mt-4 rounded-xl bg-[#fff0d5] px-3 py-2 text-xs text-[#a76513]">This is the first {{ $orderedItem?->flower_type }} delivery, so enter its prices and shelf life.</p>
                                @endif
                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <label class="bk-label">
                                        Flower type
                                        <input name="flower_type" value="{{ $receiveValue('flower_type', $orderedItem?->flower_type) }}" required class="bk-input">
                                    </label>
                                    <label class="bk-label">
                                        <span>Quantity received <span class="font-normal text-[#8aa39c]">({{ $orderedItem?->quantity }} ordered)</span></span>
                                        <input name="quantity" value="{{ $receiveValue('quantity', $orderedItem?->quantity) }}" type="number" min="1" required class="bk-input">
                                    </label>
                                    <label class="bk-label">
                                        Selling price (RM)
                                        <input name="unit_price" value="{{ $receiveValue('unit_price', $lastBatch?->unit_price) }}" type="number" min="0" step="0.01" required class="bk-input">
                                    </label>
                                    <label class="bk-label">
                                        Cost price (RM)
                                        <input name="cost_price" value="{{ $receiveValue('cost_price', $lastBatch?->cost_price) }}" type="number" min="0" step="0.01" required class="bk-input">
                                    </label>
                                    <label class="bk-label">
                                        Date received
                                        <input name="date_received" value="{{ $receiveValue('date_received', today()->format('Y-m-d')) }}" type="date" max="{{ today()->format('Y-m-d') }}" required class="bk-input">
                                    </label>
                                    <label class="bk-label">
                                        Shelf life (days)
                                        <input name="shelf_life_days" value="{{ $receiveValue('shelf_life_days', $lastBatch?->shelf_life_days) }}" type="number" min="1" required class="bk-input">
                                    </label>
                                    <label class="bk-label">
                                        Low-stock threshold
                                        <input name="low_stock_threshold" value="{{ $receiveValue('low_stock_threshold', $lastBatch?->low_stock_threshold) }}" type="number" min="0" required class="bk-input">
                                    </label>
                                    <label class="bk-label sm:col-span-2">
                                        <span>Notes <span class="font-normal text-[#8aa39c]">(optional)</span></span>
                                        <input name="notes" value="{{ $receiveValue('notes', null) }}" maxlength="1000" placeholder="e.g. 2 stems short" class="bk-input">
                                    </label>
                                </div>
                                <p class="mt-3 text-xs text-[#607a72]">If some flowers arrived with a problem, receive them all, then send those back from Stock returns.</p>
                                <div class="mt-6 flex justify-end gap-2">
                                    <button type="button" class="bk-btn bk-btn-ghost" onclick="this.closest('dialog').close()">Cancel</button>
                                    <button class="bk-btn bk-btn-primary">Add to stock</button>
                                </div>
                            </form>
                        </dialog>
                    @endcan
                    @can('edit-purchase-order', $purchaseOrder)
                        @php
                            $isFailedEdit = $failedEditId === $purchaseOrder->id;
                            $editValue = fn (string $key, mixed $current): mixed => $isFailedEdit ? old($key) : $current;
                            $firstItem = $purchaseOrder->items->first();
                        @endphp
                        <dialog
                            id="edit-po-{{ $purchaseOrder->id }}"
                            aria-labelledby="edit-po-title-{{ $purchaseOrder->id }}"
                            @if ($isFailedEdit) data-open-on-load @endif
                            class="fixed left-1/2 top-1/2 m-0 max-h-[calc(100vh-2rem)] w-[calc(100%-2rem)] max-w-lg -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-[24px] bg-white p-0 backdrop:bg-[#1c2e28]/40"
                        >
                            <form method="POST" action="{{ route('admin.purchase-orders.update', $purchaseOrder) }}" class="max-h-[calc(100vh-2rem)] overflow-y-auto p-6 sm:p-8">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="editing_purchase_order" value="{{ $purchaseOrder->id }}">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 id="edit-po-title-{{ $purchaseOrder->id }}" class="font-serif text-xl font-bold text-[#1c2e28]">Edit {{ $purchaseOrder->reference() }}</h3>
                                        <p class="mt-1 text-sm text-[#607a72]">
                                            {{ $purchaseOrder->status === 'declined' ? 'Saving sends this order to the supplier again.' : 'The supplier sees the updated details straight away.' }}
                                        </p>
                                    </div>
                                    <button type="button" aria-label="Close edit dialog" class="cursor-pointer rounded-full p-1.5 text-[#607a72] hover:bg-[#edf7f5]" onclick="this.closest('dialog').close()">&#10005;</button>
                                </div>
                                @if ($isFailedEdit && $errors->any())
                                    <ul class="mt-4 list-inside list-disc rounded-xl bg-[#f5dcd7] px-4 py-2 text-sm text-[#b84331]">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                    <label class="bk-label sm:col-span-2">
                                        Supplier
                                        <select name="supplier_id" required class="bk-input">
                                            @foreach ($suppliers as $supplier)
                                                <option value="{{ $supplier->id }}" @selected((string) $editValue('supplier_id', $purchaseOrder->supplier_id) === (string) $supplier->id)>{{ $supplier->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="bk-label">
                                        Flower type
                                        <input name="flower_type" value="{{ $editValue('flower_type', $firstItem?->flower_type) }}" required class="bk-input">
                                    </label>
                                    <label class="bk-label">
                                        Quantity
                                        <input name="quantity" value="{{ $editValue('quantity', $firstItem?->quantity) }}" type="number" min="1" required class="bk-input">
                                    </label>
                                    <label class="bk-label sm:col-span-2">
                                        Requested date
                                        <input name="requested_date" value="{{ $editValue('requested_date', $purchaseOrder->requested_date->format('Y-m-d')) }}" type="date" min="{{ ($purchaseOrder->requested_date->lt(today()) ? $purchaseOrder->requested_date : today())->format('Y-m-d') }}" required class="bk-input">
                                    </label>
                                    <label class="bk-label sm:col-span-2">
                                        <span>Notes for the supplier <span class="font-normal text-[#8aa39c]">(optional)</span></span>
                                        <textarea name="notes" rows="2" maxlength="1000" class="bk-input">{{ $editValue('notes', $purchaseOrder->notes) }}</textarea>
                                    </label>
                                </div>
                                <div class="mt-6 flex justify-end gap-2">
                                    <button type="button" class="bk-btn bk-btn-ghost" onclick="this.closest('dialog').close()">Cancel</button>
                                    <button class="bk-btn bk-btn-primary">{{ $purchaseOrder->status === 'declined' ? 'Save and re-send' : 'Save changes' }}</button>
                                </div>
                            </form>
                        </dialog>
                    @endcan
                </article>
            @empty
                <p class="bk-tile text-sm text-[#607a72]">{{ $isFiltered || $activeStatus !== '' ? 'No purchase orders match the current filters.' : 'No purchase orders have been created yet.' }}</p>
            @endforelse
        </div>
    </section>

    <script>
        document.querySelector('dialog[data-open-on-load]')?.showModal();
    </script>
</x-workspace.layout>
