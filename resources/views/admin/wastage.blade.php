<x-admin.layout
    title="Wastage History"
    description="Every flower removed from stock as wastage, with who recorded it and why."
>
    <section class="bk-card">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="bk-card-title">Wastage log</h2>
                <p class="mt-1 text-sm text-[#607a72]">Filter by the date the wastage was recorded.</p>
            </div>
            <a href="{{ route('staff.wastage.create') }}" class="bk-btn bk-btn-danger">Record wastage</a>
        </div>
        <form class="mt-5 flex flex-wrap items-end gap-2">
            <label class="bk-label">From<input name="from" value="{{ request('from') }}" type="date" class="bk-input"></label>
            <label class="bk-label">To<input name="to" value="{{ request('to') }}" type="date" class="bk-input"></label>
            <button class="bk-btn bk-btn-accent">Filter</button>
            @if (request()->filled('from') || request()->filled('to'))
                <a href="{{ route('admin.wastage') }}" class="bk-btn bk-btn-ghost">Clear</a>
            @endif
        </form>
        <div class="mt-6 overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="border-b border-[#d4e4e0] text-xs uppercase tracking-wider text-[#607a72]">
                    <tr>
                        <th class="pb-3">Date</th>
                        <th class="pb-3">Flower</th>
                        <th class="pb-3 text-right">Quantity removed</th>
                        <th class="pb-3 pl-6">Reason</th>
                        <th class="pb-3">Notes</th>
                        <th class="pb-3">Recorded by</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#dce9e6]">
                    @forelse ($adjustments as $adjustment)
                        <tr class="align-top">
                            <td class="py-4 text-[#607a72]">{{ $adjustment->created_at->format('d M Y') }}</td>
                            <td class="py-4 font-semibold">{{ $adjustment->inventoryItem->flower_type }}</td>
                            <td class="py-4 text-right font-semibold text-[#b84331]">{{ abs($adjustment->quantity_change) }}</td>
                            <td class="py-4 pl-6"><span class="rounded-full bg-[#f5dcd7] px-2.5 py-0.5 text-xs font-semibold capitalize text-[#b84331]">{{ $adjustment->reason }}</span></td>
                            <td class="max-w-xs py-4 text-[#607a72]">{{ $adjustment->notes ?? '-' }}</td>
                            <td class="py-4 text-[#607a72]">{{ $adjustment->user->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-sm text-[#607a72]">No wastage recorded for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-5">{{ $adjustments->links() }}</div>
    </section>
</x-admin.layout>
