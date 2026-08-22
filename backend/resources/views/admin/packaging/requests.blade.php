@extends('layouts.admin')

@section('title', __('admin.packaging_requests_section.title'))

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('content')
<div class="p-6 space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.packaging_requests_section.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.packaging_requests_section.subtitle') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <x-export-dropdown />
            <a href="{{ route('admin.packaging.catalog') }}" class="btn btn-secondary text-sm">{{ __('admin.packaging_requests_section.manage_catalog') }}</a>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <x-stat-card title="{{ __('admin.packaging_requests_section.stat_pending') }}" :value="$stats['pending']" icon="clock" iconBg="bg-amber-100 text-amber-600" />
        <x-stat-card title="{{ __('admin.packaging_requests_section.stat_in_transit') }}" :value="$stats['in_transit']" icon="truck" iconBg="bg-indigo-100 text-indigo-600" />
        <x-stat-card title="{{ __('admin.packaging_requests_section.stat_delivered_month') }}" :value="$stats['delivered_this_month']" icon="check-circle" iconBg="bg-emerald-100 text-emerald-600" />
    </div>

    @if($stats['revenue_this_month']->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">{{ __('admin.packaging_requests_section.revenue_this_month') }}</p>
        <div class="flex flex-wrap gap-4">
            @foreach($stats['revenue_this_month'] as $row)
                <div class="text-sm">
                    <span class="font-semibold text-gray-900">{{ number_format($row['total']) }}</span>
                    <span class="text-gray-500">{{ $row['currency'] }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- DataTable --}}
    @php
        $columns = [
            ['title' => __('admin.packaging_requests_section.col_request_number'), 'data' => 'request_number', 'name' => 'request_number'],
            ['title' => __('admin.packaging_requests_section.col_vendor'), 'data' => 'vendor', 'name' => 'vendor', 'searchable' => false],
            ['title' => __('admin.packaging_requests_section.col_status'), 'data' => 'status', 'name' => 'status', 'searchable' => false],
            ['title' => __('admin.packaging_requests_section.col_items'), 'data' => 'items_count', 'name' => 'items_count', 'orderable' => false, 'searchable' => false],
            ['title' => __('admin.packaging_requests_section.col_total'), 'data' => 'total_cost', 'name' => 'total_cost', 'searchable' => false],
            ['title' => __('admin.packaging_requests_section.col_delivery_fee'), 'data' => 'delivery_fee', 'name' => 'delivery_fee', 'searchable' => false],
            ['title' => __('admin.packaging_requests_section.col_grand_total'), 'data' => 'grand_total', 'name' => 'grand_total', 'orderable' => false, 'searchable' => false],
            ['title' => __('admin.packaging_requests_section.col_currency'), 'data' => 'currency', 'name' => 'currency', 'orderable' => false, 'searchable' => false],
            ['title' => __('admin.packaging_requests_section.col_date'), 'data' => 'created_at', 'name' => 'created_at', 'searchable' => false],
            ['title' => '', 'data' => 'actions', 'name' => 'actions', 'orderable' => false, 'searchable' => false, 'className' => 'text-end'],
        ];

        $filters = [
            [
                'type' => 'select',
                'name' => 'status',
                'label' => __('admin.packaging_requests_section.filter_status'),
                'options' => [
                    'pending' => __('admin.packaging_requests_section.filter_status_pending'),
                    'approved' => __('admin.packaging_requests_section.filter_status_approved'),
                    'shipped' => __('admin.packaging_requests_section.filter_status_shipped'),
                    'delivered' => __('admin.packaging_requests_section.filter_status_delivered'),
                    'rejected' => __('admin.packaging_requests_section.filter_status_rejected'),
                ],
            ],
            [
                'type' => 'select',
                'name' => 'vendor_id',
                'label' => __('admin.packaging_requests_section.filter_vendor'),
                'options' => $vendors->pluck('store_name', 'id')->toArray(),
            ],
            [
                'type' => 'date_range',
                'name' => 'date',
                'label' => __('admin.packaging_requests_section.filter_date_range'),
            ],
        ];
    @endphp

    <x-table.datatable id="packaging-requests-table" url="{{ route('admin.packaging.requests.datatable') }}"
        :columns="$columns" :filters="$filters" :page-length="25" />
</div>
@endsection
