@extends('layouts.admin')

@section('title', __('admin.product_relations_section.title'))

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('content')
    @php
        $columns = [
            ['title' => __('admin.product_relations_section.product'), 'data' => 'name_en', 'name' => 'name_en'],
            ['title' => __('admin.product_relations_section.sku'), 'data' => 'sku', 'name' => 'sku'],
            [
                'title' => __('admin.product_relations_section.related_count'),
                'data' => 'related_count',
                'name' => 'related_count',
                'searchable' => false,
                'className' => 'text-end',
            ],
            [
                'title' => '',
                'data' => 'actions',
                'name' => 'actions',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-end',
                'render' => 'function(data, type, row) {
                            var actions = [
                                { type: "link", label: "' . __('admin.product_relations_section.manage') . '", url: row.manage_url },
                            ];
                            return Renderers.actions(actions)(data, type, row);
                        }',
            ],
        ];

        $filters = [
            ['type' => 'text', 'name' => 'search', 'label' => __('admin.product_relations_section.product'), 'placeholder' => __('admin.product_relations_section.search_placeholder')],
            [
                'type' => 'select',
                'name' => 'has_relations',
                'label' => __('admin.product_relations_section.has_relations'),
                'options' => ['' => __('admin.product_relations_section.all'), '1' => __('admin.product_relations_section.yes'), '0' => __('admin.product_relations_section.no')],
            ],
        ];
    @endphp

    <x-table.datatable id="fbt-table" url="{{ route('admin.fbt.datatable') }}" :columns="$columns"
        :filters="$filters" :page-length="25" />
@endsection
