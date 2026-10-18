@php
    $mostWasted = collect($rows)->sortByDesc('quantity')->first();
    $reasonTotals = collect(\App\Models\InventoryItem::WASTAGE_REASONS)
        ->mapWithKeys(fn (string $reason): array => [$reason => array_sum(array_map(fn (array $row): int => $row['reasons'][$reason], $rows))]);
@endphp

<x-admin.layout
    title="Wastage Report"
    description="See which flowers were lost to wastage, why, and what it cost over a date range."
>
    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        @foreach ([
            ['label' => 'Value lost', 'value' => 'RM '.number_format($totalValueLost, 2), 'note' => 'At the cost when recorded', 'tone' => 'text-[#b84331]'],
            ['label' => 'Stems removed', 'value' => number_format($totalQuantity), 'note' => 'Across all flowers', 'tone' => 'text-[#203d37]'],
            ['label' => 'Wastage records', 'value' => number_format($recordCount), 'note' => 'Entries logged by staff', 'tone' => 'text-[#285443]'],
            ['label' => 'Most wasted', 'value' => $mostWasted['flower_type'] ?? '-', 'note' => $mostWasted ? number_format($mostWasted['quantity']).' removed' : 'No wastage yet', 'tone' => 'text-[#d47f2a]'],
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
                <h2 class="bk-card-title">Wastage report</h2>
                <p class="mt-1 text-sm text-[#607a72]">Report period: <span class="font-semibold text-[#203d37]">{{ $reportPeriod }}</span></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.wastage', request()->only(['from', 'to'])) }}" class="bk-btn bk-btn-secondary">View history</a>
                <a href="{{ route('admin.reports.wastage.export', request()->query()) }}" class="bk-btn bk-btn-primary">Export Excel</a>
            </div>
        </div>
        <x-admin.report-periods route="admin.reports.wastage" :sort="$sort" :direction="$direction" class="mt-5" />
        <form class="mt-3 flex flex-wrap items-end gap-2">
            <label class="bk-label">From<input name="from" value="{{ request('from') }}" type="date" class="bk-input"></label>
            <label class="bk-label">To<input name="to" value="{{ request('to') }}" type="date" class="bk-input"></label>
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <button class="bk-btn bk-btn-accent">Run report</button>
            @if (request()->hasAny(['from', 'to']))
                <a href="{{ route('admin.reports.wastage', ['sort' => $sort, 'direction' => $direction]) }}" class="bk-btn bk-btn-ghost">Clear</a>
            @endif
        </form>

        <div class="mt-6 overflow-x-auto">
            <table class="w-full min-w-[820px] text-left text-sm">
                <thead class="border-b border-[#d4e4e0] text-xs uppercase tracking-wider text-[#607a72]">
                    <tr>
                        <th class="pb-3">No.</th>
                        <x-admin.sort-header column="flower_type" :sort="$sort" :direction="$direction">Flower type</x-admin.sort-header>
                        @foreach (\App\Models\InventoryItem::WASTAGE_REASONS as $reason)
                            <th class="pb-3 text-right">{{ ucfirst($reason) }}</th>
                        @endforeach
                        <x-admin.sort-header column="quantity" :sort="$sort" :direction="$direction" default-direction="desc" class="text-right">Total removed</x-admin.sort-header>
                        <x-admin.sort-header column="value_lost" :sort="$sort" :direction="$direction" default-direction="desc" class="text-right">Value lost (RM)</x-admin.sort-header>
                        <th class="pb-3 pl-6">% of wastage</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#dce9e6]">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="py-4 text-[#607a72]">{{ $loop->iteration }}</td>
                            <td class="py-4 font-semibold">{{ $row['flower_type'] }}</td>
                            @foreach ($row['reasons'] as $quantity)
                                <td class="py-4 text-right {{ $quantity > 0 ? 'text-[#1c2e28]' : 'text-[#b8cfca]' }}">{{ number_format($quantity) }}</td>
                            @endforeach
                            <td class="py-4 text-right font-semibold">{{ number_format($row['quantity']) }}</td>
                            <td class="py-4 text-right font-semibold text-[#b84331]">{{ number_format($row['value_lost'], 2) }}</td>
                            <td class="py-4 pl-6">
                                <div class="flex items-center gap-3">
                                    <div class="h-2 w-24 overflow-hidden rounded-full bg-[#f5dcd7]">
                                        <div class="h-full rounded-full bg-[#b84331]" style="width: {{ round($row['share'] * 100, 2) }}%"></div>
                                    </div>
                                    <span class="text-[#607a72]">{{ number_format($row['share'] * 100, 2) }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-8 text-center text-sm text-[#607a72]">No wastage recorded in this date range.</td></tr>
                    @endforelse
                </tbody>
                @if (count($rows) > 0)
                    <tfoot class="border-t-2 border-[#d4e4e0] font-bold">
                        <tr>
                            <td class="pt-4"></td>
                            <td class="pt-4">Total</td>
                            @foreach ($reasonTotals as $total)
                                <td class="pt-4 text-right">{{ number_format($total) }}</td>
                            @endforeach
                            <td class="pt-4 text-right">{{ number_format($totalQuantity) }}</td>
                            <td class="pt-4 text-right">{{ number_format($totalValueLost, 2) }}</td>
                            <td class="pt-4 pl-6">100.00%</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>
</x-admin.layout>
