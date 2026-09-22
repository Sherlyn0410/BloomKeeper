<x-layouts.auth title="Create account | BloomKeeper">
    <div class="space-y-2">
        <h1 class="text-2xl font-semibold tracking-tight text-[#29463e]">Create your account</h1>
        <p class="text-sm text-[#607a72]">Keep every stem, vase, and order beautifully organized.</p>
    </div>

    <form method="POST" action="{{ route('register.store') }}" class="mt-7 space-y-5">
        @csrf
        <x-auth-field name="name" label="Full name" autocomplete="name" placeholder="Your name" autofocus />
        <x-auth-field name="email" label="Email" type="email" autocomplete="email" placeholder="you@example.com" />
        <div class="space-y-2">
            <label for="role" class="block text-sm font-medium text-[#29463e]">Account type</label>
            <select id="role" name="role" class="block w-full rounded-lg border border-[#c6d8d4] bg-white px-3 py-2.5 text-sm text-[#29463e] shadow-sm outline-none transition focus:border-[#285443] focus:ring-2 focus:ring-[#285443]/15">
                <option value="florist" @selected(old('role', 'florist') === 'florist')>Florist</option>
                <option value="supplier" @selected(old('role') === 'supplier')>Supplier</option>
            </select>
            @error('role')
                <p class="text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>
        <x-auth-field name="password" label="Password" type="password" autocomplete="new-password" placeholder="At least 8 characters" password-strength />
        <x-auth-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" placeholder="Repeat your password" />

        <button type="submit" class="w-full rounded-lg bg-[#2d2d2d] px-4 py-3 text-sm font-medium text-white transition hover:bg-[#1c1c1c] focus:outline-none focus:ring-2 focus:ring-[#285443] focus:ring-offset-2">
            Create account
        </button>
    </form>

    <p class="mt-7 text-center text-sm text-[#607a72]">
        Already have an account?
        <a href="{{ route('login') }}" class="font-semibold text-[#285443] underline decoration-[#9cbcb4] underline-offset-4 hover:text-[#173d30]">Sign in</a>
    </p>
</x-layouts.auth>
