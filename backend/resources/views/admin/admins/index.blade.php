@extends('layouts.admin')

@section('title', __('admin.admins_section.administrators'))

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/admin/admins.js'])
@endpush

@section('content')
    {{-- ── Impersonation Banner ─────────────────────────────────────────── --}}
    @if(session('impersonating_original_id'))
        <div
            class="sticky top-0 z-50 bg-amber-500 text-white px-4 py-2 flex items-center justify-between shadow-sm mb-4 rounded-lg">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
                <span class="text-sm font-medium">
                    {!! __('admin.admins_section.impersonating_banner', ['name' => '<strong>' . e(auth('admin')->user()->name) . '</strong>']) !!}
                </span>
            </div>
            <form method="POST" action="{{ route('admin.admins.stop-impersonating') }}">
                @csrf
                <button type="submit" class="btn btn-xs bg-white text-amber-700 hover:bg-amber-50 font-semibold">
                    {{ __('admin.admins_section.stop_impersonating') }}
                </button>
            </form>
        </div>
    @endif

    @php
        $columns = [
            ['title' => __('admin.admins_section.name_column'), 'data' => 'name', 'name' => 'name'],
            ['title' => __('admin.admins_section.email_column'), 'data' => 'email', 'name' => 'email', 'searchable' => false],
            ['title' => __('admin.admins_section.country_column'), 'data' => 'country_name', 'name' => 'country_name', 'searchable' => false, 'orderable' => false],
            [
                'title' => __('admin.admins_section.roles_column'),
                'data' => 'roles',
                'name' => 'roles',
                'searchable' => false,
                'orderable' => false,
                'render' => 'function(data) {
                            if (!data || !data.length) return "<span class=\"text-gray-400 text-xs\">—</span>";
                            return data.map(function(r) {
                                return "<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700\">" + r + "</span>";
                            }).join(" ");
                        }',
            ],
            [
                'title' => __('admin.admins_section.status_column'),
                'data' => 'status',
                'name' => 'status',
                'searchable' => false,
                'render' => 'Renderers.badge({
                            active:   { label: "' . __('common.active') . '",   color: "success" },
                            inactive: { label: "' . __('common.inactive') . '", color: "gray"    }
                        })',
            ],
            [
                'title' => __('admin.admins_section.last_login_column'),
                'data' => 'last_login_at',
                'name' => 'last_login_at',
                'searchable' => false,
                'render' => 'Renderers.dateAgo',
            ],
            [
                'title' => __('admin.admins_section.created_column'),
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
                            { type: "link",   label: "' . __('common.edit') . '",        url: ":edit_url",   class: "btn-secondary" },
                            { type: "button", label: "' . __('admin.admins_section.sessions') . '",    id: "sessions",     class: "btn-ghost" },
                            { type: "button", label: "' . __('admin.admins_section.reset_pwd') . '",   id: "reset-pwd",    class: "btn-ghost" },
                            { type: "button", label: "' . __('admin.admins_section.impersonate') . '", id: "impersonate",  class: "btn-warning",   condition: (row) => row.can_impersonate },
                            { type: "button", label: "' . __('admin.admins_section.deactivate') . '",  id: "deactivate",   class: "btn-secondary", condition: (row) => !row.is_self && row.status === "active" },
                            { type: "button", label: "' . __('admin.admins_section.activate') . '",    id: "activate",     class: "btn-success",   condition: (row) => !row.is_self && row.status === "inactive" },
                            { type: "button", label: "' . __('admin.admins_section.delete') . '",      id: "delete",       class: "btn-danger",    condition: (row) => row.can_delete },
                        ])',
            ],
        ];

        $filters = [
            ['type' => 'text', 'name' => 'search', 'label' => __('admin.admins_section.name_email_label'), 'placeholder' => __('common.search') . '…'],
            [
                'type' => 'select',
                'name' => 'status',
                'label' => __('admin.admins_section.status_column'),
                'options' => ['' => __('admin.admins_section.all_statuses'), 'active' => __('common.active'), 'inactive' => __('common.inactive')],
            ],
            [
                'type' => 'select',
                'name' => 'role',
                'label' => __('admin.admins_section.role'),
                'options' => ['' => __('admin.admins_section.all_roles')] + $roles->pluck('name', 'name')->toArray(),
            ],
            [
                'type' => 'select',
                'name' => 'country_id',
                'label' => __('admin.admins_section.country_column'),
                'options' => ['' => __('admin.admins_section.all_countries')] + $countries->pluck('name_en', 'id')->toArray(),
            ],
        ];
    @endphp

    <x-table.datatable id="admins-table" url="{{ route('admin.admins.datatable') }}" :columns="$columns" :filters="$filters"
        :create-action="['url' => route('admin.admins.create'), 'label' => __('admin.admins_section.add_administrator')]" :page-length="25"
        :order="[[6, 'desc']]" />

    {{-- ── Login Sessions Modal ──────────────────────────────────────────── --}}
    <div id="sessions-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" id="sessions-backdrop"></div>
            <div
                class="relative transform overflow-hidden rounded-xl bg-white text-start shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900" id="sessions-modal-title">{{ __('admin.admins_section.login_sessions') }}</h3>
                    <button type="button" id="sessions-modal-close" class="text-gray-400 hover:text-gray-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="px-6 py-4 max-h-96 overflow-y-auto">
                    <div id="sessions-loading" class="text-center py-8 text-gray-500 text-sm">{{ __('common.loading') }}</div>
                    <div id="sessions-list" class="hidden space-y-2"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            deleteAdministratorConfirm: @json(__('admin.admins_section.delete_administrator_confirm')),
            deleteAdministratorTitle: @json(__('admin.admins_section.delete_administrator_title')),
            administratorDeleted: @json(__('admin.admins_section.administrator_deleted')),
            deleteFailed: @json(__('admin.admins_section.delete_failed')),
            deactivateConfirm: @json(__('admin.admins_section.deactivate_confirm')),
            deactivateTitle: @json(__('admin.admins_section.deactivate_title')),
            administratorDeactivated: @json(__('admin.admins_section.administrator_deactivated')),
            administratorActivated: @json(__('admin.admins_section.administrator_activated')),
            actionFailed: @json(__('admin.admins_section.action_failed')),
            resetPwdConfirm: @json(__('admin.admins_section.reset_pwd_confirm')),
            resetPwdTitle: @json(__('admin.admins_section.reset_pwd_title')),
            passwordResetDone: @json(__('admin.admins_section.password_reset_done')),
            resetFailed: @json(__('admin.admins_section.reset_failed')),
            impersonateConfirm: @json(__('admin.admins_section.impersonate_confirm')),
            impersonateTitle: @json(__('admin.admins_section.impersonate_title')),
            noSessionsFound: @json(__('admin.admins_section.no_sessions_found')),
            failedLoadSessions: @json(__('admin.admins_section.failed_load_sessions')),
            sessionsModalTitle: @json(__('admin.admins_section.sessions_modal_title')),
            impersonationBadge: @json(__('admin.admins_section.impersonation_badge')),
        });

        window.tableActions = window.tableActions || {};

        // ── Delete ─────────────────────────────────────────────────────────────────
        window.tableActions.delete = async function (id, row) {
            const confirmed = window.confirmDelete
                ? await window.confirmDelete(window.TRANSLATIONS.deleteAdministratorConfirm.replace(':name', row.name), { title: window.TRANSLATIONS.deleteAdministratorTitle })
                : confirm(window.TRANSLATIONS.deleteAdministratorConfirm.replace(':name', row.name));
            if (!confirmed) return;

            $.ajax({
                url: row.delete_url, method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            })
                .done(() => { window.Toast?.success(window.TRANSLATIONS.administratorDeleted); window.reloadDataTable('admins-table'); })
                .fail(xhr => { window.Toast?.error(xhr.responseJSON?.message || window.TRANSLATIONS.deleteFailed); });
        };

        // ── Deactivate ─────────────────────────────────────────────────────────────
        window.tableActions.deactivate = async function (id, row) {
            const confirmed = window.confirmBulkAction
                ? await window.confirmBulkAction(window.TRANSLATIONS.deactivateConfirm.replace(':name', row.name), { title: window.TRANSLATIONS.deactivateTitle })
                : confirm(window.TRANSLATIONS.deactivateConfirm.replace(':name', row.name));
            if (!confirmed) return;

            $.ajax({
                url: row.toggle_url, method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            })
                .done(() => { window.Toast?.success(window.TRANSLATIONS.administratorDeactivated); window.reloadDataTable('admins-table'); })
                .fail(xhr => { window.Toast?.error(xhr.responseJSON?.message || window.TRANSLATIONS.actionFailed); });
        };

        // ── Activate ───────────────────────────────────────────────────────────────
        window.tableActions.activate = async function (id, row) {
            $.ajax({
                url: row.toggle_url, method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            })
                .done(() => { window.Toast?.success(window.TRANSLATIONS.administratorActivated); window.reloadDataTable('admins-table'); })
                .fail(xhr => { window.Toast?.error(xhr.responseJSON?.message || window.TRANSLATIONS.actionFailed); });
        };

        // ── Reset Password ─────────────────────────────────────────────────────────
        window.tableActions['reset-pwd'] = async function (id, row) {
            const confirmed = window.confirmBulkAction
                ? await window.confirmBulkAction(window.TRANSLATIONS.resetPwdConfirm.replace(':name', row.name), { title: window.TRANSLATIONS.resetPwdTitle })
                : confirm(window.TRANSLATIONS.resetPwdConfirm.replace(':name', row.name));
            if (!confirmed) return;

            $.ajax({
                url: row.reset_pwd_url, method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            })
                .done(data => { window.Toast?.success(data.message || window.TRANSLATIONS.passwordResetDone); })
                .fail(xhr => { window.Toast?.error(xhr.responseJSON?.message || window.TRANSLATIONS.resetFailed); });
        };

        // ── Impersonate ────────────────────────────────────────────────────────────
        window.tableActions.impersonate = async function (id, row) {
            const confirmed = window.confirmBulkAction
                ? await window.confirmBulkAction(window.TRANSLATIONS.impersonateConfirm.replace(':name', row.name), { title: window.TRANSLATIONS.impersonateTitle })
                : confirm(window.TRANSLATIONS.impersonateConfirm.replace(':name', row.name));
            if (!confirmed) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = row.impersonate_url;
            form.innerHTML = '<input type="hidden" name="_token" value="' + document.querySelector('meta[name="csrf-token"]').content + '">';
            document.body.appendChild(form);
            form.submit();
        };

        // ── Login Sessions Modal ────────────────────────────────────────────────────
        window.tableActions.sessions = function (id, row) {
            const modal = document.getElementById('sessions-modal');
            const title = document.getElementById('sessions-modal-title');
            const loading = document.getElementById('sessions-loading');
            const list = document.getElementById('sessions-list');

            title.textContent = window.TRANSLATIONS.sessionsModalTitle.replace(':name', row.name);
            loading.classList.remove('hidden');
            list.classList.add('hidden');
            list.innerHTML = '';
            modal.classList.remove('hidden');

            $.get(row.sessions_url)
                .done(function (data) {
                    loading.classList.add('hidden');
                    if (!data.data || !data.data.length) {
                        list.innerHTML = '<p class="text-sm text-gray-500 py-4 text-center">' + window.TRANSLATIONS.noSessionsFound + '</p>';
                    } else {
                        list.innerHTML = data.data.map(function (s) {
                            const impersonationBadge = s.is_impersonation
                                ? '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700 ml-2">' + window.TRANSLATIONS.impersonationBadge + '</span>'
                                : '';
                            return '<div class="border border-gray-200 rounded-lg px-4 py-3 text-sm">'
                                + '<div class="flex items-start justify-between gap-2">'
                                + '<div>'
                                + '<span class="font-medium text-gray-900">' + (s.ip_address || '—') + '</span>'
                                + impersonationBadge
                                + '<p class="text-xs text-gray-500 mt-0.5 truncate max-w-xs" title="' + s.user_agent + '">' + (s.user_agent || '—') + '</p>'
                                + '</div>'
                                + '<div class="text-end shrink-0">'
                                + '<p class="text-xs text-gray-500">' + (s.started_at ? new Date(s.started_at).toLocaleString() : '—') + '</p>'
                                + (s.duration ? '<p class="text-xs text-gray-400">' + s.duration + '</p>' : '')
                                + '</div>'
                                + '</div>'
                                + '</div>';
                        }).join('');
                    }
                    list.classList.remove('hidden');
                })
                .fail(function () {
                    loading.textContent = window.TRANSLATIONS.failedLoadSessions;
                });
        };

        document.getElementById('sessions-modal-close').addEventListener('click', () => {
            document.getElementById('sessions-modal').classList.add('hidden');
        });
        document.getElementById('sessions-backdrop').addEventListener('click', () => {
            document.getElementById('sessions-modal').classList.add('hidden');
        });
    </script>
@endpush