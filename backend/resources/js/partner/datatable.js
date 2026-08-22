/**
 * partner/datatable.js
 * Shared utilities and DataTable factory for all partner pages.
 *
 * Usage:
 *   import { createPartnerTable, toast, postJson, showModal, hideModal, showError, hideError } from './datatable.js';
 *
 *   const table = createPartnerTable('inventory-table', {
 *     url:          cfg.datatableUrl,
 *     ajaxData:     (d) => { d.filter = cfg.filterParam; },
 *     columns:      [...],
 *     order:        [[3, 'asc']],
 *     searchInputId: 'inventory-search',
 *     language:     { emptyTable: 'لا توجد بيانات' },
 *   });
 */

import DataTable from 'datatables.net-dt';

// ─────────────────────────────────────────────────────────────────────────────
// Shared utilities
// ─────────────────────────────────────────────────────────────────────────────

export function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export function showModal(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.remove('hidden'); el.classList.add('flex'); }
}

export function hideModal(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.add('hidden'); el.classList.remove('flex'); }
}

export function showError(elId, msg) {
    const el = document.getElementById(elId);
    if (el) { el.textContent = msg; el.classList.remove('hidden'); }
}

export function hideError(elId) {
    const el = document.getElementById(elId);
    if (el) el.classList.add('hidden');
}

export function toast(msg, type = 'success') {
    if (window.Toastify) {
        Toastify({
            text: msg,
            duration: 4000,
            gravity: 'top',
            position: 'left',
            style: {
                background: type === 'success' ? '#16a34a' : '#dc2626',
                borderRadius: '0.75rem',
                fontFamily: 'inherit',
            },
        }).showToast();
    }
}

export async function postJson(url, data) {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
        },
        body: JSON.stringify(data),
    });
    return { ok: res.ok, status: res.status, data: await res.json() };
}

// ─────────────────────────────────────────────────────────────────────────────
// Arabic language defaults
// ─────────────────────────────────────────────────────────────────────────────

const ARABIC_LANGUAGE = {
    emptyTable:     'لا توجد بيانات',
    info:           'عرض _START_ إلى _END_ من أصل _TOTAL_ سجل',
    infoEmpty:      'عرض 0 إلى 0 من أصل 0 سجل',
    infoFiltered:   '(مصفاة من _MAX_ سجل إجمالي)',
    lengthMenu:     'أظهر _MENU_ سجلاً',
    loadingRecords: 'جارٍ التحميل...',
    processing:     '<div class="flex justify-center py-8"><div class="w-6 h-6 border-2 border-yellow-400 border-t-transparent rounded-full animate-spin"></div></div>',
    search:         'بحث:',
    zeroRecords:    'لم يتم العثور على نتائج',
    paginate: {
        first:    'الأولى',
        last:     'الأخيرة',
        next:     'التالية',
        previous: 'السابقة',
    },
    aria: {
        sortAscending:  ': تفعيل لترتيب العمود تصاعدياً',
        sortDescending: ': تفعيل لترتيب العمود تنازلياً',
    },
};

// ─────────────────────────────────────────────────────────────────────────────
// Custom pagination renderer
// Expects elements:  #{tableId}-info  and  #{tableId}-pagination
// ─────────────────────────────────────────────────────────────────────────────

function buildPaginationDrawCallback(tableId) {
    return function () {
        const api  = this.api();
        const info = api.page.info();

        const infoEl = document.getElementById(`${tableId}-info`);
        if (infoEl) {
            infoEl.textContent = info.recordsTotal === 0
                ? ''
                : `عرض ${info.start + 1}–${info.end} من ${info.recordsTotal} سجل`;
        }

        const pagEl = document.getElementById(`${tableId}-pagination`);
        if (!pagEl) return;
        pagEl.innerHTML = '';

        const page  = info.page;
        const pages = info.pages;
        if (pages <= 1) return;

        const btn = (label, pg, disabled, active) => {
            const b = document.createElement('button');
            b.innerHTML = label;
            b.className = [
                'min-w-[32px] h-8 px-2 rounded-lg text-xs font-medium transition-colors',
                active   ? 'bg-yellow-400 text-gray-900 font-bold' : 'text-gray-600 hover:bg-gray-100',
                disabled ? 'opacity-30 cursor-not-allowed pointer-events-none' : '',
            ].join(' ');
            if (!disabled && !active) b.addEventListener('click', () => { api.page(pg).draw(false); });
            return b;
        };

        pagEl.appendChild(btn('«', 0,          page === 0,          false));
        pagEl.appendChild(btn('‹', page - 1,   page === 0,          false));

        const startPg = Math.max(0, page - 2);
        const endPg   = Math.min(pages - 1, page + 2);
        for (let i = startPg; i <= endPg; i++) {
            pagEl.appendChild(btn(i + 1, i, false, i === page));
        }

        pagEl.appendChild(btn('›', page + 1,   page >= pages - 1,   false));
        pagEl.appendChild(btn('»', pages - 1,  page >= pages - 1,   false));
    };
}

// ─────────────────────────────────────────────────────────────────────────────
// Factory
// ─────────────────────────────────────────────────────────────────────────────

/**
 * @param {string} tableId          - The HTML element id of the <table>
 * @param {object} options
 * @param {string}   options.url          - Server-side AJAX endpoint (GET)
 * @param {Function} [options.ajaxData]   - (d) => void  — attach extra params to the DT request
 * @param {Array}    options.columns      - DataTables column definitions
 * @param {Array}    [options.order]      - Default sort, e.g. [[0, 'asc']]
 * @param {string}   [options.searchInputId] - ID of an external search <input> to debounce-reload on
 * @param {object}   [options.language]   - Language overrides merged on top of ARABIC_LANGUAGE
 * @param {number}   [options.pageLength] - Rows per page (default 25)
 * @returns {DataTable}
 */
export function createPartnerTable(tableId, options) {
    const tableEl = document.getElementById(tableId);
    if (!tableEl) return null;

    const table = new DataTable(tableEl, {
        processing: true,
        serverSide: true,
        ajax: {
            url:  options.url,
            data: options.ajaxData ?? (() => {}),
        },
        columns:    options.columns,
        order:      options.order ?? [[0, 'asc']],
        language:   { ...ARABIC_LANGUAGE, ...(options.language ?? {}) },
        dom:        't',
        pageLength: options.pageLength ?? 25,
        searching:  false,
        createdRow: (row) => {
            row.classList.add('hover:bg-gray-50', 'transition-colors');
        },
        drawCallback: buildPaginationDrawCallback(tableId),
    });

    // Wire external search input
    if (options.searchInputId) {
        let debounce = null;
        document.getElementById(options.searchInputId)?.addEventListener('input', () => {
            clearTimeout(debounce);
            debounce = setTimeout(() => table.ajax.reload(), 400);
        });
    }

    return table;
}
