@props(['order'])

@if ($order->isLocked())
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 text-xs font-semibold text-[#8aa39c]']) }} title="Completed orders are final and can't be changed">
        <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" /></svg>
        Locked
    </span>
@else
    <div {{ $attributes->merge(['class' => 'flex items-center gap-3 text-xs font-semibold']) }}>
        @if ($order->canBeEdited())
            <a href="{{ route('staff.orders.edit', $order) }}" class="text-[#285443] underline underline-offset-2">Edit</a>
        @endif
        @if ($order->canBeDeleted())
            <form method="POST" action="{{ route('staff.orders.destroy', $order) }}" onsubmit="return confirm(@js("Delete order {$order->order_number}? This cannot be undone."))">
                @csrf
                @method('DELETE')
                <button type="submit" class="cursor-pointer text-[#b84331] underline underline-offset-2">Delete</button>
            </form>
        @endif
    </div>
@endif
