@props([
    'navigation',
    'subtitle',
    'roleLabel',
    'homeRoute',
    'mobile' => false,
])

@if ($mobile)
    {{-- Small screens: a top bar with a menu button that slides the sidebar in. --}}
    <div class="flex shrink-0 items-center justify-between gap-3 border-b border-[#d4e4e0] bg-[#e9f5f2] px-4 py-2.5 shadow-[0_4px_12px_rgba(36,73,64,0.06)] sm:px-7 lg:hidden">
        <a href="{{ route($homeRoute) }}" class="flex items-center gap-2.5">
            <img src="{{ asset('images/bloomkeeper-logo.png') }}" alt="BloomKeeper" class="size-9 rounded-full object-cover">
            <span class="font-serif text-lg font-bold leading-tight">BloomKeeper</span>
        </a>
        <button
            type="button"
            class="flex size-10 cursor-pointer items-center justify-center rounded-xl text-[#285443] transition hover:bg-[#d5e4e1]"
            aria-label="Open menu"
            aria-controls="mobile-sidebar"
            onclick="document.getElementById('mobile-sidebar').showModal()"
        >
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
        </button>
    </div>

    <dialog
        id="mobile-sidebar"
        aria-label="Menu"
        class="m-0 h-dvh max-h-none w-80 max-w-[85vw] bg-transparent p-3 backdrop:bg-[#1c2e28]/40 transition duration-200 starting:open:-translate-x-full lg:hidden"
        onclick="if (event.target === this) this.close()"
    >
        <div class="relative flex h-full flex-col rounded-[28px] bg-[#e9f5f2] p-4 shadow-[0_18px_45px_rgba(36,73,64,0.2)]">
            <button
                type="button"
                class="absolute top-3 right-3 flex size-9 cursor-pointer items-center justify-center rounded-full text-[#607a72] transition hover:bg-[#d5e4e1] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#285443]/40"
                aria-label="Close menu"
                onclick="this.closest('dialog').close()"
            >
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
            </button>
            <x-sidebar-content :navigation="$navigation" :subtitle="$subtitle" :role-label="$roleLabel" :home-route="$homeRoute" />
        </div>
    </dialog>
@else
    <aside class="hidden min-h-0 w-64 shrink-0 flex-col rounded-[28px] bg-[#e9f5f2]/90 p-4 shadow-[0_18px_45px_rgba(36,73,64,0.12)] lg:flex">
        <x-sidebar-content :navigation="$navigation" :subtitle="$subtitle" :role-label="$roleLabel" :home-route="$homeRoute" />
    </aside>
@endif
