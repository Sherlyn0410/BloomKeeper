@php
    // Admins and staff share this page; links and forms stay inside the viewer's own area.
    $area = auth()->user()->can('manage-users') ? 'admin' : 'staff';
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

    <section class="bk-card min-w-0 overflow-hidden">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="bk-card-title">Live inventory</h2>
                <p class="mt-1 text-sm text-[#607a72]">Low stock and approaching spoilage are highlighted.</p>
                <form class="mt-4 flex flex-wrap gap-2">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <input name="search" value="{{ request('search') }}" placeholder="Search flower" class="bk-input w-56">
                    <button class="bk-btn bk-btn-primary">Search</button>
                    @if (request()->filled('search'))
                        <a href="{{ route($area.'.inventory', ['sort' => $sort, 'direction' => $direction]) }}" class="bk-btn bk-btn-ghost">Clear</a>
                    @endif
                </form>
            </div>
            <div class="flex flex-wrap gap-2 text-xs font-semibold">
                <span class="flex items-center gap-1.5 rounded-full bg-[#f5dcd7] px-3 py-1.5 text-[#b84331]">
                    <span class="h-2 w-2 rounded-full bg-[#b84331]"></span> Spoilage alert
                </span>
                <span class="flex items-center gap-1.5 rounded-full bg-[#fff0d5] px-3 py-1.5 text-[#a76513]">
                    <span class="h-2 w-2 rounded-full bg-[#a76513]"></span> Low stock
                </span>
            </div>
        </div>

        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[920px] text-left text-sm">
                <thead class="border-b border-[#d4e4e0] text-xs uppercase tracking-wider text-[#607a72]">
                    <tr>
                        <x-admin.sort-header column="flower_type" :sort="$sort" :direction="$direction" class="px-3">Flower</x-admin.sort-header>
                        <x-admin.sort-header column="quantity" :sort="$sort" :direction="$direction" class="px-3">Quantity</x-admin.sort-header>
                        <th class="px-3 pb-3">Selling price</th>
                        <th class="px-3 pb-3">Margin</th>
                        <x-admin.sort-header column="date_received" :sort="$sort" :direction="$direction" default-direction="desc" class="px-3">Received</x-admin.sort-header>
                        <x-admin.sort-header column="spoilage_date" :sort="$sort" :direction="$direction" class="px-3">Spoil-by</x-admin.sort-header>
                        <th class="px-3 pb-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#dce9e6]">
                    @forelse ($inventory as $item)
                        @php
                            $spoilageDate = $item->spoilageDate();
                            $isNearSpoilage = $spoilageDate->lte(now()->addDays(2));
                            $daysLeft = (int) today()->diffInDays($spoilageDate, false);
                            $margin = (float) $item->unit_price - (float) $item->cost_price;
                            $isLowStock = $item->quantity <= $item->low_stock_threshold;
                        @endphp
                        <tr class="align-top">
                            <td class="px-3 py-4">
                                <span class="font-semibold text-[#1c2e28]">{{ $item->flower_type }}</span>
                                <span class="mt-0.5 block text-xs text-[#607a72]">{{ $item->supplier?->name ?? 'No supplier' }}</span>
                                @if ($item->notes)
                                    <span class="mt-0.5 block max-w-48 truncate text-xs text-[#8aa39c]" title="{{ $item->notes }}">{{ $item->notes }}</span>
                                @endif
                                <div class="mt-1.5 flex flex-wrap gap-1.5">
                                    @if ($isNearSpoilage)
                                        <span class="rounded-full bg-[#f5dcd7] px-2 py-0.5 text-xs font-semibold text-[#b84331]">Spoilage alert</span>
                                    @endif
                                    @if ($isLowStock)
                                        <span class="rounded-full bg-[#fff0d5] px-2 py-0.5 text-xs font-semibold text-[#a76513]">Low stock</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-4 {{ $isLowStock ? 'font-bold text-[#b84331]' : 'text-[#1c2e28]' }}">
                                {{ $item->quantity }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-[#1c2e28]">
                                RM {{ number_format($item->unit_price, 2) }}
                                <span class="block text-xs text-[#8aa39c]">cost RM {{ number_format($item->cost_price, 2) }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 font-semibold {{ $margin < 0 ? 'text-[#b84331]' : 'text-[#187849]' }}">RM {{ number_format($margin, 2) }}</td>
                            <td class="whitespace-nowrap px-3 py-4 text-[#607a72]">{{ $item->date_received->format('d M Y') }}</td>
                            <td class="whitespace-nowrap px-3 py-4 text-[#607a72]">
                                {{ $spoilageDate->format('d M Y') }}
                                <span @class(['block text-xs', 'font-semibold text-[#b84331]' => $daysLeft <= 2])>
                                    {{ $daysLeft < 0 ? abs($daysLeft).' '.Str::plural('day', abs($daysLeft)).' overdue' : ($daysLeft === 0 ? 'Spoils today' : $daysLeft.' '.Str::plural('day', $daysLeft).' left') }}
                                </span>
                            </td>
                            <td class="px-3 py-4 text-right">
                                <div class="flex justify-end gap-3 whitespace-nowrap text-xs font-semibold">
                                    <button
                                        type="button"
                                        class="cursor-pointer text-[#285443] underline underline-offset-2"
                                        onclick="document.getElementById('edit-stock-{{ $item->id }}').showModal()"
                                    >Edit</button>
                                    <a href="{{ route('staff.wastage.create', ['item' => $item->id]) }}" class="text-[#a76513] underline underline-offset-2">Wastage</a>
                                    @if ($item->canBeDeleted())
                                        <form method="POST" action="{{ route($area.'.inventory.destroy', $item) }}" onsubmit="return confirm(@js("Delete {$item->flower_type}? This cannot be undone."))">
                                            @csrf @method('DELETE')
                                            <button class="cursor-pointer text-[#b84331] underline underline-offset-2" type="submit">Delete</button>
                                        </form>
                                    @else
                                        <span class="cursor-not-allowed text-[#a9bcb6]" title="Has stock history (wastage, order stock, or adjustments), so it can't be deleted. Set its quantity to 0 instead.">Delete</span>
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
                                            <input name="quantity" type="number" min="0" value="{{ $item->quantity }}" required class="bk-input">
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

                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-sm text-[#607a72]">{{ request()->filled('search') ? 'No flowers match your search.' : 'No inventory items yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-workspace.layout>