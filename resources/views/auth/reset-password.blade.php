<x-layouts.auth title="Choose a new password | BloomKeeper">
    <div class="space-y-2">
        <h1 class="text-2xl font-semibold tracking-tight text-[#29463e]">Choose a new password</h1>
        <p class="text-sm text-[#607a72]">Make it long and memorable so your inventory stays protected.</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="mt-7 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-auth-field name="email" label="Email" type="email" autocomplete="email" placeholder="you@example.com" :value="$email" autofocus />
        <x-auth-field name="password" label="New password" type="password" autocomplete="new-password" placeholder="At least 8 characters" password-strength />
        <x-auth-field name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" placeholder="Repeat your password" />
        <button type="submit" class="w-full rounded-lg bg-[#2d2d2d] px-4 py-3 text-sm font-medium text-white transition hover:bg-[#1c1c1c] focus:outline-none focus:ring-2 focus:ring-[#285443] focus:ring-offset-2">
            Reset password
        </button>
    </form>
</x-layouts.auth>
