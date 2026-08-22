{{--
  Usage:
  <x-filter-card :active="$hasActiveFilters">
    <x-slot name="filters">
      {{-- filter fields go here, e.g. <x-filter.search /> <x-filter.select .../> --}}
    </x-slot>
  </x-filter-card>

  Props:
    active (bool)   — true if any filter is currently applied (shows badge, starts expanded)
    action (string) — form action URL (default: current URL)
    method (string) — form method (default: GET; filters should always be GET)
--}}
@props([
    'active' => false,
    'action' => '',
    'method' => 'GET',
])

<div x-data="{ open: {{ $active ? 'true' : 'false' }} }" class="bg-white border border-gray-200 rounded-xl shadow-sm mb-4">

    <button type="button" @click="open = !open"
        class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 rounded-xl transition-colors">
        <span class="flex items-center gap-2 text-sm font-medium text-gray-700">
            <x-heroicon name="adjustments-horizontal" class="w-4 h-4 text-gray-400" />
            {{ __('common.filters') }}
            @if($active)
                <span class="px-2 py-0.5 text-xs font-semibold bg-primary-100 text-primary-700 rounded-full">
                    {{ __('common.active') }}
                </span>
            @endif
        </span>
        <x-heroicon name="chevron-down" class="w-4 h-4 text-gray-400 transition-transform duration-200"
            x-bind:class="open ? 'rotate-180' : ''" />
    </button>

    <div x-show="open" x-transition class="border-t border-gray-100">
        <form method="{{ $method }}" action="{{ $action ?: request()->url() }}" class="px-4 py-4">

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 mb-4">
                {{ $filters }}
            </div>

            <div class="flex items-center gap-2 pt-3 border-t border-gray-100">
                <button type="submit" class="btn-primary btn-sm">
                    {{ __('common.apply_filters') }}
                </button>
                <a href="{{ request()->url() }}" class="btn-secondary btn-sm">
                    {{ __('common.clear_all') }}
                </a>

                @foreach(request()->except(['page', 'date_from', 'date_to', 'search',
                    'status', 'country_id', 'vendor_id', 'type', 'payment_method',
                    'carrier_id', 'zone_id', 'category_id', 'export']) as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
            </div>
        </form>
    </div>
</div>
