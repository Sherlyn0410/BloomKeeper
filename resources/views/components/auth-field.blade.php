@props(['name', 'label', 'type' => 'text', 'autocomplete' => null])

<div class="space-y-2">
    <label for="{{ $name }}" class="block text-sm font-medium text-[#29463e]">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $type === 'password' ? '' : old($name) }}"
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        {{ $attributes->merge(['class' => 'block w-full rounded-lg border border-[#c6d8d4] bg-white px-3 py-2.5 text-sm text-[#29463e] shadow-sm outline-none transition placeholder:text-[#a4b3b0] focus:border-[#285443] focus:ring-2 focus:ring-[#285443]/15']) }}
    >
    @error($name)
        <p class="text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>
