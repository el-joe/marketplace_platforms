@props([
    'title'    => null,
    'subtitle' => null,
    'padding'  => 'md', // sm | md | lg | none
])

@php
    $paddingClass = match($padding) {
        'none' => '',
        'sm'   => 'p-3',
        'lg'   => 'p-8',
        default => 'p-6',
    };
@endphp

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl shadow-sm border border-gray-200']) }}>
    @if($title)
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-4">
            <div class="min-w-0">
                <h3 class="text-base font-semibold text-gray-900 truncate">{{ $title }}</h3>
                @if($subtitle)
                    <p class="text-sm text-gray-500 truncate">{{ $subtitle }}</p>
                @endif
            </div>
            @if(isset($actions) && $actions->isNotEmpty())
                <div class="flex-shrink-0">{{ $actions }}</div>
            @endif
        </div>
    @endif

    <div class="{{ $paddingClass }}">
        {{ $slot }}
    </div>
</div>
