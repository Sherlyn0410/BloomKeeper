@props(['alerts', 'title' => 'Quick alerts', 'type' => null, 'viewAllUrl' => null, 'emptyMessage' => 'No active alerts right now.'])

{{-- $type forces every alert to show as 'low-stock' or 'spoilage'; leave it null to decide per item --}}
<aside class="bk-card min-w-0">
    <div class="flex items-center justify-between gap-4">
        <h2 class="bk-card-title">{{ $title }}</h2>
        @if ($viewAllUrl)
            <a href="{{ $viewAllUrl }}" class="shrink-0 text-sm font-semibold text-[#285443] underline underline-offset-4">View inventory</a>
        @endif
    </div>
    <div class="mt-5 space-y-3">
        @forelse ($alerts as $alert)
            @php
                $isLowStock = $type === null ? $alert->quantity <= $alert->low_stock_threshold : $type === 'low-stock';
                $cardBg = $isLowStock ? 'bg-[#fff8ec]' : 'bg-[#fbeae7]';
                $badgeBg = $isLowStock ? 'bg-[#fff0d5]' : 'bg-[#f5dcd7]';
                $textColor = $isLowStock ? 'text-[#a76513]' : 'text-[#b84331]';
            @endphp
            <div class="rounded-2xl {{ $cardBg }} p-3">
                <div class="flex items-start justify-between gap-2">
                    <p class="font-semibold text-[#1c2e28]">{{ $alert->flower_type }}</p>
                    <span class="shrink-0 rounded-full {{ $badgeBg }} px-2.5 py-1 text-xs font-semibold {{ $textColor }}">
                        {{ $isLowStock ? 'Low stock' : 'Spoilage' }}
                    </span>
                </div>
                <p class="mt-1 flex items-center justify-between gap-2 text-sm {{ $textColor }}">
                    @if ($isLowStock)
                        <span>{{ $alert->quantity }} remaining</span>
                        <a href="{{ route((auth()->user()->can('manage-users') ? 'admin' : 'staff').'.purchase-orders', $alert->reorderQuery()) }}#create-purchase-order" class="shrink-0 text-xs font-semibold text-[#285443] underline underline-offset-2">Reorder</a>
                    @else
                        Check by {{ $alert->date_received->copy()->addDays($alert->shelf_life_days)->format('d M Y') }}
                    @endif
                </p>
            </div>
        @empty
            <p class="text-sm text-[#607a72]">{{ $emptyMessage }}</p>
        @endforelse
    </div>
</aside>
