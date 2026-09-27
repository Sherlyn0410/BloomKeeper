@props([
    'column',
    'sort',
    'direction',
    'defaultDirection' => 'asc',
])

@php
    $isActive = $sort === $column;
    $nextDirection = $isActive ? ($direction === 'asc' ? 'desc' : 'asc') : $defaultDirection;
    $url = request()->fullUrlWithQuery(['sort' => $column, 'direction' => $nextDirection]);
@endphp

<th {{ $attributes->merge(['class' => 'pb-3']) }} @if ($isActive) aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif>
    <a href="{{ $url }}" @class([
        'group inline-flex items-center gap-1 uppercase tracking-wider transition hover:text-[#203d37]',
        'text-[#203d37]' => $isActive,
    ])>
        <span>{{ $slot }}</span>
        <svg class="size-3.5 shrink-0" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
            <path d="M8 2.5 11.5 6.5h-7L8 2.5Z" @class(['opacity-100' => $isActive && $direction === 'asc', 'opacity-30 group-hover:opacity-60' => ! ($isActive && $direction === 'asc')]) />
            <path d="M8 13.5 4.5 9.5h7L8 13.5Z" @class(['opacity-100' => $isActive && $direction === 'desc', 'opacity-30 group-hover:opacity-60' => ! ($isActive && $direction === 'desc')]) />
        </svg>
    </a>
</th>
