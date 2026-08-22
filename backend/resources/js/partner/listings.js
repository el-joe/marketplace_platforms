/**
 * partner/listings.js
 * Product Listings module: DataTable, product search, price edit, status toggle, stock adjust
 */

import './app.js';
import { createPartnerTable, csrfToken, postJson, showModal, hideModal, showError, hideError, toast } from './datatable.js';

// ─────────────────────────────────────────────────────────────────────────────
// Status badge HTML (for DataTable column rendering)
// ─────────────────────────────────────────────────────────────────────────────

function listingStatusBadge(status) {
    const map = {
        active: ['bg-green-100 text-green-700', 'نشط'],
        paused: ['bg-gray-100 text-gray-600', 'موقوف'],
        pending_review: ['bg-yellow-100 text-yellow-700', 'قيد المراجعة'],
        draft: ['bg-gray-100 text-gray-500', 'مسودة'],
        rejected: ['bg-red-100 text-red-700', 'مرفوض'],
        out_of_stock: ['bg-red-50 text-red-500', 'نفد المخزون'],
        archived: ['bg-gray-100 text-gray-400', 'مؤرشف'],
    };
    const [cls, label] = map[status] ?? ['bg-gray-100 text-gray-600', status];
    return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${cls}">${label}</span>`;
}

// ─────────────────────────────────────────────────────────────────────────────
// Listings DataTable (index page)
// ─────────────────────────────────────────────────────────────────────────────

let listingsTable = null;
let selectedIds = new Set();

function initListingsDataTable() {
    const tableEl = document.getElementById('listings-table');
    const cfg = window.LISTINGS;
    if (!tableEl || !cfg) return;

    listingsTable = createPartnerTable('listings-table', {
        url: cfg.datatableUrl,
        ajaxData: (d) => {
            d.status = cfg.statusFilter !== 'all' ? cfg.statusFilter : '';
            const searchEl = document.getElementById('listing-search');
            d.search_term = searchEl ? searchEl.value : '';
        },
        searchInputId: 'listing-search',
        order: [[7, 'desc']],
        language: {
            emptyTable:  '<div class="py-16 text-center"><div class="text-4xl mb-3">📦</div><p class="text-gray-500 font-medium">لا توجد قوائم</p><p class="text-gray-400 text-xs mt-1">لم يتم إنشاء أي قوائم منتجات بعد</p></div>',
            zeroRecords: '<div class="py-16 text-center"><div class="text-4xl mb-3">🔍</div><p class="text-gray-500 font-medium">لا توجد نتائج مطابقة</p></div>',
            processing:  '<div class="flex justify-center py-12"><div class="w-7 h-7 border-2 border-yellow-400 border-t-transparent rounded-full animate-spin"></div></div>',
        },
        columns: [
            {
                data: null,
                orderable: false,
                className: 'px-4 py-3',
                render: (data, type, row) =>
                    `<input type="checkbox" class="row-select rounded border-gray-300" data-id="${row.id}" data-status="${row.status}">`,
            },
            {
                data: 'name_ar',
                className: 'px-4 py-3',
                render: (data, type, row) => {
                    const img = row.image_url
                        ? `<img src="${row.image_url}" class="w-10 h-10 rounded-xl object-cover shrink-0 border border-gray-100">`
                        : `<div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center shrink-0"><span class="text-gray-400 text-sm">📦</span></div>`;
                    return `<a href="${row.show_url}" class="flex items-center gap-3 group">
                        ${img}
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-800 text-sm group-hover:text-yellow-600 leading-tight truncate max-w-[180px]">${data || row.name_en}</p>
                            ${row.name_ar && row.name_en ? `<p class="text-xs text-gray-400 truncate max-w-[180px] mt-0.5">${row.name_en}</p>` : ''}
                        </div>
                    </a>`;
                },
            },
            {
                data: 'variant_name',
                className: 'px-4 py-3',
                render: (data, type, row) =>
                    `<div><p class="text-sm text-gray-700 font-medium">${data}</p><p class="font-mono text-xs text-gray-400 mt-0.5">${row.sku}</p></div>`,
            },
            {
                data: 'status',
                className: 'px-4 py-3',
                render: (data) => listingStatusBadge(data),
            },
            {
                data: 'price',
                className: 'px-4 py-3',
                render: (data, type, row) =>
                    `<div class="flex items-center gap-2">
                        <span class="font-bold text-gray-900 text-sm" id="price-display-${row.id}">${data}</span>
                        <button class="btn-edit-price w-6 h-6 flex items-center justify-center rounded-lg text-gray-300 hover:text-yellow-500 hover:bg-yellow-50 transition-colors"
                                data-listing-id="${row.id}" data-price="${row.price_raw / 100}" title="تعديل السعر">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        </button>
                    </div>`,
            },
            {
                data: 'available_stock',
                className: 'px-4 py-3 text-center',
                render: (data) => {
                    const n = parseInt(data, 10);
                    const cls = n <= 0 ? 'text-red-600 font-semibold' : n <= 5 ? 'text-orange-500 font-semibold' : 'text-gray-700';
                    return `<span class="text-sm ${cls}">${data}</span>`;
                },
            },
            {
                data: 'total_sold',
                className: 'px-4 py-3 text-center text-sm text-gray-600',
            },
            {
                data: 'rating_avg',
                className: 'px-4 py-3 text-center text-sm text-gray-600',
                render: (data) => data !== '—'
                    ? `<span class="inline-flex items-center gap-1">${data}<span class="text-yellow-400">★</span></span>`
                    : '<span class="text-gray-300">—</span>',
            },
            {
                data: null,
                orderable: false,
                className: 'px-4 py-3 text-center',
                render: (data, type, row) =>
                    `<a href="${row.show_url}" class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-yellow-600 border border-gray-200 hover:border-yellow-300 rounded-lg px-2.5 py-1.5 transition-colors">تفاصيل</a>`,
            },
        ],
    });

    if (!listingsTable) return;

    // Row select
    tableEl.addEventListener('change', (e) => {
        const cb = e.target.closest('.row-select');
        if (!cb) return;
        const id = cb.dataset.id;
        if (cb.checked) selectedIds.add(id);
        else selectedIds.delete(id);
        updateBulkActions();
    });

    document.getElementById('select-all-listings')?.addEventListener('change', (e) => {
        document.querySelectorAll('.row-select').forEach(cb => {
            cb.checked = e.target.checked;
            const id = cb.dataset.id;
            if (e.target.checked) selectedIds.add(id);
            else selectedIds.delete(id);
        });
        updateBulkActions();
    });

    // Price edit from table
    tableEl.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-edit-price');
        if (!btn) return;
        const id = btn.dataset.listingId;
        const price = btn.dataset.price;
        document.getElementById('price-listing-id').value = id;
        document.getElementById('price-input').value = price;
        hideError('price-error');
        showModal('price-modal');
    });

}

function updateBulkActions() {
    const container = document.getElementById('bulk-actions');
    const countEl = document.getElementById('bulk-count');
    if (!container) return;
    const count = selectedIds.size;
    if (count > 0) {
        container.classList.remove('hidden');
        if (countEl) countEl.textContent = `${count} محدد`;
    } else {
        container.classList.add('hidden');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Inline Price Edit Modal (index page)
// ─────────────────────────────────────────────────────────────────────────────

function initPriceModal() {
    const modal = document.getElementById('price-modal');
    const form = document.getElementById('price-form');
    if (!modal || !form) return;

    document.getElementById('price-modal-close')?.addEventListener('click', () => hideModal('price-modal'));
    document.getElementById('price-cancel-btn')?.addEventListener('click', () => hideModal('price-modal'));
    modal.addEventListener('click', (e) => { if (e.target === modal) hideModal('price-modal'); });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideError('price-error');
        const listingId = document.getElementById('price-listing-id').value;
        const price = document.getElementById('price-input').value;
        const submitBtn = form.querySelector('[type=submit]');
        submitBtn.disabled = true;

        const url = window.location.origin + '/listings/' + listingId + '/update-price';
        const { ok, data } = await postJson(url, { price });
        if (ok) {
            hideModal('price-modal');
            toast(data.message ?? 'تم تحديث السعر.');
            // Update the displayed price in the table row
            const displayEl = document.getElementById(`price-display-${listingId}`);
            if (displayEl) displayEl.textContent = data.price_formatted;
            listingsTable?.ajax.reload(null, false);
        } else {
            showError('price-error', data.message ?? 'حدث خطأ.');
        }
        submitBtn.disabled = false;
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// Product Search  (create page)
// ─────────────────────────────────────────────────────────────────────────────

function initProductSearch() {
    const searchInput = document.getElementById('product-search-input');
    const resultsDiv = document.getElementById('product-search-results');
    const cfg = window.LISTINGS_CREATE;
    if (!searchInput || !resultsDiv || !cfg) return;

    let debounceTimer = null;

    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(async () => {
            const q = searchInput.value.trim();
            if (q.length < 2) {
                resultsDiv.innerHTML = '<p class="text-xs text-gray-400 text-center py-6">ابدأ الكتابة للبحث...</p>';
                return;
            }

            resultsDiv.innerHTML = '<p class="text-xs text-gray-400 text-center py-4 animate-pulse">جاري البحث...</p>';

            const res = await fetch(`${cfg.productSearchUrl}?q=${encodeURIComponent(q)}`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            });
            const data = await res.json();

            if (!data.length) {
                resultsDiv.innerHTML = '<p class="text-xs text-gray-400 text-center py-6">لا توجد نتائج مطابقة.</p>';
                return;
            }

            resultsDiv.innerHTML = data.map(product => {
                const img = product.image_url
                    ? `<img src="${product.image_url}" class="w-10 h-10 rounded-lg object-cover shrink-0">`
                    : `<div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center shrink-0 text-gray-400 text-xs">📦</div>`;

                if (!product.has_variants) {
                    // Single-variant product: the default variant is the only option.
                    // Make the whole card clickable — no variant chooser needed.
                    const v = product.variants[0];
                    const disabled = v?.already_listed;
                    const skuLabel = v ? `<span class="font-mono text-xs text-gray-400 mt-1 block">SKU: ${escapeHtml(v.sku)}</span>` : '';
                    const alreadyBadge = disabled ? `<span class="text-xs text-green-600 mt-1 block">✓ مُدرج بالفعل</span>` : '';
                    return `<div class="simple-product-card flex items-start gap-3 p-3 rounded-xl border border-transparent transition-colors ${disabled ? 'opacity-50 cursor-not-allowed' : 'hover:border-yellow-300 hover:bg-yellow-50 cursor-pointer'}"
                        data-product-id="${product.id}"
                        data-product-name="${escapeHtml(product.name)}"
                        data-variant-id="${v?.id || ''}"
                        data-variant-name=""
                        data-sku="${escapeHtml(v?.sku || '')}"
                        data-image="${product.image_url || ''}"
                        data-has-variants="0"
                        ${disabled ? 'aria-disabled="true"' : ''}>
                        ${img}
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 leading-tight">${escapeHtml(product.name)}</p>
                            ${product.model ? `<p class="text-xs text-gray-400">${escapeHtml(product.model)}</p>` : ''}
                            ${skuLabel}
                            ${alreadyBadge}
                        </div>
                    </div>`;
                }

                // Multi-variant product: show individual variant buttons.
                const variants = product.variants.map(v => {
                    const disabled = v.already_listed;
                    return `<button type="button"
                        class="variant-select-btn text-xs px-2 py-1 rounded-lg border transition-colors ${disabled ? 'border-gray-100 text-gray-300 cursor-not-allowed' : 'border-gray-200 text-gray-600 hover:border-yellow-400 hover:text-yellow-600'}"
                        data-product-id="${product.id}"
                        data-product-name="${escapeHtml(product.name)}"
                        data-variant-id="${v.id}"
                        data-variant-name="${escapeHtml(v.variant_name)}"
                        data-sku="${escapeHtml(v.sku)}"
                        data-image="${product.image_url || ''}"
                        data-has-variants="1"
                        ${disabled ? 'disabled title="قائمة موجودة بالفعل"' : ''}>
                        ${escapeHtml(v.variant_name)}${disabled ? ' ✓' : ''}
                    </button>`;
                }).join('');

                return `<div class="flex items-start gap-3 p-3 rounded-xl border border-transparent hover:border-gray-100 hover:bg-gray-50 transition-colors">
                    ${img}
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 leading-tight">${escapeHtml(product.name)}</p>
                        ${product.model ? `<p class="text-xs text-gray-400">${escapeHtml(product.model)}</p>` : ''}
                        <div class="flex flex-wrap gap-1.5 mt-2">${variants}</div>
                    </div>
                </div>`;
            }).join('');

            // Bind simple (no-variant) product card clicks
            resultsDiv.querySelectorAll('.simple-product-card:not([aria-disabled="true"])').forEach(card => {
                card.addEventListener('click', () => selectVariant(card.dataset));
            });

            // Bind variant select buttons (multi-variant products)
            resultsDiv.querySelectorAll('.variant-select-btn:not([disabled])').forEach(btn => {
                btn.addEventListener('click', () => selectVariant(btn.dataset));
            });
        }, 350);
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function selectVariant(data) {
    const hasVariants = data.hasVariants === '1' || data.hasVariants === true;

    // Store resolution data on the hidden input so submit can read it
    const hiddenInput = document.getElementById('form-product-variant-id');
    hiddenInput.value = data.variantId;
    hiddenInput.dataset.productId = data.productId;
    hiddenInput.dataset.hasVariants = hasVariants ? '1' : '0';

    // Update selected product display
    const imgEl = document.getElementById('selected-img');
    if (imgEl) {
        imgEl.innerHTML = data.image
            ? `<img src="${data.image}" class="w-full h-full object-cover">`
            : `<div class="w-full h-full flex items-center justify-center text-gray-300 text-lg">📦</div>`;
    }
    const nameEl = document.getElementById('selected-product-name');
    const variantEl = document.getElementById('selected-variant-name');
    const skuEl = document.getElementById('selected-sku');
    if (nameEl) nameEl.textContent = data.productName;
    // For no-variation products there is nothing meaningful to show in the variant line
    if (variantEl) {
        variantEl.textContent = hasVariants ? data.variantName : '';
        variantEl.style.display = hasVariants ? '' : 'none';
    }
    if (skuEl) skuEl.textContent = data.sku ? `SKU: ${data.sku}` : '';

    fetchCustomerUrlPreview(data.productId, data.variantId);

    document.dispatchEvent(new CustomEvent('listing:product-selected', {
        detail: { productId: data.productId },
    }));

    // Show form, hide placeholder
    document.getElementById('listing-form-placeholder')?.classList.add('hidden');
    document.getElementById('listing-form-container')?.classList.remove('hidden');

    // New listings default to global_system_type = merchant_fbp (FBP)
    document.getElementById('vendor-covers-delivery-field')?.classList.remove('hidden');

    const fulfillmentSelect = document.querySelector('select[name="fulfillment_model"]');
    loadShippingMethods(data.variantId, fulfillmentSelect?.value);
}

function fetchCustomerUrlPreview(productId, variantId) {
    const wrap = document.getElementById('customer-url-preview-wrap');
    const input = document.getElementById('customer-url-preview');
    const summaryEl = document.getElementById('customer-url-attribute-summary');
    const template = window.LISTINGS_CREATE?.urlInfoUrlTemplate;
    if (!wrap || !input || !template || !variantId) return;

    const url = template.replace('__VARIANT__', variantId);

    fetch(url, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
        .then((res) => res.json())
        .then((data) => {
            input.value = data.preview_url ?? '';
            if (summaryEl) summaryEl.textContent = data.attribute_summary || '—';
            wrap.classList.remove('hidden');
        })
        .catch(() => {});
}

async function loadShippingMethods(variantId, fulfillmentModel) {
    const cfg = window.LISTINGS_CREATE;
    const select = document.getElementById('primary-shipping-method-select');
    if (!select || !cfg || !variantId) return;

    select.disabled = true;
    select.innerHTML = '<option value="">جاري التحميل...</option>';

    try {
        const params = new URLSearchParams({ variant_id: variantId });
        if (fulfillmentModel) params.set('fulfillment_model', fulfillmentModel);

        const res = await fetch(`${cfg.availableShippingMethodsUrl}?${params.toString()}`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        });
        const methods = await res.json();

        const defaultOption = '<option value="">Use category default</option>';
        if (!methods.length) {
            select.innerHTML = defaultOption;
        } else {
            select.innerHTML = defaultOption + methods.map(m =>
                `<option value="${m.id}">${escapeHtml(m.name)}${m.is_default ? ' (default)' : ''}</option>`
            ).join('');
        }
    } catch {
        select.innerHTML = '<option value="">خطأ في تحميل طرق الشحن</option>';
    }
    select.disabled = false;
}

function initChangeProduct() {
    document.getElementById('change-product-btn')?.addEventListener('click', () => {
        document.getElementById('listing-form-placeholder')?.classList.remove('hidden');
        document.getElementById('listing-form-container')?.classList.add('hidden');
        document.getElementById('form-product-variant-id').value = '';
        document.getElementById('customer-url-preview-wrap')?.classList.add('hidden');
        document.getElementById('product-search-input').value = '';
        document.getElementById('product-search-results').innerHTML =
            '<p class="text-xs text-gray-400 text-center py-6">ابدأ الكتابة للبحث...</p>';
        document.getElementById('product-search-input').focus();
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// Create Form Submit (create page)
// ─────────────────────────────────────────────────────────────────────────────

async function loadWarehousesByCountry(countryId, fulfillmentModel) {
    const cfg = window.LISTINGS_CREATE;
    const select = document.querySelector('select[name="warehouse_id"]');
    if (!select || !cfg) return;

    select.disabled = true;
    select.innerHTML = '<option value="">جاري التحميل...</option>';

    if (!countryId) {
        select.innerHTML = '<option value="">اختر المستودع...</option>';
        select.disabled = false;
        return;
    }

    try {
        const params = new URLSearchParams({ country_id: countryId });
        if (fulfillmentModel) params.set('fulfillment_model', fulfillmentModel);

        const res = await fetch(`${cfg.warehousesByCountryUrl}?${params.toString()}`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        });
        const warehouses = await res.json();
        if (!warehouses.length) {
            select.innerHTML = '<option value="">لا توجد مستودعات مطابقة</option>';
        } else {
            const typeLabel = (t) => t === 'seller_owned' ? 'مستودع خاص' : t === 'platform_fbn' ? 'مستودع المنصة' : t === 'third_party' ? 'مستودع طرف ثالث' : t;
            select.innerHTML = '<option value="">اختر المستودع...</option>' +
                warehouses.map(w =>
                    `<option value="${w.id}">${escapeHtml(w.name)}${w.code ? ` (${escapeHtml(w.code)})` : ''} — ${typeLabel(w.type)}</option>`
                ).join('');
        }
    } catch {
        select.innerHTML = '<option value="">خطأ في تحميل المستودعات</option>';
    }
    select.disabled = false;
}

function initCreateForm() {
    const form = document.getElementById('listing-create-form');
    const cfg = window.LISTINGS_CREATE;
    if (!form || !cfg) return;

    const countryInput = form.querySelector('input[name="country_id"]');
    const fulfillmentSelect = form.querySelector('select[name="fulfillment_model"]');

    const reloadWarehouses = () => {
        loadWarehousesByCountry(countryInput?.value, fulfillmentSelect?.value);
    };

    // Reload warehouses when fulfillment model changes
    if (fulfillmentSelect) fulfillmentSelect.addEventListener('change', reloadWarehouses);

    // Trigger immediately to populate for the pre-selected vendor country
    if (countryInput?.value) reloadWarehouses();

    // Reload available shipping methods when fulfillment model changes (variant already selected)
    fulfillmentSelect?.addEventListener('change', () => {
        const variantId = document.getElementById('form-product-variant-id')?.value;
        if (variantId) loadShippingMethods(variantId, fulfillmentSelect.value);
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideError('create-error');

        const hiddenInput = document.getElementById('form-product-variant-id');
        const variantId = hiddenInput.value;
        const productId = hiddenInput.dataset.productId;
        const hasVariants = hiddenInput.dataset.hasVariants === '1';

        if (!variantId && !productId) {
            showError('create-error', 'يرجى اختيار منتج أولاً.');
            return;
        }

        const formData = new FormData(form);
        const payload = {};
        for (const [key, value] of formData.entries()) {
            if (key.endsWith('[]')) {
                const cleanKey = key.slice(0, -2);
                (payload[cleanKey] ??= []).push(value);
            } else {
                payload[key] = value;
            }
        }

        if (hasVariants) {
            // Multi-variant: send the explicitly chosen variant id
            payload.product_variant_id = variantId;
        } else {
            // No-variation: let the backend resolve the default variant from product_id
            payload.product_id = productId;
            delete payload.product_variant_id;
        }

        const submitBtn = document.getElementById('create-submit-btn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'جاري الإنشاء...';

        const res = await fetch(cfg.storeUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });
        const data = await res.json();

        if (res.ok && data.success) {
            toast(data.message ?? 'تم إنشاء القائمة بنجاح.');
            setTimeout(() => { window.location.href = data.redirect; }, 1000);
        } else {
            showError('create-error', data.message ?? 'حدث خطأ. يرجى التحقق من البيانات.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'إنشاء القائمة';
        }
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// Listing Detail Page — Price + Status + Adjust Stock
// ─────────────────────────────────────────────────────────────────────────────

function initDetailPage() {
    const cfg = window.LISTING_DETAIL;
    if (!cfg) return;

    // ── Update Price Modal ────────────────────────────────────────────────────
    document.getElementById('btn-update-price')?.addEventListener('click', () => {
        hideError('price-update-error');
        showModal('update-price-modal');
    });
    document.getElementById('price-modal-close-detail')?.addEventListener('click', () => hideModal('update-price-modal'));
    document.getElementById('price-close-btn')?.addEventListener('click', () => hideModal('update-price-modal'));

    document.getElementById('price-update-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideError('price-update-error');
        const price = document.getElementById('new-price-input').value;
        const submitBtn = e.target.querySelector('[type=submit]');
        submitBtn.disabled = true;

        const { ok, data } = await postJson(cfg.updatePriceUrl, { price });
        if (ok) {
            hideModal('update-price-modal');
            toast(data.message ?? 'تم تحديث السعر.');
            const displayEl = document.getElementById('display-price');
            if (displayEl) displayEl.textContent = data.price_formatted;
        } else {
            showError('price-update-error', data.message ?? 'حدث خطأ.');
        }
        submitBtn.disabled = false;
    });

    // ── Update Shipping Method Modal ──────────────────────────────────────────
    document.getElementById('btn-update-shipping')?.addEventListener('click', () => {
        hideError('shipping-update-error');
        showModal('update-shipping-modal');
    });
    document.getElementById('shipping-modal-close')?.addEventListener('click', () => hideModal('update-shipping-modal'));
    document.getElementById('shipping-close-btn')?.addEventListener('click', () => hideModal('update-shipping-modal'));

    document.getElementById('shipping-update-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideError('shipping-update-error');
        const select = document.getElementById('shipping-method-select');
        const primary_shipping_method_id = select?.value;
        const submitBtn = e.target.querySelector('[type=submit]');
        submitBtn.disabled = true;

        const { ok, data } = await postJson(cfg.updateShippingUrl, { primary_shipping_method_id });
        if (ok) {
            hideModal('update-shipping-modal');
            toast(data.message ?? 'تم تحديث طريقة الشحن.');
            const displayEl = document.getElementById('display-shipping-method');
            if (displayEl) displayEl.textContent = data.shipping_method_name;
        } else {
            showError('shipping-update-error', data.message ?? 'حدث خطأ.');
        }
        submitBtn.disabled = false;
    });

    // ── Toggle Status ─────────────────────────────────────────────────────────
    document.getElementById('btn-toggle-status')?.addEventListener('click', async () => {
        const btn = document.getElementById('btn-toggle-status');
        btn.disabled = true;
        const { ok, data } = await postJson(cfg.toggleStatusUrl, {});
        if (ok) {
            toast(data.message ?? 'تم تغيير الحالة.');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            toast(data.message ?? 'حدث خطأ.', 'error');
            btn.disabled = false;
        }
    });

    // ── Vendor Covers Delivery Toggle ────────────────────────────────────────
    document.getElementById('vendor-covers-delivery-toggle')?.addEventListener('change', async (e) => {
        const checkbox = e.target;
        checkbox.disabled = true;
        const { ok, data } = await postJson(cfg.toggleCoversDeliveryUrl, { vendor_covers_delivery: checkbox.checked });
        if (ok) {
            toast(data.message ?? 'تم التحديث.');
        } else {
            checkbox.checked = !checkbox.checked;
            toast(data.message ?? 'حدث خطأ.', 'error');
        }
        checkbox.disabled = false;
    });

    // ── Adjust Stock Buttons ──────────────────────────────────────────────────
    document.querySelectorAll('.btn-adjust-stock').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('adjust-inv-id').value = btn.dataset.invId;
            document.getElementById('adjust-warehouse-name').textContent = btn.dataset.warehouse;
            document.getElementById('adjust-current-qty').textContent = btn.dataset.onHand;
            document.getElementById('adjust-form')?.reset();
            document.getElementById('adjust-inv-id').value = btn.dataset.invId;
            hideError('adjust-error');
            showModal('adjust-stock-modal');
        });
    });

    document.getElementById('adjust-modal-close')?.addEventListener('click', () => hideModal('adjust-stock-modal'));
    document.getElementById('adjust-cancel-btn')?.addEventListener('click', () => hideModal('adjust-stock-modal'));

    // ── Update Shipping & Dimensions Modal ────────────────────────────────────
    document.getElementById('btn-update-dimensions')?.addEventListener('click', () => {
        hideError('dimensions-update-error');
        showModal('update-dimensions-modal');
    });
    document.getElementById('dimensions-modal-close')?.addEventListener('click', () => hideModal('update-dimensions-modal'));
    document.getElementById('dimensions-close-btn')?.addEventListener('click', () => hideModal('update-dimensions-modal'));

    document.getElementById('dimensions-update-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideError('dimensions-update-error');
        const formData = new FormData(e.target);
        const payload = Object.fromEntries(formData.entries());
        const submitBtn = e.target.querySelector('[type=submit]');
        submitBtn.disabled = true;

        const { ok, data } = await postJson(cfg.updateDimensionsUrl, payload);
        if (ok) {
            hideModal('update-dimensions-modal');
            toast(data.message ?? 'تم التحديث.');
            const weightEl = document.getElementById('display-declared-weight');
            const dimsEl = document.getElementById('display-declared-dimensions');
            const classEl = document.getElementById('display-weight-class');
            const handlingEl = document.getElementById('display-handling-class');
            const classLabels = { light: 'خفيف / Light', medium: 'متوسط / Medium', heavy: 'ثقيل / Heavy' };
            const handlingLabels = { standard: 'عادي / Standard', refrigerated: 'يحتاج تبريد / Refrigerated', fragile: 'هش / Fragile', special_tech: 'تقنية خاصة / Special Tech' };
            if (weightEl) weightEl.textContent = `${payload.declared_weight_grams} g`;
            if (dimsEl) dimsEl.textContent = `${payload.declared_length_cm || '—'} × ${payload.declared_width_cm || '—'} × ${payload.declared_height_cm || '—'}`;
            if (classEl) classEl.textContent = classLabels[data.weight_class] ?? data.weight_class;
            if (handlingEl) handlingEl.textContent = handlingLabels[payload.handling_class] ?? payload.handling_class;
        } else {
            showError('dimensions-update-error', data.message ?? 'حدث خطأ.');
        }
        submitBtn.disabled = false;
    });

    // ── Delivery Cost Preview ─────────────────────────────────────────────────
    let shippingPreviewLoaded = false;
    window.loadShippingPreview = async () => {
        if (shippingPreviewLoaded) return;
        shippingPreviewLoaded = true;

        const loadingEl = document.getElementById('shipping-preview-loading');
        const emptyEl = document.getElementById('shipping-preview-empty');
        const wrapEl = document.getElementById('shipping-preview-table-wrap');
        const tbody = document.getElementById('shipping-preview-tbody');
        if (!loadingEl || !tbody) return;

        try {
            const res = await fetch(cfg.shippingPreviewUrl, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            });
            const rows = await res.json();
            loadingEl.classList.add('hidden');

            if (!rows.length) {
                emptyEl?.classList.remove('hidden');
                return;
            }

            wrapEl?.classList.remove('hidden');
            tbody.innerHTML = rows.map(row => {
                let display;
                if (row.customer_pays === 0 && row.vendor_contribution > 0) {
                    display = `<span class="text-green-600 font-semibold">FREE 🎉 (you cover: ${row.vendor_contribution} ${row.currency})</span>`;
                } else if (row.customer_pays === 0) {
                    display = `<span class="text-green-600 font-semibold">FREE 🎉 (platform covers)</span>`;
                } else {
                    display = `<span class="text-gray-600">Paid by customer</span>`;
                }
                return `<tr>
                    <td class="py-2.5 text-gray-800">${row.zone_name}</td>
                    <td class="py-2.5 text-gray-600">${row.method_name}</td>
                    <td class="py-2.5 text-center">${row.raw_fee} ${row.currency}</td>
                    <td class="py-2.5 text-center text-blue-600">${row.platform_subsidy} ${row.currency}</td>
                    <td class="py-2.5 text-center">${row.vendor_contribution} ${row.currency}</td>
                    <td class="py-2.5 text-center font-semibold">${row.customer_pays} ${row.currency}</td>
                    <td class="py-2.5 text-center">${display}</td>
                </tr>`;
            }).join('');
        } catch {
            loadingEl.classList.add('hidden');
            emptyEl?.classList.remove('hidden');
        }
    };

    document.getElementById('adjust-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideError('adjust-error');
        const formData = new FormData(e.target);
        const payload = Object.fromEntries(formData.entries());
        const submitBtn = e.target.querySelector('[type=submit]');
        submitBtn.disabled = true;

        const { ok, data } = await postJson(cfg.adjustStockUrl, payload);
        if (ok) {
            hideModal('adjust-stock-modal');
            toast(data.message ?? 'تم تعديل المخزون.');
            // Update on-hand and available displays in the table
            const invId = payload.warehouse_inventory_id;
            const onHandEl = document.getElementById(`onhand-${invId}`);
            const availEl = document.getElementById(`avail-${invId}`);
            if (onHandEl) onHandEl.textContent = data.new_quantity;
            if (availEl) availEl.textContent = data.new_quantity;
        } else {
            showError('adjust-error', data.message ?? 'حدث خطأ.');
        }
        submitBtn.disabled = false;
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// Boot
// ─────────────────────────────────────────────────────────────────────────────

function initCopyButtons() {
    document.querySelectorAll('.js-copy').forEach((btn) => {
        btn.addEventListener('click', () => {
            const value = btn.dataset.value;
            if (!value) return;
            window.copyToClipboard(value, 'تم النسخ!', 'فشل النسخ.');
            const original = btn.textContent;
            btn.textContent = 'تم النسخ!';
            setTimeout(() => { btn.textContent = original; }, 1500);
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initListingsDataTable();
    initPriceModal();
    initProductSearch();
    initChangeProduct();
    initCreateForm();
    initDetailPage();
    initCopyButtons();
});
