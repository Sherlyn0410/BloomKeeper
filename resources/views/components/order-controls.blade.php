@props(['order'])

@php
    // Admins and staff share these controls; forms post to the viewer's own area.
    $area = auth()->user()->can('manage-users') ? 'admin' : 'staff';
@endphp

{{-- Phones: status on its own row, then the buttons sharing the width. Wider screens: one row, status on the left. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col gap-2 border-t border-[#e3eeeb] pt-3 sm:flex-row sm:items-center sm:justify-between']) }}>
    @if ($order->status === 'cancelled')
        <label class="sr-only" for="order-status-{{ $order->id }}">Status</label>
        <select id="order-status-{{ $order->id }}" disabled title="Cancelled orders can't be changed. They can only be deleted." class="bk-input bk-input-sm h-8 w-full cursor-not-allowed sm:w-32 bg-[#eef3f1] py-1 text-[#8aa39c]">
            <option>Cancelled</option>
        </select>
    @elseif (! $order->isLocked())
        <form method="POST" action="{{ route($area.'.orders.status', $order) }}" class="sm:w-32">
            @csrf
            @method('PATCH')
            <label class="sr-only" for="order-status-{{ $order->id }}">Update status</label>
            <select
                id="order-status-{{ $order->id }}"
                name="status"
                data-current="{{ $order->status }}"
                onchange="if (this.dataset.current === 'ready' && this.value === 'cancelled' && ! confirm(@js("Order {$order->order_number} is already prepared. Cancelling it records its flowers as wastage instead of returning them to stock. Continue?"))) { this.value = this.dataset.current; return; } this.form.submit()"
                class="bk-input bk-input-sm h-8 w-full cursor-pointer py-1 text-[#285443]"
            >
                @foreach (['pending' => 'Pending', 'ready' => 'Ready', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label)
                    <option value="{{ $value }}" @selected($order->status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    @endif

    <div class="flex flex-wrap items-center gap-2 sm:ml-auto">
        @if ($order->canBeMarkedPaid())
            <form method="POST" action="{{ route($area.'.orders.paid', $order) }}" class="flex-1 sm:flex-none">
                @csrf
                @method('PATCH')
                <button type="submit" class="bk-btn bk-btn-primary bk-btn-sm h-8 w-full">
                    <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.58l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                    Mark as paid
                </button>
            </form>
        @endif
        <x-order-actions :order="$order" class="flex-1 sm:flex-none" />
    </div>
</div>
