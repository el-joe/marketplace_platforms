{{-- Copy the selected page (sections, blocks, items) to one or more other countries --}}
<x-modal id="copy-page-modal" title="{{ __('admin.page_builder.copy_to_country.title') }}" size="md">
    <form id="copy-page-form" class="px-6 py-4 space-y-4">
        @csrf
        <p class="text-sm text-gray-600">{{ __('admin.page_builder.copy_to_country.description') }}</p>

        <x-form.input name="copy_name" label="{{ __('admin.page_builder.create_page_modal.page_name') }}" />

        <div>
            <div class="text-sm font-medium text-gray-700 mb-2">{{ __('admin.page_builder.copy_to_country.target_countries') }}</div>
            <div id="copy-country-list" class="max-h-56 overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100">
                @foreach($countries as $c)
                    <label class="flex items-center gap-2 px-3 py-2 text-sm cursor-pointer hover:bg-gray-50" data-country-id="{{ $c->id }}">
                        <input type="checkbox" name="country_ids[]" value="{{ $c->id }}" class="rounded border-gray-300">
                        <span>{{ $c->name_en }} ({{ $c->site_code ?? '—' }})</span>
                    </label>
                @endforeach
            </div>
        </div>
    </form>

    <div class="px-6 py-3 border-t border-gray-200 flex justify-end gap-2 bg-gray-50 rounded-b-lg">
        <button type="button" data-modal-close class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
            {{ __('common.cancel') }}
        </button>
        <button type="submit" form="copy-page-form" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg">
            {{ __('admin.page_builder.copy_to_country.submit') }}
        </button>
    </div>
</x-modal>
