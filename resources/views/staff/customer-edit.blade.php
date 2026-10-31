<x-workspace.layout
    title="Edit customer"
    description="Update saved customer details. Past orders keep the details they were placed with."
>
    <section class="bk-card">
        <h2 class="bk-card-title">Customer details</h2>
        <form method="POST" action="{{ route('staff.customers.update', $customer) }}" class="mt-5 grid gap-3 sm:grid-cols-3">
            @csrf
            @method('PUT')
            <label class="bk-label">
                Name
                <input name="name" value="{{ old('name', $customer->name) }}" required maxlength="255" class="bk-input">
            </label>
            <label class="bk-label">
                Phone
                <input name="phone" value="{{ old('phone', $customer->formatted_phone) }}" type="tel" inputmode="numeric" required maxlength="13" placeholder="012-345 6789" data-phone-input class="bk-input">
            </label>
            <label class="bk-label">
                <span>Email <span class="font-normal text-[#8aa39c]">(optional)</span></span>
                <input name="email" value="{{ old('email', $customer->email) }}" type="email" maxlength="255" class="bk-input">
            </label>
            <div class="bk-form-actions">
                <a href="{{ route('staff.customers.index') }}" class="bk-btn bk-btn-ghost">Cancel</a>
                <button class="bk-btn bk-btn-primary">Save changes</button>
            </div>
        </form>
    </section>
</x-workspace.layout>
