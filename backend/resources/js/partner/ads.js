import './app.js';
import DataTable from 'datatables.net-dt';

// ─────────────────────────────────────────────────────────────────────────────
// Config (injected from Blade via window.ADS_CONFIG)
// ─────────────────────────────────────────────────────────────────────────────
const cfg = () => window.ADS_CONFIG || {};

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

// ─────────────────────────────────────────────────────────────────────────────
// Utilities
// ─────────────────────────────────────────────────────────────────────────────
function formatMoney(amount) {
    return new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(amount);
}

function formatNumber(n) {
    return new Intl.NumberFormat('ar-SA').format(n ?? 0);
}

function toast(msg, type = 'success') {
    window.Toastify?.({
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

async function apiFetch(url, options = {}) {
    const res = await fetch(url, {
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            ...options.headers,
        },
        ...options,
    });
    const json = await res.json();
    if (!res.ok) throw json;
    return json;
}

function el(id) { return document.getElementById(id); }

// ─────────────────────────────────────────────────────────────────────────────
// DataTable — index page
// ─────────────────────────────────────────────────────────────────────────────

const ARABIC_LANGUAGE = {
    emptyTable:     'لا توجد حملات',
    info:           'عرض _START_ إلى _END_ من أصل _TOTAL_ سجل',
    infoEmpty:      'لا توجد سجلات',
    infoFiltered:   '(مصفاة من _MAX_ سجل)',
    lengthMenu:     'أظهر _MENU_ سجلاً',
    loadingRecords: 'جارٍ التحميل...',
    processing:     '<div class="flex justify-center py-8"><div class="w-6 h-6 border-2 border-primary-400 border-t-transparent rounded-full animate-spin"></div></div>',
    search:         '',
    zeroRecords:    'لا توجد نتائج مطابقة',
    paginate: { first: 'الأولى', last: 'الأخيرة', next: 'التالية', previous: 'السابقة' },
};

function buildPaginationDrawCallback(tableId) {
    return function () {
        const api  = this.api();
        const info = api.page.info();

        const infoEl = el(`${tableId}-info`);
        if (infoEl) {
            infoEl.textContent = info.recordsTotal === 0
                ? ''
                : `عرض ${info.start + 1}–${info.end} من ${info.recordsTotal} سجل`;
        }

        const pagEl = el(`${tableId}-pagination`);
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
                active   ? 'bg-primary-500 text-white font-bold' : 'text-gray-600 hover:bg-gray-100',
                disabled ? 'opacity-30 cursor-not-allowed pointer-events-none' : '',
            ].join(' ');
            if (!disabled && !active) b.addEventListener('click', () => api.page(pg).draw(false));
            return b;
        };

        pagEl.appendChild(btn('«', 0, page === 0, false));
        pagEl.appendChild(btn('‹', page - 1, page === 0, false));

        const startPg = Math.max(0, page - 2);
        const endPg   = Math.min(pages - 1, page + 2);
        for (let i = startPg; i <= endPg; i++) pagEl.appendChild(btn(i + 1, i, false, i === page));

        pagEl.appendChild(btn('›', page + 1, page >= pages - 1, false));
        pagEl.appendChild(btn('»', pages - 1, page >= pages - 1, false));
    };
}

function initIndexPage() {
    const tableEl = el('ads-table');
    if (!tableEl) return;

    const table = new DataTable(tableEl, {
        processing: true,
        serverSide: true,
        searching:  false,
        dom:        't',
        pageLength: 25,
        order:      [[5, 'desc']],
        language:   ARABIC_LANGUAGE,
        ajax: {
            url:  cfg().datatableUrl,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data: (d) => {
                const status = el('ads-filter-status')?.value;
                if (status) d.status = status;
                const search = el('ads-search')?.value?.trim();
                if (search) d.search = { value: search };
                return d;
            },
        },
        columns: [
            { data: 'name' },
            { data: 'type' },
            { data: 'status' },
            { data: 'budget' },
            { data: 'bid' },
            { data: 'date' },
            { data: 'actions', orderable: false, searchable: false },
        ],
        createdRow:   (row) => row.classList.add('hover:bg-gray-50', 'transition-colors'),
        drawCallback: buildPaginationDrawCallback('ads-table'),
    });

    el('ads-filter-status')?.addEventListener('change', () => table.ajax.reload());

    let searchDebounce = null;
    el('ads-search')?.addEventListener('input', () => {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => table.ajax.reload(), 350);
    });

    wizInit();
}

// ─────────────────────────────────────────────────────────────────────────────
// Create Campaign Wizard
// ─────────────────────────────────────────────────────────────────────────────

const STEP_LABELS = ['الأساسيات', 'الاستهداف', 'المنتجات', 'الميزانية', 'مراجعة'];
const TOTAL_STEPS = 5;

let wizStep               = 1;
let wizSubmitting         = false;
let wizListings           = [];
let wizListingsLoaded     = false;
let wizCategories         = [];
let wizCategoriesLoaded   = false;
let wizSelectedProducts   = [];
let wizSelectedCategories = [];
let wizKeywords           = [{ keyword: '', match_type: 'broad' }];

function wizShow(id, displayType = 'block') {
    const e = el(id); if (e) e.style.display = displayType;
}
function wizHide(id) {
    const e = el(id); if (e) e.style.display = 'none';
}
function wizSetError(msg) {
    const e = el('wiz-error');
    if (!e) return;
    if (msg) { e.textContent = msg; e.style.display = 'block'; }
    else      { e.style.display = 'none'; }
}
function wizGetType()      { return document.querySelector('input[name="wiz-type"]:checked')?.value || 'cpc'; }
function wizGetTargeting() { return el('wiz-targeting')?.value || 'auto'; }
function wizNeedsKeywords()   { return ['keyword', 'mixed'].includes(wizGetTargeting()); }
function wizNeedsCategories() { return ['category', 'mixed'].includes(wizGetTargeting()); }

function wizRenderProgress() {
    const container = el('wiz-progress');
    if (!container) return;
    container.innerHTML = STEP_LABELS.map((label, i) => {
        const num    = i + 1;
        const done   = num < wizStep;
        const active = num === wizStep;
        const dotCls = done
            ? 'bg-primary-500 text-white'
            : active
                ? 'bg-primary-600 text-white ring-4 ring-primary-100'
                : 'bg-gray-100 text-gray-400';
        const lineBefore = i === 0                        ? 'bg-transparent' : (i < wizStep ? 'bg-primary-500' : 'bg-gray-200');
        const lineAfter  = i === STEP_LABELS.length - 1   ? 'bg-transparent' : (num < wizStep ? 'bg-primary-500' : 'bg-gray-200');
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

function wizShowStep(step) {
    for (let i = 1; i <= TOTAL_STEPS; i++) {
        const s = el(`wiz-step-${i}`);
        if (s) s.style.display = i === step ? 'block' : 'none';
    }

    const stepLabel = el('wiz-step-label');
    if (stepLabel) stepLabel.textContent = step;

    wizRenderProgress();

    if (step > 1) wizShow('wiz-prev', 'inline-flex'); else wizHide('wiz-prev');
    const spacer = el('wiz-prev-spacer');
    if (spacer) spacer.style.display = step > 1 ? 'none' : 'inline';

    if (step < TOTAL_STEPS) {
        wizShow('wiz-next', 'inline-flex');
        wizHide('wiz-submit');
    } else {
        wizHide('wiz-next');
        wizShow('wiz-submit', 'inline-flex');
        wizRenderReview();
    }

    if (step === 2) wizRenderTargetingUI();
    if (step === 3) wizRenderProducts();

    if (step === 4) {
        const bidLabel = el('wiz-bid-label');
        if (bidLabel) {
            bidLabel.textContent = wizGetType() === 'cpc'
                ? 'مزايدة النقرة (ر.س)'
                : 'مزايدة الألف ظهور (ر.س)';
        }
    }
}

function wizRenderTargetingUI() {
    const targeting  = wizGetTargeting();
    const autoMsg    = el('wiz-auto-msg');
    const kwSection  = el('wiz-keywords-section');
    const catSection = el('wiz-categories-section');

    if (autoMsg)    autoMsg.style.display    = targeting === 'auto' ? 'block' : 'none';
    if (kwSection)  kwSection.style.display  = wizNeedsKeywords()   ? 'block' : 'none';
    if (catSection) catSection.style.display = wizNeedsCategories() ? 'block' : 'none';

    if (wizNeedsKeywords())   wizRenderKeywords();
    if (wizNeedsCategories()) wizLoadAndRenderCategories();
}

function wizRenderKeywords() {
    const list = el('wiz-keywords-list');
    if (!list) return;

    list.innerHTML = wizKeywords.map((kw, i) => `
        <div class="flex gap-2 items-start mb-2">
            <input type="text" value="${kw.keyword}" placeholder="كلمة مفتاحية..."
                class="flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                data-kw="${i}" data-field="keyword">
            <select class="rounded-lg border border-gray-200 px-2 py-2 text-sm" data-kw="${i}" data-field="match_type">
                <option value="broad"  ${kw.match_type === 'broad'  ? 'selected' : ''}>واسع</option>
                <option value="phrase" ${kw.match_type === 'phrase' ? 'selected' : ''}>عبارة</option>
                <option value="exact"  ${kw.match_type === 'exact'  ? 'selected' : ''}>مطابق</option>
            </select>
            ${wizKeywords.length > 1
                ? `<button type="button" class="text-gray-300 hover:text-red-400 pt-2 wiz-kw-remove" data-kw="${i}">
                       <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                           <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                       </svg>
                   </button>`
                : '<span class="w-6"></span>'}
        </div>`).join('');

    list.querySelectorAll('[data-kw][data-field]').forEach(input => {
        input.addEventListener('change', e => {
            wizKeywords[+e.target.dataset.kw][e.target.dataset.field] = e.target.value;
        });
    });
    list.querySelectorAll('.wiz-kw-remove').forEach(btn => {
        btn.addEventListener('click', e => {
            wizKeywords.splice(+e.currentTarget.dataset.kw, 1);
            wizRenderKeywords();
        });
    });
}

async function wizLoadAndRenderCategories() {
    if (wizCategoriesLoaded) { wizRenderCategories(); return; }
    wizShow('wiz-categories-loading');
    wizHide('wiz-categories-grid');
    try {
        const data = await apiFetch(cfg().categoriesUrl);
        wizCategories = data.data || [];
        wizCategoriesLoaded = true;
        wizRenderCategories();
    } catch {
        wizCategories = [];
        wizCategoriesLoaded = true;
        wizHide('wiz-categories-loading');
    }
}

function wizRenderCategories() {
    wizHide('wiz-categories-loading');
    const grid = el('wiz-categories-grid');
    if (!grid) return;
    grid.style.display = 'grid';
    grid.innerHTML = wizCategories.map(cat => {
        const selected = wizSelectedCategories.some(c => c.category_id === cat.id);
        return `<label class="flex items-center gap-2 rounded-lg border-2 p-2.5 cursor-pointer text-sm transition-colors ${selected ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-gray-100 hover:border-gray-200'}"
            data-cat-id="${cat.id}">
            <input type="checkbox" class="sr-only" ${selected ? 'checked' : ''}>
            <span class="truncate">${cat.name_ar || cat.name_en}</span>
        </label>`;
    }).join('');

    grid.querySelectorAll('[data-cat-id]').forEach(label => {
        label.addEventListener('click', (e) => {
            e.preventDefault();
            const id  = +label.dataset.catId;
            const idx = wizSelectedCategories.findIndex(c => c.category_id === id);
            if (idx === -1) {
                wizSelectedCategories.push({ category_id: id });
                label.classList.add('border-primary-500', 'bg-primary-50', 'text-primary-700');
                label.classList.remove('border-gray-100', 'hover:border-gray-200');
            } else {
                wizSelectedCategories.splice(idx, 1);
                label.classList.remove('border-primary-500', 'bg-primary-50', 'text-primary-700');
                label.classList.add('border-gray-100', 'hover:border-gray-200');
            }
        });
    });
}

async function wizRenderProducts() {
    if (wizListingsLoaded) { wizDisplayProducts(); return; }
    wizShow('wiz-products-loading');
    wizHide('wiz-products-empty');
    wizHide('wiz-products-grid');

    try {
        const url  = cfg().listingsUrl;
        const sep  = url.includes('?') ? '&' : '?';
        const data = await apiFetch(`${url}${sep}status=active&per_page=100`);
        // Partner listings datatable returns { data: [...] } (DataTables format) or paginated { data: { data: [...] } }
        const items = Array.isArray(data.data) ? data.data : (data.data?.data || []);
        wizListings = items;
    } catch {
        wizListings = [];
    }

    wizListingsLoaded = true;
    wizDisplayProducts();
}

function wizDisplayProducts() {
    wizHide('wiz-products-loading');

    if (!wizListings.length) {
        wizShow('wiz-products-empty');
        return;
    }

    const grid = el('wiz-products-grid');
    if (!grid) return;
    grid.style.display = 'grid';

    grid.innerHTML = wizListings.map(listing => {
        const id       = listing.id ?? listing.product_variant_id;
        const selected = wizSelectedProducts.includes(id);
        const img      = listing.primary_image
            ? `<img src="${listing.primary_image}" class="h-full w-full object-cover" alt="">`
            : `<svg class="h-5 w-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                   <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
               </svg>`;
        return `<label class="flex items-center gap-3 rounded-xl border-2 p-3 cursor-pointer transition-colors ${selected ? 'border-primary-500 bg-primary-50' : 'border-gray-100 hover:border-gray-200'}"
            data-listing-id="${id}">
            <input type="checkbox" class="sr-only" ${selected ? 'checked' : ''}>
            <div class="shrink-0 h-10 w-10 rounded-lg bg-gray-100 overflow-hidden flex items-center justify-center">${img}</div>
            <div class="min-w-0 flex-1">
                <div class="text-sm font-medium text-gray-800 line-clamp-1">${listing.name_ar || listing.name_en || listing.sku || '—'}</div>
                <div class="text-xs text-gray-500">${listing.sku || ''}</div>
            </div>
            <div class="shrink-0 h-5 w-5 rounded-full bg-primary-500 items-center justify-center text-white text-xs wiz-check-icon ${selected ? 'flex' : 'hidden'}">✓</div>
        </label>`;
    }).join('');

    grid.querySelectorAll('[data-listing-id]').forEach(label => {
        label.addEventListener('click', (e) => {
            e.preventDefault();
            const id        = label.dataset.listingId;
            const idx       = wizSelectedProducts.indexOf(id);
            const checkIcon = label.querySelector('.wiz-check-icon');
            if (idx === -1) {
                wizSelectedProducts.push(id);
                label.classList.add('border-primary-500', 'bg-primary-50');
                label.classList.remove('border-gray-100', 'hover:border-gray-200');
                checkIcon?.classList.replace('hidden', 'flex');
            } else {
                wizSelectedProducts.splice(idx, 1);
                label.classList.remove('border-primary-500', 'bg-primary-50');
                label.classList.add('border-gray-100', 'hover:border-gray-200');
                checkIcon?.classList.replace('flex', 'hidden');
            }
        });
    });
}

function wizRenderReview() {
    const table = el('wiz-review-table');
    if (!table) return;

    const targeting      = wizGetTargeting();
    const targetingLabel = { auto: 'تلقائي', keyword: 'كلمات مفتاحية', category: 'فئات', mixed: 'مختلط' }[targeting];
    const budgetTotal    = el('wiz-budget-total')?.value || '';
    const budgetDaily    = el('wiz-budget-daily')?.value || '';
    const bid            = el('wiz-bid')?.value          || '';
    const startsAt       = el('wiz-starts-at')?.value    || '';
    const endsAt         = el('wiz-ends-at')?.value      || '';

    const rows = [
        ['اسم الحملة',          el('wiz-name')?.value || ''],
        ['النموذج',              wizGetType().toUpperCase()],
        ['الاستهداف',            targetingLabel],
        wizNeedsKeywords()   ? ['الكلمات المفتاحية',  wizKeywords.filter(k => k.keyword.trim()).length + ' كلمة']  : null,
        wizNeedsCategories() ? ['الفئات المستهدفة',   wizSelectedCategories.length + ' فئة']                       : null,
        ['عدد المنتجات',         wizSelectedProducts.length],
        ['الميزانية الإجمالية', budgetTotal ? budgetTotal + ' ر.س' : '—'],
        budgetDaily ? ['الميزانية اليومية', budgetDaily + ' ر.س'] : null,
        ['المزايدة',             bid ? bid + ' ر.س' : '—'],
        ['تاريخ البدء',          startsAt || '—'],
        endsAt ? ['تاريخ الانتهاء', endsAt] : null,
    ].filter(Boolean);

    table.innerHTML = rows.map(([label, val]) =>
        `<div class="flex justify-between px-4 py-2.5">
            <span class="text-gray-500">${label}</span>
            <span class="font-medium text-gray-800">${val}</span>
        </div>`
    ).join('');
}

function wizValidateStep(s) {
    if (s === 1) {
        if (!el('wiz-name')?.value.trim()) return 'اسم الحملة مطلوب.';
        if (!wizGetType())                 return 'نموذج التسعير مطلوب.';
        if (!wizGetTargeting())            return 'نوع الاستهداف مطلوب.';
    }
    if (s === 2 && wizNeedsKeywords()   && !wizKeywords.some(k => k.keyword.trim())) return 'أدخل كلمة مفتاحية واحدة على الأقل.';
    if (s === 2 && wizNeedsCategories() && !wizSelectedCategories.length)            return 'اختر فئة واحدة على الأقل.';
    if (s === 3 && !wizSelectedProducts.length) return 'اختر منتجاً واحداً على الأقل.';
    if (s === 4) {
        const bt  = Number(el('wiz-budget-total')?.value);
        const bd  = Number(el('wiz-budget-daily')?.value);
        const bid = Number(el('wiz-bid')?.value);
        if (!bt || bt < 1)            return 'الميزانية الإجمالية مطلوبة.';
        if (!bid || bid < 0.01)       return 'مبلغ المزايدة مطلوب (0.01 ر.س على الأقل).';
        if (!el('wiz-starts-at')?.value) return 'تاريخ البدء مطلوب.';
        if (bd && bd > bt)            return 'الميزانية اليومية لا يمكن أن تتجاوز الإجمالية.';
    }
    return null;
}

function wizOpen() {
    wizStep               = 1;
    wizSubmitting         = false;
    wizSelectedProducts   = [];
    wizSelectedCategories = [];
    wizKeywords           = [{ keyword: '', match_type: 'broad' }];
    wizListingsLoaded     = false;
    wizCategoriesLoaded   = false;
    wizSetError(null);

    ['wiz-name', 'wiz-budget-total', 'wiz-budget-daily', 'wiz-bid', 'wiz-starts-at', 'wiz-ends-at']
        .forEach(id => { const e = el(id); if (e) e.value = ''; });

    const cpcRadio = document.querySelector('input[name="wiz-type"][value="cpc"]');
    if (cpcRadio) {
        cpcRadio.checked = true;
        cpcRadio.dispatchEvent(new Event('change'));
    }

    const targeting = el('wiz-targeting');
    if (targeting) targeting.value = 'auto';

    const today = new Date().toISOString().split('T')[0];
    const startsAt = el('wiz-starts-at');
    if (startsAt) startsAt.min = today;

    wizShowStep(1);
    const overlay = el('wizard-overlay');
    if (overlay) { overlay.style.display = 'flex'; overlay.classList.remove('hidden'); }
    document.body.style.overflow = 'hidden';
}

function wizClose() {
    if (wizSubmitting) return;
    const overlay = el('wizard-overlay');
    if (overlay) { overlay.style.display = 'none'; overlay.classList.add('hidden'); }
    document.body.style.overflow = '';
}

async function wizSubmit() {
    wizSetError(null);
    wizSubmitting = true;
    const submitBtn = el('wiz-submit');
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'جاري الإرسال...'; }

    const payload = {
        name:           el('wiz-name')?.value.trim(),
        type:           wizGetType(),
        targeting_type: wizGetTargeting(),
        budget_total:   Number(el('wiz-budget-total')?.value),
        budget_daily:   el('wiz-budget-daily')?.value ? Number(el('wiz-budget-daily').value) : null,
        bid:            Number(el('wiz-bid')?.value),
        starts_at:      el('wiz-starts-at')?.value,
        ends_at:        el('wiz-ends-at')?.value || null,
        vendor_listing_ids: wizSelectedProducts,
        keywords: wizNeedsKeywords()
            ? wizKeywords.filter(k => k.keyword.trim()).map(k => ({ keyword: k.keyword, match_type: k.match_type }))
            : undefined,
        category_ids: wizNeedsCategories()
            ? wizSelectedCategories.map(c => c.category_id)
            : undefined,
    };

    try {
        const data = await apiFetch(cfg().storeUrl, { method: 'POST', body: JSON.stringify(payload) });
        wizClose();
        toast('تم إنشاء الحملة بنجاح — ستُراجَع خلال 24 ساعة.');
        if (data.redirect) {
            window.location.href = data.redirect;
        } else if (data.data?.id) {
            window.location.href = `${window.location.pathname}/${data.data.id}`;
        }
    } catch (e) {
        const firstError = e.errors
            ? Object.values(e.errors)[0]?.[0]
            : (e.message || 'حدث خطأ أثناء الإنشاء. حاول مرة أخرى.');
        wizSetError(firstError);
        wizSubmitting = false;
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'إرسال للمراجعة'; }
    }
}

function wizInit() {
    el('btn-open-wizard')?.addEventListener('click', wizOpen);
    el('wiz-close')?.addEventListener('click', wizClose);
    el('wiz-cancel')?.addEventListener('click', wizClose);
    el('wizard-backdrop')?.addEventListener('click', wizClose);

    el('wiz-next')?.addEventListener('click', () => {
        const err = wizValidateStep(wizStep);
        if (err) { wizSetError(err); return; }
        wizSetError(null);
        wizStep = Math.min(wizStep + 1, TOTAL_STEPS);
        wizShowStep(wizStep);
    });

    el('wiz-prev')?.addEventListener('click', () => {
        wizSetError(null);
        wizStep = Math.max(wizStep - 1, 1);
        wizShowStep(wizStep);
    });

    el('wiz-submit')?.addEventListener('click', wizSubmit);

    el('btn-add-keyword')?.addEventListener('click', () => {
        wizKeywords.push({ keyword: '', match_type: 'broad' });
        wizRenderKeywords();
    });

    document.querySelectorAll('input[name="wiz-type"]').forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelectorAll('input[name="wiz-type"]').forEach(r => {
                const lbl = r.closest('label');
                if (!lbl) return;
                if (r.checked) {
                    lbl.classList.add('border-primary-500', 'bg-primary-50');
                    lbl.classList.remove('border-gray-200');
                    lbl.querySelector('div > div:first-child')?.classList.replace('text-gray-700', 'text-primary-700');
                } else {
                    lbl.classList.remove('border-primary-500', 'bg-primary-50');
                    lbl.classList.add('border-gray-200');
                    lbl.querySelector('div > div:first-child')?.classList.replace('text-primary-700', 'text-gray-700');
                }
            });
        });
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// Show page — performance chart & quality score
// ─────────────────────────────────────────────────────────────────────────────

let perfChart = null;

window.initShowPage = function () {
    loadQualityScore();
    loadPerformance();
    el('apply-dates')?.addEventListener('click', loadPerformance);
};

async function loadQualityScore() {
    const section = el('quality-score-section');
    if (!section) return;

    try {
        const data = await apiFetch(cfg().qualityScoreUrl);
        const qs   = data.data;

        if (!qs || qs.has_data === false) {
            section.innerHTML = `
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-base font-semibold text-gray-800">جودة الإعلان</h2>
                </div>
                <div class="p-8 text-center text-sm text-gray-400">
                    لا توجد بيانات كافية لحساب الجودة بعد
                </div>`;
            return;
        }

        const overall = qs.score ?? qs.overall ?? 0;
        const overallEl = el('qs-overall');
        if (overallEl) overallEl.textContent = Number(overall).toFixed(1);

        [
            { key: 'ctr',       id: 'qs-ctr' },
            { key: 'relevance', id: 'qs-relevance' },
            { key: 'landing',   id: 'qs-landing' },
            { key: 'vendor',    id: 'qs-vendor' },
        ].forEach(({ key, id }) => {
            const val   = qs[key] ?? qs[`${key}_score`] ?? 0;
            const barEl = el(`${id}-bar`);
            const valEl = el(`${id}-val`);
            if (barEl) barEl.style.width = (val * 10) + '%';
            if (valEl) valEl.textContent  = Number(val).toFixed(1);
        });
    } catch {}
}

async function loadPerformance() {
    const canvas = el('perf-chart');
    if (!canvas || typeof Chart === 'undefined') return;

    const from = el('perf-from')?.value || '';
    const to   = el('perf-to')?.value   || '';

    const params = new URLSearchParams();
    if (from) params.set('from', from);
    if (to)   params.set('to', to);

    try {
        const data = await apiFetch(`${cfg().performanceUrl}?${params}`);
        const rows = data.data?.rows || [];

        const labels      = rows.map(r => r.date);
        const impressions = rows.map(r => r.impressions);
        const clicks      = rows.map(r => r.clicks);

        if (perfChart) perfChart.destroy();

        perfChart = new Chart(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'مشاهدات',
                        data: impressions,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,0.08)',
                        tension: 0.3,
                        yAxisID: 'y',
                    },
                    {
                        label: 'نقرات',
                        data: clicks,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16,185,129,0.08)',
                        tension: 0.3,
                        yAxisID: 'y2',
                    },
                ],
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'top' } },
                scales: {
                    y:  { position: 'right', title: { display: true, text: 'مشاهدات' } },
                    y2: { position: 'left',  title: { display: true, text: 'نقرات' }, grid: { drawOnChartArea: false } },
                },
            },
        });

        const totals = data.data?.totals;
        if (totals) {
            const set = (id, val) => { const e = el(id); if (e) e.textContent = val; };
            set('total-impressions', formatNumber(totals.impressions));
            set('total-clicks',      formatNumber(totals.clicks));
            set('total-conversions', formatNumber(totals.conversions));
            set('total-spend',       formatMoney((totals.spend ?? 0) / 100));
            set('total-revenue',     formatMoney((totals.revenue_attributed ?? 0) / 100));
        }
    } catch {}
}

// ─────────────────────────────────────────────────────────────────────────────
// Pause / Resume (called via onclick from show.blade.php)
// ─────────────────────────────────────────────────────────────────────────────

window.pauseCampaign = async function (url) {
    const ok = window.Swal
        ? await window.Swal.fire({
            title: 'إيقاف مؤقت؟',
            text:  'هل تريد إيقاف الحملة مؤقتاً؟',
            icon:  'warning',
            showCancelButton:  true,
            confirmButtonText: 'إيقاف',
            cancelButtonText:  'إلغاء',
          }).then(r => r.isConfirmed)
        : confirm(window.PARTNER_TRANSLATIONS?.confirmPauseCampaign ?? 'Pause this campaign?');

    if (!ok) return;
    try {
        await apiFetch(url, { method: 'POST' });
        location.reload();
    } catch (e) {
        toast(e.message || 'تعذّر إيقاف الحملة.', 'error');
    }
};

window.resumeCampaign = async function (url) {
    const ok = window.Swal
        ? await window.Swal.fire({
            title: 'استئناف الحملة؟',
            text:  'هل تريد استئناف تشغيل الحملة؟',
            icon:  'question',
            showCancelButton:  true,
            confirmButtonText: 'استئناف',
            cancelButtonText:  'إلغاء',
          }).then(r => r.isConfirmed)
        : confirm(window.PARTNER_TRANSLATIONS?.confirmResumeCampaign ?? 'Resume this campaign?');

    if (!ok) return;
    try {
        await apiFetch(url, { method: 'POST' });
        location.reload();
    } catch (e) {
        toast(e.message || 'تعذّر استئناف الحملة.', 'error');
    }
};

// ─────────────────────────────────────────────────────────────────────────────
// Boot
// ─────────────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    if (el('ads-table')) initIndexPage();
    // show page is booted by the inline DOMContentLoaded in show.blade.php via window.initShowPage()
});
