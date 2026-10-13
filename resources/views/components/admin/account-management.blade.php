@props([
    'users',
    'accountRole',
    'accountLabel',
])

<section class="bk-card overflow-hidden">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="bk-card-title">{{ $accountLabel }} directory</h2>
            <p class="mt-1 text-sm text-[#607a72]">Manage access, approval status, and account details.</p>
        </div>
        <form method="GET" class="flex flex-wrap gap-2" data-live-filter>
            <input
                type="search"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search name or email"
                class="bk-input"
                data-live-filter-input
            >
            <select name="status" class="bk-input" data-live-filter-select>
                <option value="">All statuses</option>
                <option value="approved" @selected(request('status') === 'approved')>Active</option>
                <option value="suspended" @selected(request('status') === 'suspended')>Inactive</option>
                <option value="declined" @selected(request('status') === 'declined')>Declined</option>
                <option value="pending" @selected(request('status') === 'pending')>Pending</option>
            </select>
        </form>
    </div>

    <div class="mt-5 overflow-x-auto">
        <table class="w-full min-w-[480px] table-fixed text-left text-sm md:min-w-[760px]">
            <colgroup>
                <col class="w-[45%] md:w-[38%]">
                <col class="hidden w-[16%] md:table-column">
                <col class="w-[20%] md:w-[16%]">
                <col class="w-[35%] md:w-[30%]">
            </colgroup>
            <thead class="border-b border-[#d4e4e0] text-xs uppercase tracking-wider text-[#607a72]">
                <tr>
                    <th class="px-3 pb-4 font-medium">User</th>
                    <th class="hidden px-3 pb-4 font-medium md:table-cell">Joined</th>
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
                        <td class="truncate px-3 py-5 font-semibold">
                            {{ $user->name }}
                            <span class="block truncate text-xs font-normal text-[#607a72]">{{ $user->email }}</span>
                        </td>
                        <td class="hidden px-3 py-5 text-[#607a72] md:table-cell">{{ $user->created_at->format('d M Y') }}</td>
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
                                        <button class="bk-btn bk-btn-primary bk-btn-sm" type="submit">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.users.decline', $user) }}">
                                        @csrf @method('PATCH')
                                        <button class="bk-btn bk-btn-danger-soft bk-btn-sm" type="submit">Decline</button>
                                    </form>
                                @elseif ($user->approval_status === 'approved')
                                    <form method="POST" action="{{ route('admin.users.suspend', $user) }}">
                                        @csrf @method('PATCH')
                                        <button
                                            class="bk-btn bk-btn-sm {{ $isSelf ? 'cursor-not-allowed bg-[#e7ece9] text-[#a3b3ad]' : 'bk-btn-danger-soft' }}"
                                            type="submit"
                                            {{ $isSelf ? 'disabled title=You cannot suspend your own account' : '' }}
                                        >Suspend</button>
                                    </form>
                                @elseif (in_array($user->approval_status, ['suspended', 'declined']))
                                    <form method="POST" action="{{ route('admin.users.restore', $user) }}">
                                        @csrf @method('PATCH')
                                        <button class="bk-btn bk-btn-success-soft bk-btn-sm" type="submit">Restore</button>
                                    </form>
                                @endif
                            </div>
                            <button
                                type="button"
                                class="mt-3 cursor-pointer text-xs font-semibold text-[#285443] underline underline-offset-2"
                                onclick="document.getElementById('edit-user-{{ $user->id }}').showModal()"
                            >Edit account</button>

                            <dialog
                                id="edit-user-{{ $user->id }}"
                                aria-labelledby="edit-user-title-{{ $user->id }}"
                                class="fixed left-1/2 top-1/2 m-0 max-h-[calc(100vh-2rem)] w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-[24px] bg-white p-0 backdrop:bg-[#1c2e28]/40"
                            >
                                <div class="max-h-[calc(100vh-2rem)] overflow-y-auto p-6 sm:p-8">
                                    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="text-left">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="role" value="{{ $accountRole }}">

                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <h3 id="edit-user-title-{{ $user->id }}" class="font-serif text-xl font-bold text-[#1c2e28]">Edit account</h3>
                                                <p class="mt-1 text-sm text-[#607a72]">Update {{ $user->name }}'s details.</p>
                                            </div>
                                            <button
                                                type="button"
                                                aria-label="Close"
                                                class="cursor-pointer rounded-full p-1.5 text-[#607a72] hover:bg-[#edf7f5]"
                                                onclick="document.getElementById('edit-user-{{ $user->id }}').close()"
                                            >&#10005;</button>
                                        </div>

                                        <div class="mt-6 grid gap-4">
                                            <label class="grid gap-1 text-sm">
                                                <span class="font-semibold text-[#1c2e28]">Full name</span>
                                                <input name="name" value="{{ $user->name }}" required class="bk-input">
                                            </label>
                                            <label class="grid gap-1 text-sm">
                                                <span class="font-semibold text-[#1c2e28]">Email</span>
                                                <input name="email" type="email" value="{{ $user->email }}" required class="bk-input">
                                            </label>

                                            <div class="border-t border-[#e5efec] pt-4">
                                                <p class="text-xs font-semibold uppercase tracking-wide text-[#607a72]">Reset password</p>
                                                <p class="mt-1 text-xs text-[#8199a2]">Leave both fields blank to keep the current password.</p>
                                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                                    <input name="password" type="password" placeholder="New password" minlength="8" class="bk-input">
                                                    <input name="password_confirmation" type="password" placeholder="Confirm new password" minlength="8" class="bk-input">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-7 flex justify-end gap-3">
                                            <button
                                                type="button"
                                                class="bk-btn bk-btn-ghost"
                                                onclick="document.getElementById('edit-user-{{ $user->id }}').close()"
                                            >Cancel</button>
                                            <button class="bk-btn bk-btn-primary">Save changes</button>
                                        </div>
                                    </form>
                                </div>
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