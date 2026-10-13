@php
    $statusStyles = [
        'sent' => ['label' => 'Awaiting you', 'badge' => 'bg-[#fff0d5] text-[#a76513]', 'dot' => 'bg-[#a76513]'],
        'fulfilled' => ['label' => 'Fulfilled', 'badge' => 'bg-[#d7f0df] text-[#187849]', 'dot' => 'bg-[#187849]'],
        'declined' => ['label' => 'Declined', 'badge' => 'bg-[#f5dcd7] text-[#b84331]', 'dot' => 'bg-[#b84331]'],
        'cancelled' => ['label' => 'Cancelled', 'badge' => 'bg-[#dce9e6] text-[#49665f]', 'dot' => 'bg-[#8aa39c]'],
    ];
@endphp

<x-staff.layout
    title="Purchase orders"
    description="Flowers the shop has ordered from you. Mark each one fulfilled when you deliver, or decline it with a reason."
>
    <section class="bk-card">
        <nav class="flex flex-wrap gap-2" aria-label="Filter by status">
            @foreach (['' => 'All', ...collect($statusStyles)->map(fn ($style) => $style['label'])->all()] as $status => $label)
                @php
                    $isActive = (string) $activeStatus === (string) $status;
                    $count = $status === '' ? $statusCounts->sum() : ($statusCounts[$status] ?? 0);
                @endphp
                <a
                    href="{{ route('supplier.purchase-orders.index', array_filter(['status' => $status])) }}"
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
            @forelse ($purchaseOrders as $purchaseOrder)
                @php
                    $daysUntilNeeded = (int) today()->diffInDays($purchaseOrder->requested_date, false);
                    $dueLabel = ! $purchaseOrder->isAwaitingSupplier() ? null : match (true) {
                        $daysUntilNeeded < 0 => ['text' => 'Overdue', 'class' => 'bg-[#f5dcd7] text-[#b84331]'],
                        $daysUntilNeeded === 0 => ['text' => 'Due today', 'class' => 'bg-[#e3953d] text-white'],
                        $daysUntilNeeded <= 3 => ['text' => "Due in {$daysUntilNeeded} ".Str::plural('day', $daysUntilNeeded), 'class' => 'bg-[#fff0d5] text-[#a76513]'],
                        default => null,
                    };
                @endphp
                <article @class(['bk-tile', 'opacity-70' => $purchaseOrder->status === 'cancelled'])>
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold tracking-wide text-[#8aa39c]">{{ $purchaseOrder->reference() }}</p>
                            <h3 class="mt-0.5 text-lg font-semibold text-[#1c2e28]">{{ $purchaseOrder->items->map(fn ($item) => $item->quantity.' × '.$item->flower_type)->join(', ') }}</h3>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusStyles[$purchaseOrder->status]['badge'] ?? '' }}">{{ $statusStyles[$purchaseOrder->status]['label'] ?? ucfirst($purchaseOrder->status) }}</span>
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-[#607a72]">
                        <span>Needed by {{ $purchaseOrder->requested_date->format('d M Y') }}</span>
                        @if ($dueLabel)
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $dueLabel['class'] }}">{{ $dueLabel['text'] }}</span>
                        @endif
                        <span>· ordered by {{ $purchaseOrder->creator->name }} on {{ $purchaseOrder->created_at->timezone('Asia/Kuala_Lumpur')->format('d M Y') }}</span>
                    </div>

                    @if ($purchaseOrder->notes)
                        <p class="mt-3 rounded-lg bg-[#fdf6ea] px-3 py-2 text-sm text-[#7a5a2b]"><span class="font-semibold">Note from the shop:</span> {{ $purchaseOrder->notes }}</p>
                    @endif

                    @if ($purchaseOrder->isAwaitingSupplier())
                        <form method="POST" action="{{ route('supplier.purchase-orders.fulfill', $purchaseOrder) }}" class="mt-4 border-t border-[#e3eeeb] pt-4">
                            @csrf
                            @method('PATCH')
                            <label class="bk-label">
                                <span>Note to the shop <span class="font-normal text-[#8aa39c]">(optional when fulfilling, required when declining)</span></span>
                                <textarea name="supplier_note" rows="2" maxlength="1000" placeholder="e.g. Delivered to the back door, 10am" class="bk-input"></textarea>
                            </label>
                            <div class="mt-3 flex flex-wrap justify-end gap-2">
                                <button
                                    type="submit"
                                    formaction="{{ route('supplier.purchase-orders.decline', $purchaseOrder) }}"
                                    class="bk-btn bk-btn-danger-soft"
                                    onclick="const note = this.form.supplier_note; if (! note.value.trim()) { note.setCustomValidity('Please give the shop a reason for declining.'); note.reportValidity(); note.addEventListener('input', () => note.setCustomValidity(''), { once: true }); return false; } return confirm(@js('Decline '.$purchaseOrder->reference().'? The shop will see your reason.'))"
                                >Decline</button>
                                <button type="submit" class="bk-btn bk-btn-primary" onclick="return confirm(@js('Mark '.$purchaseOrder->reference().' as fulfilled?'))">Mark fulfilled</button>
                            </div>
                        </form>
                    @else
                        @if ($purchaseOrder->supplier_note)
                            <p class="mt-3 rounded-lg bg-[#edf7f5] px-3 py-2 text-sm text-[#285443]"><span class="font-semibold">Your note:</span> {{ $purchaseOrder->supplier_note }}</p>
                        @endif
                        @if ($purchaseOrder->fulfilled_at)
                            <p class="mt-3 text-sm text-[#187849]">Fulfilled on {{ $purchaseOrder->fulfilled_at->timezone('Asia/Kuala_Lumpur')->format('d M Y, h:i A') }}</p>
                        @endif
                    @endif
                </article>
            @empty
                <div class="bk-tile py-10 text-center">
                    <p class="font-semibold text-[#49665f]">No purchase orders here</p>
                    <p class="mt-1 text-sm text-[#607a72]">When the shop sends you an order, it will appear on this page.</p>
                </div>
            @endforelse
        </div>
    </section>
</x-staff.layout>
