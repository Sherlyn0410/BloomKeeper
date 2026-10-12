@props(['order'])

@php
    $cancelsInstead = $order->mustBeCancelledInsteadOfDeleted();
    $confirmMessage = $cancelsInstead
        ? "Order {$order->order_number} is {$order->status}, so it will be cancelled instead of deleted. Continue?"
        : "Delete order {$order->order_number}? This cannot be undone.";
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-3 text-xs font-semibold']) }}>
    <a href="{{ route('staff.orders.edit', $order) }}" class="text-[#285443] underline underline-offset-2">Edit</a>
    @unless ($order->status === 'cancelled' && $cancelsInstead)
        <form method="POST" action="{{ route('staff.orders.destroy', $order) }}" onsubmit="return confirm(@js($confirmMessage))">
            @csrf
            @method('DELETE')
            <button type="submit" class="cursor-pointer text-[#b84331] underline underline-offset-2">{{ $cancelsInstead ? 'Cancel order' : 'Delete' }}</button>
        </form>
    @endunless
</div>
