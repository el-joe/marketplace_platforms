/**
 * column-renderers.js — Reusable DataTables column render functions.
 *
 * All renderers are exposed under window.Renderers so page scripts can
 * reference them inside the `columns` array passed to initDataTable().
 *
 * Usage inside a Blade view's @push('scripts'):
 *
 *   columns: [
 *     { data: 'status', render: Renderers.badge({ active: { label: 'Active', color: 'success' } }) },
 *     { data: 'price',  render: Renderers.currency('EGP') },
 *     { data: 'image',  render: Renderers.image('48px') },
 *   ]
 */

/* =========================================================
   TIME AGO HELPER  (no external dependency)
   ========================================================= */
const _isAr = document.documentElement.lang === 'ar';

function timeAgo(dateStr) {
    if (!dateStr) return '—';
    const now = Date.now();
    const then = new Date(dateStr).getTime();
    const diff = Math.floor((now - then) / 1000);

    if (_isAr) {
        if (diff < 60) return `منذ ${diff} ث`;
        if (diff < 3600) return `منذ ${Math.floor(diff / 60)} د`;
        if (diff < 86400) return `منذ ${Math.floor(diff / 3600)} س`;
        if (diff < 2592000) return `منذ ${Math.floor(diff / 86400)} يوم`;
        if (diff < 31536000) return `منذ ${Math.floor(diff / 2592000)} شهر`;
        return `منذ ${Math.floor(diff / 31536000)} سنة`;
    }

    if (diff < 60) return `${diff}s ago`;
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 2592000) return `${Math.floor(diff / 86400)}d ago`;
    if (diff < 31536000) return `${Math.floor(diff / 2592000)}mo ago`;
    return `${Math.floor(diff / 31536000)}y ago`;
}

window.timeAgo = timeAgo;

/* =========================================================
   BADGE COLOR CLASSES
   ========================================================= */
const BADGE_CLASSES = {
    gray: 'bg-gray-100 text-gray-700',
    primary: 'bg-primary-100 text-primary-700',
    success: 'bg-green-100  text-green-700',
    warning: 'bg-yellow-100 text-yellow-700',
    danger: 'bg-red-100    text-red-700',
    info: 'bg-blue-100   text-blue-700',
    blue: 'bg-blue-100    text-blue-700',
    green: 'bg-green-100   text-green-700',
    amber: 'bg-amber-100   text-amber-700',
    purple: 'bg-purple-100  text-purple-700',
    indigo: 'bg-indigo-100  text-indigo-700',
    yellow: 'bg-yellow-100  text-yellow-700',
    pink: 'bg-pink-100    text-pink-700',
    teal: 'bg-teal-100    text-teal-700',
    orange: 'bg-orange-100  text-orange-700',
};

/* =========================================================
   RENDERERS
   ========================================================= */
window.Renderers = {

    /**
     * badge(colorMap)
     *
     * colorMap: { [value]: { label: string, color: 'success'|'warning'|'danger'|... } }
     *
     * Example:
     *   Renderers.badge({
     *     active:   { label: 'Active',   color: 'success' },
     *     inactive: { label: 'Inactive', color: 'gray'    },
     *     banned:   { label: 'Banned',   color: 'danger'  },
     *   })
     */
    badge(colorMap) {
        return function (data) {
            if (data === null || data === undefined) return '—';
            const cfg = colorMap[data] || { label: String(data), color: 'gray' };
            const cls = BADGE_CLASSES[cfg.color] || BADGE_CLASSES.gray;
            return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${cls}">${cfg.label}</span>`;
        };
    },

    /**
     * currency(currencyCode)
     * Expects data to be an integer in cents.
     *
     * Example: Renderers.currency('EGP')
     */
    currency(currency) {
        return function (data) {
            if (data === null || data === undefined || data === '') return '—';
            const amount = new Intl.NumberFormat('en-EG', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(Number(data) / 100);
            return `<span class="tabular-nums">${amount} ${currency || 'EGP'}</span>`;
        };
    },

    /**
     * image(size?)
     * Renders a thumbnail. size defaults to '40px'.
     *
     * Example: Renderers.image('48px')
     */
    image(size) {
        size = size || '40px';
        return function (data) {
            if (!data) {
                return `<div class="rounded bg-gray-100 flex items-center justify-center text-gray-300"
                             style="width:${size};height:${size}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Z"/>
                            </svg>
                        </div>`;
            }
            return `<img src="${data}" alt="" loading="lazy"
                        class="rounded object-cover border border-gray-100"
                        style="width:${size};height:${size}">`;
        };
    },

    /**
     * dateAgo(data, type, row)
     * Directly usable as a render function (not a factory).
     *
     * Example: { data: 'created_at', render: Renderers.dateAgo }
     */
    dateAgo(data) {
        if (!data) return '—';
        const formatted = new Date(data).toLocaleDateString('en-EG', {
            year: 'numeric', month: 'short', day: 'numeric',
        });
        return `<span class="text-gray-500 text-xs" title="${data}">${timeAgo(data)}</span>
                <span class="hidden">${formatted}</span>`;
    },

    /**
     * date(data, type, row)
     * Formats as "Jan 1, 2025". Directly usable as a render function.
     */
    date(data) {
        if (!data) return '—';
        return new Date(data).toLocaleDateString('en-EG', {
            year: 'numeric', month: 'short', day: 'numeric',
        });
    },

    /**
     * actions(actions)
     *
     * actions: array of action definitions:
     *   { type: 'link',   label, url: '/path/:id', icon: '<svg>…</svg>', class? }
     *   { type: 'button', label, id: 'delete',     icon: '<svg>…</svg>', class?, condition?: fn(row)→bool }
     *
     * Rendered buttons trigger the global data-action handler defined in datatable.js.
     */
    actions(actions) {
        return function (data, type, row) {
            if (type !== 'display') return data;

            const visible = actions.filter((a) => !a.condition || a.condition(row));
            if (!visible.length) return '';

            let html = '<div class="flex items-center gap-1">';
            visible.forEach(function (action) {
                if (action.type === 'link') {
                    const href = (action.url || '#').replace(/:([a-zA-Z_]+)/g, (_, key) =>
                        row[key] !== undefined && row[key] !== null ? row[key] : `:${key}`
                    );
                    html += `<a href="${href}"
                                class="btn btn-xs ${action.class || 'btn-ghost'}"
                                title="${action.label}">
                                ${action.icon || action.label}
                             </a>`;
                } else if (action.type === 'button') {
                    // Safely encode row as JSON for data-row attribute
                    const rowJson = JSON.stringify(row).replace(/'/g, '&#39;');
                    html += `<button type="button"
                                class="btn btn-xs ${action.class || 'btn-ghost'}"
                                data-action="${action.id}"
                                data-id="${row.id || ''}"
                                data-row='${rowJson}'
                                title="${action.label}">
                                ${action.icon || action.label}
                             </button>`;
                }
            });
            html += '</div>';
            return html;
        };
    },

    /**
     * rating(data, type, row)
     * Renders star rating (0–5). Directly usable as a render function.
     */
    rating(data) {
        if (data === null || data === undefined || data === '') return '—';
        const val = parseFloat(data);
        const stars = Math.round(val);
        let html = '<div class="flex items-center gap-0.5">';
        for (let i = 1; i <= 5; i++) {
            const filled = i <= stars;
            html += `<svg class="w-3.5 h-3.5 ${filled ? 'text-yellow-400' : 'text-gray-200'}"
                          fill="currentColor" viewBox="0 0 20 20">
                         <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 0 0 .95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 0 0-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 0 0-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 0 0-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 0 0 .951-.69l1.07-3.292Z"/>
                     </svg>`;
        }
        html += `<span class="text-xs text-gray-600 ml-1">${val.toFixed(1)}</span>`;
        html += '</div>';
        return html;
    },

    /**
     * boolean(trueLabel?, falseLabel?)
     * Renders a yes/no badge for boolean columns.
     */
    boolean(trueLabel, falseLabel) {
        trueLabel = trueLabel || (window.t ? t('shared.yes') : 'Yes');
        falseLabel = falseLabel || (window.t ? t('shared.no') : 'No');
        return function (data) {
            const on = data === true || data === 1 || data === '1';
            return on
                ? `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${BADGE_CLASSES.success}">${trueLabel}</span>`
                : `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${BADGE_CLASSES.gray}">${falseLabel}</span>`;
        };
    },

    /**
     * truncate(maxLength?)
     * Truncates long text with a tooltip showing the full value.
     */
    truncate(maxLength) {
        maxLength = maxLength || 60;
        return function (data) {
            if (!data) return '—';
            const str = String(data);
            if (str.length <= maxLength) return str;
            const escaped = str.replace(/"/g, '&quot;');
            return `<span title="${escaped}" class="cursor-help">${str.slice(0, maxLength)}…</span>`;
        };
    },
};
