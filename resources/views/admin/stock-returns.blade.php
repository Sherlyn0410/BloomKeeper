@php
    // Admins and staff share this page; links and forms stay inside the viewer's own area.
    $area = auth()->user()->can('manage-users') ? 'admin' : 'staff';
    // When an edit fails validation, its old input belongs to that edit dialog, not to the create form.
    $failedEditId = (int) old('editing_stock_return');
    $createOld = fn (string $key): mixed => $failedEditId ? null : old($key);
    $statusStyles = [
        'requested' => ['label' => 'Pending', 'badge' => 'bg-[#fff0d5] text-[#a76513]', 'dot' => 'bg-[#a76513]'],
        'accepted' => ['label' => 'Accepted', 'badge' => 'bg-[#d7f0df] text-[#187849]', 'dot' => 'bg-[#187849]'],
        'rejected' => ['label' => 'Rejected', 'badge' => 'bg-[#f5dcd7] text-[#b84331]', 'dot' => 'bg-[#b84331]'],
        'cancelled' => ['label' => 'Cancelled', 'badge' => 'bg-[#dce9e6] text-[#49665f]', 'dot' => 'bg-[#8aa39c]'],
    ];
@endphp

<x-workspace.layout
    title="Stock returns"
    description="Return defective stock to suppliers. Returned stock is deducted immediately. Rejected returns are recorded as wastage."
>
    <section class="bk-card">
        <div>
            <h2 class="bk-card-title">Create stock return</h2>
            <p class="mt-1 text-sm text-[#607a72]">Stock may be returned within {{ \App\Models\StockReturn::RETURN_WINDOW_DAYS }} days of receipt.</p>
        </div>
        @if ($returnableBatches->isEmpty())
            <p class="mt-4 rounded-xl bg-[#fff0d5] px-3 py-2 text-sm text-[#a76513]">No stock is eligible for return. Only stock received within the last {{ \App\Models\StockReturn::RETURN_WINDOW_DAYS }} days from a recorded supplier can be returned. Record other defective stock as wastage.</p>
        @endif
        <form method="POST" action="{{ route($area.'.stock-returns.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-[3fr_1fr_1.5fr]">
            @csrf
            <label class="bk-label sm:col-span-2 lg:col-span-1">
                Stock batch
                <select name="inventory_item_id" required class="bk-input">
                    <option value="">Select a batch</option>
                    @foreach ($returnableBatches as $batch)
                        <option value="{{ $batch->id }}" @selected((int) ($createOld('inventory_item_id') ?? $selectedItemId) === $batch->id)>
                            {{ $batch->flower_type }} · {{ $batch->supplier->name }} · received {{ $batch->date_received->format('d M') }} · {{ $batch->quantity }} in stock
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="bk-label">
                Quantity
                <input name="quantity" value="{{ $createOld('quantity') }}" type="number" min="1" required class="bk-input">
            </label>
            <label class="bk-label">
                Reason
                <select name="reason" required class="bk-input">
                    @foreach (\App\Models\StockReturn::REASONS as $value => $label)
                        <option value="{{ $value }}" @selected($createOld('reason') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex flex-col gap-3 sm:col-span-2 sm:flex-row sm:items-end lg:col-span-3">
                <label class="bk-label flex-1">
                    <span>Notes for the supplier <span class="font-normal text-[#8aa39c]">(optional)</span></span>
                    <input name="notes" value="{{ $createOld('notes') }}" maxlength="1000" placeholder="e.g. Petals bruised on arrival" class="bk-input">
                </label>
                <button class="bk-btn bk-btn-primary shrink-0">Submit return</button>
            </div>
        </form>
    </section>

    <section data-scroll-on-filter="status" class="scroll-mt-4 bk-card">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="bk-card-title">Return history</h2>
                <p class="mt-1 text-sm text-[#607a72]">Track the status of each return.</p>
            </div>
            <div class="rounded-xl bg-[#edf7f5] px-3 py-2 text-right">
                <p class="text-xs text-[#607a72]">Credit from accepted returns</p>
                <p class="font-bold text-[#187849]">RM {{ number_format($acceptedValue, 2) }}</p>
            </div>
        </div>

        <nav class="mt-5 flex flex-wrap gap-2" aria-label="Filter by status">
            @foreach (['' => 'All', ...collect($statusStyles)->map(fn ($style) => $style['label'])->all()] as $status => $label)
                @php
                    $isActive = (string) $activeStatus === (string) $status;
                    $count = $status === '' ? $statusCounts->sum() : ($statusCounts[$status] ?? 0);
                @endphp
                <a
                    href="{{ route($area.'.stock-returns', array_filter(['status' => $status])) }}"
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
            @forelse ($stockReturns as $stockReturn)
                <article @class(['bk-tile', 'opacity-70' => $stockReturn->status === 'cancelled'])>
                    {{-- Header: reference and supplier, with the status on the right --}}
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold tracking-wide text-[#8aa39c]">{{ $stockReturn->reference() }} · {{ $stockReturn->supplier->name }}</p>
                            <h3 class="mt-0.5 text-lg font-semibold text-[#1c2e28]">{{ $stockReturn->quantity }} × {{ $stockReturn->flower_type }}</h3>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusStyles[$stockReturn->status]['badge'] ?? '' }}">{{ $statusStyles[$stockReturn->status]['label'] ?? ucfirst($stockReturn->status) }}</span>
                    </div>

                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-4">
                        <div>
                            <dt class="text-xs text-[#8aa39c]">Reason</dt>
                            <dd class="font-medium text-[#1c2e28]">{{ $stockReturn->reasonLabel() }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[#8aa39c]">Value at cost</dt>
                            <dd class="font-medium text-[#1c2e28]">RM {{ number_format($stockReturn->value(), 2) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[#8aa39c]">Returned</dt>
                            <dd class="font-medium text-[#1c2e28]">{{ $stockReturn->created_at->timezone('Asia/Kuala_Lumpur')->format('d M Y') }} <span class="font-normal text-[#607a72]">by {{ $stockReturn->creator->name }}</span></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[#8aa39c]">{{ $stockReturn->responded_at ? 'Responded' : 'Received' }}</dt>
                            <dd class="font-medium text-[#1c2e28]">
                                @if ($stockReturn->responded_at)
                                    {{ $stockReturn->responded_at->timezone('Asia/Kuala_Lumpur')->format('d M Y') }}
                                @else
                                    {{ $stockReturn->inventoryItem?->date_received->format('d M Y') ?? '—' }}
                                @endif
                            </dd>
                        </div>
                    </dl>

                    @if ($stockReturn->notes)
                        <p class="mt-3 rounded-lg bg-[#fdf6ea] px-3 py-2 text-sm text-[#7a5a2b]"><span class="font-semibold">Notes:</span> {{ $stockReturn->notes }}</p>
                    @endif
                    @if ($stockReturn->supplier_note)
                        <p @class(['mt-2 rounded-lg px-3 py-2 text-sm', 'bg-[#fbeae7] text-[#b84331]' => $stockReturn->status === 'rejected', 'bg-[#edf7f5] text-[#285443]' => $stockReturn->status !== 'rejected'])>
                            <span class="font-semibold">Supplier response:</span> {{ $stockReturn->supplier_note }}
                        </p>
                    @endif

                    @if ($stockReturn->status === 'rejected' || auth()->user()->can('cancel-stock-return', $stockReturn) || auth()->user()->can('edit-stock-return', $stockReturn))
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-[#e3eeeb] pt-3">
                            @if ($stockReturn->status === 'rejected')
                                <p class="text-xs font-semibold text-[#a76513]">Rejected by the supplier. Recorded as wastage.</p>
                            @else
                                <p class="text-xs text-[#607a72]">Awaiting response from {{ $stockReturn->supplier->name }}.</p>
                            @endif
                            <div class="ml-auto flex flex-wrap items-center gap-2">
                            @can('edit-stock-return', $stockReturn)
                                <button type="button" onclick="document.getElementById('edit-return-{{ $stockReturn->id }}').showModal()" class="bk-btn bk-btn-secondary bk-btn-sm h-8">
                                    <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.59 3.59a2 2 0 0 1 2.82 2.82l-8.6 8.6a2 2 0 0 1-.9.52l-3.12.89a.5.5 0 0 1-.62-.62l.89-3.12a2 2 0 0 1 .52-.9l8.6-8.6Z" /></svg>
                                    Edit
                                </button>
                            @endcan
                            @can('cancel-stock-return', $stockReturn)
                                <form method="POST" action="{{ route($area.'.stock-returns.cancel', $stockReturn) }}" onsubmit="return confirm(@js("Cancel {$stockReturn->reference()}? The stock will be restored to inventory."))">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="bk-btn bk-btn-secondary bk-btn-sm h-8">Cancel return</button>
                                </form>
                            @endcan
                            </div>
                        </div>
                    @endif

                    @can('edit-stock-return', $stockReturn)
                        @php
                            $isFailedEdit = $failedEditId === $stockReturn->id;
                            $editValue = fn (string $key, mixed $current): mixed => $isFailedEdit ? old($key) : $current;
                            $availableToReturn = ($stockReturn->inventoryItem?->quantity ?? 0) + $stockReturn->quantity;
                        @endphp
                        <dialog
                            id="edit-return-{{ $stockReturn->id }}"
                            aria-labelledby="edit-return-title-{{ $stockReturn->id }}"
                            @if ($isFailedEdit) data-open-on-load @endif
                            class="fixed left-1/2 top-1/2 m-0 max-h-[calc(100vh-2rem)] w-[calc(100%-2rem)] max-w-lg -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-[24px] bg-white p-0 backdrop:bg-[#1c2e28]/40"
                        >
                            <form method="POST" action="{{ route($area.'.stock-returns.update', $stockReturn) }}" class="max-h-[calc(100vh-2rem)] overflow-y-auto p-6 sm:p-8">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="editing_stock_return" value="{{ $stockReturn->id }}">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 id="edit-return-title-{{ $stockReturn->id }}" class="font-serif text-xl font-bold text-[#1c2e28]">Edit {{ $stockReturn->reference() }}</h3>
                                        <p class="mt-1 text-sm text-[#607a72]">{{ $stockReturn->flower_type }} · {{ $stockReturn->supplier->name }}. Stock is adjusted to match the new quantity.</p>
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
                                    <label class="bk-label">
                                        <span>Quantity <span class="font-normal text-[#8aa39c]">(max {{ $availableToReturn }})</span></span>
                                        <input name="quantity" value="{{ $editValue('quantity', $stockReturn->quantity) }}" type="number" min="1" max="{{ $availableToReturn }}" required class="bk-input">
                                    </label>
                                    <label class="bk-label">
                                        Reason
                                        <select name="reason" required class="bk-input">
                                            @foreach (\App\Models\StockReturn::REASONS as $value => $label)
                                                <option value="{{ $value }}" @selected($editValue('reason', $stockReturn->reason) === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="bk-label sm:col-span-2">
                                        <span>Notes for the supplier <span class="font-normal text-[#8aa39c]">(optional)</span></span>
                                        <textarea name="notes" rows="2" maxlength="1000" class="bk-input">{{ $editValue('notes', $stockReturn->notes) }}</textarea>
                                    </label>
                                </div>
                                <div class="mt-6 flex justify-end gap-2">
                                    <button type="button" class="bk-btn bk-btn-ghost" onclick="this.closest('dialog').close()">Cancel</button>
                                    <button class="bk-btn bk-btn-primary">Save changes</button>
                                </div>
                            </form>
                        </dialog>
                    @endcan
                </article>
            @empty
                <p class="bk-tile text-sm text-[#607a72]">{{ $activeStatus ? 'No returns with this status.' : 'No stock returns have been recorded.' }}</p>
            @endforelse
        </div>
    </section>
    <script>
        document.querySelector('dialog[data-open-on-load]')?.showModal();
    </script>
</x-workspace.layout>
