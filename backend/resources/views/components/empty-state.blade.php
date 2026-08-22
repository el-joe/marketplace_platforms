@props([
    'icon'        => 'inbox',
    'title'       => null,
    'message'     => '',
    'actionLabel' => null,
    'actionUrl'   => null,
])

@php
    $title = $title ?? __('common.nothing_here_yet');
@endphp

<div {{ $attributes->merge(['class' => 'text-center py-12 px-6']) }}>
    <div class="mx-auto w-14 h-14 rounded-full bg-gray-100 inline-flex items-center justify-center text-gray-400">
        <x-heroicon :name="$icon" class="w-7 h-7" />
    </div>
    <h3 class="mt-4 text-base font-semibold text-gray-900">{{ $title }}</h3>
    @if($message)
        <p class="mt-1 text-sm text-gray-500 max-w-md mx-auto">{{ $message }}</p>
    @endif
    @if($actionLabel && $actionUrl)
        <a href="{{ $actionUrl }}" class="btn-primary mt-5 inline-flex">
            {{ $actionLabel }}
        </a>
    @endif
</div>
