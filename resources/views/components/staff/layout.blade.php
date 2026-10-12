@props([
    'title',
    'description',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }} | BloomKeeper</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-[#c4e6e1] font-sans text-[#203d37]">
        <div class="mx-auto flex min-h-screen w-full max-w-6xl flex-col gap-6 p-3 sm:p-6">
            <header class="flex flex-wrap items-center justify-between gap-4 rounded-[28px] bg-[#e9f5f2]/90 px-4 py-3 shadow-[0_18px_45px_rgba(36,73,64,0.12)] sm:px-5">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                    <img src="{{ asset('images/bloomkeeper-logo.png') }}" alt="BloomKeeper" class="size-10 rounded-full object-cover">
                    <span>
                        <span class="block font-serif text-lg font-bold leading-tight">BloomKeeper</span>
                        <span class="text-xs capitalize text-[#607a72]">{{ auth()->user()->role }} workspace</span>
                    </span>
                </a>
                <nav class="flex flex-wrap items-center gap-1.5 text-sm font-semibold" aria-label="Workspace">
                    @php
                        $navLinks = auth()->user()->can('manage-customers')
                            ? ['dashboard' => 'Dashboard', 'staff.orders.index' => 'Orders', 'staff.orders.create' => 'New order', 'staff.customers.index' => 'Customers', 'staff.wastage.create' => 'Wastage', 'profile.edit' => 'Profile']
                            : ['dashboard' => 'Dashboard', 'profile.edit' => 'Profile'];
                    @endphp
                    @foreach ($navLinks as $routeName => $label)
                        <a href="{{ route($routeName) }}" @if (request()->routeIs($routeName)) aria-current="page" @endif @class([
                            'rounded-full px-4 py-2 transition',
                            'bg-[#285443] text-white' => request()->routeIs($routeName),
                            'text-[#49665f] hover:bg-[#d5e4e1]' => ! request()->routeIs($routeName),
                        ])>{{ $label }}</a>
                    @endforeach
                    <form method="POST" action="{{ route('logout') }}" class="ml-1">
                        @csrf
                        <button class="bk-btn bk-btn-ghost" type="submit">Sign out</button>
                    </form>
                </nav>
            </header>

            <main class="px-1 pb-8 sm:px-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.25em] text-[#49665f]">Staff workspace</p>
                    <h1 class="mt-2 font-serif text-3xl font-bold tracking-tight sm:text-4xl">{{ $title }}</h1>
                    <p class="mt-2 max-w-2xl text-sm text-[#607a72]">{{ $description }}</p>
                </div>

                @if (session('status'))
                    <p class="mt-6 rounded-2xl bg-[#d7f0df] px-4 py-3 text-sm text-[#187849]">{{ session('status') }}</p>
                @endif
                @if ($errors->any())
                    <div class="mt-6 rounded-2xl bg-[#f5dcd7] px-4 py-3 text-sm text-[#b84331]">
                        <ul class="list-inside list-disc space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="mt-8 space-y-6">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
