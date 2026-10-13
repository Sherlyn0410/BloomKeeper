@props(['alerts'])

<aside class="bk-card">
    <h2 class="bk-card-title">Quick alerts</h2>
    <div class="mt-5 space-y-3">
        @forelse ($alerts as $alert)
            @php
                $isLowStock = $alert->quantity <= $alert->low_stock_threshold;
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
                <p class="mt-1 text-sm {{ $textColor }}">
                    @if ($isLowStock)
                        {{ $alert->quantity }} remaining
                    @else
                        Check by {{ $alert->date_received->copy()->addDays($alert->shelf_life_days)->format('d M Y') }}
                    @endif
                </p>
            </div>
        @empty
            <p class="text-sm text-[#607a72]">No active alerts right now.</p>
        @endforelse
    </div>
</aside>
