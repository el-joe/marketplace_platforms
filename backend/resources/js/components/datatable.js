/**
 * datatable.js — jQuery DataTables v2 initialisation layer.
 *
 * Depends on:
 *   - jQuery (window.$)
 *   - datatables.net + extensions (imported below)
 *   - window.Toast (Toastify wrapper, defined in app.js)
 *
 * Usage:
 *   window.initDataTable('my-table', {
 *     url:        '/products/datatable',
 *     columns:    [...],   // DataTables column definitions
 *     pageLength: 25,
 *     order:      [[0, 'desc']],
 *     selectable: true,
 *     responsive: true,
 *   });
 */
import $ from 'jquery';
window.$ = window.jQuery = $;

import DataTable from 'datatables.net-dt';
import Responsive from 'datatables.net-responsive-dt';
import Select from 'datatables.net-select-dt';
import Buttons from 'datatables.net-buttons-dt';

import 'datatables.net-dt/css/dataTables.dataTables.css';
import 'datatables.net-responsive-dt/css/responsive.dataTables.css';
import 'datatables.net-select-dt/css/select.dataTables.css';
import 'datatables.net-buttons-dt/css/buttons.dataTables.css';

// Registry: tableId → DataTable instance
const registry = {};

// Locale helpers derived from the <html> element set by SetLocale middleware
const _isRtl = document.documentElement.dir === 'rtl';
const _isAr  = document.documentElement.lang === 'ar';

export const dtLanguage = _isAr
    ? {
        processing:  '<div class="flex items-center justify-center gap-2 py-6 text-sm text-gray-500">'
            + '<svg class="w-5 h-5 animate-spin text-primary-500" fill="none" viewBox="0 0 24 24">'
            + '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>'
            + '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>'
            + '</svg>جاري التحميل…</div>',
        emptyTable:  '<div class="py-12 text-center text-sm text-gray-400">لا توجد سجلات</div>',
        zeroRecords: '<div class="py-12 text-center text-sm text-gray-400">لا توجد نتائج مطابقة</div>',
        info:        'عرض _START_ إلى _END_ من _TOTAL_ سجل',
        infoEmpty:   'عرض 0 إلى 0 من 0 سجل',
        infoFiltered:'(مصفّاة من _MAX_ سجل)',
        search:      'بحث:',
        lengthMenu:  'عرض _MENU_ سجل',
        paginate: {
            first:    '«',
            last:     '»',
            next:     '›',
            previous: '‹',
        },
    }
    : {
        processing:  '<div class="flex items-center justify-center gap-2 py-6 text-sm text-gray-500">'
            + '<svg class="w-5 h-5 animate-spin text-primary-500" fill="none" viewBox="0 0 24 24">'
            + '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>'
            + '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>'
            + '</svg>Loading…</div>',
        emptyTable:  '<div class="py-12 text-center text-sm text-gray-400">No records found</div>',
        zeroRecords: '<div class="py-12 text-center text-sm text-gray-400">No matching records found</div>',
        info:        'Showing _START_ to _END_ of _TOTAL_ entries',
        infoEmpty:   'Showing 0 to 0 of 0 entries',
        infoFiltered:'(filtered from _MAX_ total)',
        paginate: {
            first:    '«',
            last:     '»',
            next:     '›',
            previous: '‹',
        },
    };

/* =========================================================
   HELPERS
   ========================================================= */

function updateFilterCount(tableId, clear) {
    if (clear) {
        $(`#${tableId}-filter-count`).text('0').addClass('hidden');
        return;
    }
    const $form = $(`#${tableId}-filter-form`);
    const count = $form.serializeArray().filter((f) => f.value.trim() !== '').length;
    const $badge = $(`#${tableId}-filter-count`);
    $badge.text(count);
    count > 0 ? $badge.removeClass('hidden') : $badge.addClass('hidden');
}

function initTooltips() {
    // Placeholder — extend with your tooltip library if needed
}

function initTableDropdowns() {
    // Re-init any Alpine/jQuery dropdowns injected into table cells
    if (window.Alpine) {
        document.querySelectorAll('[x-data]:not([data-alpine-initialized])').forEach((el) => {
            try { window.Alpine.initTree(el); } catch (_) { }
        });
    }
}

/* =========================================================
   SELECTABLE ROWS
   ========================================================= */
function initSelectableRows(tableId, table) {
    const $table = $(`#${tableId}`);
    const $allChk = $(`#${tableId}-select-all`);
    const $bar = $(`#${tableId}-bulk-bar`);
    const $count = $(`#${tableId}-selected-count`);

    function refreshBulkBar() {
        const n = getSelectedIds(tableId).length;
        if (n > 0) {
            $bar.removeClass('hidden').addClass('flex');
            $count.text(_isAr ? `${n} محدد` : `${n} selected`);
        } else {
            $bar.addClass('hidden').removeClass('flex');
            $allChk.prop('checked', false).prop('indeterminate', false);
        }
    }

    // Delegate checkbox clicks inside tbody
    $table.on('change', 'tbody input[type="checkbox"]', function () {
        const checked = $table.find('tbody input[type="checkbox"]:checked').length;
        const total = $table.find('tbody input[type="checkbox"]').length;
        $allChk.prop('checked', checked === total && total > 0);
        $allChk.prop('indeterminate', checked > 0 && checked < total);
        refreshBulkBar();
    });

    // Select-all toggle
    $allChk.on('change', function () {
        $table.find('tbody input[type="checkbox"]').prop('checked', this.checked);
        refreshBulkBar();
    });

    // Deselect-all button
    $(`#${tableId}-deselect-all`).on('click', function () {
        $table.find('tbody input[type="checkbox"], #' + tableId + '-select-all')
            .prop('checked', false).prop('indeterminate', false);
        refreshBulkBar();
    });

    // Reset checkboxes on table redraw
    table.on('draw', function () {
        $allChk.prop('checked', false).prop('indeterminate', false);
        refreshBulkBar();
    });
}

function getSelectedIds(tableId) {
    return $(`#${tableId} tbody input[type="checkbox"]:checked`)
        .map(function () { return $(this).val(); })
        .get();
}

window.getSelectedIds = getSelectedIds;

/* =========================================================
   BULK ACTION HANDLER
   ========================================================= */
$(document).on('click', '[data-bulk-action]', async function () {
    const action = $(this).data('bulk-action');
    const tableId = $(this).data('table');
    const needConfirm = $(this).data('confirm') !== false;
    const message = $(this).data('confirm-message')
        || (_isAr ? 'هل أنت متأكد من تنفيذ هذا الإجراء على العناصر المحددة؟' : 'Are you sure you want to perform this action on the selected items?');
    const ids = getSelectedIds(tableId);

    if (!ids.length) {
        window.Toast && window.Toast.warning(
            _isAr ? 'يرجى تحديد صف واحد على الأقل.' : 'Please select at least one row.'
        );
        return;
    }

    function executeAction() {
        if (window.tableActions && window.tableActions[action]) {
            window.tableActions[action](ids, tableId);
        } else {
            console.warn(`No handler registered for bulk action: ${action}`);
        }
    }

    if (needConfirm) {
        const isDeleteAction = String(action || '').toLowerCase().includes('delete');

        // Use a confirm modal if available, otherwise native confirm
        if (window.bulkConfirmModal) {
            window.bulkConfirmModal(message, ids.length, executeAction);
        } else if (window.confirmBulkAction) {
            const confirmed = await window.confirmBulkAction(message, ids.length, { destructive: isDeleteAction });
            if (!confirmed) return;
            executeAction();
        } else if (window.confirm(`${message}\n\n${ids.length} ${_isAr ? 'عنصر محدد.' : 'item(s) selected.'}`)) {
            executeAction();
        }
    } else {
        executeAction();
    }
});

/* =========================================================
   ROW ACTION HANDLER
   ========================================================= */
$(document).on('click', '[data-action]', function (e) {
    // Ignore if inside a form submit button
    if ($(this).is('[type="submit"]')) return;

    const action = $(this).data('action');
    const id = $(this).data('id');
    let row = $(this).data('row');

    if (typeof row === 'string') {
        try { row = JSON.parse(row); } catch (_) { row = {}; }
    }

    if (window.tableActions && typeof window.tableActions[action] === 'function') {
        e.preventDefault();
        window.tableActions[action](id, row);
    }
});

/* =========================================================
   MAIN INIT
   ========================================================= */
window.initDataTable = function (tableId, options) {
    // Destroy existing instance if re-initialising
    if (registry[tableId]) {
        registry[tableId].destroy();
        delete registry[tableId];
    }

    const defaults = {
        processing: true,
        serverSide: true,
        responsive: options.responsive !== false,
        pageLength: options.pageLength || 25,
        order: options.order || [[0, 'desc']],
        language: dtLanguage,
        // Custom DOM layout — we render info + paginator only; search/filter is handled manually
        dom: 'rt<"dt-footer flex items-center justify-between px-4 py-3 border-t border-gray-100"<"text-sm text-gray-500"i><"flex items-center gap-1"p>>',
        ajax: {
            url: options.url,
            type: options.ajaxMethod || 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'X-Requested-With': 'XMLHttpRequest',
            },
            data: function (d) {
                const $filterForm = $(`#${tableId}-filter-form`);
                if ($filterForm.length) {
                    $filterForm.serializeArray().forEach(function (item) {
                        if (item.value !== '') d[item.name] = item.value;
                    });
                }
                // Support inline serverSideFilters: { paramName: () => value }
                if (options.serverSideFilters) {
                    Object.entries(options.serverSideFilters).forEach(([key, fn]) => {
                        const val = typeof fn === 'function' ? fn() : fn;
                        if (val !== null && val !== undefined && val !== '') {
                            d[key] = val;
                        }
                    });
                }
                return d;
            },
            error: function (xhr) {
                if (xhr.status === 401) {
                    window.location.href = '/login';
                } else {
                    window.Toast && window.Toast.error(_isAr ? 'فشل تحميل بيانات الجدول.' : 'Failed to load table data.');
                }
            },
        },
        columns: options.columns,
        columnDefs: [
            { targets: '_all', defaultContent: '—' },
            ...(options.columnDefs || []),
        ],
        drawCallback: function () {
            initTooltips();
            initTableDropdowns();
        },
        initComplete: function () {
            // After first load, init any async-selects in filter panel
            if (window.initSelect2) {
                window.initSelect2($(`#${tableId}-filters`));
            }
            if (window.initDatePickers) {
                window.initDatePickers($(`#${tableId}-filters`));
            }
        },
    };

    const table = new DataTable(`#${tableId}`, defaults);
    registry[tableId] = table;

    // ── Search with debounce ──────────────────────────────────────────────
    let searchTimeout;
    $(`#${tableId}-search`).on('input', function () {
        clearTimeout(searchTimeout);
        const val = $(this).val();
        searchTimeout = setTimeout(function () {
            table.search(val).draw();
        }, 400);
    });

    // ── Filter form ───────────────────────────────────────────────────────
    $(`#${tableId}-filter-form`).on('submit', function (e) {
        e.preventDefault();
        updateFilterCount(tableId);
        table.ajax.reload();
    });

    $(`#${tableId}-clear-filters`).on('click', function () {
        const $form = $(`#${tableId}-filter-form`);
        $form[0].reset();

        // Clear Select2 fields
        $('[data-select2="true"], [data-async-select]', $form)
            .val(null).trigger('change');

        // Clear Flatpickr fields
        $('[data-flatpickr]', $form).each(function () {
            if (this._flatpickr) this._flatpickr.clear();
        });

        updateFilterCount(tableId, true);
        table.ajax.reload();
    });

    // ── Filter toggle ─────────────────────────────────────────────────────
    $(`#${tableId}-filter-toggle`).on('click', function () {
        $(`#${tableId}-filters`).slideToggle(200);
    });

    // ── Selectable rows ───────────────────────────────────────────────────
    if (options.selectable) {
        initSelectableRows(tableId, table);
    }

    return table;
};

/**
 * Reload a registered table from outside.
 */
window.reloadDataTable = function (tableId) {
    if (registry[tableId]) {
        registry[tableId].ajax.reload(null, false);
    }
};

/**
 * Convenience helper for bulk POST actions.
 *
 * window.bulkPost('/products/bulk-delete', ids, tableId, 'Products deleted.');
 */
window.bulkPost = function (url, ids, tableId, successMessage) {
    $.ajax({
        url,
        method: 'POST',
        data: { ids },
    })
        .done(function (res) {
            const msg = res.message || successMessage || (_isAr ? 'تم.' : 'Done.');
            window.Toast && window.Toast.success(msg);
            window.reloadDataTable(tableId);
        })
        .fail(function (xhr) {
            const msg = xhr.responseJSON?.message || (_isAr ? 'فشل الإجراء.' : 'Action failed.');
            window.Toast && window.Toast.error(msg);
        });
};

/**
 * Show a confirm modal for bulk actions (injectable; falls back to native confirm).
 * Replace with your real modal implementation.
 */
window.bulkConfirmModal = function (message, count, callback) {
    if (window.confirmBulkAction) {
        window.confirmBulkAction(message, count).then(function (confirmed) {
            if (confirmed) callback();
        });
        return;
    }

    if (window.confirm(`${message}\n\n${count} ${_isAr ? 'عنصر سيتأثر.' : 'item(s) will be affected.'}`)) {
        callback();
    }
};
