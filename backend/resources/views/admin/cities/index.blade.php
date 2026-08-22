@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('title', __('admin.geography.cities_title'))

@section('content')
    @php
        $columns = [
            ['title' => __('admin.geography.city_name'), 'data' => 'name', 'name' => 'name'],
            ['title' => __('admin.geography.country_name'), 'data' => 'country_name', 'name' => 'country_name', 'orderable_column' => 'countries.name_en'],
            ['title' => __('admin.geography.zone_col'), 'data' => 'shipping_zone', 'name' => 'shipping_zone', 'orderable' => false, 'searchable' => false],
            [
                'title' => __('admin.geography.cod_col_city'),
                'data' => 'cod_available',
                'name' => 'cod_available',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-center w-12'
            ],
            [
                'title' => __('admin.geography.status_col'),
                'data' => 'is_active',
                'name' => 'is_active',
                'searchable' => false,
                'className' => 'text-center',
                'render' => 'Renderers.badge({true:{label:"' . __('common.active') . '",color:"success"},false:{label:"' . __('admin.geography.inactive') . '",color:"gray"}})'
            ],
            [
                'title' => '',
                'data' => 'actions',
                'name' => 'actions',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-end',
                'render' => 'Renderers.actions([{type:"link",label:"' . __('admin.geography.edit_link') . '",url:":edit_url",class:"btn-ghost btn-sm"}])'
            ],
        ];

        $filters = [
            ['type' => 'text', 'name' => 'search', 'label' => __('admin.geography.name_col')],
            [
                'type' => 'select',
                'name' => 'country_id',
                'label' => __('admin.geography.country_name'),
                'options' => array_merge(['' => __('admin.geography.all_countries')], $countries->toArray())
            ],
            [
                'type' => 'select',
                'name' => 'is_active',
                'label' => __('admin.geography.status_col'),
                'options' => ['' => __('common.all'), '1' => __('common.active'), '0' => __('admin.geography.inactive')]
            ],
            [
                'type' => 'select',
                'name' => 'no_zone',
                'label' => __('admin.geography.zone_label'),
                'options' => ['' => __('common.all'), '1' => __('admin.geography.no_zone_warning')]
            ],
        ];
    @endphp

    <div class="flex items-center justify-between mb-4">
        <div></div>
        <div class="flex gap-2">
            <button type="button" id="btn-bulk-import" class="btn btn-secondary">
                <x-heroicon name="arrow-up-tray" class="w-4 h-4" />
                {{ __('admin.geography.bulk_import_csv') }}
            </button>
            <a href="{{ route('admin.cities.create') }}" class="btn btn-primary">
                <x-heroicon name="plus" class="w-4 h-4" />
                {{ __('admin.geography.add_city') }}
            </a>
        </div>
    </div>

    <x-table.datatable id="cities-table" url="{{ route('admin.cities.datatable') }}" :columns="$columns" :filters="$filters"
        :page-length="50" :order="[[1, 'asc'], [0, 'asc']]" />

    {{-- Bulk Import Modal --}}
    <x-modal id="bulk-import-modal" title="{{ __('admin.geography.bulk_import_cities_title') }}">
        <div class="p-4 space-y-4">
            <div class="bg-blue-50 rounded-lg p-3 text-sm text-blue-800">
                <p class="font-semibold mb-1">{{ __('admin.geography.csv_format_title') }}</p>
                <code
                    class="text-xs block font-mono">name_en, name_ar, country_id_or_iso2, latitude, longitude, shipping_zone_id, is_active (0/1), cod_available (0/1)</code>
                <p class="mt-1 text-xs text-blue-600">{{ __('admin.geography.csv_optional_note') }}</p>
            </div>
            <form id="bulk-import-form" enctype="multipart/form-data" novalidate>
                @csrf
                <x-form.input name="file" label="{{ __('admin.geography.csv_file_label') }}" type="file" accept=".csv,.txt" required />
            </form>
            <div id="import-progress" class="hidden">
                <div class="flex items-center gap-2 text-sm text-gray-600">
                    <svg class="animate-spin w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    {{ __('admin.geography.importing') }}
                </div>
            </div>
            <div id="import-result" class="hidden text-sm"></div>
        </div>
        <div class="flex justify-end gap-2 px-4 pb-4">
            <button type="button" class="btn btn-ghost" data-modal-close="bulk-import-modal">{{ __('common.close') }}</button>
            <button type="submit" form="bulk-import-form" id="btn-start-import" class="btn btn-primary">{{ __('admin.geography.import') }}</button>
        </div>
    </x-modal>
@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            selectCsvFile: @json(__('admin.geography.select_csv_file')),
            importingEllipsis: @json(__('admin.geography.importing_ellipsis')),
            importBtn: @json(__('admin.geography.import_btn')),
            rowErrorsCount: @json(__('admin.geography.row_errors_count')),
            moreErrors: @json(__('admin.geography.more_errors')),
            importFailed: @json(__('admin.geography.import_failed')),
            citiesInserted: @json(__('admin.geography.cities_inserted')),
        });
    </script>
    @vite('resources/js/admin/cities.js')
@endpush