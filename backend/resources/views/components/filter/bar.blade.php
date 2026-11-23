{{--
  Always-visible GET filter row for server-rendered admin lists (no DataTables).
  Usage:
    <x-filter.bar :statuses="['active' => __('common.active'), 'inactive' => __('common.inactive')]"
                  :search-placeholder="__('admin.faqs.search_placeholder')" />
  Extra fields go in the default slot. Other query params (e.g. ?context=) are preserved.

  Props:
    statuses          (array)       value => label for the `status` select (omit to hide it)
    statusName        (string)      request key for the status select (default `status`)
    searchPlaceholder (string|null)
--}}
@props([
    'statuses' => [],
    'statusName' => 'status',
    'searchPlaceholder' => null,
])

@php
    $isFiltered = filled(request('search')) || filled(request($statusName));
@endphp

<form method="GET" action="{{ request()->url() }}" role="search"
    {{ $attributes->merge(['class' => 'flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm']) }}>
    <div class="min-w-[14rem] flex-1">
        <x-filter.search name="search" :placeholder="$searchPlaceholder" />
    </div>

    @if(count($statuses))
        <div class="w-full sm:w-48">
            <x-filter.select :name="$statusName" :label="__('common.status')" :options="$statuses" />
        </div>
    @endif

    {{ $slot }}

    @foreach(request()->except(['search', $statusName, 'page']) as $key => $value)
        @if(is_scalar($value))
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach

    <div class="flex items-center gap-2">
        <button type="submit" class="btn btn-primary btn-sm">{{ __('common.apply_filters') }}</button>
        @if($isFiltered)
            <a href="{{ request()->fullUrlWithQuery(['search' => null, $statusName => null, 'page' => null]) }}" class="btn btn-secondary btn-sm">{{ __('common.clear_all') }}</a>
        @endif
    </div>
</form>
