<x-layouts.auth title="Verify your email | BloomKeeper">
    <div class="space-y-3">
        <h1 class="text-2xl font-semibold tracking-tight text-[#29463e]">Verify your email</h1>
        <p class="text-sm leading-6 text-[#607a72]">
            We sent a verification link to <span class="font-medium text-[#29463e]">{{ auth()->user()->email }}</span>.
            Open it to activate your BloomKeeper account.
        </p>
    </div>

    @if (session('status'))
        <p class="mt-5 rounded-lg bg-[#d6eee9] px-3 py-2 text-sm text-[#285443]">
            {{ session('status') }}
        </p>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="mt-7" data-submit-once>
        @csrf
        <button type="submit" data-submit-button class="w-full rounded-lg bg-[#2d2d2d] px-4 py-3 text-sm font-medium text-white transition hover:bg-[#1c1c1c] focus:outline-none focus:ring-2 focus:ring-[#285443] focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">
            Resend verification email
        </button>
    </form>

</x-layouts.auth>
