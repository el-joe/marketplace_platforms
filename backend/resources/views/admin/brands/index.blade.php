@extends('layouts.admin')

@section('title', __('admin.brands.title'))

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/admin/brands.js'])
@endpush

@section('content')
    @php
        $columns = [
            ['title' => __('admin.brands.name_en_col'), 'data' => 'name_en', 'name' => 'name_en'],
            ['title' => __('admin.brands.name_ar_col'), 'data' => 'name_ar', 'name' => 'name_ar', 'searchable' => false],
            [
                'title' => __('admin.brands.verified'),
                'data' => 'is_verified',
                'name' => 'is_verified',
                'searchable' => false,
                'render' => 'Renderers.badge({ true: { label: "' . __('common.yes') . '", color: "success" }, false: { label: "' . __('common.no') . '", color: "gray" } })',
            ],
            [
                'title' => __('admin.brands.restricted'),
                'data' => 'is_restricted',
                'name' => 'is_restricted',
                'searchable' => false,
                'render' => 'Renderers.badge({ true: { label: "' . __('common.yes') . '", color: "danger" }, false: { label: "' . __('common.no') . '", color: "gray" } })',
            ],
            [
                'title' => __('common.active'),
                'data' => 'is_active',
                'name' => 'is_active',
                'searchable' => false,
                'render' => 'Renderers.badge({ true: { label: "' . __('common.active') . '", color: "success" }, false: { label: "' . __('common.inactive') . '", color: "gray" } })',
            ],
            ['title' => __('admin.brands.products_count'), 'data' => 'product_count', 'name' => 'product_count', 'orderable' => false, 'searchable' => false, 'className' => 'text-end'],
            [
                'title' => __('admin.created_at'),
                'data' => 'created_at',
                'name' => 'created_at',
                'searchable' => false,
                'render' => 'Renderers.dateAgo',
            ],
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
                                        ])',
            ],
        ];

        $filters = [
            ['type' => 'text', 'name' => 'search', 'label' => __('common.name'), 'placeholder' => __('admin.brands.search_placeholder')],
            [
                'type' => 'select',
                'name' => 'is_verified',
                'label' => __('admin.brands.verified'),
                'options' => ['' => __('common.all'), '1' => __('admin.brands.verified'), '0' => __('admin.brands.unverified')],
            ],
            [
                'type' => 'select',
                'name' => 'is_restricted',
                'label' => __('admin.brands.restricted'),
                'options' => ['' => __('common.all'), '1' => __('admin.brands.restricted'), '0' => __('admin.brands.not_restricted')],
            ],
            [
                'type' => 'select',
                'name' => 'is_active',
                'label' => __('common.active'),
                'options' => ['' => __('common.all'), '1' => __('common.active'), '0' => __('common.inactive')],
            ],
        ];
    @endphp

    <x-table.datatable id="brands-table" url="{{ route('admin.brands.datatable') }}" :columns="$columns" :filters="$filters"
        :create-action="['url' => route('admin.brands.create'), 'label' => __('admin.brands.add_brand')]" :page-length="25" :order="[[6, 'desc']]" />
@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            deleteBrandQuestion: @json(__('admin.brands.delete_confirm_generic')),
            thisBrand: @json(__('admin.brands.this_brand')),
            deleteBrandTitle: @json(__('admin.brands.delete_title')),
            brandDeleted: @json(__('admin.brands.deleted_success')),
            deleteFailed: @json(__('admin.brands.delete_failed')),
        });

        window.tableActions = window.tableActions || {};

        window.tableActions.delete = async function (id, row) {
            const message = window.TRANSLATIONS.deleteBrandQuestion.replace(':name', row.name_en || window.TRANSLATIONS.thisBrand);
            const confirmed = window.confirmDelete
                ? await window.confirmDelete(message, { title: window.TRANSLATIONS.deleteBrandTitle })
                : confirm(message);
            if (!confirmed) return;

            $.ajax({ url: row.delete_url, method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } })
                .done(function (res) {
                    window.Toast && window.Toast.success(window.TRANSLATIONS.brandDeleted);
                    window.reloadDataTable('brands-table');
                })
                .fail(function (xhr) {
                    window.Toast && window.Toast.error(xhr.responseJSON?.message || window.TRANSLATIONS.deleteFailed);
                });
        };
    </script>
@endpush