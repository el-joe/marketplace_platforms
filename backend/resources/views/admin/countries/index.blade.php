@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('title', __('admin.geography.countries_title'))

@section('content')
    @php
        $columns = [
            ['title' => __('admin.geography.country_name'), 'data' => 'name', 'name' => 'name'],
            ['title' => __('admin.geography.code_col'), 'data' => 'iso_code_2', 'name' => 'iso_code_2', 'className' => 'text-center w-16'],
            ['title' => __('admin.geography.currency_col'), 'data' => 'currency_code', 'name' => 'currency_code', 'orderable' => false, 'searchable' => false, 'className' => 'text-center w-20'],
            ['title' => __('admin.geography.vat_col'), 'data' => 'vat_rate', 'name' => 'vat_rate', 'orderable' => false, 'searchable' => false, 'className' => 'text-end w-16'],
            ['title' => __('admin.geography.cities_col'), 'data' => 'cities_count', 'name' => 'cities_count', 'orderable' => false, 'searchable' => false, 'className' => 'text-center w-16'],
            ['title' => __('admin.geography.payment_gateways_col'), 'data' => 'active_payment_gateways', 'name' => 'active_payment_gateways', 'orderable' => false, 'searchable' => false, 'className' => 'text-center'],
            [
                'title' => __('admin.geography.cod_col'),
                'data' => 'cod_available',
                'name' => 'cod_available',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-center w-12',
                'render' => 'function(data){return data?"<span class=\"text-success-600 font-bold\">✓</span>":"<span class=\"text-gray-300\">—</span>";}'
            ],
            [
                'title' => __('admin.geography.status_col'),
                'data' => 'is_launched',
                'name' => 'is_launched',
                'orderable_column' => 'countries.is_launched',
                'searchable' => false,
                'className' => 'text-center',
                'render' => 'Renderers.badge({
                                                  true:  { label: "' . __('admin.geography.launched') . '", color: "success" },
                                                  false: { label: "' . __('admin.geography.draft') . '",    color: "gray"    }
                                              })',
            ],
            [
                'title' => '',
                'data' => 'actions',
                'name' => 'actions',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-end',
                'render' => 'Renderers.actions([
                                             { type: "link", label: "' . __('admin.geography.configure') . '", url: ":edit_url", class: "btn-primary btn-sm" }
                                         ])',
            ],
        ];

        $filters = [
            ['type' => 'text', 'name' => 'search', 'label' => __('common.search'), 'placeholder' => __('admin.geography.search_placeholder_countries')],
            [
                'type' => 'select',
                'name' => 'is_launched',
                'label' => __('admin.geography.launch_status'),
                'options' => ['' => __('common.all'), '1' => __('admin.geography.launched'), '0' => __('admin.geography.draft')]
            ],
            [
                'type' => 'select',
                'name' => 'is_active',
                'label' => __('admin.geography.is_active'),
                'options' => ['' => __('common.all'), '1' => __('common.active'), '0' => __('admin.geography.inactive')]
            ],
        ];
    @endphp

    <x-table.datatable id="countries-table" url="{{ route('admin.countries.datatable') }}" :columns="$columns"
        :filters="$filters" :create-action="['url' => route('admin.countries.create'), 'label' => __('admin.geography.add_country')]"
        :selectable="false" :page-length="25" :order="[[0, 'asc']]" />
@endsection

@push('scripts')
    @vite('resources/js/admin/countries.js')
@endpush
