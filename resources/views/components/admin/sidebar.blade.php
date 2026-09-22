<aside class="hidden w-64 shrink-0 flex-col rounded-[28px] bg-[#e9f5f2]/90 p-5 shadow-[0_18px_45px_rgba(36,73,64,0.12)] lg:flex">
    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-2">
        <img src="{{ asset('images/bloomkeeper-logo.png') }}" alt="BloomKeeper" class="size-11 rounded-full object-cover">
        <span>
            <span class="block font-serif text-lg font-bold">BloomKeeper</span>
            <span class="text-xs text-[#607a72]">Admin console</span>
        </span>
    </a>

    <nav class="mt-10 space-y-2 text-sm font-medium">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 {{ request()->routeIs('admin.dashboard') ? 'bg-[#d5e4e1] text-[#203d37]' : 'text-[#49665f] transition hover:bg-[#d5e4e1]' }}">
            <span class="{{ request()->routeIs('admin.dashboard') ? 'text-[#e3953d]' : 'text-[#b8cfca]' }}">●</span>Overview
        </a>
        <a href="{{ route('admin.users.index') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 {{ request()->routeIs('admin.users.*') ? 'bg-[#d5e4e1] text-[#203d37]' : 'text-[#49665f] transition hover:bg-[#d5e4e1]' }}">
            <span class="{{ request()->routeIs('admin.users.*') ? 'text-[#e3953d]' : 'text-[#b8cfca]' }}">●</span>Manage users
        </a>
        <a href="{{ route('admin.operations') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 {{ request()->routeIs('admin.operations', 'admin.inventory.*', 'admin.orders.*', 'admin.purchase-orders.*', 'admin.settings.*', 'admin.reports.*') ? 'bg-[#d5e4e1] text-[#203d37]' : 'text-[#49665f] transition hover:bg-[#d5e4e1]' }}">
            <span class="{{ request()->routeIs('admin.operations', 'admin.inventory.*', 'admin.orders.*', 'admin.purchase-orders.*', 'admin.settings.*', 'admin.reports.*') ? 'text-[#e3953d]' : 'text-[#b8cfca]' }}">●</span>Operations
        </a>
    </nav>

    <div class="mt-auto rounded-2xl bg-white/70 p-4 text-sm">
        <p class="text-[#607a72]">Signed in as</p>
        <p class="mt-1 font-semibold">{{ auth()->user()->name }}</p>
        <form method="POST" action="{{ route('logout') }}" class="mt-3">
            @csrf
            <button type="submit" class="text-[#49665f] underline underline-offset-4">Sign out</button>
        </form>
    </div>
</aside>
