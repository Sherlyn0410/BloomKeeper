@props(['route', 'sort', 'direction'])

@php
    // Quick periods in Malaysia time; each one just fills in the From and To dates.
    $shopToday = now('Asia/Kuala_Lumpur');
    $periods = [
        'Daily' => ['from' => $shopToday->toDateString(), 'to' => $shopToday->toDateString()],
        'Weekly' => ['from' => $shopToday->copy()->startOfWeek()->toDateString(), 'to' => $shopToday->copy()->endOfWeek()->toDateString()],
        'Monthly' => ['from' => $shopToday->copy()->startOfMonth()->toDateString(), 'to' => $shopToday->copy()->endOfMonth()->toDateString()],
    ];
    $isAllTime = ! request()->filled('from') && ! request()->filled('to');

    // Work out whether the chosen dates are a whole day, week, or month, so the arrows can step by that period.
    $activePeriod = null;
    $periodLabel = null;
    $previousRange = null;
    $nextRange = null;
    try {
        $rangeFrom = request()->filled('from') ? \Illuminate\Support\Carbon::parse(request('from'))->startOfDay() : null;
        $rangeTo = request()->filled('to') ? \Illuminate\Support\Carbon::parse(request('to'))->startOfDay() : null;
    } catch (\Throwable) {
        $rangeFrom = $rangeTo = null;
    }
    if ($rangeFrom && $rangeTo) {
        $activePeriod = match (true) {
            $rangeFrom->equalTo($rangeTo) => 'Daily',
            $rangeFrom->isMonday() && $rangeTo->equalTo($rangeFrom->copy()->addDays(6)) => 'Weekly',
            $rangeFrom->day === 1 && $rangeTo->equalTo($rangeFrom->copy()->endOfMonth()->startOfDay()) => 'Monthly',
            default => null,
        };
        $shift = fn (int $step): array => match ($activePeriod) {
            'Daily' => ['from' => $rangeFrom->copy()->addDays($step)->toDateString(), 'to' => $rangeFrom->copy()->addDays($step)->toDateString()],
            'Weekly' => ['from' => $rangeFrom->copy()->addWeeks($step)->toDateString(), 'to' => $rangeFrom->copy()->addWeeks($step)->addDays(6)->toDateString()],
            'Monthly' => ['from' => $rangeFrom->copy()->addMonthsNoOverflow($step)->startOfMonth()->toDateString(), 'to' => $rangeFrom->copy()->addMonthsNoOverflow($step)->endOfMonth()->toDateString()],
        };
        if ($activePeriod) {
            $previousRange = $shift(-1);
            $nextRange = $shift(1);
            $periodLabel = match ($activePeriod) {
                'Daily' => $rangeFrom->format('d M Y'),
                'Weekly' => $rangeFrom->format($rangeFrom->month === $rangeTo->month ? 'd' : 'd M').' – '.$rangeTo->format('d M Y'),
                'Monthly' => $rangeFrom->format('F Y'),
            };
        }
    }
    $isFuture = fn (?array $range): bool => $range !== null && $range['from'] > $shopToday->toDateString();
@endphp

{{-- Daily, Weekly, Monthly, and All time shortcuts, with arrows that step through the chosen period. --}}
<nav {{ $attributes->merge(['class' => 'flex flex-wrap gap-2']) }} aria-label="Report period">
    @foreach ($periods as $label => $range)
        @php
            $isActive = $activePeriod === $label;
        @endphp
        <a
            href="{{ route($route, [...$range, 'sort' => $sort, 'direction' => $direction]) }}"
            title="{{ \Illuminate\Support\Carbon::parse($range['from'])->format('d M Y') }}{{ $range['from'] !== $range['to'] ? ' – '.\Illuminate\Support\Carbon::parse($range['to'])->format('d M Y') : '' }}"
            @if ($isActive) aria-current="page" @endif
            @class([
                'rounded-full px-4 py-1.5 text-sm font-semibold transition',
                'bg-[#285443] text-white' => $isActive,
                'bg-white/70 text-[#49665f] hover:bg-white' => ! $isActive,
            ])
        >{{ $label }}</a>
    @endforeach
    <a
        href="{{ route($route, ['sort' => $sort, 'direction' => $direction]) }}"
        @if ($isAllTime) aria-current="page" @endif
        @class([
            'rounded-full px-4 py-1.5 text-sm font-semibold transition',
            'bg-[#285443] text-white' => $isAllTime,
            'bg-white/70 text-[#49665f] hover:bg-white' => ! $isAllTime,
        ])
    >All time</a>
    @if ($activePeriod)
        <div class="flex items-center gap-1 sm:ml-auto">
            <a href="{{ route($route, [...$previousRange, 'sort' => $sort, 'direction' => $direction]) }}" class="flex size-8 items-center justify-center rounded-full text-[#285443] transition hover:bg-white" aria-label="Previous period" title="Previous period">
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" /></svg>
            </a>
            <span class="min-w-36 text-center text-sm font-semibold text-[#203d37]">{{ $periodLabel }}</span>
            @if ($isFuture($nextRange))
                <span class="flex size-8 items-center justify-center text-[#c6d8d4]" aria-hidden="true">
                    <svg class="size-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                </span>
            @else
                <a href="{{ route($route, [...$nextRange, 'sort' => $sort, 'direction' => $direction]) }}" class="flex size-8 items-center justify-center rounded-full text-[#285443] transition hover:bg-white" aria-label="Next period" title="Next period">
                    <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                </a>
            @endif
        </div>
    @endif
</nav>
