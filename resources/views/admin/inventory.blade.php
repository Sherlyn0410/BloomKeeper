<x-admin.layout
    title="Inventory"
    description="Track flower stock, monitor shelf life, and keep quantities ready for upcoming orders."
>
    <section class="rounded-[28px] bg-[#edf7f5]/90 p-5 shadow-[0_12px_30px_rgba(36,73,64,0.1)] sm:p-7">
        <div>
            <h2 class="font-serif text-2xl font-bold">Add flower stock</h2>
            <p class="mt-1 text-sm text-[#607a72]">Record a new batch with its pricing and alert thresholds.</p>
        </div>
        <form method="POST" action="{{ route('admin.inventory.store') }}" class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @csrf
            <input name="flower_type" placeholder="Flower type" required class="rounded-xl border-0 p-3">
            <input name="quantity" type="number" min="0" placeholder="Quantity" required class="rounded-xl border-0 p-3">
            <input name="unit_price" type="number" min="0" step="0.01" placeholder="Unit price (RM)" required class="rounded-xl border-0 p-3">
            <input name="date_received" type="date" required class="rounded-xl border-0 p-3">
            <input name="shelf_life_days" type="number" min="1" placeholder="Shelf life days" required class="rounded-xl border-0 p-3">
            <input name="low_stock_threshold" type="number" min="0" placeholder="Low stock threshold" required class="rounded-xl border-0 p-3">
            <button class="rounded-xl bg-[#e3953d] px-4 py-3 font-semibold text-white transition hover:bg-[#d47f2a] sm:col-span-2 lg:col-span-3">Add stock item</button>
        </form>
    </section>

    <section class="min-w-0 overflow-hidden rounded-[28px] bg-[#edf7f5]/90 p-5 shadow-[0_12px_30px_rgba(36,73,64,0.1)] sm:p-7">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="font-serif text-2xl font-bold">Live inventory</h2>
                <p class="mt-1 text-sm text-[#607a72]">Low stock and approaching spoilage are highlighted.</p>
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
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="border-b border-[#d4e4e0] text-xs uppercase tracking-wider text-[#607a72]">
                    <tr>
                        <th class="px-3 pb-3">Flower</th>
                        <th class="px-3 pb-3">Quantity</th>
                        <th class="px-3 pb-3">Unit price</th>
                        <th class="px-3 pb-3">Received</th>
                        <th class="px-3 pb-3">Shelf life</th>
                        <th class="px-3 pb-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#dce9e6]">
                    @forelse ($inventory as $item)
                        @php
                            $spoilageDate = $item->date_received->copy()->addDays($item->shelf_life_days);
                            $isNearSpoilage = $spoilageDate->lte(now()->addDays(2));
                            $isLowStock = $item->quantity <= $item->low_stock_threshold;
                        @endphp
                        <tr class="align-top">
                            <td class="px-3 py-4">
                                <span class="font-semibold text-[#1c2e28]">{{ $item->flower_type }}</span>
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
                            <td class="px-3 py-4 text-[#1c2e28]">RM {{ number_format($item->unit_price, 2) }}</td>
                            <td class="px-3 py-4 text-[#607a72]">{{ $item->date_received->format('d M Y') }}</td>
                            <td class="px-3 py-4 text-[#607a72]">
                                {{ $item->shelf_life_days }} days
                                <span class="block text-xs">until {{ $spoilageDate->format('d M Y') }}</span>
                            </td>
                            <td class="px-3 py-4 text-right">
                                <div class="flex flex-wrap justify-end gap-3 text-xs font-semibold">
                                    <button
                                        type="button"
                                        class="cursor-pointer text-[#285443] underline underline-offset-2"
                                        onclick="document.getElementById('edit-stock-{{ $item->id }}').showModal()"
                                    >Edit</button>
                                    <form method="POST" action="{{ route('admin.inventory.destroy', $item) }}" onsubmit="return confirm('Delete {{ $item->flower_type }}? This cannot be undone.')">
                                        @csrf @method('DELETE')
                                        <button class="cursor-pointer text-[#b84331] underline underline-offset-2" type="submit">Delete</button>
                                    </form>
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
                                <form method="POST" action="{{ route('admin.inventory.update', $item) }}" class="text-left">
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
                                            <input name="flower_type" value="{{ $item->flower_type }}" required class="rounded-xl border border-[#d4e4e0] bg-[#f7fbfa] p-3 text-sm focus:border-[#285443] focus:outline-none">
                                        </label>
                                        <label class="grid gap-1 text-sm">
                                            <span class="font-semibold text-[#1c2e28]">Quantity</span>
                                            <input name="quantity" type="number" min="0" value="{{ $item->quantity }}" required class="rounded-xl border border-[#d4e4e0] bg-[#f7fbfa] p-3 text-sm focus:border-[#285443] focus:outline-none">
                                        </label>
                                        <label class="grid gap-1 text-sm">
                                            <span class="font-semibold text-[#1c2e28]">Unit price (RM)</span>
                                            <input name="unit_price" type="number" min="0" step="0.01" value="{{ $item->unit_price }}" required class="rounded-xl border border-[#d4e4e0] bg-[#f7fbfa] p-3 text-sm focus:border-[#285443] focus:outline-none">
                                        </label>
                                        <label class="grid gap-1 text-sm">
                                            <span class="font-semibold text-[#1c2e28]">Date received</span>
                                            <input name="date_received" type="date" value="{{ $item->date_received->format('Y-m-d') }}" required class="rounded-xl border border-[#d4e4e0] bg-[#f7fbfa] p-3 text-sm focus:border-[#285443] focus:outline-none">
                                        </label>
                                        <label class="grid gap-1 text-sm">
                                            <span class="font-semibold text-[#1c2e28]">Shelf life (days)</span>
                                            <input name="shelf_life_days" type="number" min="1" value="{{ $item->shelf_life_days }}" required class="rounded-xl border border-[#d4e4e0] bg-[#f7fbfa] p-3 text-sm focus:border-[#285443] focus:outline-none">
                                        </label>
                                        <label class="grid gap-1 text-sm">
                                            <span class="font-semibold text-[#1c2e28]">Low stock threshold</span>
                                            <input name="low_stock_threshold" type="number" min="0" value="{{ $item->low_stock_threshold }}" required class="rounded-xl border border-[#d4e4e0] bg-[#f7fbfa] p-3 text-sm focus:border-[#285443] focus:outline-none">
                                        </label>
                                    </div>
                                    <div class="mt-7 flex justify-end gap-3">
                                        <button type="button" class="cursor-pointer rounded-full px-4 py-2 text-sm font-semibold text-[#607a72] hover:bg-[#edf7f5]" onclick="document.getElementById('edit-stock-{{ $item->id }}').close()">Cancel</button>
                                        <button class="cursor-pointer rounded-full bg-[#285443] px-5 py-2 text-sm font-semibold text-white hover:bg-[#1c3e32]">Save changes</button>
                                    </div>
                                </form>
                            </div>
                        </dialog>

                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-sm text-[#607a72]">No inventory items yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-admin.layout>