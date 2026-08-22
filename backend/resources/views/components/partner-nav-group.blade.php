@props(['label'])

@php
    $hasActiveChild = str_contains($slot->toHtml(), 'bg-yellow-400/10');
@endphp

<div x-data="{ open: {{ $hasActiveChild ? 'true' : 'false' }} }" class="pt-1">
    <button type="button"
            @click="open = !open"
            class="w-full flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold uppercase tracking-wider text-gray-400 hover:text-gray-200 transition-colors duration-150 select-none">
        <span class="flex-1 text-start">{{ $label }}</span>
        <x-heroicon name="chevron-down" class="w-3.5 h-3.5 flex-shrink-0 transition-transform duration-150"
                    x-bind:class="{ '-rotate-180': open }" />
    </button>
    <div x-show="open"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-cloak
         class="mt-0.5 space-y-0.5">
        {{ $slot }}
    </div>
</div>
