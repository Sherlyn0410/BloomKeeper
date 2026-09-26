<x-admin.layout
    title="Manage Users"
    description="Manage staff and supplier accounts, approval status, access, and credentials from one place."
>
    <div class="rounded-[24px] bg-[#e9f5f2]/90 p-2 shadow-[0_12px_30px_rgba(36,73,64,0.08)]">
        <nav class="grid grid-cols-2 gap-2" aria-label="Account type">
            <a
                href="{{ route('admin.users', ['role' => 'staff']) }}"
                class="rounded-2xl px-4 py-3 text-center text-sm font-semibold transition {{ $accountRole === 'staff' ? 'bg-[#285443] text-white shadow-sm' : 'text-[#49665f] hover:bg-white' }}"
                aria-current="{{ $accountRole === 'staff' ? 'page' : 'false' }}"
            >Staff</a>
            <a
                href="{{ route('admin.users', ['role' => 'supplier']) }}"
                class="rounded-2xl px-4 py-3 text-center text-sm font-semibold transition {{ $accountRole === 'supplier' ? 'bg-[#285443] text-white shadow-sm' : 'text-[#49665f] hover:bg-white' }}"
                aria-current="{{ $accountRole === 'supplier' ? 'page' : 'false' }}"
            >Suppliers</a>
        </nav>
    </div>

    <x-admin.account-management
        :users="$users"
        :account-role="$accountRole"
        :account-label="$accountLabel"
    />
</x-admin.layout>
