<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'BloomKeeper' }}</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-[#c4e6e1] font-sans text-[#29463e] antialiased">
        <main class="flex min-h-screen items-center justify-center px-4 py-10">
            <section class="w-full max-w-md rounded-xl bg-[#e9f5f2] p-7 shadow-[0_14px_35px_rgba(36,73,64,0.18)] sm:p-8">
                <a href="{{ route('login') }}" class="mb-7 flex items-center gap-3 text-xl font-semibold tracking-tight text-[#29463e]">
                    <img src="{{ asset('images/bloomkeeper-logo.png') }}" alt="BloomKeeper" class="size-9 rounded-full object-cover">
                    <span>BloomKeeper</span>
                </a>

                {{ $slot }}
            </section>
        </main>
    </body>
</html>
