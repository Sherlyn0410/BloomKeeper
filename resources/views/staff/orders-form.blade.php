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
    // Flowers already on an order keep the price they were sold at; everything else uses today's price.
    $prices = $flowerPrices->merge($isEditing ? $order->items->pluck('unit_price', 'flower_type') : []);
@endphp

<x-workspace.layout
    :title="$isEditing ? 'Edit order '.$order->order_number : 'Create customer order'"
    :description="$isEditing ? 'Update the customer, collection details, and flowers for this order.' : 'Pick a saved customer or add a new one, then list the flowers for collection.'"
>
    <form method="POST" action="{{ $isEditing ? route('staff.orders.update', $order) : route('staff.orders.store') }}" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <div class="min-w-0 space-y-6">
            <section class="bk-card" data-new-customer="{{ $isNewCustomer ? 'true' : 'false' }}" id="customer-card">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="bk-card-title">Customer</h2>
                        <p class="bk-card-subtitle" id="customer-mode-hint">Choose a saved customer, or add someone new.</p>
                    </div>
                    <button type="button" id="customer-mode-toggle" class="bk-btn bk-btn-secondary bk-btn-sm h-8">
                        <x-icons.plus />
                        <span id="customer-mode-label">New customer</span>
                    </button>
                </div>

                <input type="hidden" id="new-customer-flag" name="customer_id" value="new" @disabled(! $isNewCustomer)>

                <label id="saved-customer-panel" class="mt-4 {{ $labelClass }}" @if ($isNewCustomer) hidden @endif>
                    <span class="sr-only">Saved customer</span>
                    <select id="customer-select" name="customer_id" class="{{ $inputClass }}" @disabled($isNewCustomer) @required(! $isNewCustomer)>
                        <option value="">Select a customer</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected($selectedCustomerId === (string) $customer->id)>
                                {{ $customer->name }}{{ $customer->phone ? ' · '.$customer->formatted_phone : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <div id="new-customer-panel" class="mt-4 grid gap-3 sm:grid-cols-3" @if (! $isNewCustomer) hidden @endif>
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
                <div class="flex items-center justify-between gap-4">
                    <h2 class="bk-card-title">Flowers</h2>
                    <button type="button" id="add-item" class="bk-btn bk-btn-secondary bk-btn-sm h-8"><x-icons.plus /> Add flower</button>
                </div>

                <div class="mt-4 hidden grid-cols-[minmax(0,1fr)_6rem_6rem_2rem] gap-3 px-1 text-xs font-semibold text-[#8aa39c] sm:grid">
                    <span>Flower type</span>
                    <span>Quantity</span>
                    <span class="text-right">Subtotal</span>
                    <span></span>
                </div>
                <div id="item-rows" class="mt-2 space-y-2">
                    @foreach (array_values($oldItems) as $index => $item)
                        <div class="item-row grid grid-cols-[minmax(0,1fr)_5rem_2rem] items-center gap-2 sm:grid-cols-[minmax(0,1fr)_6rem_6rem_2rem] sm:gap-3">
                            <label class="sr-only" for="item-flower-{{ $index }}">Flower type</label>
                            <select id="item-flower-{{ $index }}" name="items[{{ $index }}][flower_type]" required class="{{ $inputClass }} w-full">
                                <option value="">Select a flower</option>
                                @foreach ($flowerStock as $flowerType => $available)
                                    <option value="{{ $flowerType }}" data-price="{{ $prices[$flowerType] ?? 0 }}" @selected(($item['flower_type'] ?? '') === $flowerType)>{{ $flowerType }} ({{ $available }} in stock)</option>
                                @endforeach
                            </select>
                            <label class="sr-only" for="item-quantity-{{ $index }}">Quantity</label>
                            <input id="item-quantity-{{ $index }}" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" type="number" min="1" required class="{{ $inputClass }} w-full">
                            <span data-line-total class="hidden text-right text-sm font-semibold text-[#1c2e28] sm:block">RM 0.00</span>
                            <button type="button" class="remove-item flex size-8 cursor-pointer items-center justify-center rounded-lg text-[#b84331] transition hover:bg-[#f5dcd7]" aria-label="Remove flower" title="Remove flower">
                                <svg class="pointer-events-none size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
                            </button>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <aside class="space-y-6 lg:sticky lg:top-0">
            <section class="bk-card">
                <h2 class="bk-card-title">Collection and payment</h2>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <label class="{{ $labelClass }}">
                        Collection date
                        <input name="collection_date" value="{{ old('collection_date', $order?->collection_date?->format('Y-m-d')) }}" type="date" min="{{ ($order?->collection_date?->lte(today()) ? $order->collection_date : today()->addDay())->format('Y-m-d') }}" required class="{{ $inputClass }}">
                    </label>
                    <label class="{{ $labelClass }}">
                        <span>Time <span class="font-normal text-[#8aa39c]">(optional)</span></span>
                        <input name="collection_time" value="{{ old('collection_time', $order?->collection_time ? substr($order->collection_time, 0, 5) : null) }}" type="time" class="{{ $inputClass }}">
                    </label>
                    <label @class([$labelClass, 'col-span-2' => ! $isEditing])>
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
                        </label>
                        @if ($order->status === 'ready')
                            <p class="col-span-2 text-xs text-[#a76513]">Cancelling a ready order records its flowers as wastage.</p>
                        @endif
                    @endif
                    <label class="{{ $labelClass }} col-span-2">
                        <span>Notes <span class="font-normal text-[#8aa39c]">(card message, wrapping)</span></span>
                        <textarea name="notes" rows="2" maxlength="1000" class="{{ $inputClass }}">{{ old('notes', $order?->notes) }}</textarea>
                    </label>
                </div>
            </section>

            <section class="bk-card">
                <h2 class="bk-card-title">Summary</h2>
                <ul id="summary-lines" class="mt-3 space-y-2 text-sm"></ul>
                <p id="summary-empty" class="mt-3 text-sm text-[#8aa39c]">No flowers added yet.</p>
                <dl class="mt-3 space-y-1.5 border-t border-[#e3eeeb] pt-3 text-sm">
                    <div class="flex justify-between text-[#607a72]">
                        <dt>Stems</dt>
                        <dd id="summary-stems">0</dd>
                    </div>
                    <div class="flex items-baseline justify-between border-t border-[#e3eeeb] pt-2">
                        <dt class="font-semibold text-[#1c2e28]">Estimated total</dt>
                        <dd id="summary-total" class="text-xl font-bold text-[#1c2e28]">RM 0.00</dd>
                    </div>
                </dl>
                <div class="mt-4 grid gap-2">
                    <button class="bk-btn bk-btn-accent w-full">{{ $isEditing ? 'Save changes' : 'Create order' }}</button>
                    @if ($isEditing)
                        <a href="{{ auth()->user()->role === 'admin' ? route('admin.orders') : route('staff.orders.index') }}" class="bk-btn bk-btn-ghost w-full">Cancel</a>
                    @endif
                </div>
            </section>
        </aside>
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
            const money = (amount) => 'RM ' + amount.toFixed(2);
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

            const updateSummary = () => {
                let stems = 0;
                let total = 0;
                const lines = [];

                itemRows.querySelectorAll('.item-row').forEach((row) => {
                    const select = row.querySelector('select');
                    const quantity = Math.max(0, parseInt(row.querySelector('input').value, 10) || 0);
                    const price = parseFloat(select.selectedOptions[0]?.dataset.price ?? 0) || 0;
                    const lineTotal = select.value ? price * quantity : 0;

                    row.querySelector('[data-line-total]').textContent = money(lineTotal);
                    stems += select.value ? quantity : 0;
                    total += lineTotal;

                    if (select.value && quantity > 0) {
                        lines.push({ flower: select.value, price, quantity, lineTotal });
                    }
                });

                const summaryLines = document.getElementById('summary-lines');
                summaryLines.replaceChildren(...lines.map(({ flower, price, quantity, lineTotal }) => {
                    const item = document.createElement('li');
                    item.className = 'flex items-start justify-between gap-3';
                    const details = document.createElement('div');
                    const name = document.createElement('p');
                    name.className = 'font-semibold text-[#1c2e28]';
                    name.textContent = flower;
                    const breakdown = document.createElement('p');
                    breakdown.className = 'text-xs text-[#607a72] tabular-nums';
                    breakdown.textContent = `${money(price)} × ${quantity}`;
                    const amount = document.createElement('span');
                    amount.className = 'font-semibold text-[#1c2e28] tabular-nums';
                    amount.textContent = money(lineTotal);
                    details.append(name, breakdown);
                    item.append(details, amount);

                    return item;
                }));
                document.getElementById('summary-empty').hidden = lines.length > 0;

                document.getElementById('summary-stems').textContent = stems;
                document.getElementById('summary-total').textContent = money(total);
            };

            const updateRemoveButtons = () => {
                itemRows.querySelectorAll('.remove-item').forEach((button) => {
                    button.disabled = itemRows.children.length === 1;
                    button.classList.toggle('invisible', itemRows.children.length === 1);
                });
            };

            document.getElementById('add-item').addEventListener('click', () => {
                const row = itemRows.firstElementChild.cloneNode(true);
                const select = row.querySelector('select');
                const input = row.querySelector('input');
                select.name = `items[${nextIndex}][flower_type]`;
                select.id = `item-flower-${nextIndex}`;
                select.value = '';
                input.name = `items[${nextIndex}][quantity]`;
                input.id = `item-quantity-${nextIndex}`;
                input.value = 1;
                row.querySelectorAll('label').forEach((label, position) => label.htmlFor = position === 0 ? select.id : input.id);
                nextIndex++;
                itemRows.appendChild(row);
                updateRemoveButtons();
                updateSummary();
                select.focus();
            });

            itemRows.addEventListener('click', (event) => {
                const removeButton = event.target.closest('.remove-item');

                if (removeButton && itemRows.children.length > 1) {
                    removeButton.closest('.item-row').remove();
                    updateRemoveButtons();
                    updateSummary();
                }
            });

            itemRows.addEventListener('input', updateSummary);
            itemRows.addEventListener('change', updateSummary);

            setCustomerMode(customerCard.dataset.newCustomer === 'true');
            updateRemoveButtons();
            updateSummary();
        })();
    </script>
</x-workspace.layout>
