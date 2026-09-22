<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Admin overview | BloomKeeper</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-[#c4e6e1] font-sans text-[#203d37]">
        <div class="mx-auto flex min-h-screen max-w-[1500px] gap-6 p-4 sm:p-6">
            <aside class="hidden w-64 shrink-0 flex-col rounded-[28px] bg-[#e9f5f2]/90 p-5 shadow-[0_18px_45px_rgba(36,73,64,0.12)] lg:flex">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-2">
                    <img src="{{ asset('images/bloomkeeper-logo.png') }}" alt="BloomKeeper" class="size-11 rounded-full object-cover">
                    <span>
                        <span class="block font-serif text-lg font-bold">BloomKeeper</span>
                        <span class="text-xs text-[#607a72]">Admin console</span>
                    </span>
                </a>
                <nav class="mt-10 space-y-2 text-sm font-medium">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-2xl bg-[#d5e4e1] px-4 py-3 text-[#203d37]"><span class="text-[#e3953d]">●</span>Overview</a>
                    <a href="{{ route('admin.users.index') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-[#49665f] transition hover:bg-[#d5e4e1]"><span class="text-[#b8cfca]">●</span>Manage users</a>
                </nav>
                <div class="mt-auto rounded-2xl bg-white/70 p-4 text-sm">
                    <p class="text-[#607a72]">Signed in as</p>
                    <p class="mt-1 font-semibold">{{ auth()->user()->name }}</p>
                    <form method="POST" action="{{ route('logout') }}" class="mt-3">
                        @csrf
                        <button type="submit" class="text-[#49665f] underline underline-offset-4">Sign out</button>
                    </form>
                </div>
            </aside>

            <main class="min-w-0 flex-1">
                <header class="flex items-center justify-between gap-4 px-1 py-2 sm:px-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-[#49665f]">Administrator view</p>
                        <h1 class="mt-1 font-serif text-3xl font-bold tracking-tight sm:text-4xl">Good morning, {{ Str::of(auth()->user()->name)->before(' ') }}.</h1>
                    </div>
                    <a href="{{ route('admin.users.index') }}" class="rounded-full bg-[#e3953d] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#d47f2a]">Manage users</a>
                </header>

                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ([
                        ['label' => 'Total users', 'value' => $totalUsers, 'note' => 'All registered accounts', 'tone' => 'text-[#203d37]'],
                        ['label' => 'Pending approval', 'value' => $pendingUsers, 'note' => 'Needs your review', 'tone' => 'text-[#d47f2a]'],
                        ['label' => 'Active users', 'value' => $activeUsers, 'note' => 'Approved to sign in', 'tone' => 'text-[#1c9b5f]'],
                        ['label' => 'Suspended', 'value' => $suspendedUsers, 'note' => 'Access currently blocked', 'tone' => 'text-[#d5533d]'],
                    ] as $stat)
                        <div class="rounded-3xl bg-[#edf7f5]/90 p-5 shadow-[0_12px_30px_rgba(36,73,64,0.1)]">
                            <p class="text-sm text-[#49665f]">{{ $stat['label'] }}</p>
                            <p class="mt-3 text-3xl font-bold {{ $stat['tone'] }}">{{ $stat['value'] }}</p>
                            <p class="mt-2 text-sm text-[#607a72]">{{ $stat['note'] }}</p>
                        </div>
                    @endforeach
                </div>

                <section class="mt-6 rounded-[28px] bg-[#edf7f5]/90 p-5 shadow-[0_12px_30px_rgba(36,73,64,0.1)] sm:p-7">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="font-serif text-2xl font-bold">Recent accounts</h2>
                            <p class="mt-1 text-sm text-[#607a72]">Keep an eye on the latest activity.</p>
                        </div>
                        <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-[#285443] underline underline-offset-4">View all</a>
                    </div>
                    <div class="mt-6 overflow-x-auto">
                        <table class="w-full min-w-[620px] text-left text-sm">
                            <thead class="border-b border-[#d4e4e0] text-xs uppercase tracking-wider text-[#607a72]">
                                <tr><th class="pb-3 font-medium">Name</th><th class="pb-3 font-medium">Account type</th><th class="pb-3 font-medium">Joined</th><th class="pb-3 font-medium">Status</th></tr>
                            </thead>
                            <tbody class="divide-y divide-[#dce9e6]">
                                @foreach ($recentUsers as $user)
                                    <tr>
                                        <td class="py-4 font-semibold">{{ $user->name }}<span class="block text-xs font-normal text-[#607a72]">{{ $user->email }}</span></td>
                                        <td class="py-4 capitalize">{{ $user->role }}</td>
                                        <td class="py-4 text-[#607a72]">{{ $user->created_at->format('d M Y') }}</td>
                                        <td class="py-4"><span class="rounded-full px-3 py-1 text-xs font-semibold {{ $user->approval_status === 'approved' ? 'bg-[#d7f0df] text-[#187849]' : ($user->approval_status === 'pending' ? 'bg-[#fff0d5] text-[#a76513]' : 'bg-[#f5dcd7] text-[#b84331]') }}">{{ str($user->approval_status)->headline() }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </body>
</html>
