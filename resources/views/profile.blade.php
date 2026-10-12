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
        <form method="POST" action="{{ route('profile.password') }}" class="mt-5 grid gap-3 sm:grid-cols-3">
            @csrf
            @method('PUT')
            <label class="{{ $labelClass }}">
                Current password
                <input name="current_password" type="password" required autocomplete="current-password" class="{{ $inputClass }}">
            </label>
            <label class="{{ $labelClass }}">
                New password
                <input name="password" type="password" required minlength="8" autocomplete="new-password" class="{{ $inputClass }}">
            </label>
            <label class="{{ $labelClass }}">
                Confirm new password
                <input name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" class="{{ $inputClass }}">
            </label>
            <div class="bk-form-actions">
                <button class="bk-btn bk-btn-primary">Change password</button>
            </div>
        </form>
    </section>
</x-workspace.layout>
