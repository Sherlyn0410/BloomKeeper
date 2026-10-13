@props(['navigation'])

@php
    $links = collect($navigation)->flatMap(fn (array $item): array => $item['children'] ?? [$item]);
@endphp

<nav class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1 text-sm font-semibold lg:hidden" aria-label="Pages">
    @foreach ($links as $link)
        <a
            href="{{ route($link['route'], $link['params'] ?? []) }}"
            @if ($link['active']) aria-current="page" @endif
            @class([
                'shrink-0 rounded-full px-3.5 py-1.5 transition',
                'bg-[#285443] text-white' => $link['active'],
                'bg-white/70 text-[#49665f] hover:bg-white' => ! $link['active'],
            ])
        >{{ $link['label'] }}</a>
    @endforeach
    <a href="{{ route('profile.edit') }}" @class(['shrink-0 rounded-full px-3.5 py-1.5 transition', 'bg-[#285443] text-white' => request()->routeIs('profile.*'), 'bg-white/70 text-[#49665f] hover:bg-white' => ! request()->routeIs('profile.*')])>Profile</a>
</nav>
