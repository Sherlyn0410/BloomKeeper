@php
    $statusStyles = [
        'requested' => ['label' => 'Awaiting you', 'badge' => 'bg-[#fff0d5] text-[#a76513]', 'dot' => 'bg-[#a76513]'],
        'accepted' => ['label' => 'Accepted', 'badge' => 'bg-[#d7f0df] text-[#187849]', 'dot' => 'bg-[#187849]'],
        'rejected' => ['label' => 'Rejected', 'badge' => 'bg-[#f5dcd7] text-[#b84331]', 'dot' => 'bg-[#b84331]'],
        'cancelled' => ['label' => 'Cancelled', 'badge' => 'bg-[#dce9e6] text-[#49665f]', 'dot' => 'bg-[#8aa39c]'],
    ];
@endphp

<x-staff.layout
    title="Returns"
    description="Flowers the shop is sending back because of a problem. Accept the return, or reject it with a reason."
>
    <section data-scroll-on-filter="status" class="scroll-mt-4 bk-card">
        <nav class="flex flex-wrap gap-2" aria-label="Filter by status">
            @foreach (['' => 'All', ...collect($statusStyles)->map(fn ($style) => $style['label'])->all()] as $status => $label)
                @php
                    $isActive = (string) $activeStatus === (string) $status;
                    $count = $status === '' ? $statusCounts->sum() : ($statusCounts[$status] ?? 0);
                @endphp
                <a
                    href="{{ route('supplier.stock-returns.index', array_filter(['status' => $status])) }}"
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
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold tracking-wide text-[#8aa39c]">{{ $stockReturn->reference() }}</p>
                            <h3 class="mt-0.5 text-lg font-semibold text-[#1c2e28]">{{ $stockReturn->quantity }} × {{ $stockReturn->flower_type }}</h3>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusStyles[$stockReturn->status]['badge'] ?? '' }}">{{ $statusStyles[$stockReturn->status]['label'] ?? ucfirst($stockReturn->status) }}</span>
                    </div>

                    <p class="mt-2 text-sm text-[#607a72]">
                        Reason: <span class="font-semibold text-[#1c2e28]">{{ $stockReturn->reasonLabel() }}</span>
                        · RM {{ number_format($stockReturn->value(), 2) }} at cost
                        · sent by {{ $stockReturn->creator->name }} on {{ $stockReturn->created_at->timezone('Asia/Kuala_Lumpur')->format('d M Y') }}
                    </p>

                    @if ($stockReturn->notes)
                        <p class="mt-3 rounded-lg bg-[#fdf6ea] px-3 py-2 text-sm text-[#7a5a2b]"><span class="font-semibold">Details from the shop:</span> {{ $stockReturn->notes }}</p>
                    @endif

                    @if ($stockReturn->isAwaitingSupplier())
                        <form method="POST" action="{{ route('supplier.stock-returns.accept', $stockReturn) }}" class="mt-4 border-t border-[#e3eeeb] pt-4">
                            @csrf
                            @method('PATCH')
                            <label class="bk-label">
                                <span>Note to the shop <span class="font-normal text-[#8aa39c]">(optional when accepting, required when rejecting)</span></span>
                                <textarea name="supplier_note" rows="2" maxlength="1000" placeholder="e.g. Credit added to your next invoice" class="bk-input"></textarea>
                            </label>
                            <div class="mt-3 flex flex-wrap justify-end gap-2">
                                <button
                                    type="submit"
                                    formaction="{{ route('supplier.stock-returns.reject', $stockReturn) }}"
                                    class="bk-btn bk-btn-danger-soft"
                                    onclick="const note = this.form.supplier_note; if (! note.value.trim()) { note.setCustomValidity('Please give the shop a reason for rejecting the return.'); note.reportValidity(); note.addEventListener('input', () => note.setCustomValidity(''), { once: true }); return false; } return confirm(@js('Reject '.$stockReturn->reference().'? The shop will see your reason.'))"
                                >Reject</button>
                                <button type="submit" class="bk-btn bk-btn-primary" onclick="return confirm(@js('Accept '.$stockReturn->reference().'?'))">Accept return</button>
                            </div>
                        </form>
                    @else
                        @if ($stockReturn->supplier_note)
                            <p class="mt-3 rounded-lg bg-[#edf7f5] px-3 py-2 text-sm text-[#285443]"><span class="font-semibold">Your note:</span> {{ $stockReturn->supplier_note }}</p>
                        @endif
                        @if ($stockReturn->responded_at)
                            <p class="mt-3 text-sm text-[#607a72]">{{ ucfirst($stockReturn->status) }} on {{ $stockReturn->responded_at->timezone('Asia/Kuala_Lumpur')->format('d M Y, h:i A') }}</p>
                        @endif
                    @endif
                </article>
            @empty
                <div class="bk-tile py-10 text-center">
                    <p class="font-semibold text-[#49665f]">No returns here</p>
                    <p class="mt-1 text-sm text-[#607a72]">When the shop sends flowers back to you, they will appear on this page.</p>
                </div>
            @endforelse
        </div>
    </section>
</x-staff.layout>
