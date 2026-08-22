@extends('layouts.admin')

@section('title', __('admin.attributes_section.title'))

@push('styles')
    @vite([
        'resources/js/components/datatable.js',
        'resources/js/components/column-renderers.js',
        'resources/js/admin/attributes.js',
    ])
@endpush

@section('content')
    @php
        $columns = [
            ['title' => __('common.name'), 'data' => 'name_en', 'name' => 'name_en'],
            ['title' => __('admin.attributes_section.code'), 'data' => 'code', 'name' => 'code'],
            [
                'title' => __('admin.attributes_section.type'),
                'data' => 'type',
                'name' => 'type',
                'searchable' => false,
                'render' => 'Renderers.badge({
                                                text:        { label: "' . __('admin.attributes_section.type_text') . '",         color: "gray"    },
                                                number:      { label: "' . __('admin.attributes_section.type_number') . '",       color: "gray"    },
                                                select:      { label: "' . __('admin.attributes_section.type_select') . '",       color: "primary" },
                                                multi_select:{ label: "' . __('admin.attributes_section.type_multi_select') . '", color: "primary" },
                                                boolean:     { label: "' . __('admin.attributes_section.type_boolean') . '",      color: "warning" },
                                                color:       { label: "' . __('admin.attributes_section.type_color') . '",        color: "danger"  },
                                                date:        { label: "' . __('admin.attributes_section.type_date') . '",         color: "gray"    }
                                            })'
            ],
            ['title' => __('admin.attributes_section.unit_col'), 'data' => 'unit', 'name' => 'unit', 'searchable' => false],
            ['title' => __('admin.created_at'), 'data' => 'created_at', 'name' => 'created_at', 'searchable' => false, 'render' => 'Renderers.dateAgo'],
            [
                'title' => '',
                'data' => 'actions',
                'name' => 'actions',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-end',
                'render' => 'Renderers.actions([
                                                { type: "link",   label: "' . __('common.edit') . '",   url: ":edit_url" },
                                                { type: "button", label: "' . __('common.delete') . '", id: "delete", class: "btn-danger" }
                                            ])'
            ],
        ];

        $filters = [
            ['type' => 'text', 'name' => 'search', 'label' => __('admin.attributes_section.name_code_placeholder'), 'placeholder' => __('admin.attributes_section.search_placeholder')],
            [
                'type' => 'select',
                'name' => 'type',
                'label' => __('admin.attributes_section.type'),
                'options' => [
                    'text' => __('admin.attributes_section.type_text'),
                    'number' => __('admin.attributes_section.type_number'),
                    'select' => __('admin.attributes_section.type_select'),
                    'multi_select' => __('admin.attributes_section.type_multi_select'),
                    'boolean' => __('admin.attributes_section.type_boolean'),
                    'color' => __('admin.attributes_section.type_color'),
                    'date' => __('admin.attributes_section.type_date'),
                ],
            ],
        ];
    @endphp

    <x-table.datatable id="attributes-table" url="{{ route('admin.attributes.datatable') }}" :columns="$columns"
        :filters="$filters" :create-action="['url' => route('admin.attributes.create'), 'label' => __('admin.attributes_section.new_attribute')]" />

    <script>
        window.ROUTES_ATTR = {
            destroy: '{{ url('attributes') }}/:id',
        };

        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            deleteConfirm: @json(__('admin.attributes_section.delete_confirm')),
            deleteConfirmTitle: @json(__('admin.attributes_section.delete_confirm_title')),
            deletedSuccess: @json(__('admin.attributes_section.deleted_success')),
            deleteFailed: @json(__('admin.attributes_section.delete_failed')),
        });
    </script>
@endsection