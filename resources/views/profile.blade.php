@php
    $inputClass = 'bk-input';
    $labelClass = 'bk-label';
@endphp

<x-workspace.layout
    title="My profile"
    description="Update your name and email, or change your password."
>
    <section class="bk-card">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="bk-card-title">Account details</h2>
            <span class="rounded-full bg-[#d6eee9] px-3 py-1 text-sm font-medium capitalize text-[#285443]">{{ $user->role }} account</span>
        </div>
        <form method="POST" action="{{ route('profile.update') }}" class="mt-5 grid gap-3 sm:grid-cols-2">
            @csrf
            @method('PUT')
            <label class="{{ $labelClass }}">
                Name
                <input name="name" value="{{ old('name', $user->name) }}" required maxlength="255" class="{{ $inputClass }}">
            </label>
            <label class="{{ $labelClass }}">
                Email
                <input name="email" value="{{ old('email', $user->email) }}" type="email" required maxlength="255" class="{{ $inputClass }}">
            </label>
            <p class="text-xs text-[#607a72] sm:col-span-2">Changing your email means you will need to verify the new address before continuing.</p>
            <div class="bk-form-actions">
                <button class="bk-btn bk-btn-primary">Save details</button>
            </div>
        </form>
    </section>

    <section class="bk-card">
        <h2 class="bk-card-title">Change password</h2>
        @if ($errors->password->any())
            <ul class="mt-4 list-inside list-disc space-y-1 rounded-lg bg-[#f5dcd7] px-4 py-3 text-sm text-[#b84331]">
                @foreach ($errors->password->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
        <form method="POST" action="{{ route('profile.password') }}" class="mt-5 grid items-start gap-3 sm:grid-cols-3">
            @csrf
            @method('PUT')
            <x-password-input name="current_password" label="Current password" autocomplete="current-password" required />
            <x-password-input name="password" label="New password" required minlength="8" password-strength />
            <x-password-input name="password_confirmation" label="Confirm new password" required minlength="8" />
            <div class="bk-form-actions">
                <button class="bk-btn bk-btn-primary">Change password</button>
            </div>
        </form>
    </section>
</x-workspace.layout>
