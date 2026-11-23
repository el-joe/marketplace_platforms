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
                'title' => __('admin.geography.cod_max_col'),
                'data' => 'cod_max_amount',
                'name' => 'cod_max_amount',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-center w-20',
                'render' => 'function(data){return data ? data : "' . __('admin.geography.unlimited_placeholder') . '";}'
            ],
            [
                'title' => __('admin.geography.status_col'),
                'data' => 'status',
                'name' => 'is_launched',
                'orderable_column' => 'countries.is_launched',
                'searchable' => false,
                'className' => 'text-center',
                'render' => 'Renderers.badge(' . json_encode([
                    \App\Models\Country::STATUS_LIVE => ['label' => __('admin.geography.status_live'), 'color' => 'success'],
                    \App\Models\Country::STATUS_DRAFT => ['label' => __('admin.geography.status_draft'), 'color' => 'gray'],
                    \App\Models\Country::STATUS_HIDDEN => ['label' => __('admin.geography.status_hidden'), 'color' => 'warning'],
                    \App\Models\Country::STATUS_DELETED => ['label' => __('admin.geography.status_deleted'), 'color' => 'danger'],
                ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) . ')',
            ],
            [
                'title' => __('admin.geography.vendors_col'),
                'data' => 'vendors_open',
                'name' => 'vendors_open',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-center',
                'render' => 'Renderers.badge(' . json_encode([
                    'true' => ['label' => __('admin.geography.vendors_open'), 'color' => 'primary'],
                    'false' => ['label' => __('admin.geography.vendors_closed'), 'color' => 'gray'],
                ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) . ')',
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
                'name' => 'visibility',
                'label' => __('admin.geography.visibility_filter'),
                'options' => [
                    '' => __('common.all'),
                    \App\Models\Country::STATUS_LIVE => __('admin.geography.status_live'),
                    \App\Models\Country::STATUS_DRAFT => __('admin.geography.status_draft'),
                    \App\Models\Country::STATUS_HIDDEN => __('admin.geography.status_hidden'),
                    \App\Models\Country::STATUS_DELETED => __('admin.geography.status_deleted'),
                ],
            ],
            [
                'type' => 'select',
                'name' => 'vendors_open',
                'label' => __('admin.geography.vendors_col'),
                'options' => ['' => __('common.all'), '1' => __('admin.geography.vendors_open'), '0' => __('admin.geography.vendors_closed')],
            ],
        ];
    @endphp

    <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-600" data-testid="country-status-legend">
        <p class="mb-2 font-semibold text-gray-800">{{ __('admin.geography.status_legend_title') }}</p>
        <ul class="space-y-1.5">
            <li class="flex items-start gap-2"><x-badge color="success">{{ __('admin.geography.status_live') }}</x-badge><span>{{ __('admin.geography.status_legend_live') }}</span></li>
            <li class="flex items-start gap-2"><x-badge color="gray">{{ __('admin.geography.status_draft') }}</x-badge><span>{{ __('admin.geography.status_legend_draft') }}</span></li>
            <li class="flex items-start gap-2"><x-badge color="warning">{{ __('admin.geography.status_hidden') }}</x-badge><span>{{ __('admin.geography.status_legend_hidden') }}</span></li>
            <li class="flex items-start gap-2"><x-badge color="primary">{{ __('admin.geography.vendors_open') }}</x-badge><span>{{ __('admin.geography.status_legend_vendors') }}</span></li>
        </ul>
    </div>

    <x-table.datatable id="countries-table" url="{{ route('admin.countries.datatable') }}" :columns="$columns"
        :filters="$filters" :create-action="['url' => route('admin.countries.create'), 'label' => __('admin.geography.add_country')]"
        :selectable="false" :page-length="25" :order="[[0, 'asc']]" />
@endsection

@push('scripts')
    @vite('resources/js/admin/countries.js')
@endpush
