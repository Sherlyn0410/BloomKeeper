@props(['order'])

@if ($order->isLocked())
    <span {{ $attributes->merge(['class' => 'inline-flex h-8 items-center gap-1 text-xs font-semibold text-[#8aa39c]']) }} title="Completed orders are final and can't be changed">
        <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" /></svg>
        Locked
    </span>
@else
    <div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
        @if ($order->canBeEdited())
            <a href="{{ route('staff.orders.edit', $order) }}" class="bk-btn bk-btn-secondary bk-btn-sm h-8 flex-1 sm:flex-none">
                <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.59 3.59a2 2 0 0 1 2.82 2.82l-8.6 8.6a2 2 0 0 1-.9.52l-3.12.89a.5.5 0 0 1-.62-.62l.89-3.12a2 2 0 0 1 .52-.9l8.6-8.6Z" /></svg>
                Edit
            </a>
        @endif
        @if ($order->canBeDeleted())
            <form method="POST" action="{{ route('staff.orders.destroy', $order) }}" onsubmit="return confirm(@js("Delete order {$order->order_number}? This cannot be undone."))" class="flex-1 sm:flex-none">
                @csrf
                @method('DELETE')
                <button type="submit" class="bk-btn bk-btn-danger-soft bk-btn-sm h-8 w-full">
                    <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75V4H3.5a.75.75 0 0 0 0 1.5h.3l.82 10.62A2.75 2.75 0 0 0 7.36 18.6h5.28a2.75 2.75 0 0 0 2.74-2.48L16.2 5.5h.3a.75.75 0 0 0 0-1.5H14v-.25A2.75 2.75 0 0 0 11.25 1h-2.5ZM7.5 3.75c0-.69.56-1.25 1.25-1.25h2.5c.69 0 1.25.56 1.25 1.25V4h-5v-.25Z" clip-rule="evenodd" /></svg>
                    Delete
                </button>
            </form>
        @endif
    </div>
@endif
