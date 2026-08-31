import './app.js';
import DataTable from 'datatables.net-dt';
import { csrfToken, toast } from './datatable.js';

// ─────────────────────────────────────────────────────────────────────────────
// Config
// ─────────────────────────────────────────────────────────────────────────────
const cfg = () => window.CLASSIFIEDS_CFG || {};
const el  = (id) => document.getElementById(id);

// ─────────────────────────────────────────────────────────────────────────────
// Shared utilities
// ─────────────────────────────────────────────────────────────────────────────
async function apiFetch(url, options = {}) {
    const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken(), ...options.headers };
    if (!(options.body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
    }
    const res  = await fetch(url, { ...options, headers });
    const json = await res.json();
    if (!res.ok) throw json;
    return json;
}

function formatMoney(cents, currency = 'SAR') {
    return new Intl.NumberFormat('ar-SA', { style: 'currency', currency }).format(cents / 100);
}

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('ar-SA', { year: 'numeric', month: 'short', day: 'numeric' });
}

function statusBadge(status) {
    const map = {
        draft:            { label: 'مسودة',           cls: 'bg-gray-100 text-gray-700' },
        pending_contract: { label: 'بانتظار العقد',    cls: 'bg-amber-100 text-amber-700' },
        pending_review:   { label: 'قيد المراجعة',     cls: 'bg-blue-100 text-blue-700' },
        active:           { label: 'نشط',              cls: 'bg-emerald-100 text-emerald-700' },
        paused:           { label: 'موقوف',             cls: 'bg-gray-100 text-gray-600' },
        sold:             { label: 'تم البيع',          cls: 'bg-purple-100 text-purple-700' },
        expired:          { label: 'منتهي',             cls: 'bg-red-100 text-red-600' },
        rejected:         { label: 'مرفوض',             cls: 'bg-red-100 text-red-700' },
    };
    const s = map[status] || { label: status, cls: 'bg-gray-100 text-gray-600' };
    return `<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${s.cls}">${s.label}</span>`;
}

async function confirmDialog(title, text) {
    if (window.Swal) {
        return Swal.fire({
            title, text, icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'تأكيد',
            cancelButtonText:  'إلغاء',
        }).then(r => r.isConfirmed);
    }
    return confirm(text || title);
}

// ─────────────────────────────────────────────────────────────────────────────
// INDEX PAGE — DataTable
// ─────────────────────────────────────────────────────────────────────────────
let clTable        = null;
let clTableContainer = null; // saved before DataTables wraps the table

function initIndexPage() {
    const tableEl = el('cl-table');
    if (!tableEl) return;

    // Save the .bg-white wrapper reference before DataTables moves the element
    clTableContainer = tableEl.parentElement;

    clTable = new DataTable(tableEl, {
        processing: true,
        serverSide: true,
        ajax: {
            url:  cfg().datatableUrl,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data(d) {
                d.search_term = el('cl-search')?.value.trim() || '';
                d.status      = el('cl-filter-status')?.value  || '';
            },
        },
        columns: [
            {
                data: null,
                render(row) {
                    const showUrl = buildShowUrl(row.id);
                    const img = row.primary_image
                        ? `<img src="${row.primary_image}" class="h-full w-full object-cover" alt="">`
                        : `<svg class="h-5 w-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3 3l18 18"/></svg>`;
                    const purposeCls = row.listing_purpose === 'sale'
                        ? 'bg-blue-50 text-blue-700'
                        : 'bg-violet-50 text-violet-700';
                    const purposeLabel = row.listing_purpose === 'sale' ? 'بيع' : 'إيجار';
                    return `<div class="flex items-center gap-3 min-w-0">
                        <div class="shrink-0 h-11 w-11 rounded-xl bg-gray-100 overflow-hidden flex items-center justify-center border border-gray-100">${img}</div>
                        <div class="min-w-0">
                            <a href="${showUrl}" class="font-semibold text-gray-900 hover:text-primary-600 line-clamp-1 text-sm">${row.title_ar || row.title_en || '—'}</a>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span class="text-xs text-gray-400 font-mono">${row.listing_number || ''}</span>
                                <span class="inline-flex items-center rounded-full px-1.5 py-px text-[10px] font-semibold ${purposeCls}">${purposeLabel}</span>
                            </div>
                        </div>
                    </div>`;
                },
            },
            {
                data: 'category_name',
                render: (val) => val
                    ? `<span class="text-xs text-gray-600 bg-gray-100 rounded-full px-2.5 py-0.5 font-medium">${val}</span>`
                    : '<span class="text-gray-300">—</span>',
            },
            { data: 'status', render: (s) => statusBadge(s) },
            {
                data: null,
                render(row) {
                    const price = `<span class="font-semibold text-gray-900">${formatMoney(row.price, row.currency)}</span>`;
                    const neg   = row.price_negotiable
                        ? `<span class="block text-[10px] text-emerald-600 font-medium mt-0.5">قابل للتفاوض</span>`
                        : '';
                    return price + neg;
                },
            },
            {
                data: 'views_count',
                render: (v) => `<div class="flex items-center gap-1 text-gray-600">
                    <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                    <span class="text-sm font-medium">${(v ?? 0).toLocaleString('ar-SA')}</span>
                </div>`,
            },
            {
                data: 'created_at',
                render(d, type, row) {
                    const dateStr = formatDate(d);
                    const expStr  = row.expires_at ? `<span class="block text-[10px] text-amber-600 mt-0.5">ينتهي ${formatDate(row.expires_at)}</span>` : '';
                    return `<span class="text-xs text-gray-500">${dateStr}</span>${expStr}`;
                },
            },
            {
                data: null,
                render(row) {
                    return `<a href="${buildShowUrl(row.id)}"
                        class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-700 bg-primary-50 hover:bg-primary-100 rounded-lg px-2.5 py-1.5 transition-colors">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                        عرض
                    </a>`;
                },
            },
        ],
        order:      [[5, 'desc']],
        dom:        't',
        pageLength: 20,
        searching:  false,
        language: {
            emptyTable:     'لا توجد إعلانات',
            loadingRecords: 'جارٍ التحميل...',
            processing:     '<div class="flex justify-center py-8"><div class="w-6 h-6 border-2 border-primary-400 border-t-transparent rounded-full animate-spin"></div></div>',
            zeroRecords:    'لم يتم العثور على نتائج',
        },
        createdRow(row, data) {
            row.classList.add('hover:bg-gray-50', 'cursor-pointer', 'transition-colors');
            row.addEventListener('click', (e) => {
                if (e.target.closest('a')) return;
                window.location.href = buildShowUrl(data.id);
            });
        },
        drawCallback() {
            const api  = this.api();
            const info = api.page.info();

            // Info label
            const infoEl = el('cl-info');
            if (infoEl) {
                infoEl.textContent = info.recordsTotal === 0
                    ? ''
                    : `عرض ${info.start + 1}–${info.end} من ${info.recordsTotal} سجل`;
            }

            // Empty state — driven by total records, not just current filtered view
            const empty = el('cl-empty');
            if (info.recordsTotal === 0) {
                if (clTableContainer) clTableContainer.style.display = 'none';
                if (empty) empty.classList.remove('hidden');
            } else {
                if (clTableContainer) clTableContainer.style.display = '';
                if (empty) empty.classList.add('hidden');
            }

            // Custom pagination
            const pagEl = el('cl-pagination');
            if (!pagEl) return;
            pagEl.innerHTML = '';
            const page  = info.page;
            const pages = info.pages;
            if (pages <= 1) return;

            const mkBtn = (label, pg, disabled, active) => {
                const b = document.createElement('button');
                b.innerHTML = label;
                b.className = [
                    'min-w-[32px] h-8 px-2 rounded-lg text-xs font-medium transition-colors',
                    active   ? 'bg-primary-500 text-white font-bold' : 'text-gray-600 hover:bg-gray-100',
                    disabled ? 'opacity-30 cursor-not-allowed pointer-events-none' : '',
                ].join(' ');
                if (!disabled && !active) b.addEventListener('click', () => api.page(pg).draw(false));
                return b;
            };

            pagEl.appendChild(mkBtn('«', 0,          page === 0,        false));
            pagEl.appendChild(mkBtn('‹', page - 1,   page === 0,        false));
            const start = Math.max(0, page - 2);
            const end   = Math.min(pages - 1, page + 2);
            for (let i = start; i <= end; i++) pagEl.appendChild(mkBtn(i + 1, i, false, i === page));
            pagEl.appendChild(mkBtn('›', page + 1,   page >= pages - 1, false));
            pagEl.appendChild(mkBtn('»', pages - 1,  page >= pages - 1, false));
        },
    });

    // Debounced search
    let searchTimer;
    el('cl-search')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => clTable.ajax.reload(), 400);
    });

    // Status filter
    el('cl-filter-status')?.addEventListener('change', () => clTable.ajax.reload());
}

function buildShowUrl(id) {
    const base = window.location.origin + window.location.pathname.replace(/\/$/, '');
    return `${base}/${id}`;
}

// ─────────────────────────────────────────────────────────────────────────────
// Wizard state
// ─────────────────────────────────────────────────────────────────────────────
let wizStep          = 1;
let wizSubmitting    = false;
let wizCategories    = [];
let wizCategoriesLoaded = false;
let wizSelectedCategory = null;
let wizSelectedParentId = null;
let wizImageFiles    = [];
let wizSketchFile    = null;
let wizAttachmentFiles = [];
let wizAttachmentsByType = {};
let wizAttributes    = {};

// Friendly labels for known attachment type keys
const ATTACHMENT_TYPE_LABELS = {
    ownership_deed:       { en: 'Ownership Deed',        ar: 'سند الملكية',        icon: '📄' },
    id_copy:              { en: 'ID Copy',                ar: 'نسخة من الهوية',     icon: '🪪' },
    floor_plan:           { en: 'Floor Plan',             ar: 'مخطط الطابق',        icon: '📐' },
    title_deed:           { en: 'Title Deed',             ar: 'صك الملكية',         icon: '📋' },
    passport_copy:        { en: 'Passport Copy',          ar: 'نسخة من جواز السفر', icon: '🛂' },
    utility_bill:         { en: 'Utility Bill',           ar: 'فاتورة خدمات',       icon: '🧾' },
    trade_license:        { en: 'Trade License',          ar: 'رخصة تجارية',        icon: '🏢' },
    tax_certificate:      { en: 'Tax Certificate',        ar: 'شهادة ضريبية',       icon: '📊' },
    bank_statement:       { en: 'Bank Statement',         ar: 'كشف حساب بنكي',      icon: '🏦' },
    vehicle_registration: { en: 'Vehicle Registration',   ar: 'تسجيل المركبة',      icon: '🚗' },
};
let wizContractLoaded = false;

// Step labels are injected by the Blade view via CLASSIFIEDS_CFG so they
// respect the active locale (AR/RTL or EN/LTR) automatically.
const STEP_LABELS_FULL        = cfg().stepLabelsFull        || ['Category', 'Basics', 'Location & Attributes', 'Images & Attachments', 'Contract', 'Review'];
const STEP_LABELS_NO_CONTRACT = cfg().stepLabelsNoContract  || ['Category', 'Basics', 'Location & Attributes', 'Images & Attachments', 'Review'];

function wizHasContract()      { return !!wizSelectedCategory?.contract_template_id; }
function wizNeedsLocation()    { return !!wizSelectedCategory?.requires_location_map; }
function wizNeedsSketch()      { return !!wizSelectedCategory?.requires_sketch_upload; }
function wizNeedsAttachments() { return (wizSelectedCategory?.required_attachment_types?.length ?? 0) > 0; }
function wizStepLabels()       { return wizHasContract() ? STEP_LABELS_FULL : STEP_LABELS_NO_CONTRACT; }

// Dom steps 1-6 always exist; step 5 (contract) skipped in nav if !wizHasContract
function wizNextDomStep(cur) { return (cur === 4 && !wizHasContract()) ? 6 : cur + 1; }
function wizPrevDomStep(cur) { return (cur === 6 && !wizHasContract()) ? 4 : cur - 1; }

function wizRenderProgress() {
    const container = el('cl-wiz-progress');
    if (!container) return;
    const labels = wizStepLabels();
    const total  = labels.length;

    container.innerHTML = labels.map((label, i) => {
        const num    = i + 1;
        const done   = num < wizStep;
        const active = num === wizStep;
        const dotCls = done
            ? 'bg-primary-500 text-white'
            : active
                ? 'bg-primary-600 text-white ring-4 ring-primary-100'
                : 'bg-gray-100 text-gray-400';
        const lineBefore = i === 0         ? 'bg-transparent' : (i < wizStep       ? 'bg-primary-500' : 'bg-gray-200');
        const lineAfter  = i === total - 1 ? 'bg-transparent' : (num < wizStep     ? 'bg-primary-500' : 'bg-gray-200');
        return `<div class="flex-1 flex flex-col items-center">
            <div class="flex items-center w-full">
                <div class="h-0.5 flex-1 ${lineBefore}"></div>
                <div class="h-7 w-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0 ${dotCls}">${done ? '✓' : num}</div>
                <div class="h-0.5 flex-1 ${lineAfter}"></div>
            </div>
            <span class="mt-1 text-xs text-gray-500 whitespace-nowrap hidden sm:block">${label}</span>
        </div>`;
    }).join('');
}

function wizShowStep(domStep) {
    for (let i = 1; i <= 6; i++) {
        const s = el(`cl-wiz-step-${i}`);
        if (s) s.style.display = i === domStep ? 'block' : 'none';
    }

    const logicalStep = (!wizHasContract() && domStep === 6) ? 5 : domStep;
    wizStep = domStep;

    if (el('cl-wiz-step-label')) el('cl-wiz-step-label').textContent = logicalStep;
    if (el('cl-wiz-total-label')) el('cl-wiz-total-label').textContent = wizStepLabels().length;
    wizRenderProgress();

    const isFirst = domStep === 1;
    const isLast  = domStep === 6;

    const prevBtn   = el('cl-wiz-prev');
    const prevSpacer = el('cl-wiz-prev-spacer');
    const nextBtn   = el('cl-wiz-next');
    const submitBtn = el('cl-wiz-submit');

    if (prevBtn)    prevBtn.style.display    = isFirst ? 'none' : 'inline-flex';
    if (prevSpacer) prevSpacer.style.display = isFirst ? 'inline' : 'none';
    if (nextBtn)    nextBtn.style.display    = isLast  ? 'none' : 'inline-flex';
    if (submitBtn)  submitBtn.style.display  = isLast  ? 'inline-flex' : 'none';

    if (domStep === 1 && !wizCategoriesLoaded) wizLoadCategories();
    if (domStep === 1 && wizCategoriesLoaded)  wizRenderCategories();
    if (domStep === 3) wizRenderAttributeFields();
    if (domStep === 4) wizRenderConditionalFileFields();
    if (domStep === 5 && wizHasContract()) wizLoadContractInWizard();
    if (domStep === 6) wizRenderReview();
}

// ─── Step 1: Categories ───────────────────────────────────────────────────────
async function wizLoadCategories() {
    if (wizCategoriesLoaded) { wizRenderCategories(); return; }
    try {
        const data = await apiFetch(cfg().categoriesUrl);
        wizCategories        = data.data || [];
        wizCategoriesLoaded  = true;
        wizRenderCategories();
    } catch {
        const loadEl = el('cl-categories-loading');
        if (loadEl) loadEl.textContent = 'تعذّر تحميل الفئات.';
    }
}

function wizRenderCategories() {
    const loadEl = el('cl-categories-loading');
    const grid   = el('cl-categories-grid');
    if (loadEl) loadEl.style.display = 'none';
    if (!grid) return;
    grid.style.display = 'grid';
    grid.innerHTML = wizCategories.map(cat => {
        const sel = wizSelectedParentId === cat.id;
        return `<button type="button" onclick="wizSelectParent('${cat.id}')"
            class="flex items-center gap-3 rounded-xl border-2 p-3 text-start transition-colors ${sel ? 'border-primary-500 bg-primary-50' : 'border-gray-100 hover:border-gray-200'}">
            ${cat.icon ? `<span class="text-xl shrink-0">${cat.icon}</span>` : ''}
            <div class="min-w-0">
                <div class="text-sm font-semibold ${sel ? 'text-primary-700' : 'text-gray-800'} leading-snug">${cat.name_ar}</div>
                <div class="text-xs text-gray-400">${cat.name_en || ''}</div>
            </div>
        </button>`;
    }).join('');
}

window.wizSelectParent = function (parentId) {
    wizSelectedParentId = parentId;
    wizSelectedCategory = null;
    wizRenderCategories();

    const parent     = wizCategories.find(c => c.id === parentId);
    const subSection = el('cl-subcategory-section');
    const subGrid    = el('cl-subcategories-grid');
    if (!parent) return;

    if (parent.children?.length) {
        subSection?.classList.remove('hidden');
        subGrid.innerHTML = parent.children.map(child => wizSubCategoryCard(child)).join('');
    } else {
        subSection?.classList.add('hidden');
        wizSelectedCategory = parent;
    }
    wizUpdateStepCount();
};

window.wizSelectCategory = function (id) {
    const parent = wizCategories.find(c => c.id === wizSelectedParentId);
    wizSelectedCategory = parent?.children?.find(c => c.id === id) || null;
    const subGrid = el('cl-subcategories-grid');
    if (subGrid && parent?.children) {
        subGrid.innerHTML = parent.children.map(child => wizSubCategoryCard(child)).join('');
    }
    wizUpdateStepCount();
};

function wizSubCategoryCard(child) {
    const sel = wizSelectedCategory?.id === child.id;
    return `<button type="button" onclick="wizSelectCategory('${child.id}')"
        class="flex items-center gap-2 rounded-xl border-2 p-2.5 text-start text-sm transition-colors ${sel ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-gray-100 hover:border-gray-200 text-gray-800'}">
        <span class="truncate">${child.name_ar}</span>
    </button>`;
}

function wizUpdateStepCount() {
    if (el('cl-wiz-total-label')) {
        el('cl-wiz-total-label').textContent = wizStepLabels().length;
    }
}

// ─── Step 3: Location & Attributes ───────────────────────────────────────────
function wizRenderAttributeFields() {
    const locSection  = el('cl-location-section');
    const attrSection = el('cl-attributes-section');
    const attrFields  = el('cl-attributes-fields');

    if (wizNeedsLocation()) locSection?.classList.remove('hidden');
    else                    locSection?.classList.add('hidden');

    // Use category attribute_schema if present, else generic free-form key-value builder
    const schema = wizSelectedCategory?.attribute_schema;
    if (schema?.length && attrFields) {
        attrFields.innerHTML = schema.map(attr => {
            if (attr.type === 'select') {
                return `<div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">${attr.label}</label>
                    <select id="cl-attr-${attr.key}" onchange="wizSetAttr('${attr.key}', this.value)"
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                        <option value="">— اختياري —</option>
                        ${(attr.options || []).map(o => `<option value="${o}" ${wizAttributes[attr.key] === o ? 'selected' : ''}>${o}</option>`).join('')}
                    </select>
                </div>`;
            }
            return `<div>
                <label class="block text-xs font-medium text-gray-600 mb-1">${attr.label}</label>
                <input type="${attr.type || 'text'}" id="cl-attr-${attr.key}" value="${wizAttributes[attr.key] ?? ''}"
                    oninput="wizSetAttr('${attr.key}', this.value)"
                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                    placeholder="اختياري">
            </div>`;
        }).join('');
        attrFields.className = 'grid grid-cols-2 gap-3';
        attrSection?.classList.remove('hidden');
    } else if (attrFields) {
        // Free-form key-value list builder when no schema is defined
        attrSection?.classList.remove('hidden');
        renderFreeformAttributes(attrFields);
    }
}

function renderFreeformAttributes(container) {
    const entries = Object.entries(wizAttributes);
    container.className = 'space-y-2';
    const rows = entries.map(([k, v], i) =>
        `<div class="flex gap-2">
            <input type="text" value="${k}" placeholder="الخاصية"
                oninput="wizUpdateAttrKey(${i}, this.value)"
                class="flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
            <input type="text" value="${v}" placeholder="القيمة"
                oninput="wizUpdateAttrVal(${i}, this.value)"
                class="flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
            <button type="button" onclick="wizRemoveAttrRow(${i})" class="text-red-400 hover:text-red-600 px-2">✕</button>
        </div>`
    ).join('');
    container.innerHTML = rows + `<button type="button" onclick="wizAddAttrRow()"
        class="text-xs text-primary-600 hover:underline mt-1">+ إضافة خاصية</button>`;
}

window.wizSetAttr = function (key, val) {
    if (val) wizAttributes[key] = val; else delete wizAttributes[key];
};

window.wizAddAttrRow = function () {
    wizAttributes[''] = '';
    const attrFields = el('cl-attributes-fields');
    if (attrFields) renderFreeformAttributes(attrFields);
};

window.wizUpdateAttrKey = function (idx, newKey) {
    const entries = Object.entries(wizAttributes);
    if (entries[idx]) {
        const val = entries[idx][1];
        delete wizAttributes[entries[idx][0]];
        wizAttributes[newKey] = val;
    }
};

window.wizUpdateAttrVal = function (idx, newVal) {
    const entries = Object.entries(wizAttributes);
    if (entries[idx]) wizAttributes[entries[idx][0]] = newVal;
};

window.wizRemoveAttrRow = function (idx) {
    const entries = Object.entries(wizAttributes);
    if (entries[idx]) {
        delete wizAttributes[entries[idx][0]];
        const attrFields = el('cl-attributes-fields');
        if (attrFields) renderFreeformAttributes(attrFields);
    }
};

// ─── Step 4: Conditional file sections ───────────────────────────────────────
function wizRenderConditionalFileFields() {
    const sketchSection = el('cl-sketch-section');

    if (wizNeedsSketch()) sketchSection?.classList.remove('hidden');
    else                  sketchSection?.classList.add('hidden');

    const requiredTypes = wizSelectedCategory?.required_attachment_types ?? [];
    if (requiredTypes.length > 0) {
        renderTypedAttachmentSlots(requiredTypes);
    } else {
        renderGenericAttachmentSection();
    }
}

function renderGenericAttachmentSection() {
    const attSection = el('cl-attachments-section');
    if (!attSection) return;
    const i18n = cfg().attachmentsI18n ?? {};

    attSection.innerHTML = `
        <label class="block text-sm font-semibold text-gray-800">${i18n.title ?? 'Additional Attachments'}
            <span class="text-gray-400 text-xs font-normal">${i18n.optional ?? 'optional'}</span>
        </label>
        <p class="text-xs text-gray-500">${i18n.genericDesc ?? ''}</p>
        <div class="relative border-2 border-dashed border-gray-200 rounded-xl p-4 text-center cursor-pointer hover:border-primary-400 transition-colors">
            <input type="file" id="cl-attachments-input" multiple class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
            <p class="text-sm text-gray-500">${i18n.uploadHint ?? ''}</p>
        </div>
        <div id="cl-attachments-list" class="space-y-1"></div>
    `;

    initAttachmentUpload();
    renderAttachmentList();
}

function renderTypedAttachmentSlots(types) {
    const attSection = el('cl-attachments-section');
    if (!attSection) return;
    const i18n = cfg().attachmentsI18n ?? {};

    attSection.innerHTML = `
        <div class="space-y-1 mb-1">
            <p class="text-sm font-semibold text-gray-800">${i18n.title ?? 'Additional Attachments'}
                <span class="text-xs font-normal text-gray-400 ml-1">${i18n.optional ?? 'optional'}</span>
            </p>
            <p class="text-xs text-gray-500">${i18n.typedDesc ?? ''}</p>
        </div>
        <div id="cl-typed-attachment-slots" class="space-y-2"></div>
    `;

    const slotsEl = document.getElementById('cl-typed-attachment-slots');
    if (!slotsEl) return;

    slotsEl.innerHTML = types.map(type => {
        const info     = ATTACHMENT_TYPE_LABELS[type] ?? { en: type, ar: type, icon: '📎' };
        const label    = info.ar + ' / ' + info.en;
        const file     = wizAttachmentsByType[type];
        const fileName = file ? file.name : null;

        return `<div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3"
                     id="att-slot-${type}">
            <span class="text-xl shrink-0">${info.icon}</span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800">${label}</p>
                ${fileName
                    ? `<p class="text-xs text-emerald-600 mt-0.5 truncate">✓ ${fileName}</p>`
                    : `<p class="text-xs text-gray-400 mt-0.5">${i18n.noFileChosen ?? 'No file chosen'}</p>`
                }
            </div>
            <label class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium
                          rounded-lg border border-gray-300 bg-white text-gray-700
                          hover:bg-gray-100 cursor-pointer transition-colors">
                ${fileName ? (i18n.replaceFile ?? 'Replace') : (i18n.chooseFile ?? 'Choose file')}
                <input type="file" class="sr-only att-type-input" data-type="${type}"
                       accept=".pdf,.jpg,.jpeg,.png,.webp">
            </label>
            ${fileName
                ? `<button type="button" class="shrink-0 text-red-400 hover:text-red-600 text-xs att-remove-btn"
                          data-type="${type}" title="Remove">✕</button>`
                : ''
            }
        </div>`;
    }).join('');

    slotsEl.querySelectorAll('.att-type-input').forEach(input => {
        input.addEventListener('change', () => {
            const type = input.dataset.type;
            const file = input.files[0];
            if (file) {
                wizAttachmentsByType[type] = file;
                renderTypedAttachmentSlots(types);
            }
            input.value = '';
        });
    });

    slotsEl.querySelectorAll('.att-remove-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            delete wizAttachmentsByType[btn.dataset.type];
            renderTypedAttachmentSlots(types);
        });
    });
}

// ─── Step 5: Contract (wizard) ────────────────────────────────────────────────
async function wizLoadContractInWizard() {
    if (wizContractLoaded) return;
    const loadEl   = el('cl-contract-loading');
    const contentEl = el('cl-contract-content');
    const textEl   = el('cl-contract-text');

    // Try to use embedded template content from the categories response
    const templateText = wizSelectedCategory?.contract_template_content;

    if (loadEl) loadEl.style.display = 'none';
    if (contentEl) contentEl.classList.remove('hidden');

    if (textEl) {
        if (templateText) {
            textEl.textContent = templateText;
        } else {
            // NOTE: contractShow() requires an existing listing ID, so we cannot fetch the full
            // contract before creation. Display terms summary; detailed contract sent after creation.
            textEl.textContent = 'سيتم إنشاء العقد التفصيلي بعد إرسال الإعلان وسيُطلب منك قبوله قبل النشر. بالمتابعة توافق على الشروط العامة للسوق المفتوح في المنصة.';
        }
    }
    wizContractLoaded = true;
}

// ─── Step 6: Review ───────────────────────────────────────────────────────────
function wizRenderReview() {
    const table = el('cl-review-table');
    if (!table) return;

    const rows = [
        ['الفئة',              wizSelectedCategory ? (wizSelectedCategory.name_ar || wizSelectedCategory.name_en) : '—'],
        ['العنوان (عربي)',     el('cl-title-ar')?.value  || '—'],
        ['العنوان (إنجليزي)', el('cl-title-en')?.value  || '—'],
        ['الدولة',              (() => {
            const sel = el('cl-country-id');
            return sel?.options[sel.selectedIndex]?.text || '—';
        })()],
        ['الغرض',              el('cl-purpose')?.value === 'sale' ? 'بيع' : 'إيجار'],
        ['السعر',              `${el('cl-price')?.value || 0} ${el('cl-currency')?.value || 'SAR'}`],
        ['قابل للتفاوض',      el('cl-negotiable')?.checked ? 'نعم' : 'لا'],
        ['ترويج المسوّقين',   el('cl-marketer-promo')?.checked ? 'مسموح' : 'غير مسموح'],
        ['عدد الصور',         wizImageFiles.length],
        wizNeedsSketch()      ? ['مخطط',          wizSketchFile ? wizSketchFile.name : 'لم يُرفع']                      : null,
        wizNeedsLocation()    ? ['إحداثيات',      `${el('cl-latitude')?.value  || '—'} / ${el('cl-longitude')?.value || '—'}`] : null,
        wizNeedsAttachments()
            ? ['مرفقات', `${Object.keys(wizAttachmentsByType).length} من ${wizSelectedCategory.required_attachment_types.length} مستندات`]
            : (wizAttachmentFiles.length > 0 ? ['مرفقات', wizAttachmentFiles.length + ' ملف/ملفات'] : null),
        wizHasContract()      ? ['العقد',          el('cl-contract-agree')?.checked ? 'تمت الموافقة' : 'لم توافق بعد'] : null,
    ].filter(Boolean);

    table.innerHTML = rows.map(([label, val]) =>
        `<div class="flex justify-between px-4 py-2.5">
            <span class="text-gray-500">${label}</span>
            <span class="font-medium text-gray-800">${val}</span>
        </div>`
    ).join('');
}

// ─── Validation ───────────────────────────────────────────────────────────────
function wizValidate(domStep) {
    if (domStep === 1 && !wizSelectedCategory) return 'يرجى اختيار فئة للإعلان.';
    if (domStep === 2) {
        if (!el('cl-country-id')?.value) return 'يرجى اختيار الدولة.';
        if (!el('cl-currency')?.value) return 'يرجى اختيار الدولة للحصول على العملة.';
        if (!el('cl-title-ar')?.value.trim()) return 'العنوان بالعربية مطلوب.';
        if (!el('cl-title-en')?.value.trim()) return 'العنوان بالإنجليزية مطلوب.';
        if (!el('cl-price')?.value || Number(el('cl-price').value) < 0) return 'يرجى إدخال سعر صحيح.';
    }
    if (domStep === 4 && wizImageFiles.length === 0) return 'يرجى رفع صورة واحدة على الأقل.';
    if (domStep === 5 && wizHasContract()) {
        if (!el('cl-signature-name')?.value.trim()) return 'يرجى إدخال الاسم للتوقيع.';
        if (!el('cl-contract-agree')?.checked) return 'يجب الموافقة على العقد للمتابعة.';
    }
    return null;
}

// ─── Submit ───────────────────────────────────────────────────────────────────
async function wizSubmit() {
    if (wizSubmitting) return;
    wizSetError(null);

    if (!wizImageFiles.length)                              { wizSetError('يرجى رفع صورة واحدة على الأقل.'); return; }
    if (wizHasContract() && !el('cl-contract-agree')?.checked) { wizSetError('يجب الموافقة على العقد.'); return; }

    wizSubmitting = true;
    const submitBtn = el('cl-wiz-submit');
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'جاري الإرسال...'; }

    const fd = new FormData();
    fd.append('classified_category_id', wizSelectedCategory.id);
    fd.append('country_id',             el('cl-country-id')?.value || '');
    const cityId = el('cl-city-id')?.value;
    if (cityId) fd.append('city_id', cityId);
    fd.append('listing_purpose',        el('cl-purpose')?.value || 'sale');
    fd.append('title_ar',               el('cl-title-ar')?.value.trim());
    fd.append('title_en',               el('cl-title-en')?.value.trim());
    fd.append('description_ar',         el('cl-desc-ar')?.value.trim() || '');
    fd.append('description_en',         el('cl-desc-en')?.value.trim() || '');
    fd.append('price',                   el('cl-price')?.value || 0);
    fd.append('currency',               el('cl-currency')?.value || 'SAR');
    fd.append('price_negotiable',            el('cl-negotiable')?.checked ? '1' : '0');
    fd.append('marketer_promotion_enabled',  el('cl-marketer-promo')?.checked ? '1' : '0');

    if (wizNeedsLocation()) {
        const lat = el('cl-latitude')?.value;
        const lng = el('cl-longitude')?.value;
        if (lat) fd.append('latitude', lat);
        if (lng) fd.append('longitude', lng);
    }

    Object.entries(wizAttributes).forEach(([k, v]) => {
        fd.append(`attributes[${k}]`, v);
    });

    wizImageFiles.forEach((f, i) => fd.append(`images[${i}]`, f));
    if (wizSketchFile) fd.append('sketch_file', wizSketchFile);
    wizAttachmentFiles.forEach((f, i) => fd.append(`attachments[${i}]`, f));
    Object.entries(wizAttachmentsByType).forEach(([type, file]) => fd.append(`attachments[${type}]`, file));

    if (wizHasContract()) {
        const sig = el('cl-signature-name')?.value.trim();
        if (sig) fd.append('signature_name', sig);
        fd.append('agreed', '1');
    }

    try {
        const data = await apiFetch(cfg().storeUrl, { method: 'POST', body: fd });
        wizClose();
        toast('تم إنشاء الإعلان بنجاح.');
        if (data.redirect) {
            window.location.href = data.redirect;
        } else if (data.data?.id) {
            window.location.href = buildShowUrl(data.data.id);
        }
    } catch (e) {
        const firstError = e.errors ? Object.values(e.errors)[0]?.[0] : e.message;
        wizSetError(firstError || 'حدث خطأ أثناء الإرسال. حاول مرة أخرى.');
        wizSubmitting = false;
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'إرسال الإعلان'; }
    }
}

// ─── Open / Close / Reset ─────────────────────────────────────────────────────
function wizSetError(msg) {
    const e = el('cl-wiz-error');
    if (!e) return;
    if (msg) { e.textContent = msg; e.style.display = 'block'; }
    else      e.style.display = 'none';
}

function wizOpen() {
    wizStep             = 1;
    wizSubmitting       = false;
    wizSelectedCategory = null;
    wizSelectedParentId = null;
    wizImageFiles       = [];
    wizSketchFile       = null;
    wizAttachmentFiles  = [];
    wizAttachmentsByType = {};
    wizAttributes       = {};
    wizContractLoaded   = false;
    wizSetError(null);

    ['cl-title-ar','cl-title-en','cl-desc-ar','cl-desc-en','cl-price',
     'cl-latitude','cl-longitude','cl-signature-name'].forEach(id => {
        const e = el(id); if (e) e.value = '';
    });
    const neg     = el('cl-negotiable');     if (neg)     neg.checked  = false;
    const mktPromo = el('cl-marketer-promo'); if (mktPromo) mktPromo.checked = false;
    const agree   = el('cl-contract-agree'); if (agree)   agree.checked = false;
    const purpose  = el('cl-purpose');        if (purpose)  purpose.value = 'sale';
    const currency = el('cl-currency');       if (currency) currency.value = '';
    updateCurrencyFromCountry('');

    const imgPrev = el('cl-images-preview');   if (imgPrev) imgPrev.innerHTML = '';
    const sketch  = el('cl-sketch-name');      if (sketch)  sketch.classList.add('hidden');
    const attList = el('cl-attachments-list'); if (attList) attList.innerHTML = '';
    const subSec  = el('cl-subcategory-section'); if (subSec) subSec.classList.add('hidden');
    const countryEl = el('cl-country-id'); if (countryEl) countryEl.value = '';
    resetCitySelect();

    if (wizCategoriesLoaded) wizRenderCategories();

    wizShowStep(1);
    const overlay = el('cl-wizard-overlay');
    if (overlay) overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function wizClose() {
    if (wizSubmitting) return;
    const overlay = el('cl-wizard-overlay');
    if (overlay) overlay.style.display = 'none';
    document.body.style.overflow = '';
}

// ─── Image / File handling ────────────────────────────────────────────────────
const MAX_IMAGE_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB

function initImageUpload() {
    const input = el('cl-images-input');
    if (!input) return;
    input.addEventListener('change', () => {
        const incoming = Array.from(input.files).filter(f => {
            if (f.size > MAX_IMAGE_SIZE_BYTES) { toast(`${f.name} يتجاوز الحد المسموح (10 ميجابايت).`, 'error'); return false; }
            return true;
        });
        wizImageFiles = [...wizImageFiles, ...incoming].slice(0, 10);
        renderImagePreviews();
        input.value = '';
    });
}

function renderImagePreviews() {
    const preview = el('cl-images-preview');
    if (!preview) return;
    preview.innerHTML = wizImageFiles.map((f, i) => {
        const url = URL.createObjectURL(f);
        return `<div class="relative group aspect-square rounded-lg overflow-hidden bg-gray-100">
            <img src="${url}" class="h-full w-full object-cover" alt="">
            <button type="button" onclick="wizRemoveImage(${i})"
                class="absolute top-1 end-1 h-5 w-5 rounded-full bg-black/60 text-white flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition-opacity">✕</button>
        </div>`;
    }).join('');
}

window.wizRemoveImage = function (i) {
    wizImageFiles.splice(i, 1);
    renderImagePreviews();
};

function initSketchUpload() {
    const input = el('cl-sketch-input');
    if (!input) return;
    input.addEventListener('change', () => {
        wizSketchFile = input.files[0] || null;
        const nameEl  = el('cl-sketch-name');
        if (nameEl) {
            if (wizSketchFile) { nameEl.textContent = wizSketchFile.name; nameEl.classList.remove('hidden'); }
            else               nameEl.classList.add('hidden');
        }
    });
}

function initAttachmentUpload() {
    const input = el('cl-attachments-input');
    if (!input) return;
    input.addEventListener('change', () => {
        wizAttachmentFiles = [...wizAttachmentFiles, ...Array.from(input.files)];
        renderAttachmentList();
        input.value = '';
    });
}

function renderAttachmentList() {
    const list = el('cl-attachments-list');
    if (!list) return;
    list.innerHTML = wizAttachmentFiles.map((f, i) =>
        `<div class="flex items-center justify-between text-xs text-gray-600 py-1">
            <span class="truncate">${f.name}</span>
            <button type="button" onclick="wizRemoveAttachment(${i})" class="text-red-400 hover:text-red-600 ms-2">✕</button>
        </div>`
    ).join('');
}

window.wizRemoveAttachment = function (i) {
    wizAttachmentFiles.splice(i, 1);
    renderAttachmentList();
};

// ─── Country / City ───────────────────────────────────────────────────────────
function updateCurrencyFromCountry(countryId) {
    const currencyMap = cfg().countryCurrencyMap ?? {};
    const currency    = countryId ? (currencyMap[countryId] ?? '') : '';

    const hiddenInput = el('cl-currency');
    const display     = el('cl-currency-display');

    if (hiddenInput) hiddenInput.value = currency;
    if (display) {
        display.textContent = currency
            ? currency
            : (cfg().selectCountryFirstLabel ?? 'Select a country first');
        display.classList.toggle('text-gray-800', !!currency);
        display.classList.toggle('font-bold',     !!currency);
        display.classList.toggle('text-gray-400', !currency);
    }
}

function resetCitySelect() {
    const cityEl = el('cl-city-id');
    if (!cityEl) return;
    cityEl.innerHTML = '<option value="">— اختر المدينة —</option>';
    cityEl.disabled = true;
}

async function loadCities(countryId) {
    resetCitySelect();
    if (!countryId) return;
    const cityEl = el('cl-city-id');
    if (!cityEl) return;
    try {
        const res  = await fetch(`/api/cities?country_id=${countryId}`, { headers: { Accept: 'application/json' } });
        const list = await res.json();
        (Array.isArray(list) ? list : (list.data ?? [])).forEach(c => {
            const opt = document.createElement('option');
            opt.value       = c.id;
            opt.textContent = c.name_ar || c.name_en;
            cityEl.appendChild(opt);
        });
        cityEl.disabled = false;
    } catch { /* non-critical */ }
}

// ─── Wizard init ──────────────────────────────────────────────────────────────
function wizInit() {
    el('btn-open-wizard')?.addEventListener('click', wizOpen);
    el('btn-open-wizard-empty')?.addEventListener('click', wizOpen);
    el('cl-wiz-close')?.addEventListener('click', wizClose);
    el('cl-country-id')?.addEventListener('change', e => {
        const countryId = e.target.value;
        loadCities(countryId);
        updateCurrencyFromCountry(countryId);
    });
    el('cl-wiz-cancel')?.addEventListener('click', wizClose);
    el('cl-wizard-backdrop')?.addEventListener('click', wizClose);

    el('cl-wiz-next')?.addEventListener('click', () => {
        const err = wizValidate(wizStep);
        if (err) { wizSetError(err); return; }
        wizSetError(null);
        wizShowStep(wizNextDomStep(wizStep));
    });

    el('cl-wiz-prev')?.addEventListener('click', () => {
        wizSetError(null);
        wizShowStep(wizPrevDomStep(wizStep));
    });

    el('cl-wiz-submit')?.addEventListener('click', wizSubmit);

    initImageUpload();
    initSketchUpload();
    initAttachmentUpload();
}

// ─────────────────────────────────────────────────────────────────────────────
// SHOW PAGE
// ─────────────────────────────────────────────────────────────────────────────
let currentListing = null;

async function loadShowData() {
    const loadingEl = el('cl-show-loading');
    const errorEl   = el('cl-show-error');
    const contentEl = el('cl-show-content');

    try {
        const data = await apiFetch(cfg().showDataUrl);
        currentListing = data.data;

        if (loadingEl) loadingEl.classList.add('hidden');
        if (contentEl) contentEl.classList.remove('hidden');

        renderShowHeader(currentListing);
        renderShowImages(currentListing.images || []);
        renderShowMeta(currentListing);
        renderShowDescription(currentListing);
        renderShowAttachments(currentListing.attachments || [], currentListing.sketch_file_url);
        updateShowButtons(currentListing.status, currentListing.expires_at);
    } catch {
        if (loadingEl) loadingEl.classList.add('hidden');
        if (errorEl)   errorEl.classList.remove('hidden');
    }
}

function renderShowHeader(listing) {
    const set = (id, val) => { const e = el(id); if (e) e.textContent = val ?? ''; };

    set('sh-listing-number', listing.listing_number ? `#${listing.listing_number}` : '');
    const badgeEl = el('sh-status-badge');
    if (badgeEl) badgeEl.innerHTML = statusBadge(listing.status);
    set('sh-title', listing.title_ar || listing.title_en);
    set('sh-category-name', listing.classified_category?.name_ar || listing.classified_category?.name_en || '—');
    set('sh-views-count', listing.views_count ?? 0);
    set('sh-created-at', formatDate(listing.created_at));

    const priceEl = el('sh-price');
    if (priceEl) priceEl.textContent = formatMoney(listing.price, listing.currency);

    const negEl = el('sh-negotiable');
    if (negEl) {
        if (listing.price_negotiable) negEl.classList.remove('hidden');
        else                          negEl.classList.add('hidden');
    }

    const rejBlock = el('sh-rejection-block');
    if (rejBlock) {
        if (listing.rejection_reason) {
            rejBlock.classList.remove('hidden');
            set('sh-rejection-reason', listing.rejection_reason);
        } else {
            rejBlock.classList.add('hidden');
        }
    }
}

function renderShowImages(images) {
    const loadingEl = el('sh-images-loading');
    const grid      = el('sh-images-grid');
    const emptyEl   = el('sh-images-empty');

    if (loadingEl) loadingEl.style.display = 'none';
    if (!images.length) { if (emptyEl) emptyEl.classList.remove('hidden'); return; }

    if (grid) {
        grid.style.display = 'grid';
        grid.innerHTML = images.map(img => {
            const url = img.url || `/storage/${img.file_path}`;
            return `<a href="${url}" target="_blank" class="block aspect-square rounded-lg overflow-hidden bg-gray-100 hover:opacity-90 transition-opacity">
                <img src="${url}" class="h-full w-full object-cover" alt="">
            </a>`;
        }).join('');
    }
}

function renderShowMeta(listing) {
    const set = (id, val) => { const e = el(id); if (e) e.textContent = val ?? ''; };
    set('sh-purpose',       listing.listing_purpose === 'sale' ? 'بيع' : 'إيجار');
    set('sh-currency',      listing.currency || '—');
    set('sh-meta-category', listing.classified_category?.name_ar || listing.classified_category?.name_en || '—');

    if (listing.expires_at) {
        set('sh-expires', formatDate(listing.expires_at));
        el('sh-expires-row')?.classList.remove('hidden');
    } else {
        el('sh-expires-row')?.classList.add('hidden');
    }
}

function renderShowDescription(listing) {
    const descEl = el('sh-description');
    if (descEl) descEl.textContent = listing.description_ar || listing.description_en || 'لا يوجد وصف.';

    const attrs = listing.attributes;
    const attrEntries = attrs ? Object.entries(attrs).filter(([, v]) => v !== null && v !== '') : [];
    if (attrEntries.length) {
        el('sh-attributes-section')?.classList.remove('hidden');
        const dl = el('sh-attributes');
        if (dl) {
            dl.innerHTML = attrEntries.map(([k, v]) =>
                `<div class="col-span-1">
                    <dt class="text-gray-400 text-xs">${k}</dt>
                    <dd class="font-medium text-gray-800">${v}</dd>
                </div>`
            ).join('');
        }
    } else {
        el('sh-attributes-section')?.classList.add('hidden');
    }
}

const ATTACHMENT_STATUS = {
    pending:  { label: 'قيد المراجعة', cls: 'bg-amber-100 text-amber-700' },
    verified: { label: 'مقبول',         cls: 'bg-emerald-100 text-emerald-700' },
    rejected: { label: 'مرفوض',         cls: 'bg-red-100 text-red-700' },
};

function renderShowAttachments(attachments, sketchUrl) {
    const sketchRow  = el('sh-sketch-row');
    const sketchLink = el('sh-sketch-link');
    if (sketchUrl && sketchRow && sketchLink) {
        sketchRow.style.display = 'flex';
        sketchLink.href = sketchUrl;
    }

    if (attachments.length) {
        el('sh-attachments-card')?.classList.remove('hidden');
        const list = el('sh-attachments-list');
        if (list) {
            list.innerHTML = attachments.map(att => {
                const s = ATTACHMENT_STATUS[att.status] || { label: att.status || 'قيد المراجعة', cls: 'bg-gray-100 text-gray-600' };
                const inner = att.url
                    ? `<a href="${att.url}" target="_blank" class="flex-1 flex items-center gap-2 min-w-0 hover:text-primary-600 transition-colors">
                            <svg class="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"/></svg>
                            <span class="text-sm truncate">${att.attachment_type || 'مرفق'}</span>
                       </a>`
                    : `<div class="flex-1 flex items-center gap-2 min-w-0">
                            <svg class="h-4 w-4 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"/></svg>
                            <span class="text-sm text-gray-500 truncate">${att.attachment_type || 'مرفق'}</span>
                       </div>`;
                return `<div class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 px-3 py-2">
                    ${inner}
                    <span class="shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ${s.cls}">${s.label}</span>
                </div>`;
            }).join('');
        }
    }
}

function updateShowButtons(status, expiresAt) {
    const pauseBtn    = el('sh-btn-pause');
    const resumeBtn   = el('sh-btn-resume');
    const soldBtn     = el('sh-btn-sold');
    const contractBtn = el('sh-btn-contract');

    // Reset all to hidden
    [pauseBtn, resumeBtn, soldBtn, contractBtn].forEach(b => {
        if (b) b.style.display = 'none';
    });

    const isExpired = expiresAt && new Date(expiresAt) < new Date();

    if (status === 'active') {
        pauseBtn?.style.setProperty('display', 'inline-flex');
        soldBtn?.style.setProperty('display', 'inline-flex');
    }
    if (status === 'paused' && !isExpired) {
        resumeBtn?.style.setProperty('display', 'inline-flex');
    }
    if (status === 'paused') {
        soldBtn?.style.setProperty('display', 'inline-flex');
    }
    if (status === 'pending_contract') {
        contractBtn?.style.setProperty('display', 'inline-flex');
    }
}

function setupShowActions() {
    const pauseBtn    = el('sh-btn-pause');
    const resumeBtn   = el('sh-btn-resume');
    const soldBtn     = el('sh-btn-sold');
    const contractBtn = el('sh-btn-contract');

    pauseBtn?.addEventListener('click', async () => {
        const ok = await confirmDialog('إيقاف مؤقت؟', 'هل تريد إيقاف الإعلان مؤقتاً؟');
        if (!ok) return;
        try {
            await apiFetch(cfg().pauseUrl, { method: 'PUT' });
            toast('تم إيقاف الإعلان مؤقتاً.');
            await loadShowData();
        } catch (e) {
            toast(e.message || 'تعذّر الإيقاف.', 'error');
        }
    });

    resumeBtn?.addEventListener('click', async () => {
        const ok = await confirmDialog('استئناف الإعلان؟', 'هل تريد استئناف الإعلان؟');
        if (!ok) return;
        try {
            await apiFetch(cfg().resumeUrl, { method: 'PUT' });
            toast('تم استئناف الإعلان.');
            await loadShowData();
        } catch (e) {
            toast(e.message || 'تعذّر الاستئناف.', 'error');
        }
    });

    soldBtn?.addEventListener('click', async () => {
        const ok = await confirmDialog('تمييز كـ "تم البيع"؟', 'هل تريد تمييز الإعلان كـ "تم البيع / الإيجار"؟');
        if (!ok) return;
        try {
            await apiFetch(cfg().markSoldUrl, { method: 'PUT' });
            toast('تم تمييز الإعلان كـ "تم البيع".');
            await loadShowData();
        } catch (e) {
            toast(e.message || 'تعذّرت العملية.', 'error');
        }
    });

    contractBtn?.addEventListener('click', () => openContractModal());
}

// ─── Contract modal ───────────────────────────────────────────────────────────
async function openContractModal() {
    const modal     = el('sh-contract-modal');
    const loadingEl = el('sh-contract-modal-loading');
    const bodyEl    = el('sh-contract-modal-body');
    const textEl    = el('sh-contract-modal-text');
    const acceptBtn = el('sh-contract-accept-btn');
    const errorEl   = el('sh-contract-modal-error');

    if (!modal) return;
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    if (loadingEl) loadingEl.style.display = 'block';
    if (bodyEl)    bodyEl.classList.add('hidden');
    if (acceptBtn) acceptBtn.style.display = 'none';
    if (errorEl)   errorEl.classList.add('hidden');

    // Reset fields
    const sigEl   = el('sh-contract-sig-name');
    const agreeEl = el('sh-contract-modal-agree');
    if (sigEl)   sigEl.value     = '';
    if (agreeEl) agreeEl.checked = false;

    try {
        const data    = await apiFetch(cfg().contractUrl);
        const content = data.data?.text || data.data?.content_ar || data.data?.content_en || data.data?.content || 'محتوى العقد غير متاح.';
        if (textEl) textEl.textContent = content;
        if (loadingEl) loadingEl.style.display = 'none';
        if (bodyEl)    bodyEl.classList.remove('hidden');
        if (acceptBtn) acceptBtn.style.display = 'inline-flex';
        updateContractAcceptBtn();
    } catch {
        if (loadingEl) loadingEl.textContent = 'تعذّر تحميل العقد.';
    }
}

function updateContractAcceptBtn() {
    const acceptBtn = el('sh-contract-accept-btn');
    if (!acceptBtn) return;
    const sigFilled = !!(el('sh-contract-sig-name')?.value.trim());
    const agreed    = !!(el('sh-contract-modal-agree')?.checked);
    acceptBtn.disabled = !(sigFilled && agreed);
}

function closeContractModal() {
    const modal = el('sh-contract-modal');
    if (modal) modal.style.display = 'none';
    document.body.style.overflow = '';
}

// ─── Inquiries ────────────────────────────────────────────────────────────────
async function loadInquiries() {
    const loadingEl = el('sh-inquiries-loading');
    const emptyEl   = el('sh-inquiries-empty');
    const listEl    = el('sh-inquiries-list');
    const countEl   = el('sh-inquiries-count');

    try {
        const data  = await apiFetch(cfg().inquiriesUrl);
        const items = data.data?.items || data.data || [];

        if (loadingEl) loadingEl.style.display = 'none';
        if (countEl)   countEl.textContent = items.length;

        if (!items.length) {
            if (emptyEl) emptyEl.classList.remove('hidden');
            return;
        }

        if (listEl) {
            listEl.classList.remove('hidden');
            listEl.innerHTML = items.map(inq => renderInquiryRow(inq)).join('');
        }
    } catch {
        if (loadingEl) loadingEl.textContent = 'تعذّر تحميل الاستفسارات.';
    }
}

function renderInquiryRow(inq) {
    const statusMap = {
        new:     { label: 'جديد',    cls: 'bg-blue-100 text-blue-700' },
        read:    { label: 'مقروء',   cls: 'bg-gray-100 text-gray-600' },
        replied: { label: 'تم الرد', cls: 'bg-emerald-100 text-emerald-700' },
        closed:  { label: 'مغلق',    cls: 'bg-gray-100 text-gray-500' },
    };
    const s = statusMap[inq.status] || { label: inq.status, cls: 'bg-gray-100 text-gray-600' };
    const statusOptions = Object.entries(statusMap).map(([v, sm]) =>
        `<option value="${v}" ${inq.status === v ? 'selected' : ''}>${sm.label}</option>`
    ).join('');

    return `<div class="px-5 py-4 space-y-2" id="inq-row-${inq.id}">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ${s.cls}">${s.label}</span>
                <span class="text-xs text-gray-500">${inq.buyer_name || 'مشترٍ'}</span>
                <span class="text-xs text-gray-400">${formatDate(inq.created_at)}</span>
            </div>
            <select onchange="updateInquiryStatus('${inq.id}', this.value)"
                class="rounded-lg border border-gray-200 px-2 py-1 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-primary-400">
                ${statusOptions}
            </select>
        </div>
        <p class="text-sm text-gray-700 leading-relaxed">${inq.message || ''}</p>
        ${inq.contact_phone ? `<p class="text-xs text-gray-500">📞 ${inq.contact_phone}</p>` : ''}
    </div>`;
}

window.updateInquiryStatus = async function (inquiryId, status) {
    const url = cfg().inquiryStatusBaseUrl.replace('__ID__', inquiryId);
    try {
        await apiFetch(url, { method: 'POST', body: JSON.stringify({ status }) });
    } catch (e) {
        toast(e.message || 'تعذّر تحديث الحالة.', 'error');
    }
};

// ─────────────────────────────────────────────────────────────────────────────
// Boot
// ─────────────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {

    // ── Index page ────────────────────────────────────────────────────────────
    if (el('cl-tbody')) {
        initIndexPage();
        wizInit();
    }

    // ── Show page ─────────────────────────────────────────────────────────────
    if (cfg().showId) {
        loadShowData();
        loadInquiries();
        setupShowActions();

        // Contract modal wiring
        el('sh-contract-close')?.addEventListener('click', closeContractModal);
        el('sh-contract-cancel-btn')?.addEventListener('click', closeContractModal);
        el('sh-contract-backdrop')?.addEventListener('click', closeContractModal);

        // Enable accept button only when both conditions are met
        el('sh-contract-sig-name')?.addEventListener('input', updateContractAcceptBtn);
        el('sh-contract-modal-agree')?.addEventListener('change', updateContractAcceptBtn);

        el('sh-contract-accept-btn')?.addEventListener('click', async () => {
            const sigName = el('sh-contract-sig-name')?.value.trim();
            const agreed  = el('sh-contract-modal-agree')?.checked;
            const errorEl = el('sh-contract-modal-error');

            if (!agreed) {
                if (errorEl) { errorEl.textContent = 'يجب الموافقة على العقد.'; errorEl.classList.remove('hidden'); }
                return;
            }
            if (!sigName) {
                if (errorEl) { errorEl.textContent = 'يرجى إدخال الاسم للتوقيع.'; errorEl.classList.remove('hidden'); }
                return;
            }

            const btn = el('sh-contract-accept-btn');
            btn.disabled    = true;
            btn.textContent = 'جاري القبول...';

            try {
                await apiFetch(cfg().contractAcceptUrl, {
                    method: 'POST',
                    body:   JSON.stringify({ signature_name: sigName, agreed: true }),
                });
                closeContractModal();
                toast('تم قبول العقد بنجاح. سيتم مراجعة الإعلان قريباً.');
                await loadShowData();
            } catch (e) {
                const msg = e.errors ? Object.values(e.errors)[0]?.[0] : e.message;
                if (errorEl) { errorEl.textContent = msg || 'حدث خطأ.'; errorEl.classList.remove('hidden'); }
                btn.disabled    = false;
                btn.textContent = 'قبول العقد وإرسال للمراجعة';
            }
        });
    }
});
