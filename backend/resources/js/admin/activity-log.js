/**
 * activity-log.js — Admin activity log: server-side DataTable + detail modal.
 */
import DataTable from 'datatables.net';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

$(function () {
    const tableEl = document.getElementById('activity-log-table');
    if (!tableEl) return;

    const baseUrl = tableEl.dataset.url;

    // ─── DataTable ────────────────────────────────────────────────────────────
    const dt = new DataTable('#activity-log-table', {
        processing: true,
        serverSide: true,
        pageLength: 50,
        order: [[0, 'desc']],
        ajax: {
            url: baseUrl,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data(d) {
                const f = $('#activity-filter-form').serializeArray();
                f.forEach(({ name, value }) => { d[name] = value; });
            },
        },
        columns: [
            {
                data: 'created_at_formatted',
                render(_, type, row) {
                    return '<div>'
                        + '<span class="text-sm text-gray-900">' + row.created_at_formatted + '</span>'
                        + '<p class="text-xs text-gray-400" title="' + (row.created_at || '') + '">' + (row.time_ago || '') + '</p>'
                        + '</div>';
                },
            },
            {
                data: 'causer_name',
                orderable: false,
                render(_, type, row) {
                    if (!row.causer_name && !row.causer_type_label) {
                        return '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">' + escapeHtml(window.TRANSLATIONS?.systemShort || 'System') + '</span>';
                    }
                    const colorMap = {
                        Admin: 'bg-blue-100 text-blue-700',
                        VendorAdmin: 'bg-green-100 text-green-700',
                        Customer: 'bg-yellow-100 text-yellow-700',
                    };
                    const color = colorMap[row.causer_type_label] || 'bg-gray-100 text-gray-600';
                    return '<div>'
                        + '<p class="text-sm font-medium text-gray-900">' + escapeHtml(row.causer_name || '—') + '</p>'
                        + '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium ' + color + '">'
                        + escapeHtml(row.causer_type_label || '') + '</span>'
                        + '</div>';
                },
            },
            {
                data: 'event',
                render(data) {
                    const colors = {
                        created: 'bg-green-100 text-green-700',
                        updated: 'bg-blue-100 text-blue-700',
                        deleted: 'bg-red-100 text-red-700',
                        restored: 'bg-purple-100 text-purple-700',
                    };
                    const c = colors[data] || 'bg-gray-100 text-gray-600';
                    return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' + c + '">'
                        + escapeHtml(data || 'custom') + '</span>';
                },
            },
            {
                data: 'subject_display_name',
                orderable: false,
                render(_, type, row) {
                    if (!row.subject_type_label && !row.subject_id) return '<span class="text-gray-300">—</span>';
                    const label = row.subject_type_label || (window.TRANSLATIONS?.unknown || 'Unknown');
                    const name = row.subject_display_name || row.subject_id || '';
                    return '<div>'
                        + '<span class="text-xs text-gray-500">' + escapeHtml(label) + '</span>'
                        + '<p class="text-sm text-gray-900 truncate max-w-xs" title="' + escapeHtml(name) + '">' + escapeHtml(name) + '</p>'
                        + '</div>';
                },
            },
            {
                data: 'description',
                render(data) {
                    const txt = data || '';
                    return '<p class="text-sm text-gray-700 max-w-xs truncate" title="' + escapeHtml(txt) + '">'
                        + escapeHtml(txt) + '</p>';
                },
            },
            {
                data: 'log_name',
                render(data) {
                    return data
                        ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">' + escapeHtml(data) + '</span>'
                        : '<span class="text-gray-300">—</span>';
                },
            },
            {
                data: 'ip_address',
                render(data) {
                    return data
                        ? '<code class="text-xs text-gray-600">' + escapeHtml(data) + '</code>'
                        : '<span class="text-gray-300">—</span>';
                },
            },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render(id) {
                    return '<button type="button" class="btn btn-xs btn-secondary" data-view-activity="' + id + '">' + escapeHtml(window.TRANSLATIONS?.view || 'View') + '</button>';
                },
            },
        ],
        rowId: 'DT_RowId',
    });

    // ─── Filter form ──────────────────────────────────────────────────────────
    $('#activity-filter-form').on('submit', function (e) {
        e.preventDefault();
        dt.ajax.reload();
    });

    $('#clear-activity-filters').on('click', function () {
        const $form = $('#activity-filter-form');
        $form[0].reset();
        $form.find('[data-async-select]').val(null).trigger('change');
        dt.ajax.reload();
    });

    // ─── Detail modal ─────────────────────────────────────────────────────────
    $(document).on('click', '[data-view-activity]', function (e) {
        e.stopPropagation();
        loadActivityDetail($(this).data('view-activity'));
    });

    $('#activity-log-table tbody').on('click', 'tr', function () {
        const id = $(this).find('[data-view-activity]').data('view-activity');
        if (id) loadActivityDetail(id);
    });

    function loadActivityDetail(id) {
        const showBaseUrl = baseUrl.replace(/\/datatable$/, '');
        $.ajax({
            url: showBaseUrl + '/' + id,
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        }).done(function (res) {
            renderActivityDetail(res.data);
            $('#activity-detail-modal').modal('open');
        }).fail(function () {
            window.Toast?.error(t('admin.activity_log.failed_to_load_detail'));
        });
    }

    function renderActivityDetail(a) {
        $('#detail-event-badge')
            .attr('class', 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' + eventBadgeClass(a.event))
            .text(a.event || 'custom');
        $('#detail-description').text(a.description || '');

        $('#detail-causer-name').text(a.causer_name || (window.TRANSLATIONS?.systemShort || 'System'));
        $('#detail-causer-type').text(a.causer_type_label || '—');
        $('#detail-causer-email').text(a.causer_email || '—');
        $('#detail-created-at').text((a.created_at_formatted || '—') + (a.time_ago ? ' (' + a.time_ago + ')' : ''));
        $('#detail-ip').text(a.ip_address || '—');
        $('#detail-log-name').text(a.log_name || '—');
        $('#detail-batch').text(a.batch_uuid || '—');
        $('#detail-user-agent').text(a.user_agent || '—');

        $('#detail-subject-type').text(a.subject_type_label || '—');
        $('#detail-subject-name').text(a.subject_display_name || '—');
        $('#detail-subject-id').text(a.subject_id || '—');

        if (a.subject_url) {
            $('#detail-subject-link').attr('href', a.subject_url).removeClass('hidden');
        } else {
            $('#detail-subject-link').addClass('hidden');
        }

        renderChangesTable(a.properties || {});

        $('#detail-raw-json').text(JSON.stringify(a.properties || {}, null, 2));
    }

    function renderChangesTable(properties) {
        const old = properties.old || {};
        const attrs = properties.attributes || {};
        const keys = Array.from(new Set([...Object.keys(old), ...Object.keys(attrs)]));

        if (!keys.length) {
            $('#detail-changes-section').addClass('hidden');
            return;
        }
        $('#detail-changes-section').removeClass('hidden');

        const sensitive = ['password', 'token', 'secret', 'api_key', 'encrypted'];

        const rows = keys.map(function (key) {
            const isSensitive = sensitive.some(s => key.toLowerCase().includes(s));
            const inOld = key in old;
            const inNew = key in attrs;
            const oldVal = old[key];
            const newVal = attrs[key];

            const fmt = (v) => {
                if (isSensitive) return '<span class="text-gray-400">●●●●●●</span>';
                if (v === null || v === undefined) return '<span class="text-gray-400 italic">null</span>';
                if (typeof v === 'object') return '<pre class="text-xs whitespace-pre-wrap break-all">' + escapeHtml(JSON.stringify(v)) + '</pre>';
                return '<span class="break-all">' + escapeHtml(String(v)) + '</span>';
            };

            let rowClass = '';
            if (!inOld && inNew) rowClass = 'bg-green-50';
            else if (inOld && !inNew) rowClass = 'bg-red-50';
            else if (String(oldVal) !== String(newVal)) rowClass = 'bg-yellow-50';

            return '<tr class="' + rowClass + '">'
                + '<td class="px-3 py-2 text-xs font-mono text-gray-700 font-medium align-top">' + escapeHtml(key) + '</td>'
                + '<td class="px-3 py-2 text-xs text-gray-600 max-w-xs align-top">' + fmt(oldVal) + '</td>'
                + '<td class="px-3 py-2 text-xs text-gray-900 max-w-xs align-top">' + fmt(newVal) + '</td>'
                + '</tr>';
        }).join('');

        $('#detail-changes-tbody').html(rows);
    }

    function eventBadgeClass(event) {
        const map = {
            created: 'bg-green-100 text-green-700',
            updated: 'bg-blue-100 text-blue-700',
            deleted: 'bg-red-100 text-red-700',
            restored: 'bg-purple-100 text-purple-700',
        };
        return map[event] || 'bg-gray-100 text-gray-600';
    }

    function escapeHtml(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
});
