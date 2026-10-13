@props(['adjustment'])

@can('edit-wastage', $adjustment)
    <div {{ $attributes->merge(['class' => 'flex items-center justify-end gap-3 whitespace-nowrap text-xs font-semibold']) }}>
        <a href="{{ route('staff.wastage.edit', $adjustment) }}" class="text-[#285443] underline underline-offset-2">Edit</a>
        <form method="POST" action="{{ route('staff.wastage.destroy', $adjustment) }}" onsubmit="return confirm(@js('Delete this wastage record? '.abs($adjustment->quantity_change).' '.$adjustment->inventoryItem->flower_type.' will be returned to stock.'))">
            @csrf
            @method('DELETE')
            <button type="submit" class="cursor-pointer text-[#b84331] underline underline-offset-2">Delete</button>
        </form>
    </div>
@else
    <span {{ $attributes->merge(['class' => 'block text-right text-xs text-[#b8cfca]']) }} title="Staff can correct their own records for {{ \App\Models\StockAdjustment::WASTAGE_EDIT_WINDOW_HOURS }} hours">Locked</span>
@endcan
