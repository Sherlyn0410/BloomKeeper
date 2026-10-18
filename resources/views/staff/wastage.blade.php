<x-workspace.layout
    title="Record wastage"
    description="Remove expired or damaged flowers from stock. Each entry is logged in the wastage history."
>
    <section class="bk-card">
        <form method="POST" action="{{ route('staff.wastage.store') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @csrf
            <label class="bk-label sm:col-span-2">
                Inventory item
                <select name="inventory_item_id" required class="bk-input">
                    <option value="">Select stock</option>
                    @foreach ($inventory as $item)
                        <option value="{{ $item->id }}" @selected((int) old('inventory_item_id', $selectedItemId) === $item->id)>
                            {{ $item->flower_type }} · received {{ $item->date_received->format('d M') }} · {{ $item->quantity }} in stock
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="bk-label">
                Quantity removed
                <input name="quantity" value="{{ old('quantity') }}" type="number" min="1" required class="bk-input">
            </label>
            <label class="bk-label">
                Reason
                <select name="reason" required class="bk-input">
                    @foreach (\App\Models\InventoryItem::WASTAGE_REASONS as $reason)
                        <option value="{{ $reason }}" @selected(old('reason') === $reason)>{{ ucfirst($reason) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="bk-label sm:col-span-2 lg:col-span-4">
                <span>Notes <span class="font-normal text-[#8aa39c]">(optional)</span></span>
                <textarea name="notes" rows="2" maxlength="1000" class="bk-input">{{ old('notes') }}</textarea>
            </label>
            <div class="bk-form-actions">
                <button class="bk-btn bk-btn-danger">Remove from stock</button>
            </div>
        </form>
    </section>

    <section data-scroll-on-filter="sort,direction" class="scroll-mt-4 bk-card">
        <h2 class="bk-card-title">Recent wastage</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[640px] text-left text-sm">
                <thead class="border-b border-[#d4e4e0] text-xs uppercase tracking-wider text-[#607a72]">
                    <tr>
                        <x-admin.sort-header column="date" :sort="$sort" :direction="$direction" default-direction="desc">Date</x-admin.sort-header>
                        <x-admin.sort-header column="flower" :sort="$sort" :direction="$direction" default-direction="asc">Flower</x-admin.sort-header>
                        <x-admin.sort-header column="quantity" :sort="$sort" :direction="$direction" default-direction="desc" class="text-right">Removed</x-admin.sort-header>
                        <x-admin.sort-header column="reason" :sort="$sort" :direction="$direction" default-direction="asc" class="pl-6">Reason</x-admin.sort-header>
                        <x-admin.sort-header column="recorded_by" :sort="$sort" :direction="$direction" default-direction="asc">By</x-admin.sort-header>
                        <th class="pb-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#dce9e6]">
                    @forelse ($recentWastage as $adjustment)
                        <tr>
                            <td class="py-3 text-[#607a72]">{{ $adjustment->created_at->format('d M Y') }}</td>
                            <td class="py-3 font-semibold">{{ $adjustment->inventoryItem->flower_type }}</td>
                            <td class="py-3 text-right font-semibold text-[#b84331]">{{ abs($adjustment->quantity_change) }}</td>
                            <td class="py-3 pl-6 capitalize">{{ $adjustment->reason }}</td>
                            <td class="py-3 text-[#607a72]">{{ $adjustment->user->name }}</td>
                            <td class="py-3"><x-wastage-actions :adjustment="$adjustment" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-[#607a72]">No wastage recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-workspace.layout>
