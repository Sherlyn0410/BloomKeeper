@props(['stats'])

<div {{ $attributes->merge(['class' => 'grid gap-4 sm:grid-cols-2 xl:grid-cols-5']) }}>
    @foreach ($stats as $stat)
        <div class="bk-card">
            <p class="text-sm text-[#49665f]">{{ $stat['label'] }}</p>
            <p class="mt-3 text-3xl font-bold {{ $stat['tone'] }}">{{ $stat['value'] }}</p>
            <p class="mt-2 text-sm text-[#607a72]">{{ $stat['note'] }}</p>
        </div>
    @endforeach
</div>
