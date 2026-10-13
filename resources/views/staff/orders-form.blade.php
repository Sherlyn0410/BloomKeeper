@php
    $order ??= null;
    $isEditing = $order !== null;
    $defaultItems = $isEditing
        ? $order->items->map(fn ($item) => ['flower_type' => $item->flower_type, 'quantity' => $item->quantity])->all()
        : [['flower_type' => '', 'quantity' => 1]];
    $oldItems = old('items', $defaultItems);
    $selectedCustomerId = (string) old('customer_id', $order?->customer_id);
    $isNewCustomer = $selectedCustomerId === 'new';
    $inputClass = 'bk-input';
    $labelClass = 'bk-label';
@endphp

<x-workspace.layout
    :title="$isEditing ? 'Edit order '.$order->order_number : 'Create customer order'"
    :description="$isEditing ? 'Update the customer, collection details, and flowers for this order.' : 'Pick a saved customer or add a new one, then list the flowers for collection.'"
>
    <form method="POST" action="{{ $isEditing ? route('staff.orders.update', $order) : route('staff.orders.store') }}" class="space-y-6">
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <section class="bk-card" data-new-customer="{{ $isNewCustomer ? 'true' : 'false' }}" id="customer-card">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="bk-card-title">Customer</h2>
                    <p class="bk-card-subtitle" id="customer-mode-hint">Choose a saved customer, or add someone new.</p>
                </div>
                <button type="button" id="customer-mode-toggle" class="bk-btn bk-btn-secondary">
                    <x-icons.plus />
                    <span id="customer-mode-label">New customer</span>
                </button>
            </div>

            <input type="hidden" id="new-customer-flag" name="customer_id" value="new" @disabled(! $isNewCustomer)>

            <label id="saved-customer-panel" class="mt-5 {{ $labelClass }}" @if ($isNewCustomer) hidden @endif>
                Saved customer
                <select id="customer-select" name="customer_id" class="{{ $inputClass }}" @disabled($isNewCustomer) @required(! $isNewCustomer)>
                    <option value="">Select a customer</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected($selectedCustomerId === (string) $customer->id)>
                            {{ $customer->name }}{{ $customer->phone ? ' · '.$customer->formatted_phone : '' }}
                        </option>
                    @endforeach
                </select>
            </label>

            <div id="new-customer-panel" class="bk-tile mt-5 grid gap-3 sm:grid-cols-3" @if (! $isNewCustomer) hidden @endif>
                <label class="{{ $labelClass }}">
                    Name
                    <input name="new_customer[name]" value="{{ old('new_customer.name') }}" maxlength="255" class="{{ $inputClass }}">
                </label>
                <label class="{{ $labelClass }}">
                    Phone
                    <input name="new_customer[phone]" value="{{ old('new_customer.phone') }}" type="tel" inputmode="numeric" maxlength="13" placeholder="012-345 6789" data-phone-input class="{{ $inputClass }}">
                </label>
                <label class="{{ $labelClass }}">
                    <span>Email <span class="font-normal text-[#8aa39c]">(optional)</span></span>
                    <input name="new_customer[email]" value="{{ old('new_customer.email') }}" type="email" maxlength="255" class="{{ $inputClass }}">
                </label>
                <p class="text-xs text-[#607a72] sm:col-span-3">This customer will be saved for future orders.</p>
            </div>
        </section>

        <section class="bk-card">
            <h2 class="bk-card-title">Collection and payment</h2>
            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <label class="{{ $labelClass }}">
                    Collection date
                    <input name="collection_date" value="{{ old('collection_date', $order?->collection_date?->format('Y-m-d')) }}" type="date" @unless ($isEditing) min="{{ today()->format('Y-m-d') }}" @endunless required class="{{ $inputClass }}">
                </label>
                <label class="{{ $labelClass }}">
                    <span>Collection time <span class="font-normal text-[#8aa39c]">(optional)</span></span>
                    <input name="collection_time" value="{{ old('collection_time', $order?->collection_time ? substr($order->collection_time, 0, 5) : null) }}" type="time" class="{{ $inputClass }}">
                </label>
                <label class="{{ $labelClass }}">
                    Payment
                    <select name="payment_status" required class="{{ $inputClass }}">
                        @foreach (\App\Models\CustomerOrder::PAYMENT_STATUSES as $paymentStatus)
                            <option value="{{ $paymentStatus }}" @selected(old('payment_status', $order?->payment_status ?? 'unpaid') === $paymentStatus)>{{ ucfirst($paymentStatus) }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($isEditing)
                    <label class="{{ $labelClass }}">
                        Status
                        <select name="status" required class="{{ $inputClass }}">
                            @foreach (\App\Models\CustomerOrder::STATUSES as $status)
                                <option value="{{ $status }}" @selected(old('status', $order->status) === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                        @if ($order->status === 'ready')
                            <span class="text-xs font-normal text-[#a76513]">Cancelling a ready order records its flowers as wastage.</span>
                        @endif
                    </label>
                @endif
                <label class="{{ $labelClass }} sm:col-span-2 lg:col-span-4">
                    <span>Notes <span class="font-normal text-[#8aa39c]">(card message, wrapping, etc.)</span></span>
                    <textarea name="notes" rows="3" maxlength="1000" class="{{ $inputClass }}">{{ old('notes', $order?->notes) }}</textarea>
                </label>
            </div>
        </section>

        <section class="bk-card">
            <div class="flex items-center justify-between gap-4">
                <h2 class="bk-card-title">Flowers</h2>
                <button type="button" id="add-item" class="bk-btn bk-btn-secondary"><x-icons.plus /> Add flower</button>
            </div>

            <div id="item-rows" class="mt-5 space-y-3">
                @foreach (array_values($oldItems) as $index => $item)
                    <div class="item-row flex flex-wrap items-end gap-3">
                        <label class="min-w-48 flex-1 {{ $labelClass }}">
                            Flower type
                            <select name="items[{{ $index }}][flower_type]" required class="{{ $inputClass }}">
                                <option value="">Select a flower</option>
                                @foreach ($flowerStock as $flowerType => $available)
                                    <option value="{{ $flowerType }}" @selected(($item['flower_type'] ?? '') === $flowerType)>{{ $flowerType }} ({{ $available }} in stock)</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="w-32 {{ $labelClass }}">
                            Quantity
                            <input name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" type="number" min="1" required class="{{ $inputClass }}">
                        </label>
                        <button type="button" class="bk-btn bk-btn-ghost remove-item text-[#b84331] hover:bg-[#f5dcd7]">Remove</button>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-end gap-2">
            @if ($isEditing)
                <a href="{{ auth()->user()->role === 'admin' ? route('admin.orders') : route('staff.orders.index') }}" class="bk-btn bk-btn-ghost">Cancel</a>
            @endif
            <button class="bk-btn bk-btn-accent">{{ $isEditing ? 'Save changes' : 'Create order' }}</button>
        </div>
    </form>

    <script>
        (() => {
            const customerCard = document.getElementById('customer-card');
            const customerSelect = document.getElementById('customer-select');
            const newCustomerFlag = document.getElementById('new-customer-flag');
            const savedCustomerPanel = document.getElementById('saved-customer-panel');
            const newCustomerPanel = document.getElementById('new-customer-panel');
            const customerModeToggle = document.getElementById('customer-mode-toggle');
            const itemRows = document.getElementById('item-rows');
            let nextIndex = itemRows.children.length;

            const setCustomerMode = (isNew, { focus = false } = {}) => {
                savedCustomerPanel.hidden = isNew;
                newCustomerPanel.hidden = ! isNew;
                customerSelect.disabled = isNew;
                customerSelect.required = ! isNew;
                newCustomerFlag.disabled = ! isNew;
                newCustomerPanel.querySelector('[name="new_customer[name]"]').required = isNew;
                newCustomerPanel.querySelector('[name="new_customer[phone]"]').required = isNew;
                document.getElementById('customer-mode-label').textContent = isNew ? 'Choose saved customer' : 'New customer';
                customerModeToggle.querySelector('svg').classList.toggle('hidden', isNew);
                document.getElementById('customer-mode-hint').textContent = isNew
                    ? 'Enter the new customer\'s details below.'
                    : 'Choose a saved customer, or add someone new.';

                if (focus) {
                    (isNew ? newCustomerPanel.querySelector('input') : customerSelect).focus();
                }
            };

            customerModeToggle.addEventListener('click', () => setCustomerMode(savedCustomerPanel.hidden === false, { focus: true }));

            const updateRemoveButtons = () => {
                itemRows.querySelectorAll('.remove-item').forEach((button) => {
                    button.hidden = itemRows.children.length === 1;
                });
            };


            document.getElementById('add-item').addEventListener('click', () => {
                const row = itemRows.firstElementChild.cloneNode(true);
                row.querySelector('select').name = `items[${nextIndex}][flower_type]`;
                row.querySelector('select').value = '';
                row.querySelector('input').name = `items[${nextIndex}][quantity]`;
                row.querySelector('input').value = 1;
                nextIndex++;
                itemRows.appendChild(row);
                updateRemoveButtons();
            });

            itemRows.addEventListener('click', (event) => {
                if (event.target.classList.contains('remove-item') && itemRows.children.length > 1) {
                    event.target.closest('.item-row').remove();
                    updateRemoveButtons();
                }
            });

            setCustomerMode(customerCard.dataset.newCustomer === 'true');
            updateRemoveButtons();
        })();
    </script>
</x-workspace.layout>
