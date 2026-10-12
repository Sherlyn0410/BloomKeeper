<x-admin.layout
    :title="$accountLabel.' Accounts'"
    :description="'Approve, suspend, and update '.strtolower($accountLabel).' accounts and their sign-in details.'"
>
    <x-admin.account-management
        :users="$users"
        :account-role="$accountRole"
        :account-label="$accountLabel"
    />
</x-admin.layout>
