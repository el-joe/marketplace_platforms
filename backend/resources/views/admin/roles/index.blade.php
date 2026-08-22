@extends('layouts.admin')

@section('title', __('admin.roles_section.roles_permissions_title'))

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('content')
    @php
        $columns = [
            ['title' => __('admin.roles_section.role_name'), 'data' => 'name', 'name' => 'name'],
            ['title' => __('admin.roles_section.guard_column'), 'data' => 'guard_name', 'name' => 'guard_name', 'searchable' => false],
            [
                'title' => __('admin.roles_section.admins_column'),
                'data' => 'admin_count',
                'name' => 'admin_count',
                'searchable' => false,
                'orderable' => false,
                'className' => 'text-end',
                'render' => 'function(data) { return "<span class=\"font-mono text-sm\">" + data + "</span>"; }',
            ],
            [
                'title' => __('admin.roles_section.permissions_column'),
                'data' => 'permissions_count',
                'name' => 'permissions_count',
                'searchable' => false,
                'orderable' => false,
                'className' => 'text-end',
                'render' => 'function(data) { return "<span class=\"font-mono text-sm\">" + data + "</span>"; }',
            ],
            [
                'title' => __('admin.roles_section.created_column'),
                'data' => 'created_at',
                'name' => 'created_at',
                'searchable' => false,
                'render' => 'Renderers.date',
            ],
            [
                'title' => '',
                'data' => 'actions',
                'name' => 'actions',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-end',
                'render' => 'Renderers.actions([
                            { type: "link",   label: "' . __('common.edit') . '",   url: ":edit_url",   class: "btn-secondary" },
                            { type: "button", label: "' . __('admin.roles_section.delete') . '", id: "delete",       class: "btn-danger",  condition: (row) => !row.is_super_admin },
                        ])',
            ],
        ];

        $filters = [
            ['type' => 'text', 'name' => 'search', 'label' => __('admin.roles_section.role_name_label'), 'placeholder' => __('common.search') . '…'],
        ];
    @endphp

    <x-table.datatable id="roles-table" url="{{ route('admin.roles.datatable') }}" :columns="$columns" :filters="$filters"
        :create-action="['url' => route('admin.roles.create'), 'label' => __('admin.roles_section.create_role')]" :page-length="25" :order="[[0, 'asc']]" />
@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            deleteRoleConfirm: @json(__('admin.roles_section.delete_role_confirm')),
            deleteRoleTitle: @json(__('admin.roles_section.delete_role_title')),
            roleDeleted: @json(__('admin.roles_section.role_deleted')),
            deleteFailed: @json(__('admin.roles_section.delete_failed')),
        });

        window.tableActions = window.tableActions || {};

        window.tableActions.delete = async function (id, row) {
            const message = window.TRANSLATIONS.deleteRoleConfirm.replace(':name', row.name);
            const confirmed = window.confirmDelete
                ? await window.confirmDelete(message, { title: window.TRANSLATIONS.deleteRoleTitle })
                : confirm(message);
            if (!confirmed) return;

            $.ajax({
                url: row.delete_url, method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            })
                .done(() => { window.Toast?.success(window.TRANSLATIONS.roleDeleted); window.reloadDataTable('roles-table'); })
                .fail(xhr => { window.Toast?.error(xhr.responseJSON?.message || window.TRANSLATIONS.deleteFailed); });
        };
    </script>
@endpush
