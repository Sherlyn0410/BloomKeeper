@props([
    'title',
    'description',
])

@if (auth()->user()->role === 'admin')
    <x-admin.layout :title="$title" :description="$description">{{ $slot }}</x-admin.layout>
@else
    <x-staff.layout :title="$title" :description="$description">{{ $slot }}</x-staff.layout>
@endif
