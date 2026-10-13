@props(['orders', 'viewAllUrl'])

<section class="bk-card">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h2 class="bk-card-title">Recent orders</h2>
            <p class="mt-1 text-sm text-[#607a72]">Latest customer activity and collection dates.</p>
        </div>
        <a href="{{ $viewAllUrl }}" class="text-sm font-semibold text-[#285443] underline underline-offset-4">View all</a>
    </div>
    <div class="mt-6 overflow-hidden">
        <table class="w-full table-fixed text-left text-sm">
            <thead class="border-b border-[#d4e4e0] text-xs uppercase tracking-wider text-[#607a72]">
                <tr><th class="w-3/10 pb-3 font-medium">Customer</th><th class="w-4/10 pb-3 font-medium">Items</th><th class="pb-3 font-medium">Date</th><th class="pb-3 font-medium">Status</th></tr>
            </thead>
            <tbody class="divide-y divide-[#dce9e6]">
                @forelse ($orders as $order)
                    <tr>
                        <td class="py-4 font-semibold">{{ $order->customer_name }}<span class="block text-xs font-normal text-[#607a72]">{{ $order->customer_email }}</span></td>
                        <td class="py-4 text-[#607a72]">{{ $order->items->map(fn ($item) => $item->quantity.' '.$item->flower_type)->join(', ') }}</td>
                        <td class="py-4 text-[#607a72]">{{ $order->collection_date ? $order->collection_date->format('d M Y') : '-' }}</td>
                        <td class="py-4"><span class="rounded-full px-3 py-1 text-xs font-semibold {{ $order->status === 'pending' ? 'bg-[#fff0d5] text-[#a76513]' : ($order->status === 'ready' ? 'bg-[#d7f0df] text-[#187849]' : ($order->status === 'completed' ? 'bg-[#d5e4e1] text-[#285443]' : 'bg-[#f5dcd7] text-[#b84331]')) }}">{{ ucfirst($order->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-sm text-[#607a72]">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
