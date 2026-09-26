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
    <body class="h-screen overflow-hidden bg-[#c4e6e1] font-sans text-[#203d37]">
        <div class="mx-auto flex h-screen w-full max-w-[1500px] gap-4 overflow-hidden p-3 sm:gap-6 sm:p-6">
            <x-admin.sidebar />
            <main class="min-w-0 flex-1 overflow-y-auto overflow-x-hidden py-2 lg:py-4">
                <header class="flex flex-wrap items-end justify-between gap-4 px-1 sm:px-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-[#49665f]">Administrator view</p>
                        <h1 class="mt-2 font-serif text-3xl font-bold tracking-tight sm:text-4xl">{{ $title }}</h1>
                        <p class="mt-2 max-w-2xl text-sm text-[#607a72]">{{ $description }}</p>
                    </div>
                    <a href="{{ route('admin.dashboard') }}" class="rounded-full bg-white/70 px-4 py-2 text-sm font-semibold text-[#49665f] transition hover:bg-white">Back to dashboard</a>
                </header>

                @if (session('status'))
                    <p class="mt-6 rounded-2xl bg-[#d7f0df] px-4 py-3 text-sm text-[#187849]">{{ session('status') }}</p>
                @endif
                @if ($errors->any())
                    <p class="mt-6 rounded-2xl bg-[#f5dcd7] px-4 py-3 text-sm text-[#b84331]">{{ $errors->first() }}</p>
                @endif

                <div class="mt-8 space-y-6 px-1 pb-8 sm:px-3">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
