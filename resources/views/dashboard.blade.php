<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Dashboard | BloomKeeper</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-[#c4e6e1] font-sans text-[#29463e]">
        <main class="mx-auto flex min-h-screen max-w-5xl flex-col gap-8 px-6 py-8">
            <header class="flex items-center justify-between">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 text-xl font-semibold">
                    <img src="{{ asset('images/bloomkeeper-logo.png') }}" alt="BloomKeeper" class="size-9 rounded-full object-cover">
                    <span>BloomKeeper</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-sm font-medium underline underline-offset-4" type="submit">Sign out</button>
                </form>
            </header>
            <section class="rounded-xl bg-[#e9f5f2] p-8 shadow-[0_14px_35px_rgba(36,73,64,0.12)]">
                <p class="text-sm text-[#607a72]">Your florist inventory workspace</p>
                <h1 class="mt-2 text-3xl font-semibold">Welcome, {{ auth()->user()->name }}.</h1>
                <p class="mt-2 inline-flex rounded-full bg-[#d6eee9] px-3 py-1 text-sm font-medium capitalize text-[#285443]">
                    {{ auth()->user()->role }} account
                </p>
                <p class="mt-3 text-[#607a72]">Your inventory dashboard is ready for the next bouquet.</p>
            </section>
        </main>
    </body>
</html>
