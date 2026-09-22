<x-layouts.auth title="Reset password | BloomKeeper">
    <div class="space-y-2">
        <h1 class="text-2xl font-semibold tracking-tight text-[#29463e]">Reset your password</h1>
        <p class="text-sm leading-6 text-[#607a72]">Enter your email and we’ll send you a secure link to choose a new password.</p>
    </div>

    @if (session('status'))
        <p class="mt-5 rounded-lg bg-[#d6eee9] px-3 py-2 text-sm text-[#285443]">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-7 space-y-5">
        @csrf
        <x-auth-field name="email" label="Email" type="email" autocomplete="email" placeholder="you@example.com" autofocus />
        <button type="submit" class="w-full rounded-lg bg-[#2d2d2d] px-4 py-3 text-sm font-medium text-white transition hover:bg-[#1c1c1c] focus:outline-none focus:ring-2 focus:ring-[#285443] focus:ring-offset-2">
            Email reset link
        </button>
    </form>

    <p class="mt-7 text-center text-sm">
        <a href="{{ route('login') }}" class="font-semibold text-[#285443] underline decoration-[#9cbcb4] underline-offset-4 hover:text-[#173d30]">Back to sign in</a>
    </p>
</x-layouts.auth>
