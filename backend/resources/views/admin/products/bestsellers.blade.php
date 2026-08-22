@extends('layouts.admin')

@section('title', __('admin.bestsellers.title'))

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('content')
    @php
        $categoryOptions = ['' => __('admin.bestsellers.all_categories')];
        foreach ($categories as $cat) {
            $categoryOptions[$cat->id] = $cat->name_en;
        }

        $countryOptions = ['' => __('admin.bestsellers.all_countries')];
        foreach ($countries as $country) {
            $countryOptions[$country->id] = $country->name_en;
        }

        $columns = [
            ['title' => 'ID', 'data' => 'id', 'name' => 'id'],
            ['title' => __('admin.bestsellers.rank'), 'data' => 'rank', 'name' => 'rank', 'searchable' => false],
            ['title' => __('common.name'), 'data' => 'product_name', 'name' => 'product_name', 'orderable' => false],
            ['title' => __('admin.bestsellers.category'), 'data' => 'category_name', 'name' => 'category_name', 'orderable' => false, 'searchable' => false],
            ['title' => __('admin.bestsellers.country'), 'data' => 'country_name', 'name' => 'country_name', 'orderable' => false, 'searchable' => false],
            ['title' => __('admin.bestsellers.total_sold'), 'data' => 'units_sold', 'name' => 'units_sold', 'searchable' => false],
            ['title' => __('admin.bestsellers.ranked_at'), 'data' => 'calculated_at', 'name' => 'calculated_at', 'searchable' => false],
        ];

        $filters = [
            ['type' => 'text', 'name' => 'search', 'label' => __('common.name')],
            ['type' => 'select', 'name' => 'category_id', 'label' => __('admin.bestsellers.category'), 'options' => $categoryOptions],
            ['type' => 'select', 'name' => 'country_id', 'label' => __('admin.bestsellers.country'), 'options' => $countryOptions],
        ];
    @endphp

    <x-table.datatable id="bestsellers-table" url="{{ route('admin.bestsellers.datatable') }}" :columns="$columns"
        :filters="$filters" :page-length="25" :order="[[1, 'asc']]" />
@endsection
