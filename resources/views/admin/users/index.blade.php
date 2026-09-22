<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Manage users | BloomKeeper</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-[#c4e6e1] font-sans text-[#203d37]">
        <main class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:py-10">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <a href="{{ route('admin.dashboard') }}" class="text-sm font-semibold text-[#49665f] underline underline-offset-4">← Back to overview</a>
                    <h1 class="mt-4 font-serif text-4xl font-bold">Manage users</h1>
                    <p class="mt-2 text-[#607a72]">Approve new accounts and control access to BloomKeeper.</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="text-sm font-semibold text-[#49665f] underline underline-offset-4">Sign out</button></form>
            </header>

            @if (session('status'))<p class="mt-6 rounded-2xl bg-[#d7f0df] px-4 py-3 text-sm text-[#187849]">{{ session('status') }}</p>@endif
            @error('user')<p class="mt-6 rounded-2xl bg-[#f5dcd7] px-4 py-3 text-sm text-[#b84331]">{{ $message }}</p>@enderror

            <section class="mt-6 overflow-hidden rounded-[28px] bg-[#edf7f5]/90 p-4 shadow-[0_12px_30px_rgba(36,73,64,0.1)] sm:p-7">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="border-b border-[#d4e4e0] text-xs uppercase tracking-wider text-[#607a72]"><tr><th class="px-3 pb-4 font-medium">User</th><th class="px-3 pb-4 font-medium">Type</th><th class="px-3 pb-4 font-medium">Joined</th><th class="px-3 pb-4 font-medium">Status</th><th class="px-3 pb-4 text-right font-medium">Action</th></tr></thead>
                        <tbody class="divide-y divide-[#dce9e6]">
                            @foreach ($users as $user)
                                <tr>
                                    <td class="px-3 py-5 font-semibold">{{ $user->name }}<span class="block text-xs font-normal text-[#607a72]">{{ $user->email }}</span></td>
                                    <td class="px-3 py-5 capitalize">{{ $user->role }}</td>
                                    <td class="px-3 py-5 text-[#607a72]">{{ $user->created_at->format('d M Y') }}</td>
                                    <td class="px-3 py-5"><span class="rounded-full px-3 py-1 text-xs font-semibold {{ $user->approval_status === 'approved' ? 'bg-[#d7f0df] text-[#187849]' : ($user->approval_status === 'pending' ? 'bg-[#fff0d5] text-[#a76513]' : 'bg-[#f5dcd7] text-[#b84331]') }}">{{ str($user->approval_status)->headline() }}</span></td>
                                    <td class="px-3 py-5 text-right">
                                        @if ($user->approval_status === 'pending')
                                            <form method="POST" action="{{ route('admin.users.approve', $user) }}">@csrf @method('PATCH')<button class="rounded-full bg-[#285443] px-4 py-2 text-xs font-semibold text-white hover:bg-[#173d30]" type="submit">Approve</button></form>
                                        @elseif ($user->approval_status === 'approved' && ! $user->is(auth()->user()))
                                            <form method="POST" action="{{ route('admin.users.suspend', $user) }}">@csrf @method('PATCH')<button class="rounded-full bg-[#f5dcd7] px-4 py-2 text-xs font-semibold text-[#b84331] hover:bg-[#edc9c2]" type="submit">Suspend</button></form>
                                        @elseif ($user->approval_status === 'suspended')
                                            <form method="POST" action="{{ route('admin.users.restore', $user) }}">@csrf @method('PATCH')<button class="rounded-full bg-[#d7f0df] px-4 py-2 text-xs font-semibold text-[#187849] hover:bg-[#c2e8d0]" type="submit">Restore</button></form>
                                        @else
                                            <span class="text-xs text-[#607a72]">Your account</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-6">{{ $users->links() }}</div>
            </section>
        </main>
    </body>
</html>
