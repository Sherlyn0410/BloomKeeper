@php
    $recordedQuantity = abs($adjustment->quantity_change);
    $selectedItemId = (int) old('inventory_item_id', $adjustment->inventory_item_id);
    $backRoute = auth()->user()->role === 'admin' ? route('admin.wastage') : route('staff.wastage.create');
@endphp

<x-workspace.layout
    title="Edit wastage record"
    description="Correct a wastage entry. Stock is adjusted automatically by the difference."
>
    <section class="bk-card">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="bk-card-title">Wastage details</h2>
                <p class="bk-card-subtitle">
                    Recorded by {{ $adjustment->user->name }} on {{ $adjustment->created_at->timezone('Asia/Kuala_Lumpur')->format('d M Y, h:i A') }}
                    · originally {{ $recordedQuantity }} {{ $adjustment->inventoryItem->flower_type }}
                </p>
            </div>
        </div>

        <form method="POST" action="{{ route('staff.wastage.update', $adjustment) }}" class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @csrf
            @method('PUT')
            <label class="bk-label sm:col-span-2">
                Inventory item
                <select name="inventory_item_id" required class="bk-input">
                    @foreach ($inventory as $item)
                        @php $available = $item->quantity + ($item->id === $adjustment->inventory_item_id ? $recordedQuantity : 0); @endphp
                        <option value="{{ $item->id }}" @selected($selectedItemId === $item->id)>
                            {{ $item->flower_type }} · received {{ $item->date_received->format('d M') }} · {{ $available }} available
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="bk-label">
                Quantity removed
                <input name="quantity" value="{{ old('quantity', $recordedQuantity) }}" type="number" min="1" required class="bk-input">
            </label>
            <label class="bk-label">
                Reason
                <select name="reason" required class="bk-input">
                    @foreach (\App\Models\InventoryItem::WASTAGE_REASONS as $reason)
                        <option value="{{ $reason }}" @selected(old('reason', $adjustment->reason) === $reason)>{{ ucfirst($reason) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="bk-label sm:col-span-2 lg:col-span-4">
                <span>Notes <span class="font-normal text-[#8aa39c]">(optional)</span></span>
                <textarea name="notes" rows="2" maxlength="1000" class="bk-input">{{ old('notes', $adjustment->notes) }}</textarea>
            </label>
            <div class="bk-form-actions">
                <a href="{{ $backRoute }}" class="bk-btn bk-btn-ghost">Cancel</a>
                <button class="bk-btn bk-btn-primary">Save changes</button>
            </div>
        </form>
    </section>
</x-workspace.layout>
