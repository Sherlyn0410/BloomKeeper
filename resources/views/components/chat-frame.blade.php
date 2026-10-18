@props([
    'title',
    'description' => null,
])

{{-- Bare page for the floating chat panel: no sidebar or header, just the chat filling the panel. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }} | BloomKeeper</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="h-full overflow-hidden bg-white font-sans text-[#203d37]">
        {{ $slot }}
    </body>
</html>
