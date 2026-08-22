{{--
  Usage:  <x-export-dropdown />

  Small Excel/CSV/Word export dropdown. Builds links from the current
  request's query string plus `export=excel|csv|word`, pointing at the
  controller's own index route (which handles the `export` param).
--}}
<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <button type="button" @click="open = !open" class="btn btn-secondary btn-sm">
        {{ __('common.export') }}
        <x-heroicon name="chevron-down" class="w-4 h-4 ms-1 inline" />
    </button>
    <div x-show="open" x-transition x-cloak
        class="absolute end-0 mt-1 z-20 w-40 bg-white rounded-lg shadow-lg border border-gray-100 py-1">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}"
            class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __('common.export_excel') }}</a>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"
            class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __('common.export_csv') }}</a>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'word']) }}"
            class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __('common.export_word') }}</a>
    </div>
</div>
