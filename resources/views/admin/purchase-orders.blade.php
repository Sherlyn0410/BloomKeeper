@php
    // When an edit fails validation, its old input belongs to that edit dialog, not to the create form.
    $failedEditId = (int) old('editing_purchase_order');
    $createOld = fn (string $key): mixed => $failedEditId ? null : old($key);
@endphp

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

    <section class="bk-card">
        @php
            $activeStatus = in_array(request('status'), \App\Models\PurchaseOrder::STATUSES, true) ? request('status') : '';
            $isFiltered = request()->filled('search') || request()->filled('date');
            $statusDots = ['sent' => 'bg-[#a76513]', 'fulfilled' => 'bg-[#187849]', 'declined' => 'bg-[#b84331]', 'cancelled' => 'bg-[#8aa39c]'];
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
                    <a href="{{ route('admin.purchase-orders', array_filter(['status' => $activeStatus])) }}" class="bk-btn bk-btn-ghost">Clear</a>
                @endif
            </form>
        </div>

        <nav class="mt-5 flex flex-wrap gap-2" aria-label="Filter by status">
            @foreach (['' => 'All', 'sent' => 'Sent', 'fulfilled' => 'Fulfilled', 'declined' => 'Declined', 'cancelled' => 'Cancelled'] as $status => $label)
                @php
                    $isActive = $activeStatus === $status;
                    $count = $status === '' ? $statusCounts->sum() : ($statusCounts[$status] ?? 0);
                @endphp
                <a
                    href="{{ route('admin.purchase-orders', array_filter([...request()->only(['search', 'date']), 'status' => $status])) }}"
                    @if ($isActive) aria-current="page" @endif
                    @class([
                        'flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold transition',
                        'bg-[#285443] text-white' => $isActive,
                        'bg-white/70 text-[#49665f] hover:bg-white' => ! $isActive,
                    ])
                >
                    @if ($status !== '')
                        <span @class(['size-2 rounded-full', $isActive ? 'bg-white' : $statusDots[$status]])></span>
                    @endif
                    {{ $label }}
                    <span @class(['rounded-full px-2 text-xs', 'bg-white/20' => $isActive, 'bg-[#dce9e6]' => ! $isActive])>{{ $count }}</span>
                </a>
            @endforeach
        </nav>

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
                        <div class="flex shrink-0 items-center gap-2">
                        @if ($purchaseOrder->canBeEdited())
                            <button type="button" onclick="document.getElementById('edit-po-{{ $purchaseOrder->id }}').showModal()" class="bk-btn bk-btn-secondary bk-btn-sm h-8">
                                <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.59 3.59a2 2 0 0 1 2.82 2.82l-8.6 8.6a2 2 0 0 1-.9.52l-3.12.89a.5.5 0 0 1-.62-.62l.89-3.12a2 2 0 0 1 .52-.9l8.6-8.6Z" /></svg>
                                Edit
                            </button>
                        @endif
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
                    </div>

                    @if ($purchaseOrder->notes)
                        <p class="rounded-lg bg-[#fdf6ea] px-3 py-1.5 text-xs text-[#7a5a2b]"><span class="font-semibold">Your note:</span> {{ $purchaseOrder->notes }}</p>
                    @endif
                    @if ($purchaseOrder->supplier_note)
                        <p @class(['rounded-lg px-3 py-1.5 text-xs', 'bg-[#fbeae7] text-[#b84331]' => $purchaseOrder->status === 'declined', 'bg-[#edf7f5] text-[#285443]' => $purchaseOrder->status !== 'declined'])>
                            <span class="font-semibold">Supplier {{ $purchaseOrder->status === 'declined' ? 'declined' : 'replied' }}:</span> {{ $purchaseOrder->supplier_note }}
                        </p>
                    @endif
                    @if ($purchaseOrder->canBeEdited())
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
                    @endif
                </article>
            @empty
                <p class="bk-tile text-sm text-[#607a72] lg:col-span-2">{{ $isFiltered || $activeStatus !== '' ? 'No purchase orders match the current filters.' : 'No purchase orders have been created yet.' }}</p>
            @endforelse
        </div>
    </section>

    <script>
        document.querySelector('dialog[data-open-on-load]')?.showModal();
    </script>
</x-admin.layout>
