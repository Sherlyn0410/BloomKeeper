<x-admin.layout
    title="Reports"
    description="Summarize flower demand over a collection-date range and export the results for review."
>
    <section class="rounded-[28px] bg-[#edf7f5]/90 p-5 shadow-[0_12px_30px_rgba(36,73,64,0.1)] sm:p-7">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="font-serif text-2xl font-bold">Sales report</h2>
                <p class="mt-1 text-sm text-[#607a72]">See the total quantity ordered for each flower type.</p>
            </div>
            <a href="{{ route('admin.reports.export', request()->query()) }}" class="rounded-lg bg-[#285443] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#1f4435]">Export CSV</a>
        </div>
        <form class="mt-5 flex flex-wrap gap-2">
            <label class="flex flex-col gap-1 text-xs font-semibold text-[#49665f]">From<input name="from" value="{{ request('from') }}" type="date" class="rounded-lg border-0 p-2 text-sm font-normal"></label>
            <label class="flex flex-col gap-1 text-xs font-semibold text-[#49665f]">To<input name="to" value="{{ request('to') }}" type="date" class="rounded-lg border-0 p-2 text-sm font-normal"></label>
            <button class="self-end rounded-lg bg-[#e3953d] px-3 py-2 font-semibold text-white transition hover:bg-[#d47f2a]">Run report</button>
        </form>
        <div class="mt-6 overflow-x-auto">
            <table class="w-full min-w-[420px] text-left text-sm">
                <thead class="border-b border-[#d4e4e0] text-xs uppercase tracking-wider text-[#607a72]"><tr><th class="pb-3">Flower type</th><th class="pb-3 text-right">Quantity ordered</th></tr></thead>
                <tbody class="divide-y divide-[#dce9e6]">
                    @forelse ($report as $row)
                        <tr><td class="py-4 font-semibold">{{ $row->flower_type }}</td><td class="py-4 text-right font-semibold">{{ $row->quantity }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="py-8 text-center text-sm text-[#607a72]">No order data matches this date range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-admin.layout>
