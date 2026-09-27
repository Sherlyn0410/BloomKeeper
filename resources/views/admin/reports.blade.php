@php
    $topSeller = collect($rows)->sortByDesc('quantity')->first();
@endphp

<x-admin.layout
    title="Reports"
    description="Summarize flower demand over a collection-date range and export the results for review."
>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'Total sales', 'value' => 'RM '.number_format($totalSales, 2), 'note' => 'Excludes cancelled orders', 'tone' => 'text-[#285443]'],
            ['label' => 'Quantity ordered', 'value' => number_format($totalQuantity), 'note' => 'Stems across all flowers', 'tone' => 'text-[#203d37]'],
            ['label' => 'Flower types', 'value' => count($rows), 'note' => 'With orders in this period', 'tone' => 'text-[#1c9b5f]'],
            ['label' => 'Top seller', 'value' => $topSeller['flower_type'] ?? '-', 'note' => $topSeller ? number_format($topSeller['quantity']).' ordered' : 'No orders yet', 'tone' => 'text-[#d47f2a]'],
        ] as $stat)
            <div class="rounded-3xl bg-[#edf7f5]/90 p-5 shadow-[0_12px_30px_rgba(36,73,64,0.1)]">
                <p class="text-sm text-[#49665f]">{{ $stat['label'] }}</p>
                <p class="mt-3 truncate text-3xl font-bold {{ $stat['tone'] }}">{{ $stat['value'] }}</p>
                <p class="mt-2 text-sm text-[#607a72]">{{ $stat['note'] }}</p>
            </div>
        @endforeach
    </div>

    <section class="mt-6 rounded-[28px] bg-[#edf7f5]/90 p-5 shadow-[0_12px_30px_rgba(36,73,64,0.1)] sm:p-7">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="font-serif text-2xl font-bold">Sales report</h2>
                <p class="mt-1 text-sm text-[#607a72]">Report period: <span class="font-semibold text-[#203d37]">{{ $reportPeriod }}</span></p>
            </div>
            <a href="{{ route('admin.reports.export', request()->query()) }}" class="rounded-lg bg-[#285443] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#1f4435]">Export Excel</a>
        </div>
        <form class="mt-5 flex flex-wrap items-end gap-2">
            <label class="flex flex-col gap-1 text-xs font-semibold text-[#49665f]">From<input name="from" value="{{ request('from') }}" type="date" class="rounded-lg border-0 p-2 text-sm font-normal"></label>
            <label class="flex flex-col gap-1 text-xs font-semibold text-[#49665f]">To<input name="to" value="{{ request('to') }}" type="date" class="rounded-lg border-0 p-2 text-sm font-normal"></label>
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <button class="rounded-lg bg-[#e3953d] px-3 py-2 font-semibold text-white transition hover:bg-[#d47f2a]">Run report</button>
            @if (request()->hasAny(['from', 'to']))
                <a href="{{ route('admin.reports', ['sort' => $sort, 'direction' => $direction]) }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-[#49665f] transition hover:bg-[#dce9e6]">Clear</a>
            @endif
        </form>
        <div class="mt-6 overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="border-b border-[#d4e4e0] text-xs uppercase tracking-wider text-[#607a72]">
                    <tr>
                        <th class="pb-3">No.</th>
                        <x-admin.sort-header column="flower_type" :sort="$sort" :direction="$direction">Flower type</x-admin.sort-header>
                        <x-admin.sort-header column="quantity" :sort="$sort" :direction="$direction" default-direction="desc" class="text-right">Quantity ordered</x-admin.sort-header>
                        <th class="pb-3 text-right">Unit price (RM)</th>
                        <x-admin.sort-header column="total_sales" :sort="$sort" :direction="$direction" default-direction="desc" class="text-right">Total sales (RM)</x-admin.sort-header>
                        <th class="pb-3 pl-6">% of total qty</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#dce9e6]">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="py-4 text-[#607a72]">{{ $loop->iteration }}</td>
                            <td class="py-4 font-semibold">{{ $row['flower_type'] }}</td>
                            <td class="py-4 text-right font-semibold">{{ number_format($row['quantity']) }}</td>
                            <td class="py-4 text-right text-[#607a72]">{{ number_format($row['unit_price'], 2) }}</td>
                            <td class="py-4 text-right font-semibold">{{ number_format($row['total_sales'], 2) }}</td>
                            <td class="py-4 pl-6">
                                <div class="flex items-center gap-3">
                                    <div class="h-2 w-24 overflow-hidden rounded-full bg-[#d5e4e1]">
                                        <div class="h-full rounded-full bg-[#285443]" style="width: {{ round($row['share'] * 100, 2) }}%"></div>
                                    </div>
                                    <span class="text-[#607a72]">{{ number_format($row['share'] * 100, 2) }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-sm text-[#607a72]">No order data matches this date range.</td></tr>
                    @endforelse
                </tbody>
                @if (count($rows) > 0)
                    <tfoot class="border-t-2 border-[#d4e4e0] font-bold">
                        <tr>
                            <td class="pt-4"></td>
                            <td class="pt-4">Total</td>
                            <td class="pt-4 text-right">{{ number_format($totalQuantity) }}</td>
                            <td class="pt-4"></td>
                            <td class="pt-4 text-right">{{ number_format($totalSales, 2) }}</td>
                            <td class="pt-4 pl-6">100.00%</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>
</x-admin.layout>
