@props([
    'route',
    'icon'  => 'squares-2x2',
    'label' => '',
    'badge' => null,
])

@php
    $isActive = request()->routeIs($route);
    $href = Route::has($route) ? route($route) : '#';
@endphp

<a href="{{ $href }}"
   @class([
       'flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors duration-150',
       'bg-yellow-400/10 text-yellow-400'       => $isActive,
       'text-gray-300 hover:text-white hover:bg-gray-700/70' => !$isActive,
   ])>
    <x-heroicon :name="$icon" class="w-4 h-4 flex-shrink-0" />
    <span class="flex-1">{{ $label }}</span>
    @if($badge)
        <span class="bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full leading-none">
            {{ $badge }}
        </span>
    @endif
</a>
