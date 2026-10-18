@php
    $topSeller = collect($rows)->sortByDesc('quantity')->first();
@endphp

<x-admin.layout
    title="Sales Report"
    description="Summarize flower demand and sales over a collection-date range and export the results for review."
>
    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        @foreach ([
            ['label' => 'Total sales', 'value' => 'RM '.number_format($totalSales, 2), 'note' => 'Excludes cancelled orders', 'tone' => 'text-[#285443]'],
            ['label' => 'Gross profit', 'value' => 'RM '.number_format($totalProfit, 2), 'note' => ($totalSales > 0 ? number_format($totalProfit / $totalSales * 100, 1).'% margin' : 'No sales yet').' · cost RM '.number_format($totalCost, 2), 'tone' => $totalProfit < 0 ? 'text-[#b84331]' : 'text-[#1c9b5f]'],
            ['label' => 'Quantity ordered', 'value' => number_format($totalQuantity), 'note' => 'Stems across all flowers', 'tone' => 'text-[#203d37]'],
            ['label' => 'Top seller', 'value' => $topSeller['flower_type'] ?? '-', 'note' => $topSeller ? number_format($topSeller['quantity']).' ordered' : 'No orders yet', 'tone' => 'text-[#d47f2a]'],
        ] as $stat)
            <div class="bk-card">
                <p class="text-sm text-[#49665f]">{{ $stat['label'] }}</p>
                <p class="mt-3 truncate text-2xl font-bold sm:text-3xl {{ $stat['tone'] }}">{{ $stat['value'] }}</p>
                <p class="mt-2 text-sm text-[#607a72]">{{ $stat['note'] }}</p>
            </div>
        @endforeach
    </div>

    <section class="bk-card mt-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="bk-card-title">Sales report</h2>
                <p class="mt-1 text-sm text-[#607a72]">Report period: <span class="font-semibold text-[#203d37]">{{ $reportPeriod }}</span></p>
            </div>
            <a href="{{ route('admin.reports.export', request()->query()) }}" class="bk-btn bk-btn-primary">Export Excel</a>
        </div>
        <x-admin.report-periods route="admin.reports" :sort="$sort" :direction="$direction" class="mt-5" />
        <form class="mt-3 flex flex-wrap items-end gap-2">
            <label class="bk-label">From<input name="from" value="{{ request('from') }}" type="date" class="bk-input"></label>
            <label class="bk-label">To<input name="to" value="{{ request('to') }}" type="date" class="bk-input"></label>
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <button class="bk-btn bk-btn-accent">Run report</button>
            @if (request()->hasAny(['from', 'to']))
                <a href="{{ route('admin.reports', ['sort' => $sort, 'direction' => $direction]) }}" class="bk-btn bk-btn-ghost">Clear</a>
            @endif
        </form>
        <div class="mt-6 overflow-x-auto">
            <table class="w-full min-w-[840px] text-left text-sm">
                <thead class="border-b border-[#d4e4e0] text-xs uppercase tracking-wider text-[#607a72]">
                    <tr>
                        <th class="pb-3">No.</th>
                        <x-admin.sort-header column="flower_type" :sort="$sort" :direction="$direction">Flower type</x-admin.sort-header>
                        <x-admin.sort-header column="quantity" :sort="$sort" :direction="$direction" default-direction="desc" class="text-right">Quantity ordered</x-admin.sort-header>
                        <x-admin.sort-header column="total_sales" :sort="$sort" :direction="$direction" default-direction="desc" class="text-right">Sales (RM)</x-admin.sort-header>
                        <th class="pb-3 text-right">Cost (RM)</th>
                        <x-admin.sort-header column="profit" :sort="$sort" :direction="$direction" default-direction="desc" class="text-right">Profit (RM)</x-admin.sort-header>
                        <th class="pb-3 text-right">Margin</th>
                        <th class="pb-3 pl-6">% of total qty</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#dce9e6]">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="py-4 text-[#607a72]">{{ $loop->iteration }}</td>
                            <td class="py-4 font-semibold">{{ $row['flower_type'] }}</td>
                            <td class="py-4 text-right font-semibold">{{ number_format($row['quantity']) }}</td>
                            <td class="py-4 text-right font-semibold">{{ number_format($row['total_sales'], 2) }}</td>
                            <td class="py-4 text-right text-[#607a72]">{{ number_format($row['total_cost'], 2) }}</td>
                            <td class="py-4 text-right font-semibold {{ $row['profit'] < 0 ? 'text-[#b84331]' : 'text-[#187849]' }}">{{ number_format($row['profit'], 2) }}</td>
                            <td class="py-4 text-right text-[#607a72]">{{ number_format($row['margin'] * 100, 1) }}%</td>
                            <td class="py-4 pl-6">
                                <div class="flex items-center gap-3">
                                    <div class="h-2 w-16 overflow-hidden rounded-full bg-[#d5e4e1]">
                                        <div class="h-full rounded-full bg-[#285443]" style="width: {{ round($row['share'] * 100, 2) }}%"></div>
                                    </div>
                                    <span class="text-[#607a72]">{{ number_format($row['share'] * 100, 2) }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-8 text-center text-sm text-[#607a72]">No order data matches this date range.</td></tr>
                    @endforelse
                </tbody>
                @if (count($rows) > 0)
                    <tfoot class="border-t-2 border-[#d4e4e0] font-bold">
                        <tr>
                            <td class="pt-4"></td>
                            <td class="pt-4">Total</td>
                            <td class="pt-4 text-right">{{ number_format($totalQuantity) }}</td>
                            <td class="pt-4 text-right">{{ number_format($totalSales, 2) }}</td>
                            <td class="pt-4 text-right">{{ number_format($totalCost, 2) }}</td>
                            <td class="pt-4 text-right {{ $totalProfit < 0 ? 'text-[#b84331]' : 'text-[#187849]' }}">{{ number_format($totalProfit, 2) }}</td>
                            <td class="pt-4 text-right">{{ $totalSales > 0 ? number_format($totalProfit / $totalSales * 100, 1) : '0.0' }}%</td>
                            <td class="pt-4 pl-6">100.00%</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>
</x-admin.layout>
