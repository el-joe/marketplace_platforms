{{--
  Usage (inside the form wrapped by <x-filter-card>):
    <x-filter.search name="search" placeholder="Search orders..." />
--}}
@props([
    'name'        => 'search',
    'label'       => null,
    'placeholder' => null,
])

<div>
    <label for="filter-{{ $name }}" class="block text-xs font-medium text-gray-500 mb-1">
        {{ $label ?? __('common.search') }}
    </label>
    <div class="relative">
        <x-heroicon name="magnifying-glass" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
        <input type="text" id="filter-{{ $name }}" name="{{ $name }}" value="{{ request($name) }}"
            placeholder="{{ $placeholder ?? __('common.search') }}"
            class="form-input text-sm pl-9 w-full">
    </div>
</div>
