@props(['stats'])

<div {{ $attributes->merge(['class' => 'grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 xl:grid-cols-5']) }}>
    @foreach ($stats as $stat)
        <div class="bk-card">
            <p class="text-sm text-[#49665f]">{{ $stat['label'] }}</p>
            <p class="mt-3 truncate text-2xl font-bold sm:text-3xl {{ $stat['tone'] }}">{{ $stat['value'] }}</p>
            <p class="mt-2 text-sm text-[#607a72]">{{ $stat['note'] }}</p>
        </div>
    @endforeach
</div>
