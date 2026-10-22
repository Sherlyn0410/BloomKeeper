@php
    // Admins and staff share this page; links and forms stay inside the viewer's own area.
    $area = auth()->user()->can('manage-users') ? 'admin' : 'staff';
    // Sold-out batches stay for their history but are hidden unless asked for.
    $showEmpty = request()->boolean('show_empty');
    $emptyBatchCount = $flowerGroups->sum(fn ($group) => $group['batches']->where('quantity', 0)->count());
@endphp

<x-workspace.layout
    title="Inventory"
    description="Track flower stock, monitor shelf life, and keep quantities ready for upcoming orders."
>
    <section class="bk-card">
        <div>
            <h2 class="bk-card-title">Add flower stock</h2>
            <p class="mt-1 text-sm text-[#607a72]">Record a new batch with its pricing and alert thresholds.</p>
        </div>
        <form method="POST" action="{{ route($area.'.inventory.store') }}" class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @csrf
            <label class="bk-label">
                Flower type
                <input name="flower_type" required class="bk-input">
            </label>
            <label class="bk-label">
                Quantity
                <input name="quantity" type="number" min="0" required class="bk-input">
            </label>
            <label class="bk-label">
                Selling price (RM)
                <input name="unit_price" type="number" min="0" step="0.01" required class="bk-input">
            </label>
            <label class="bk-label">
                Cost price (RM)
                <input name="cost_price" type="number" min="0" step="0.01" required class="bk-input">
            </label>
            <label class="bk-label">
                Date received
                <input name="date_received" type="date" max="{{ today()->format('Y-m-d') }}" required class="bk-input">
            </label>
            <label class="bk-label">
                Shelf life (days)
                <input name="shelf_life_days" type="number" min="1" required class="bk-input">
            </label>
            <label class="bk-label">
                Low stock threshold
                <input name="low_stock_threshold" type="number" min="0" required class="bk-input">
            </label>
            <label class="bk-label">
                <span>Supplier <span class="font-normal text-[#8aa39c]">(optional)</span></span>
                <select name="supplier_id" class="bk-input"><option value="">No supplier</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select>
            </label>
            <label class="bk-label sm:col-span-2 lg:col-span-3">
                <span>Notes <span class="font-normal text-[#8aa39c]">(optional)</span></span>
                <textarea name="notes" rows="1" maxlength="1000" class="bk-input"></textarea>
            </label>
            <div class="bk-form-actions">
                <button class="bk-btn bk-btn-accent">Add stock item</button>
            </div>
        </form>
    </section>

    <section data-scroll-on-filter="search,sort,direction,show_empty,alert" class="scroll-mt-4 bk-card min-w-0 overflow-hidden">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="bk-card-title">Live inventory</h2>
                <p class="mt-1 text-sm text-[#607a72]">Low stock and approaching spoilage are highlighted.</p>
                <form class="mt-4 flex flex-wrap gap-2">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    @if ($alert)
                        <input type="hidden" name="alert" value="{{ $alert }}">
                    @endif
                    <input name="search" value="{{ request('search') }}" placeholder="Search flower" class="bk-input w-56">
                    <button class="bk-btn bk-btn-primary">Search</button>
                    @if (request()->filled('search'))
                        <a href="{{ route($area.'.inventory', array_filter(['sort' => $sort, 'direction' => $direction, 'show_empty' => $showEmpty ? 1 : null, 'alert' => $alert])) }}" class="bk-btn bk-btn-ghost">Clear</a>
                    @endif
                    <label class="flex cursor-pointer items-center gap-2 px-1 text-sm text-[#49665f]">
                        <input type="checkbox" name="show_empty" value="1" @checked($showEmpty) onchange="this.form.submit()" class="size-4 cursor-pointer accent-[#285443]">
                        Show empty batches ({{ $emptyBatchCount }})
                    </label>
                </form>
            </div>
            {{-- Alert filters: click to show only those flowers, click again to show everything --}}
            <nav class="flex flex-wrap gap-2 text-xs font-semibold" aria-label="Filter by alert">
                @foreach ([
                    'spoilage' => ['label' => 'Spoilage alert', 'chip' => 'bg-[#f5dcd7] text-[#b84331]', 'active' => 'bg-[#b84331] text-white', 'dot' => 'bg-[#b84331]'],
                    'low_stock' => ['label' => 'Low stock', 'chip' => 'bg-[#fff0d5] text-[#a76513]', 'active' => 'bg-[#a76513] text-white', 'dot' => 'bg-[#a76513]'],
                ] as $alertKey => $chip)
                    @php
                        $isActive = $alert === $alertKey;
                    @endphp
                    <a
                        href="{{ route($area.'.inventory', array_filter([...request()->only(['search', 'sort', 'direction', 'show_empty']), 'alert' => $isActive ? null : $alertKey])) }}"
                        @if ($isActive) aria-current="page" @endif
                        title="{{ $isActive ? 'Show all flowers' : 'Show only '.Str::lower($chip['label']).' flowers' }}"
                        @class([
                            'flex items-center gap-1.5 rounded-full px-3 py-1.5 transition',
                            $chip['chip'].' hover:brightness-95' => ! $isActive,
                            $chip['active'] => $isActive,
                        ])
                    >
                        <span @class(['h-2 w-2 rounded-full', $isActive ? 'bg-white' : $chip['dot']])></span>
                        {{ $chip['label'] }}
                        <span @class(['rounded-full px-1.5', 'bg-white/20' => $isActive, 'bg-white/60' => ! $isActive])>{{ $alertCounts[$alertKey] }}</span>
                        @if ($isActive)
                            <span aria-hidden="true">✕</span>
                        @endif
                    </a>
                @endforeach
            </nav>
        </div>

        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="border-b border-[#d4e4e0] text-xs uppercase tracking-wider text-[#607a72]">
                    <tr>
                        <x-admin.sort-header column="flower_type" :sort="$sort" :direction="$direction" class="px-3">Flower</x-admin.sort-header>
                        <x-admin.sort-header column="quantity" :sort="$sort" :direction="$direction" class="px-3">In stock</x-admin.sort-header>
                        <th class="px-3 pb-3">Selling price</th>
                        <th class="px-3 pb-3">Margin</th>
                        <x-admin.sort-header column="date_received" :sort="$sort" :direction="$direction" default-direction="desc" class="px-3">Received</x-admin.sort-header>
                        <x-admin.sort-header column="spoilage_date" :sort="$sort" :direction="$direction" class="px-3">Spoil-by</x-admin.sort-header>
                        <th class="px-3 pb-3 text-right">Batches</th>
                    </tr>
                </thead>
                @forelse ($flowerGroups as $groupIndex => $group)
                    @php
                        $stockedBatches = $group['batches']->filter(fn ($batch) => $batch->quantity > 0);
                        $hasSpoilageAlert = $group['has_spoilage_alert'];
                        $isOutOfStock = $group['total'] === 0;
                        // Price and margin come from batches in stock, so an old sold-out batch can't skew them.
                        $pricedBatches = $isOutOfStock ? $group['batches'] : $stockedBatches;
                        $prices = $pricedBatches->pluck('unit_price')->map(fn ($price) => (float) $price)->unique()->sort()->values();
                        $margins = $pricedBatches->map(fn ($batch) => round((float) $batch->unit_price - (float) $batch->cost_price, 2))->unique()->sort()->values();
                        $visibleBatches = $showEmpty ? $group['batches'] : $stockedBatches->values();
                        // Batch rows repeat pricing only when it differs between the batches shown; otherwise the flower row already says it.
                        $showBatchPricing = $visibleBatches->map(fn ($batch) => $batch->unit_price.'|'.$batch->cost_price)->unique()->count() > 1;
                        $groupDaysLeft = (int) today()->diffInDays($group['earliest_spoilage'], false);
                        $batchCount = $visibleBatches->count();
                    @endphp
                    <tbody class="border-b border-[#dce9e6]">
                        {{-- Summary row: the flower at a glance --}}
                        <tr class="align-middle">
                            <td class="px-3 py-4">
                                <span class="font-semibold text-[#1c2e28]">{{ $group['flower_type'] }}</span>
                                @if ($group['is_low_stock'] || $hasSpoilageAlert)
                                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                                        @if ($isOutOfStock)
                                            <span class="rounded-full bg-[#f5dcd7] px-2 py-0.5 text-xs font-semibold text-[#b84331]">Out of stock</span>
                                        @elseif ($group['is_low_stock'])
                                            <span class="rounded-full bg-[#fff0d5] px-2 py-0.5 text-xs font-semibold text-[#a76513]">Low stock</span>
                                        @endif
                                        @if ($hasSpoilageAlert)
                                            <span class="rounded-full bg-[#f5dcd7] px-2 py-0.5 text-xs font-semibold text-[#b84331]">Spoilage alert</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td @class(['px-3 py-4 text-base', 'font-bold text-[#b84331]' => $group['is_low_stock'], 'font-semibold text-[#1c2e28]' => ! $group['is_low_stock']])>{{ $group['total'] }}</td>
                            <td class="whitespace-nowrap px-3 py-4 text-[#1c2e28]">
                                RM {{ number_format($prices->first(), 2) }}{{ $prices->count() > 1 ? ' – '.number_format($prices->last(), 2) : '' }}
                            </td>
                            <td @class(['whitespace-nowrap px-3 py-4 font-semibold', 'text-[#b84331]' => $margins->first() < 0, 'text-[#187849]' => $margins->first() >= 0])>
                                RM {{ number_format($margins->first(), 2) }}{{ $margins->count() > 1 ? ' – '.number_format($margins->last(), 2) : '' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-[#607a72]">{{ $group['latest_received']->format('d M Y') }}</td>
                            <td class="whitespace-nowrap px-3 py-4 text-[#607a72]">
                                @if ($isOutOfStock)
                                    —
                                @else
                                    {{ $group['earliest_spoilage']->format('d M Y') }}
                                    <span @class(['block text-xs', 'font-semibold text-[#b84331]' => $groupDaysLeft <= 2])>
                                        {{ $groupDaysLeft < 0 ? abs($groupDaysLeft).' '.Str::plural('day', abs($groupDaysLeft)).' overdue' : ($groupDaysLeft === 0 ? 'Spoils today' : $groupDaysLeft.' '.Str::plural('day', $groupDaysLeft).' left') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-4 text-right">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                @if ($group['is_low_stock'])
                                    <a href="{{ route($area.'.purchase-orders', $group['reorder_query']) }}#create-purchase-order" class="bk-btn bk-btn-accent bk-btn-sm h-8">Reorder</a>
                                @endif
                                @if ($batchCount === 0)
                                    <span class="text-xs text-[#8aa39c]">No batches in stock</span>
                                @else
                                <button
                                    type="button"
                                    class="bk-btn bk-btn-secondary bk-btn-sm h-8"
                                    aria-expanded="false"
                                    aria-controls="batches-{{ $groupIndex }}"
                                    onclick="const open = this.getAttribute('aria-expanded') !== 'true'; this.setAttribute('aria-expanded', open); this.querySelector('svg').classList.toggle('rotate-180', open); this.closest('tbody').querySelectorAll('[data-batch-row]').forEach((row) => row.hidden = ! open)"
                                >
                                    {{ $batchCount }} {{ Str::plural('batch', $batchCount) }}
                                    <svg class="size-3.5 transition" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                                </button>
                                @endif
                                </div>
                            </td>
                        </tr>

                        {{-- Batch rows: hidden until opened --}}
                        @foreach ($visibleBatches as $item)
                            @php
                                $spoilageDate = $item->spoilageDate();
                                $daysLeft = (int) today()->diffInDays($spoilageDate, false);
                                $margin = (float) $item->unit_price - (float) $item->cost_price;
                            @endphp
                            <tr data-batch-row @if ($loop->first) id="batches-{{ $groupIndex }}" @endif hidden @class(['bg-[#f4faf8] align-top text-[13px]', 'opacity-60' => $item->quantity === 0])>
                                <td class="py-3 pr-3 pl-6">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="font-semibold text-[#1c2e28]">Batch #{{ $item->id }}</span>
                                        @if ($group['sell_first_id'] === $item->id)
                                            <span class="rounded-full bg-[#285443] px-2 py-0.5 text-xs font-semibold text-white" title="Customer orders take from this batch first because it spoils soonest">Sell first</span>
                                        @endif
                                    </div>
                                    <span class="mt-0.5 block text-xs text-[#607a72]">
                                        {{ $item->supplier?->name ?? 'No supplier' }} ·
                                        @if ($item->purchaseOrder)
                                            from {{ $item->purchaseOrder->reference() }}
                                        @else
                                            added manually
                                        @endif
                                    </span>
                                    @if ($item->notes)
                                        <span class="mt-0.5 block max-w-48 truncate text-xs text-[#8aa39c]" title="{{ $item->notes }}">{{ $item->notes }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-[#1c2e28]">{{ $item->quantity }}</td>
                                @if ($showBatchPricing)
                                    <td class="whitespace-nowrap px-3 py-3 text-[#1c2e28]">
                                        RM {{ number_format($item->unit_price, 2) }}
                                        <span class="block text-xs text-[#8aa39c]">cost RM {{ number_format($item->cost_price, 2) }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 font-semibold {{ $margin < 0 ? 'text-[#b84331]' : 'text-[#187849]' }}">RM {{ number_format($margin, 2) }}</td>
                                @else
                                    <td class="px-3 py-3"></td>
                                    <td class="px-3 py-3"></td>
                                @endif
                                <td class="whitespace-nowrap px-3 py-3 text-[#607a72]">
                                    {{ $item->date_received->format('d M Y') }}
                                    <span class="block text-xs text-[#8aa39c]">added {{ $item->created_at->timezone('Asia/Kuala_Lumpur')->format('d M, h:i A') }}</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 text-[#607a72]">
                                    {{ $spoilageDate->format('d M Y') }}
                                    <span @class(['block text-xs', 'font-semibold text-[#b84331]' => $daysLeft <= 2 && $item->quantity > 0])>
                                        {{ $daysLeft < 0 ? abs($daysLeft).' '.Str::plural('day', abs($daysLeft)).' overdue' : ($daysLeft === 0 ? 'Spoils today' : $daysLeft.' '.Str::plural('day', $daysLeft).' left') }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <div class="flex justify-end gap-3 whitespace-nowrap text-xs font-semibold">
                                        @if ($item->quantity === 0)
                                            {{-- Sold out: nothing left to edit, waste, or delete; the batch is kept for its history. --}}
                                            @foreach (['Edit', 'Wastage', 'Delete'] as $disabledAction)
                                                <span class="cursor-not-allowed text-[#a9bcb6]" title="This batch is empty.">{{ $disabledAction }}</span>
                                            @endforeach
                                        @else
                                        <button
                                            type="button"
                                            class="cursor-pointer text-[#285443] underline underline-offset-2"
                                            onclick="document.getElementById('edit-stock-{{ $item->id }}').showModal()"
                                        >Edit</button>
                                        <a href="{{ route('staff.wastage.create', ['item' => $item->id]) }}" class="text-[#a76513] underline underline-offset-2">Wastage</a>
                                        @if ($item->canBeReturned())
                                            <a href="{{ route($area.'.stock-returns', ['item' => $item->id]) }}" class="text-[#285443] underline underline-offset-2">Return</a>
                                        @endif
                                        @if ($item->canBeDeleted())
                                            <form method="POST" action="{{ route($area.'.inventory.destroy', $item) }}" onsubmit="return confirm(@js("Delete {$item->flower_type}? This cannot be undone."))">
                                                @csrf @method('DELETE')
                                                <button class="cursor-pointer text-[#b84331] underline underline-offset-2" type="submit">Delete</button>
                                            </form>
                                        @else
                                            <span class="cursor-not-allowed text-[#a9bcb6]" title="Has stock history (wastage, order stock, or adjustments), so it can't be deleted. Set its quantity to 0 instead.">Delete</span>
                                        @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>

                        {{-- Edit item modal --}}
                        <dialog
                            id="edit-stock-{{ $item->id }}"
                            aria-labelledby="edit-stock-title-{{ $item->id }}"
                            class="fixed left-1/2 top-1/2 m-0 max-h-[calc(100vh-2rem)] w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-[24px] bg-white p-0 backdrop:bg-[#1c2e28]/40"
                        >
                            <div class="max-h-[calc(100vh-2rem)] overflow-y-auto p-6 sm:p-8">
                                <form method="POST" action="{{ route($area.'.inventory.update', $item) }}" class="text-left">
                                    @csrf @method('PUT')
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <h3 id="edit-stock-title-{{ $item->id }}" class="font-serif text-xl font-bold text-[#1c2e28]">Edit stock item</h3>
                                            <p class="mt-1 text-sm text-[#607a72]">Update details for {{ $item->flower_type }}.</p>
                                        </div>
                                        <button type="button" aria-label="Close edit stock dialog" class="cursor-pointer rounded-full p-1.5 text-[#607a72] hover:bg-[#edf7f5]" onclick="document.getElementById('edit-stock-{{ $item->id }}').close()">&#10005;</button>
                                    </div>
                                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                                        <label class="grid gap-1 text-sm sm:col-span-2">
                                            <span class="font-semibold text-[#1c2e28]">Flower type</span>
                                            <input name="flower_type" value="{{ $item->flower_type }}" required class="bk-input">
                                        </label>
                                        <label class="grid gap-1 text-sm">
                                            <span class="font-semibold text-[#1c2e28]">Quantity</span>
                                            @if ($item->isClosedEmpty())
                                                <input name="quantity" type="number" value="0" readonly class="bk-input cursor-not-allowed bg-[#eef3f1] text-[#8aa39c]">
                                                <span class="text-xs text-[#a76513]">This batch is empty. To add new {{ $item->flower_type }}, use Add stock item or Receive into stock.</span>
                                            @else
                                                <input name="quantity" type="number" min="0" value="{{ $item->quantity }}" required class="bk-input">
                                            @endif
                                        </label>
                                        <label class="grid gap-1 text-sm">
                                            <span class="font-semibold text-[#1c2e28]">Selling price (RM)</span>
                                            <input name="unit_price" type="number" min="0" step="0.01" value="{{ $item->unit_price }}" required class="bk-input">
                                        </label>
                                        <label class="grid gap-1 text-sm">
                                            <span class="font-semibold text-[#1c2e28]">Cost price (RM)</span>
                                            <input name="cost_price" type="number" min="0" step="0.01" value="{{ $item->cost_price }}" required class="bk-input">
                                        </label>
                                        <label class="grid gap-1 text-sm">
                                            <span class="font-semibold text-[#1c2e28]">Supplier</span>
                                            <select name="supplier_id" class="bk-input"><option value="">No supplier</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected($item->supplier_id === $supplier->id)>{{ $supplier->name }}</option>@endforeach</select>
                                        </label>
                                        <label class="grid gap-1 text-sm">
                                            <span class="font-semibold text-[#1c2e28]">Date received</span>
                                            <input name="date_received" type="date" max="{{ today()->format('Y-m-d') }}" value="{{ $item->date_received->format('Y-m-d') }}" required class="bk-input">
                                        </label>
                                        <label class="grid gap-1 text-sm">
                                            <span class="font-semibold text-[#1c2e28]">Shelf life (days)</span>
                                            <input name="shelf_life_days" type="number" min="1" value="{{ $item->shelf_life_days }}" required class="bk-input">
                                        </label>
                                        <label class="grid gap-1 text-sm">
                                            <span class="font-semibold text-[#1c2e28]">Low stock threshold</span>
                                            <input name="low_stock_threshold" type="number" min="0" value="{{ $item->low_stock_threshold }}" required class="bk-input">
                                        </label>
                                        <label class="grid gap-1 text-sm sm:col-span-2">
                                            <span class="font-semibold text-[#1c2e28]">Notes</span>
                                            <textarea name="notes" rows="2" maxlength="1000" class="bk-input">{{ $item->notes }}</textarea>
                                        </label>
                                    </div>
                                    <div class="mt-7 flex justify-end gap-3">
                                        <button type="button" class="bk-btn bk-btn-ghost" onclick="document.getElementById('edit-stock-{{ $item->id }}').close()">Cancel</button>
                                        <button class="bk-btn bk-btn-primary">Save changes</button>
                                    </div>
                                </form>
                            </div>
                        </dialog>
                        @endforeach
                    </tbody>
                @empty
                    <tbody>
                        <tr><td colspan="7" class="py-8 text-center text-sm text-[#607a72]">{{ match (true) {
                            $alert === 'low_stock' => 'No flowers are low on stock.',
                            $alert === 'spoilage' => 'No flowers are close to spoiling.',
                            request()->filled('search') => 'No flowers match your search.',
                            default => 'No inventory items yet.',
                        } }}</td></tr>
                    </tbody>
                @endforelse
            </table>
        </div>
    </section>
</x-workspace.layout>