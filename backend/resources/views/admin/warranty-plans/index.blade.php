@extends('layouts.admin')

@section('title', __('admin.warranty_plans.title'))

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('content')
    @php
        $categoryOptions = ['' => __('admin.warranty_plans.all_categories')];
        foreach ($categories as $cat) {
            $categoryOptions[$cat->id] = str_repeat('— ', $cat->depth ?? 0) . $cat->name_en;
        }

        $columns = [
            ['title' => 'ID', 'data' => 'id', 'name' => 'id'],
            ['title' => __('common.name'), 'data' => 'name_en', 'name' => 'name_en'],
            ['title' => __('admin.warranty_plans.category'), 'data' => 'category_path', 'name' => 'category_path', 'orderable' => false],
            ['title' => __('admin.warranty_plans.duration_months'), 'data' => 'duration_months', 'name' => 'duration_months', 'searchable' => false],
            ['title' => __('admin.warranty_plans.price'), 'data' => 'price_display', 'name' => 'price_display', 'searchable' => false],
            [
                'title' => __('common.active'),
                'data' => 'is_active',
                'name' => 'is_active',
                'searchable' => false,
                'render' => 'Renderers.badge({ true: { label: "' . __('admin.warranty_plans.active') . '", color: "success" }, false: { label: "' . __('admin.warranty_plans.inactive') . '", color: "gray" } })',
            ],
            ['title' => __('admin.warranty_plans.sort_order'), 'data' => 'sort_order', 'name' => 'sort_order', 'searchable' => false],
            [
                'title' => '',
                'data' => 'actions',
                'name' => 'actions',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-end',
                'render' => 'Renderers.actions([
                                            { type: "link",   label: "' . __('common.edit') . '",   url: ":edit_url" },
                                            { type: "button", label: "' . __('common.toggle') . '", id: "toggle" },
                                            { type: "button", label: "' . __('common.delete') . '", id: "delete", class: "btn-danger" }
                                        ])',
            ],
        ];

        $filters = [
            ['type' => 'text', 'name' => 'search', 'label' => __('common.name')],
            ['type' => 'select', 'name' => 'category_id', 'label' => __('admin.warranty_plans.category'), 'options' => $categoryOptions],
            [
                'type' => 'select',
                'name' => 'is_active',
                'label' => __('common.active'),
                'options' => ['' => __('admin.warranty_plans.all_statuses'), '1' => __('admin.warranty_plans.active'), '0' => __('admin.warranty_plans.inactive')],
            ],
        ];
    @endphp

    <x-table.datatable id="warranty-plans-table" url="{{ route('admin.warranty-plans.datatable') }}" :columns="$columns"
        :filters="$filters" :create-action="['url' => route('admin.warranty-plans.create'), 'label' => __('admin.warranty_plans.add_plan')]"
        :page-length="25" :order="[[6, 'asc']]" />
@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            deletePlanQuestion: @json(__('admin.warranty_plans.delete_confirm')),
            deletePlanTitle: @json(__('admin.warranty_plans.delete_title')),
            planDeleted: @json(__('admin.warranty_plans.deleted_success')),
            toggleFailed: @json(__('admin.warranty_plans.toggle_failed')),
        });

        window.tableActions = window.tableActions || {};

        window.tableActions.toggle = async function (id, row) {
            $.ajax({ url: row.toggle_url, method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } })
                .done(function (res) {
                    window.reloadDataTable('warranty-plans-table');
                })
                .fail(function (xhr) {
                    window.Toast && window.Toast.error(xhr.responseJSON?.message || window.TRANSLATIONS.toggleFailed);
                });
        };

        window.tableActions.delete = async function (id, row) {
            const message = window.TRANSLATIONS.deletePlanQuestion;
            const confirmed = window.confirmDelete
                ? await window.confirmDelete(message, { title: window.TRANSLATIONS.deletePlanTitle })
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
