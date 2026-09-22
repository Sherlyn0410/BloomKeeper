@props(['name', 'label', 'type' => 'text', 'autocomplete' => null, 'passwordStrength' => false])

<div class="space-y-2">
    <label for="{{ $name }}" class="block text-sm font-medium text-[#29463e]">{{ $label }}</label>
    <div class="relative">
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ $type === 'password' ? '' : old($name) }}"
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($passwordStrength) data-password-strength-input @endif
            @if ($type === 'password') data-password-input @endif
            {{ $attributes->merge(['class' => 'block w-full rounded-lg border border-[#c6d8d4] bg-white px-3 py-2.5 text-sm text-[#29463e] shadow-sm outline-none transition placeholder:text-[#a4b3b0] focus:border-[#285443] focus:ring-2 focus:ring-[#285443]/15' . ($type === 'password' ? ' pr-10' : '')]) }}
        >
        @if ($type === 'password')
            <button type="button" data-password-toggle data-password-visible="false" aria-label="Show password" aria-pressed="false" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-[#607a72] transition hover:text-[#285443] focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[#285443]" title="Show password">
                <svg data-password-eye viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5" aria-hidden="true">
                    <path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z" />
                    <circle cx="12" cy="12" r="2.5" />
                </svg>
                <svg data-password-eye-off viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="hidden size-5" aria-hidden="true">
                    <path d="m3 3 18 18" />
                    <path d="M10.6 6.2A10.7 10.7 0 0 1 12 6c6 0 9.5 6 9.5 6a16.6 16.6 0 0 1-3.1 3.5M6.2 6.2C3.8 7.7 2.5 12 2.5 12s3.5 6 9.5 6c1.2 0 2.3-.2 3.3-.6" />
                    <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                </svg>
            </button>
        @endif
    </div>
    @if ($passwordStrength)
        <div class="space-y-1.5" aria-live="polite">
            <div class="flex gap-1" aria-hidden="true">
                @for ($segment = 0; $segment < 4; $segment++)
                    <span data-password-strength-segment class="h-1.5 flex-1 rounded-full bg-[#c6d8d4] transition-colors"></span>
                @endfor
            </div>
            <p data-password-strength-label class="text-xs text-[#607a72]">Use 8 or more characters.</p>
        </div>
    @endif
    @error($name)
        <p class="text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>
