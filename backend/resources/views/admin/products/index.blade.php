@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/components/select2.js', 'resources/js/components/flatpickr.js'])
@endpush

@section('title', __('admin.products.title'))

@section('content')
    @php
        $columns = [
            [
                'title' => '',
                'data' => 'image',
                'name' => 'image',
                'orderable' => false,
                'searchable' => false,
                'className' => 'w-12 px-2',
                'render' => 'Renderers.image()'
            ],
            ['title' => __('admin.products.column_product'), 'data' => 'name_en', 'name' => 'name_en'],
            ['title' => __('admin.category'), 'data' => 'category', 'name' => 'category', 'searchable' => false],
            ['title' => __('admin.brand'), 'data' => 'brand', 'name' => 'brand', 'searchable' => false],
            [
                'title' => __('common.status'),
                'data' => 'status',
                'name' => 'status',
                'searchable' => false,
                'render' => 'Renderers.badge({
                                                                draft:        { label: "' . __('admin.products.draft_status') . '",        color: "gray"    },
                                                                active:       { label: "' . __('admin.products.active_status') . '",       color: "success" },
                                                                discontinued: { label: "' . __('admin.products.discontinued_status') . '", color: "warning" },
                                                                restricted:   { label: "' . __('admin.products.restricted_status') . '",   color: "danger"  }
                                                            })'
            ],
            ['title' => __('admin.products.column_sellers'), 'data' => 'seller_count', 'name' => 'seller_count', 'searchable' => false, 'className' => 'text-end'],
            ['title' => __('admin.products.column_rating'), 'data' => 'rating_avg', 'name' => 'rating_avg', 'searchable' => false, 'className' => 'text-end'],
            ['title' => __('admin.products.column_sold'), 'data' => 'total_sold', 'name' => 'total_sold', 'searchable' => false, 'className' => 'text-end'],
            [
                'title' => __('admin.products.created_column'),
                'data' => 'created_at',
                'name' => 'created_at',
                'searchable' => false,
                'render' => 'Renderers.dateAgo'
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
                                                                ])'
            ],
        ];

        $filters = [
            ['type' => 'text', 'name' => 'search', 'label' => __('admin.products.search_name_gtin'), 'placeholder' => __('admin.products.search_placeholder')],
            [
                'type' => 'select',
                'name' => 'status',
                'label' => __('common.status'),
                'options' => ['draft' => __('admin.products.draft_status'), 'active' => __('admin.products.active_status'), 'discontinued' => __('admin.products.discontinued_status'), 'restricted' => __('admin.products.restricted_status')]
            ],
            [
                'type' => 'select',
                'name' => 'category_id',
                'label' => __('admin.category'),
                'options' => $categories->toArray(),
                'searchable' => true,
                'placeholder' => __('admin.products.all_categories')
            ],
            [
                'type' => 'select',
                'name' => 'brand_id',
                'label' => __('admin.brand'),
                'options' => $brands->toArray(),
                'searchable' => true,
                'placeholder' => __('admin.products.all_brands')
            ],
            [
                'type' => 'select',
                'name' => 'is_active',
                'label' => __('common.status'),
                'options' => ['1' => __('admin.products.active_status'), '0' => __('admin.products.draft_status')]
            ],
            ['type' => 'date_range', 'name' => 'date', 'label' => __('admin.products.created_column')],
        ];

        $bulkActions = [
            ['id' => 'publish', 'label' => __('admin.products.publish'), 'class' => 'btn-success', 'confirmMessage' => __('admin.products.confirm_publish')],
            ['id' => 'archive', 'label' => __('admin.products.archive'), 'class' => 'btn-ghost', 'confirmMessage' => __('admin.products.confirm_archive')],
            ['id' => 'feature', 'label' => __('admin.products.featured'), 'class' => 'btn-secondary', 'confirmMessage' => __('admin.products.confirm_feature')],
            ['id' => 'delete', 'label' => __('common.delete'), 'class' => 'btn-danger', 'confirmMessage' => __('admin.products.confirm_delete_selected')],
        ];
    @endphp
    <div class="flex items-center justify-end mb-3">
        <x-export-dropdown />
    </div>

    <x-table.datatable id="products-table" url="{{ route('admin.products.datatable') }}" :columns="$columns"
        :filters="$filters" :bulk-actions="$bulkActions"
        :create-action="['url' => route('admin.products.create'), 'label' => __('admin.products.add_product')]" :selectable="true"
        :page-length="25" :order="[[8, 'desc']]" />
@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            deleteProductQuestion: @json(__('admin.products.confirm_delete_single')),
            thisProduct: @json(__('admin.products.this_product')),
            deleteProductTitle: @json(__('admin.products.delete_product_title')),
            productDeleted: @json(__('admin.products.product_deleted')),
            deleteFailed: @json(__('admin.products.delete_failed')),
            productsUpdatedSuffix: @json(__('admin.products.products_updated')),
        });

        window.tableActions = window.tableActions || {};

        window.tableActions.delete = async function (id, row) {
            const message = window.TRANSLATIONS.deleteProductQuestion.replace(':name', row.name_en || window.TRANSLATIONS.thisProduct);
            const confirmed = window.confirmDelete
                ? await window.confirmDelete(message, { title: window.TRANSLATIONS.deleteProductTitle })
                : confirm(message);
            if (!confirmed) return;

            $.ajax({ url: row.delete_url, method: 'DELETE' })
                .done(function (res) {
                    window.Toast && window.Toast.success(res.message || window.TRANSLATIONS.productDeleted);
                    window.reloadDataTable('products-table');
                })
                .fail(function (xhr) {
                    window.Toast && window.Toast.error(xhr.responseJSON?.message || window.TRANSLATIONS.deleteFailed);
                });
        };

        ['publish', 'archive', 'feature'].forEach(function (action) {
            window.tableActions[action] = function (ids, tableId) {
                window.bulkPost(
                    '{{ route('admin.products.bulk') }}',
                    { action: action, ids: ids },
                    tableId,
                    window.TRANSLATIONS.productsUpdatedSuffix.replace(':count', ids.length)
                );
            };
        });

        window.tableActions.delete_bulk = function (ids, tableId) {
            window.bulkPost('{{ route('admin.products.bulk') }}', { action: 'delete', ids: ids }, tableId);
        };
    </script>
@endpush