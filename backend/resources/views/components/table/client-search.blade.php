{{--
Client-side search box for server-rendered tables and card lists (pages that are not DataTables).
Usage: <x-table.client-search target="#my-tbody" />
Every direct child of `target` (e.g. <tr>) is matched against its text; rows marked
`data-client-search-ignore` (e.g. an "empty" row) are left alone.
--}}
@props([
    'target',
    'placeholder' => null,
])

<div {{ $attributes->merge(['class' => 'relative w-full sm:w-80']) }}>
    <x-heroicon name="magnifying-glass" class="w-4 h-4 text-gray-400 absolute top-1/2 -translate-y-1/2 start-3 pointer-events-none" />
    <input type="search" autocomplete="off" data-client-search="{{ $target }}"
        class="form-input text-sm w-full ps-9"
        placeholder="{{ $placeholder ?? __('admin.search') }}"
        aria-label="{{ $placeholder ?? __('admin.search') }}">
    <p class="hidden mt-2 text-xs text-gray-400 italic" data-client-search-empty>{{ __('admin.no_matching_records') }}</p>
</div>
