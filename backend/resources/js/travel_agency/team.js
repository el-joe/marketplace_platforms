/**
 * Travel Agency Portal → Team Management JS
 *
 * Server-side DataTable (window.initDataTable, see resources/js/components/datatable.js)
 * driving GET {route('travel-agency.team.datatable')}. Row payload fields are built
 * by TeamController@datatable (see app/Http/Controllers/TravelAgencyPortal/TeamController.php).
 */
import $ from 'jquery';

function labels() {
    return window.TEAM_LABELS || {};
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function toast(message, ok = true) {
    if (window.Toast) {
        ok ? window.Toast.success(message) : window.Toast.error(message);
        return;
    }
    window.Toastify?.({
        text: message,
        backgroundColor: ok ? '#22c55e' : '#ef4444',
        duration: 3500,
        gravity: 'top',
        position: 'center',
    }).showToast();
}

function reloadTable() {
    const table = $('#team-table').DataTable ? $('#team-table').DataTable() : null;
    table?.ajax?.reload(null, false);
}

/**
 * Submits a hidden form to `url` with the given HTTP verb, causing a full
 * page navigation — matches the RedirectResponse contract of destroy()/restore().
 */
function submitFormNavigation(url, method) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;
    form.style.display = 'none';

    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = csrfToken();
    form.appendChild(csrf);

    if (method !== 'POST') {
        const spoof = document.createElement('input');
        spoof.type = 'hidden';
        spoof.name = '_method';
        spoof.value = method;
        form.appendChild(spoof);
    }

    document.body.appendChild(form);
    form.submit();
}

async function postJson(url, method = 'POST') {
    const res = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message ?? (window.t ? window.t('shared.something_went_wrong') : 'Something went wrong. Please try again.'));
    return data;
}

function initTeamActions() {
    window.tableActions = window.tableActions || {};

    window.tableActions['team.toggleStatus'] = async (id, row) => {
        try {
            const data = await postJson(row.toggle_status_url, 'POST');
            toast(data.message);
            reloadTable();
        } catch (err) {
            toast(err.message, false);
        }
    };

    window.tableActions['team.forcePasswordReset'] = async (id, row) => {
        if (!window.confirm(labels().confirmForcePasswordReset)) return;
        try {
            const data = await postJson(row.force_password_reset_url, 'POST');
            toast(data.message);
        } catch (err) {
            toast(err.message, false);
        }
    };

    window.tableActions['team.destroy'] = (id, row) => {
        if (!window.confirm(labels().confirmDeleteMember)) return;
        submitFormNavigation(row.destroy_url, 'DELETE');
    };

    window.tableActions['team.restore'] = (id, row) => {
        if (!window.confirm(labels().confirmRestoreMember)) return;
        submitFormNavigation(row.restore_url, 'POST');
    };
}

function escAttr(obj) {
    return JSON.stringify(obj).replace(/'/g, '&#39;');
}

function actionsRenderer(actions, type, row) {
    if (type !== 'display') return '';

    const l = labels();
    const canManage = !!window.TEAM_CAN_MANAGE;
    if (!canManage) return '';

    if (row.trashed) {
        return `
            <button type="button" class="text-xs font-medium px-3 py-1.5 rounded-lg border border-green-300 text-green-700 hover:bg-green-50 transition-colors"
                data-action="team.restore" data-id="${row.id}" data-row='${escAttr(row)}'>
                ${l.restore}
            </button>`;
    }

    if (row.is_self) {
        return '<span class="text-xs text-gray-400">—</span>';
    }

    return `
        <div class="flex items-center justify-end gap-2 flex-wrap">
            <a href="${row.edit_url}" class="text-xs font-medium px-3 py-1.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors">
                ${l.edit}
            </a>
            <button type="button" class="text-xs font-medium px-3 py-1.5 rounded-lg border transition-colors ${row.is_active ? 'border-amber-300 text-amber-700 hover:bg-amber-50' : 'border-green-300 text-green-700 hover:bg-green-50'}"
                data-action="team.toggleStatus" data-id="${row.id}" data-row='${escAttr(row)}'>
                ${row.is_active ? l.deactivate : l.activate}
            </button>
            <button type="button" class="text-xs font-medium px-3 py-1.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors"
                data-action="team.forcePasswordReset" data-id="${row.id}" data-row='${escAttr(row)}'>
                ${l.forcePasswordReset}
            </button>
            <button type="button" class="text-xs font-medium px-3 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 transition-colors"
                data-action="team.destroy" data-id="${row.id}" data-row='${escAttr(row)}'>
                ${l.delete}
            </button>
        </div>`;
}

function statusRenderer(value, type, row) {
    if (type !== 'display') return value;
    const cls = row.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600';
    return `<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${cls}">${value}</span>`;
}

function initTable() {
    const routes = window.TEAM_ROUTES;
    if (!routes || !document.getElementById('team-table')) return;

    window.initDataTable('team-table', {
        url: routes.datatable,
        ajaxMethod: 'GET',
        order: [[0, 'asc']],
        serverSideFilters: {
            show_deleted: () => (document.getElementById('team-show-deleted')?.checked ? 1 : 0),
            is_active: () => document.getElementById('team-is-active')?.value ?? '',
            search: () => document.getElementById('team-search')?.value ?? '',
        },
        columns: [
            { data: 'name' },
            { data: 'email' },
            { data: 'phone' },
            { data: 'role' },
            { data: 'status', className: 'text-center', orderable: false, render: statusRenderer },
            { data: 'last_login_at' },
            { data: null, className: 'text-center', orderable: false, render: actionsRenderer },
        ],
    });

    document.getElementById('team-show-deleted')?.addEventListener('change', () => {
        $('#team-table').DataTable().ajax.reload();
    });

    document.getElementById('team-is-active')?.addEventListener('change', () => {
        $('#team-table').DataTable().ajax.reload();
    });

    let searchTimer;
    document.getElementById('team-search')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => $('#team-table').DataTable().ajax.reload(), 300);
    });

    document.getElementById('team-export')?.addEventListener('click', (e) => {
        e.preventDefault();
        const params = new URLSearchParams({
            search: document.getElementById('team-search')?.value ?? '',
            is_active: document.getElementById('team-is-active')?.value ?? '',
            show_deleted: document.getElementById('team-show-deleted')?.checked ? 1 : 0,
        });
        window.location.href = `${routes.export}?${params.toString()}`;
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initTeamActions();
    initTable();
});
