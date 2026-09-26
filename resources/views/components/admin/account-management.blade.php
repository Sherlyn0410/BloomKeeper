@props([
    'users',
    'accountRole',
    'accountLabel',
])

<section class="overflow-hidden rounded-[28px] bg-[#edf7f5]/90 p-5 shadow-[0_12px_30px_rgba(36,73,64,0.1)] sm:p-7">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="font-serif text-2xl font-bold">{{ $accountLabel }} directory</h2>
            <p class="mt-1 text-sm text-[#607a72]">Manage access, approval status, and account details.</p>
        </div>
        <form method="GET" class="flex flex-wrap gap-2" data-live-filter>
            <input
                type="search"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search name or email"
                class="rounded-lg border-0 p-2 text-sm"
                data-live-filter-input
            >
            <select name="status" class="rounded-lg border-0 p-2" data-live-filter-select>
                <option value="">All statuses</option>
                <option value="approved" @selected(request('status') === 'approved')>Active</option>
                <option value="suspended" @selected(request('status') === 'suspended')>Inactive</option>
                <option value="declined" @selected(request('status') === 'declined')>Declined</option>
                <option value="pending" @selected(request('status') === 'pending')>Pending</option>
            </select>
        </form>
    </div>

    <div class="mt-5 overflow-x-auto">
        <table class="w-full min-w-[760px] text-left text-sm">
            <thead class="border-b border-[#d4e4e0] text-xs uppercase tracking-wider text-[#607a72]">
                <tr>
                    <th class="px-3 pb-4 font-medium">User</th>
                    <th class="px-3 pb-4 font-medium">Joined</th>
                    <th class="px-3 pb-4 font-medium">Status</th>
                    <th class="px-3 pb-4 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#dce9e6]">
                @forelse ($users as $user)
                    @php
                        $isSelf = $user->is(auth()->user());
                        $statusStyles = [
                            'approved' => 'bg-[#d7f0df] text-[#187849]',
                            'pending' => 'bg-[#fff0d5] text-[#a76513]',
                            'suspended' => 'bg-[#f5dcd7] text-[#b84331]',
                            'declined' => 'bg-[#f5dcd7] text-[#b84331]',
                        ];
                    @endphp
                    <tr>
                        <td class="px-3 py-5 font-semibold">
                            {{ $user->name }}
                            <span class="block text-xs font-normal text-[#607a72]">{{ $user->email }}</span>
                        </td>
                        <td class="px-3 py-5 text-[#607a72]">{{ $user->created_at->format('d M Y') }}</td>
                        <td class="px-3 py-5">
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusStyles[$user->approval_status] ?? 'bg-[#f5dcd7] text-[#b84331]' }}">
                                {{ str($user->approval_status)->headline() }}
                            </span>
                        </td>
                        <td class="px-3 py-5 text-right">
                            <div class="flex flex-wrap justify-end gap-3">
                                @if ($user->approval_status === 'pending')
                                    <form method="POST" action="{{ route('admin.users.approve', $user) }}">
                                        @csrf @method('PATCH')
                                        <button class="cursor-pointer rounded-full bg-[#285443] px-4 py-2 text-xs font-semibold text-white" type="submit">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.users.decline', $user) }}">
                                        @csrf @method('PATCH')
                                        <button class="cursor-pointer rounded-full bg-[#f5dcd7] px-4 py-2 text-xs font-semibold text-[#b84331]" type="submit">Decline</button>
                                    </form>
                                @elseif ($user->approval_status === 'approved')
                                    <form method="POST" action="{{ route('admin.users.suspend', $user) }}">
                                        @csrf @method('PATCH')
                                        <button
                                            class="{{ $isSelf ? 'cursor-not-allowed' : 'cursor-pointer' }} rounded-full px-4 py-2 text-xs font-semibold {{ $isSelf ? 'bg-[#e7ece9] text-[#a3b3ad]' : 'bg-[#f5dcd7] text-[#b84331]' }}"
                                            type="submit"
                                            {{ $isSelf ? 'disabled title=You cannot suspend your own account' : '' }}
                                        >Suspend</button>
                                    </form>
                                @elseif (in_array($user->approval_status, ['suspended', 'declined']))
                                    <form method="POST" action="{{ route('admin.users.restore', $user) }}">
                                        @csrf @method('PATCH')
                                        <button class="cursor-pointer rounded-full bg-[#d7f0df] px-4 py-2 text-xs font-semibold text-[#187849]" type="submit">Restore</button>
                                    </form>
                                @endif
                            </div>
                            <button
                                type="button"
                                class="mt-3 cursor-pointer text-xs font-semibold text-[#285443] underline underline-offset-2"
                                onclick="document.getElementById('edit-user-{{ $user->id }}').showModal()"
                            >Edit account</button>

                            <dialog id="edit-user-{{ $user->id }}" class="fixed left-1/2 top-1/2 m-0 max-h-[calc(100vh-2rem)] w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-[24px] p-0 backdrop:bg-[#1c2e28]/40">
                                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="p-6 text-left">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="role" value="{{ $accountRole }}">

                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <h3 class="font-serif text-xl font-bold text-[#1c2e28]">Edit account</h3>
                                            <p class="mt-1 text-sm text-[#607a72]">Update {{ $user->name }}'s details.</p>
                                        </div>
                                        <button
                                            type="button"
                                            aria-label="Close"
                                            class="cursor-pointer rounded-full p-1 text-[#607a72] hover:bg-[#edf7f5]"
                                            onclick="document.getElementById('edit-user-{{ $user->id }}').close()"
                                        >&#10005;</button>
                                    </div>

                                    <div class="mt-5 grid gap-4">
                                        <label class="grid gap-1 text-sm">
                                            <span class="font-semibold text-[#1c2e28]">Full name</span>
                                            <input name="name" value="{{ $user->name }}" required class="rounded-xl border border-[#d4e4e0] bg-[#f7fbfa] p-3 text-sm focus:border-[#285443] focus:outline-none">
                                        </label>
                                        <label class="grid gap-1 text-sm">
                                            <span class="font-semibold text-[#1c2e28]">Email</span>
                                            <input name="email" type="email" value="{{ $user->email }}" required class="rounded-xl border border-[#d4e4e0] bg-[#f7fbfa] p-3 text-sm focus:border-[#285443] focus:outline-none">
                                        </label>

                                        <div class="border-t border-[#e5efec] pt-4">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-[#607a72]">Reset password</p>
                                            <p class="mt-1 text-xs text-[#8199a2]">Leave both fields blank to keep the current password.</p>
                                            <div class="mt-3 grid gap-3">
                                                <input name="password" type="password" placeholder="New password" minlength="8" class="rounded-xl border border-[#d4e4e0] bg-[#f7fbfa] p-3 text-sm focus:border-[#285443] focus:outline-none">
                                                <input name="password_confirmation" type="password" placeholder="Confirm new password" minlength="8" class="rounded-xl border border-[#d4e4e0] bg-[#f7fbfa] p-3 text-sm focus:border-[#285443] focus:outline-none">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-6 flex justify-end gap-3">
                                        <button
                                            type="button"
                                            class="cursor-pointer rounded-full px-4 py-2 text-sm font-semibold text-[#607a72] hover:bg-[#edf7f5]"
                                            onclick="document.getElementById('edit-user-{{ $user->id }}').close()"
                                        >Cancel</button>
                                        <button class="cursor-pointer rounded-full bg-[#285443] px-5 py-2 text-sm font-semibold text-white hover:bg-[#1c3e32]">Save changes</button>
                                    </div>
                                </form>
                            </dialog>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-sm text-[#607a72]">No {{ strtolower($accountLabel) }} accounts found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $users->appends(request()->query())->links() }}</div>
</section>