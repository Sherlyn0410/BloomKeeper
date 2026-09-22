<x-layouts.auth title="Sign in | BloomKeeper">
    <div class="space-y-2">
        <h1 class="text-2xl font-semibold tracking-tight text-[#29463e]">Welcome back</h1>
        <p class="text-sm text-[#607a72]">Sign in to manage your flower inventory.</p>
    </div>

    @if (session('status'))
        <p class="mt-5 rounded-lg bg-[#d6eee9] px-3 py-2 text-sm text-[#285443]">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="mt-7 space-y-5">
        @csrf
        <x-auth-field name="email" label="Email" type="email" autocomplete="email" placeholder="you@example.com" autofocus />
        <x-auth-field name="password" label="Password" type="password" autocomplete="current-password" placeholder="Enter your password" />

        <div class="flex items-center justify-between gap-4 text-sm">
            <label class="flex items-center gap-2 text-[#607a72]">
                <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="size-4 rounded border-[#b8cfca] text-[#285443] focus:ring-[#285443]">
                Remember me
            </label>
            <a href="{{ route('password.request') }}" class="font-medium text-[#285443] underline decoration-[#9cbcb4] underline-offset-4 hover:text-[#173d30]">Forgot password?</a>
        </div>

        <button type="submit" class="w-full rounded-lg bg-[#2d2d2d] px-4 py-3 text-sm font-medium text-white transition hover:bg-[#1c1c1c] focus:outline-none focus:ring-2 focus:ring-[#285443] focus:ring-offset-2">
            Sign in
        </button>
    </form>

    <p class="mt-7 text-center text-sm text-[#607a72]">
        New to BloomKeeper?
        <a href="{{ route('register') }}" class="font-semibold text-[#285443] underline decoration-[#9cbcb4] underline-offset-4 hover:text-[#173d30]">Create an account</a>
    </p>
</x-layouts.auth>
