@props([
    'title'         => '',
    'value'         => '',
    'change'        => null,        // numeric %, null = no change shown
    'changeLabel'   => null,
    'icon'          => null,
    'iconBg'        => 'bg-primary-100 text-primary-600',
    'loading'       => false,
    'href'          => null,
])

@php
    $isUp   = is_numeric($change) && $change >= 0;
    $changeClass = $isUp ? 'text-success-600' : 'text-danger-600';
    $changeLabel = $changeLabel ?? __('common.vs_last_period');
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'block bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:shadow-md transition-shadow']) }}>

    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm font-medium text-gray-500 truncate">{{ $title }}</p>

            @if($loading)
                <div class="mt-2 h-8 w-24 bg-gray-200 rounded animate-pulse"></div>
            @else
                <p class="mt-1 text-2xl font-bold text-gray-900 truncate">{{ $value }}</p>
            @endif

            @if(! $loading && is_numeric($change))
                <p class="mt-2 flex items-center gap-1 text-xs {{ $changeClass }}">
                    @if($isUp)
                        <x-heroicon name="arrow-trending-up" class="w-3.5 h-3.5" />
                    @else
                        <x-heroicon name="arrow-trending-down" class="w-3.5 h-3.5" />
                    @endif
                    <span class="font-semibold">{{ ($isUp ? '+' : '') . number_format((float) $change, 1) }}%</span>
                    <span class="text-gray-500 font-normal">{{ $changeLabel }}</span>
                </p>
            @endif
        </div>

        @if($icon)
            <div class="w-10 h-10 rounded-lg inline-flex items-center justify-center flex-shrink-0 {{ $iconBg }}">
                <x-heroicon :name="$icon" class="w-5 h-5" />
            </div>
        @endif
    </div>
</{{ $tag }}>
