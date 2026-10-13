@props([
    'navigation',
    'subtitle',
    'roleLabel',
    'homeRoute',
])

@php
    $user = auth()->user();
    $initials = Str::of($user->name)->explode(' ')->filter()->take(2)->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->join('');
@endphp

<aside class="hidden min-h-0 w-64 shrink-0 flex-col rounded-[28px] bg-[#e9f5f2]/90 p-4 shadow-[0_18px_45px_rgba(36,73,64,0.12)] lg:flex">
    <a href="{{ route($homeRoute) }}" class="flex items-center gap-3 px-2 pt-1">
        <img src="{{ asset('images/bloomkeeper-logo.png') }}" alt="BloomKeeper" class="size-11 rounded-full object-cover">
        <span>
            <span class="block font-serif text-lg font-bold leading-tight">BloomKeeper</span>
            <span class="text-xs text-[#607a72]">{{ $subtitle }}</span>
        </span>
    </a>

    <nav class="mt-6 min-h-0 flex-1 space-y-1 overflow-y-auto pr-1 text-sm font-medium" aria-label="{{ $subtitle }}">
        @foreach ($navigation as $item)
            @if (isset($item['children']))
                @php $isGroupActive = collect($item['children'])->contains('active', true); @endphp
                <details class="group" @if ($isGroupActive) open @endif>
                    <summary @class([
                        'flex cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-2.5 transition hover:bg-[#d5e4e1] [&::-webkit-details-marker]:hidden',
                        'text-[#203d37]' => $isGroupActive,
                        'text-[#49665f]' => ! $isGroupActive,
                    ])>
                        <span @class(['size-2 rounded-full', 'bg-[#e3953d]' => $isGroupActive, 'bg-[#b8cfca]' => ! $isGroupActive])></span>
                        <span class="flex-1">{{ $item['label'] }}</span>
                        <svg class="size-4 text-[#8aa39c] transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                    </summary>
                    <div class="mt-1 mb-2 ml-[1.1rem] space-y-0.5 border-l border-[#cfe0dc] pl-3">
                        @foreach ($item['children'] as $child)
                            <a
                                href="{{ route($child['route'], $child['params'] ?? []) }}"
                                @if ($child['active']) aria-current="page" @endif
                                @class([
                                    'block rounded-lg px-3 py-2 text-[13px] transition',
                                    'bg-[#d5e4e1] font-semibold text-[#203d37]' => $child['active'],
                                    'text-[#607a72] hover:bg-[#d5e4e1]/60 hover:text-[#203d37]' => ! $child['active'],
                                ])
                            >{{ $child['label'] }}</a>
                        @endforeach
                    </div>
                </details>
            @else
                <a
                    href="{{ route($item['route']) }}"
                    @if ($item['active']) aria-current="page" @endif
                    @class([
                        'flex items-center gap-3 rounded-xl px-3 py-2.5 transition',
                        'bg-[#d5e4e1] text-[#203d37]' => $item['active'],
                        'text-[#49665f] hover:bg-[#d5e4e1]' => ! $item['active'],
                    ])
                >
                    <span @class(['size-2 rounded-full', 'bg-[#e3953d]' => $item['active'], 'bg-[#b8cfca]' => ! $item['active']])></span>
                    {{ $item['label'] }}
                </a>
            @endif
        @endforeach
    </nav>

    <div class="mt-4 shrink-0 rounded-2xl bg-white/70 p-3">
        <div class="flex items-center gap-3">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#285443] text-xs font-bold text-white">{{ $initials }}</span>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-[#203d37]">{{ $user->name }}</p>
                <p class="text-xs text-[#607a72]">{{ $roleLabel }}</p>
            </div>
        </div>
        <div class="mt-3 flex gap-2 text-xs font-semibold">
            <a href="{{ route('profile.edit') }}" @class([
                'flex-1 rounded-lg px-2 py-1.5 text-center transition',
                'bg-[#285443] text-white' => request()->routeIs('profile.*'),
                'bg-[#edf7f5] text-[#285443] hover:bg-[#d5e4e1]' => ! request()->routeIs('profile.*'),
            ])>My profile</a>
            <form method="POST" action="{{ route('logout') }}" class="flex-1">
                @csrf
                <button type="submit" class="w-full rounded-lg px-2 py-1.5 text-[#49665f] transition hover:bg-[#f5dcd7] hover:text-[#b84331]">Sign out</button>
            </form>
        </div>
    </div>
</aside>
