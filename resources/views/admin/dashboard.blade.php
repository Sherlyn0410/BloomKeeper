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
    <body class="h-screen overflow-hidden bg-[#c4e6e1] font-sans text-[#203d37]">
        <div class="mx-auto flex h-screen w-full max-w-[1500px] gap-4 overflow-hidden p-3 sm:gap-6 sm:p-6">
            <x-admin.sidebar />

            <main class="min-w-0 flex-1 overflow-y-auto overflow-x-hidden">
                <header class="flex items-center justify-between gap-4 px-1 py-2 sm:px-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-[#49665f]">Administrator view</p>
                        <h1 class="mt-1 font-serif text-3xl font-bold tracking-tight sm:text-4xl">Good morning, {{ Str::of(auth()->user()->name)->before(' ') }}.</h1>
                    </div>
                </header>

                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    @foreach ([
                        ['label' => 'Total stock items', 'value' => $totalStockItems, 'note' => 'Flower stock on hand', 'tone' => 'text-[#203d37]'],
                        ['label' => 'Low-stock alerts', 'value' => $lowStockAlerts, 'note' => 'Items below threshold', 'tone' => 'text-[#d47f2a]'],
                        ['label' => 'Spoilage alerts', 'value' => $spoilageAlerts, 'note' => 'Approaching expiry', 'tone' => 'text-[#d5533d]'],
                        ['label' => 'Pending orders', 'value' => $pendingOrders, 'note' => 'Awaiting fulfilment', 'tone' => 'text-[#285443]'],
                        ['label' => 'Pending purchase orders', 'value' => $pendingPurchaseOrders, 'note' => 'Waiting on suppliers', 'tone' => 'text-[#1c9b5f]'],
                    ] as $stat)
                        <div class="rounded-3xl bg-[#edf7f5]/90 p-5 shadow-[0_12px_30px_rgba(36,73,64,0.1)]">
                            <p class="text-sm text-[#49665f]">{{ $stat['label'] }}</p>
                            <p class="mt-3 text-3xl font-bold {{ $stat['tone'] }}">{{ $stat['value'] }}</p>
                            <p class="mt-2 text-sm text-[#607a72]">{{ $stat['note'] }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 grid gap-6 xl:grid-cols-[1.5fr_0.9fr]">
                    <section class="rounded-[28px] bg-[#edf7f5]/90 p-5 shadow-[0_12px_30px_rgba(36,73,64,0.1)] sm:p-7">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2 class="font-serif text-2xl font-bold">Recent orders</h2>
                                <p class="mt-1 text-sm text-[#607a72]">Latest customer activity and collection dates.</p>
                            </div>
                            <a href="{{ route('admin.orders') }}" class="text-sm font-semibold text-[#285443] underline underline-offset-4">View all</a>
                        </div>
                        <div class="mt-6 overflow-hidden">
                            <table class="w-full table-fixed text-left text-sm">
                                <thead class="border-b border-[#d4e4e0] text-xs uppercase tracking-wider text-[#607a72]">
                                    <tr><th class="pb-3 font-medium">Customer</th><th class="pb-3 font-medium">Items</th><th class="pb-3 font-medium">Date</th><th class="pb-3 font-medium">Status</th></tr>
                                </thead>
                                <tbody class="divide-y divide-[#dce9e6]">
                                    @foreach ($recentOrders as $order)
                                        <tr>
                                            <td class="py-4 font-semibold">{{ $order->customer_name }}<span class="block text-xs font-normal text-[#607a72]">{{ $order->customer_email }}</span></td>
                                            <td class="py-4 text-[#607a72]">{{ $order->items->map(fn ($item) => $item->quantity.' '.$item->flower_type)->join(', ') }}</td>
                                            <td class="py-4 text-[#607a72]">{{ $order->collection_date ? $order->collection_date->format('d M Y') : '-' }}</td>
                                            <td class="py-4"><span class="rounded-full px-3 py-1 text-xs font-semibold {{ $order->status === 'pending' ? 'bg-[#fff0d5] text-[#a76513]' : ($order->status === 'ready' ? 'bg-[#d7f0df] text-[#187849]' : ($order->status === 'completed' ? 'bg-[#d5e4e1] text-[#285443]' : 'bg-[#f5dcd7] text-[#b84331]')) }}">{{ ucfirst($order->status) }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <aside class="rounded-[28px] bg-[#edf7f5]/90 p-5 shadow-[0_12px_30px_rgba(36,73,64,0.1)] sm:p-7">
                        <h2 class="font-serif text-2xl font-bold">Quick alerts</h2>
                        <div class="mt-5 space-y-3">
                            @forelse ($recentInventoryAlerts as $alert)
                                <div class="rounded-2xl bg-white/70 p-3">
                                    <p class="font-semibold">{{ $alert->flower_type }}</p>
                                    <p class="mt-1 text-sm text-[#607a72]">
                                        @if ($alert->quantity <= $alert->low_stock_threshold)
                                            Low stock: {{ $alert->quantity }} remaining
                                        @else
                                            Spoilage check due: {{ $alert->date_received->copy()->addDays($alert->shelf_life_days)->format('d M Y') }}
                                        @endif
                                    </p>
                                </div>
                            @empty
                                <p class="text-sm text-[#607a72]">No active alerts right now.</p>
                            @endforelse
                        </div>
                    </aside>
                </div>

                <section class="mt-6 rounded-[28px] bg-[#edf7f5]/90 p-5 shadow-[0_12px_30px_rgba(36,73,64,0.1)] sm:p-7">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="font-serif text-2xl font-bold">Recent accounts</h2>
                            <p class="mt-1 text-sm text-[#607a72]">Keep an eye on the latest administrative activity.</p>
                            <p class="mt-2 text-sm font-semibold text-[#a76513]">Pending approval: {{ $pendingUsers }}</p>
                        </div>
                        <div class="flex flex-wrap justify-end gap-3 text-sm font-semibold">
                            <a href="{{ route('admin.users', ['role' => 'staff']) }}" class="text-[#285443] underline underline-offset-4">Manage users</a>
                        </div>
                    </div>
                    <div class="mt-6 overflow-hidden">
                        <table class="w-full table-fixed text-left text-sm">
                            <thead class="border-b border-[#d4e4e0] text-xs uppercase tracking-wider text-[#607a72]">
                                <tr><th class="pb-3 font-medium">Name</th><th class="pb-3 font-medium">Account type</th><th class="pb-3 font-medium">Joined</th><th class="pb-3 font-medium">Status</th><th class="pb-3 text-right font-medium">Action</th></tr>
                            </thead>
                            <tbody class="divide-y divide-[#dce9e6]">
                                @forelse ($recentUsers as $user)
                                    <tr>
                                        <td class="py-4 font-semibold">{{ $user->name }}<span class="block text-xs font-normal text-[#607a72]">{{ $user->email }}</span></td>
                                        <td class="py-4">{{ str($user->role)->headline() }}</td>
                                        <td class="py-4 text-[#607a72]">{{ $user->created_at->format('d M Y') }}</td>
                                        <td class="py-4"><span class="rounded-full px-3 py-1 text-xs font-semibold {{ $user->approval_status === 'approved' ? 'bg-[#d7f0df] text-[#187849]' : ($user->approval_status === 'pending' ? 'bg-[#fff0d5] text-[#a76513]' : 'bg-[#f5dcd7] text-[#b84331]') }}">{{ str($user->approval_status)->headline() }}</span></td>
                                        <td class="py-4 text-right">
                                            @if ($user->approval_status === 'pending')
                                                <div class="flex justify-end gap-2">
                                                    <form method="POST" action="{{ route('admin.users.approve', $user) }}">@csrf @method('PATCH')<button type="submit" class="rounded-full bg-[#285443] px-3 py-2 text-xs font-semibold text-white transition hover:bg-[#1f4435]">Approve</button></form>
                                                    <form method="POST" action="{{ route('admin.users.decline', $user) }}">@csrf @method('PATCH')<button type="submit" class="rounded-full bg-[#f5dcd7] px-3 py-2 text-xs font-semibold text-[#b84331] transition hover:bg-[#edc9c2]">Decline</button></form>
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
    </body>
</html>
