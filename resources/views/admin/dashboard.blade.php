<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Admin dashboard | BloomKeeper</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="flex h-screen flex-col overflow-hidden bg-[#c4e6e1] font-sans text-[#203d37]">
        <x-admin.sidebar mobile />
        <div class="mx-auto flex min-h-0 w-full max-w-[1500px] flex-1 gap-4 overflow-hidden p-3 sm:gap-6 sm:p-6">
            <x-admin.sidebar />

            <main class="min-w-0 flex-1 overflow-y-auto overflow-x-hidden">
                <header class="flex items-center justify-between gap-4 px-1 py-2 sm:px-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-[#49665f]">Administrator view</p>
                        <h1 class="mt-1 font-serif text-3xl font-bold tracking-tight sm:text-4xl">{{ auth()->user()->greeting() }}</h1>
                    </div>
                </header>

                <x-dashboard.stats class="mt-6" :stats="[
                    ['label' => 'Flowers in stock', 'value' => $totalStockItems, 'note' => 'Flower types with stock on hand', 'tone' => 'text-[#203d37]'],
                    ['label' => 'Low-stock alerts', 'value' => $lowStockAlerts, 'note' => 'Flowers at or below threshold', 'tone' => 'text-[#d47f2a]'],
                    ['label' => 'Spoilage alerts', 'value' => $spoilageAlerts, 'note' => 'Flowers spoiling within 2 days', 'tone' => 'text-[#d5533d]'],
                    ['label' => 'Pending orders', 'value' => $pendingOrders, 'note' => 'Awaiting fulfilment', 'tone' => 'text-[#285443]'],
                    ['label' => 'Pending purchase orders', 'value' => $pendingPurchaseOrders, 'note' => 'Waiting on suppliers', 'tone' => 'text-[#1c9b5f]'],
                ]" />

                <div class="mt-6 grid gap-6 xl:grid-cols-[1.5fr_0.9fr]">
                    <x-dashboard.recent-orders :orders="$recentOrders" :view-all-url="route('admin.orders')" />
                    <div class="min-w-0 space-y-6">
                        <x-dashboard.quick-alerts title="Low stock" type="low-stock" :alerts="$lowStockItems" :view-all-url="route('admin.inventory', ['alert' => 'low_stock'])" empty-message="Every flower is above its low-stock threshold." />
                        <x-dashboard.quick-alerts title="Spoilage alerts" type="spoilage" :alerts="$spoilageItems" :view-all-url="route('admin.inventory', ['alert' => 'spoilage'])" empty-message="Nothing is close to spoiling." />
                    </div>
                </div>

                <section class="bk-card mt-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="bk-card-title">Recent accounts</h2>
                            <p class="mt-1 text-sm text-[#607a72]">Keep an eye on the latest administrative activity.</p>
                            <p class="mt-2 text-sm font-semibold text-[#a76513]">Pending approval: {{ $pendingUsers }}</p>
                        </div>
                        <div class="flex flex-wrap justify-end gap-3 text-sm font-semibold">
                            <a href="{{ route('admin.users', ['role' => 'staff']) }}" class="text-[#285443] underline underline-offset-4">Manage users</a>
                        </div>
                    </div>
                    <div class="mt-6 overflow-x-auto">
                        <table class="w-full min-w-[640px] table-fixed text-left text-sm">
                            <colgroup>
                                <col class="w-[36%]">
                                <col class="w-[14%]">
                                <col class="w-[15%]">
                                <col class="w-[15%]">
                                <col class="w-[20%]">
                            </colgroup>
                            <thead class="border-b border-[#d4e4e0] text-xs uppercase tracking-wider text-[#607a72]">
                                <tr><th class="pb-3 font-medium">Name</th><th class="pb-3 font-medium">Type</th><th class="pb-3 font-medium">Joined</th><th class="pb-3 font-medium">Status</th><th class="pb-3 text-right font-medium">Action</th></tr>
                            </thead>
                            <tbody class="divide-y divide-[#dce9e6]">
                                @forelse ($recentUsers as $user)
                                    <tr>
                                        <td class="py-4 pr-4">
                                            <span class="block truncate font-semibold" title="{{ $user->name }}">{{ $user->name }}</span>
                                            <span class="block truncate text-xs text-[#607a72]" title="{{ $user->email }}">{{ $user->email }}</span>
                                        </td>
                                        <td class="py-4">{{ str($user->role)->headline() }}</td>
                                        <td class="py-4 text-[#607a72]">{{ $user->created_at->format('d M Y') }}</td>
                                        <td class="py-4"><span class="rounded-full px-3 py-1 text-xs font-semibold {{ $user->approval_status === 'approved' ? 'bg-[#d7f0df] text-[#187849]' : ($user->approval_status === 'pending' ? 'bg-[#fff0d5] text-[#a76513]' : 'bg-[#f5dcd7] text-[#b84331]') }}">{{ str($user->approval_status)->headline() }}</span></td>
                                        <td class="py-4 text-right">
                                            @if ($user->approval_status === 'pending')
                                                <div class="flex justify-end gap-2">
                                                    <form method="POST" action="{{ route('admin.users.approve', $user) }}">@csrf @method('PATCH')<button type="submit" class="bk-btn bk-btn-primary bk-btn-sm">Approve</button></form>
                                                    <form method="POST" action="{{ route('admin.users.decline', $user) }}">@csrf @method('PATCH')<button type="submit" class="bk-btn bk-btn-danger-soft bk-btn-sm">Decline</button></form>
                                                </div>
                                            @else
                                                <span class="text-xs text-[#607a72]">No action</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="py-8 text-center text-sm text-[#607a72]">No non-admin accounts have been created yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
        @if (auth()->user()->can('use-workspace'))
            <x-chat-widget />
        @endif
    </body>
</html>
