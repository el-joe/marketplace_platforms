@extends('layouts.admin')

@section('title', __('admin.product_highlights.title'))

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('content')
    @php
        $columns = [
            ['title' => 'ID', 'data' => 'id', 'name' => 'id'],
            ['title' => __('admin.product_highlights.product'), 'data' => 'product_name', 'name' => 'product_name', 'orderable' => false],
            [
                'title' => __('common.name'),
                'data' => 'text_en',
                'name' => 'text_en',
                'render' => 'Renderers.truncate(80)',
            ],
            ['title' => __('admin.product_highlights.sort_order'), 'data' => 'position', 'name' => 'position', 'searchable' => false],
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
            ['type' => 'text', 'name' => 'search', 'label' => __('common.name')],
        ];
    @endphp

    <x-table.datatable id="product-highlights-table" url="{{ route('admin.product-highlights.datatable') }}"
        :columns="$columns" :filters="$filters"
        :create-action="['url' => route('admin.product-highlights.create'), 'label' => __('admin.product_highlights.add_highlight')]"
        :page-length="25" :order="[[3, 'asc']]" />
@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            deleteHighlightQuestion: @json(__('admin.product_highlights.delete_confirm')),
            deleteHighlightTitle: @json(__('admin.product_highlights.delete_title')),
        });

        window.tableActions = window.tableActions || {};

        window.tableActions.delete = async function (id, row) {
            const message = window.TRANSLATIONS.deleteHighlightQuestion;
            const confirmed = window.confirmDelete
                ? await window.confirmDelete(message, { title: window.TRANSLATIONS.deleteHighlightTitle })
                : confirm(message);
            if (!confirmed) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = row.delete_url;
            form.innerHTML = `@csrf @method('DELETE')`;
            document.body.appendChild(form);
            form.submit();
        };
    </script>
@endpush
