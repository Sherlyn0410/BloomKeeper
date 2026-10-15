@php
    // Admins and staff share this page; links and forms stay inside the viewer's own area.
    $area = auth()->user()->can('manage-users') ? 'admin' : 'staff';
    $statusStyles = [
        'requested' => ['label' => 'Waiting for supplier', 'badge' => 'bg-[#fff0d5] text-[#a76513]', 'dot' => 'bg-[#a76513]'],
        'accepted' => ['label' => 'Supplier agreed', 'badge' => 'bg-[#d7f0df] text-[#187849]', 'dot' => 'bg-[#187849]'],
        'rejected' => ['label' => 'Supplier said no', 'badge' => 'bg-[#f5dcd7] text-[#b84331]', 'dot' => 'bg-[#b84331]'],
        'cancelled' => ['label' => 'Cancelled', 'badge' => 'bg-[#dce9e6] text-[#49665f]', 'dot' => 'bg-[#8aa39c]'],
    ];
@endphp

<x-workspace.layout
    title="Stock returns"
    description="Got flowers in bad condition? Send them back to the supplier here. They are taken out of your stock right away. If the supplier says no, they are counted as wastage (thrown away)."
>
    <section class="bk-card">
        <div>
            <h2 class="bk-card-title">Send flowers back</h2>
            <p class="mt-1 text-sm text-[#607a72]">Choose the flowers, how many, and what is wrong with them. Flowers can only be sent back within {{ \App\Models\StockReturn::RETURN_WINDOW_DAYS }} days of arriving.</p>
        </div>
        @if ($returnableBatches->isEmpty())
            <p class="mt-4 rounded-xl bg-[#fff0d5] px-3 py-2 text-sm text-[#a76513]">There is nothing you can send back right now. Flowers can only be sent back if they arrived in the last {{ \App\Models\StockReturn::RETURN_WINDOW_DAYS }} days and you know who supplied them. Older flowers with a problem should be recorded as wastage.</p>
        @endif
        <form method="POST" action="{{ route($area.'.stock-returns.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-[3fr_1fr_1.5fr]">
            @csrf
            <label class="bk-label sm:col-span-2 lg:col-span-1">
                Which flowers
                <select name="inventory_item_id" required class="bk-input">
                    <option value="">Choose flowers</option>
                    @foreach ($returnableBatches as $batch)
                        <option value="{{ $batch->id }}" @selected((int) old('inventory_item_id', $selectedItemId) === $batch->id)>
                            {{ $batch->flower_type }} from {{ $batch->supplier->name }} · arrived {{ $batch->date_received->format('d M') }} · {{ $batch->quantity }} left
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="bk-label">
                How many
                <input name="quantity" value="{{ old('quantity') }}" type="number" min="1" required class="bk-input">
            </label>
            <label class="bk-label">
                What's wrong
                <select name="reason" required class="bk-input">
                    @foreach (\App\Models\StockReturn::REASONS as $value => $label)
                        <option value="{{ $value }}" @selected(old('reason') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex flex-col gap-3 sm:col-span-2 sm:flex-row sm:items-end lg:col-span-3">
                <label class="bk-label flex-1">
                    <span>Message to the supplier <span class="font-normal text-[#8aa39c]">(optional)</span></span>
                    <input name="notes" value="{{ old('notes') }}" maxlength="1000" placeholder="e.g. Petals were bruised when they arrived" class="bk-input">
                </label>
                <button class="bk-btn bk-btn-primary shrink-0">Send back</button>
            </div>
        </form>
    </section>

    <section class="bk-card">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="bk-card-title">Flowers sent back</h2>
                <p class="mt-1 text-sm text-[#607a72]">See whether each supplier agreed to take the flowers back.</p>
            </div>
            <div class="rounded-xl bg-[#edf7f5] px-3 py-2 text-right">
                <p class="text-xs text-[#607a72]">Suppliers owe you</p>
                <p class="font-bold text-[#187849]">RM {{ number_format($acceptedValue, 2) }}</p>
            </div>
        </div>

        <nav class="mt-5 flex flex-wrap gap-2" aria-label="Filter by status">
            @foreach (['' => 'All', ...collect($statusStyles)->map(fn ($style) => $style['label'])->all()] as $status => $label)
                @php
                    $isActive = (string) $activeStatus === (string) $status;
                    $count = $status === '' ? $statusCounts->sum() : ($statusCounts[$status] ?? 0);
                @endphp
                <a
                    href="{{ route($area.'.stock-returns', array_filter(['status' => $status])) }}"
                    @if ($isActive) aria-current="page" @endif
                    @class([
                        'flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold transition',
                        'bg-[#285443] text-white' => $isActive,
                        'bg-white/70 text-[#49665f] hover:bg-white' => ! $isActive,
                    ])
                >
                    @if ($status !== '')
                        <span @class(['size-2 rounded-full', $isActive ? 'bg-white' : $statusStyles[$status]['dot']])></span>
                    @endif
                    {{ $label }}
                    <span @class(['rounded-full px-2 text-xs', 'bg-white/20' => $isActive, 'bg-[#dce9e6]' => ! $isActive])>{{ $count }}</span>
                </a>
            @endforeach
        </nav>

        <div class="mt-5 space-y-3">
            @forelse ($stockReturns as $stockReturn)
                <article @class(['bk-tile', 'opacity-70' => $stockReturn->status === 'cancelled'])>
                    {{-- Header: reference and supplier, with the status on the right --}}
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold tracking-wide text-[#8aa39c]">{{ $stockReturn->reference() }} · sent back to {{ $stockReturn->supplier->name }}</p>
                            <h3 class="mt-0.5 text-lg font-semibold text-[#1c2e28]">{{ $stockReturn->quantity }} × {{ $stockReturn->flower_type }}</h3>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusStyles[$stockReturn->status]['badge'] ?? '' }}">{{ $statusStyles[$stockReturn->status]['label'] ?? ucfirst($stockReturn->status) }}</span>
                    </div>

                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-4">
                        <div>
                            <dt class="text-xs text-[#8aa39c]">Problem</dt>
                            <dd class="font-medium text-[#1c2e28]">{{ $stockReturn->reasonLabel() }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[#8aa39c]">Worth (what you paid)</dt>
                            <dd class="font-medium text-[#1c2e28]">RM {{ number_format($stockReturn->value(), 2) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[#8aa39c]">Sent back on</dt>
                            <dd class="font-medium text-[#1c2e28]">{{ $stockReturn->created_at->timezone('Asia/Kuala_Lumpur')->format('d M Y') }} <span class="font-normal text-[#607a72]">by {{ $stockReturn->creator->name }}</span></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[#8aa39c]">{{ $stockReturn->responded_at ? 'Supplier answered on' : 'Flowers arrived on' }}</dt>
                            <dd class="font-medium text-[#1c2e28]">
                                @if ($stockReturn->responded_at)
                                    {{ $stockReturn->responded_at->timezone('Asia/Kuala_Lumpur')->format('d M Y') }}
                                @else
                                    {{ $stockReturn->inventoryItem?->date_received->format('d M Y') ?? '—' }}
                                @endif
                            </dd>
                        </div>
                    </dl>

                    @if ($stockReturn->notes)
                        <p class="mt-3 rounded-lg bg-[#fdf6ea] px-3 py-2 text-sm text-[#7a5a2b]"><span class="font-semibold">Your message:</span> {{ $stockReturn->notes }}</p>
                    @endif
                    @if ($stockReturn->supplier_note)
                        <p @class(['mt-2 rounded-lg px-3 py-2 text-sm', 'bg-[#fbeae7] text-[#b84331]' => $stockReturn->status === 'rejected', 'bg-[#edf7f5] text-[#285443]' => $stockReturn->status !== 'rejected'])>
                            <span class="font-semibold">Supplier's reply:</span> {{ $stockReturn->supplier_note }}
                        </p>
                    @endif

                    @if ($stockReturn->status === 'rejected' || auth()->user()->can('cancel-stock-return', $stockReturn))
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-[#e3eeeb] pt-3">
                            @if ($stockReturn->status === 'rejected')
                                <p class="text-xs font-semibold text-[#a76513]">The supplier did not take these back, so they are counted as wastage (thrown away).</p>
                            @else
                                <p class="text-xs text-[#607a72]">Waiting for {{ $stockReturn->supplier->name }} to say yes or no.</p>
                            @endif
                            @can('cancel-stock-return', $stockReturn)
                                <form method="POST" action="{{ route($area.'.stock-returns.cancel', $stockReturn) }}" onsubmit="return confirm(@js("Cancel {$stockReturn->reference()}? The flowers will go back into your stock."))" class="ml-auto">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="bk-btn bk-btn-secondary bk-btn-sm h-8">Cancel, keep the flowers</button>
                                </form>
                            @endcan
                        </div>
                    @endif
                </article>
            @empty
                <p class="bk-tile text-sm text-[#607a72]">{{ $activeStatus ? 'Nothing here yet.' : 'You have not sent any flowers back yet.' }}</p>
            @endforelse
        </div>
    </section>
</x-workspace.layout>
