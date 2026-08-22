/**
 * Flash Sale Create / Edit form JS.
 *
 * Handles:
 *  - AJAX form submission (POST store / PUT update)
 *  - Live preview card (name, timeline)
 *  - Timeline visual bar (shows relative placement of each milestone)
 *  - Flatpickr date-picker wiring (chronological min-date constraints)
 *  - Discount percent live feedback
 */

import flatpickr from 'flatpickr';

// ─── Constants ────────────────────────────────────────────────────────────────

const TIMELINE_FIELDS = () => [
    { name: 'submission_opens_at', label: t('admin.flash_sale_form.sub_opens_label'), color: '#3B82F6' },
    { name: 'submission_closes_at', label: t('admin.flash_sale_form.sub_closes_label'), color: '#F59E0B' },
    { name: 'review_deadline_at', label: t('admin.flash_sale_form.review_label'), color: '#8B5CF6' },
    { name: 'sale_starts_at', label: t('admin.flash_sale_form.sale_start_label'), color: '#10B981' },
    { name: 'sale_ends_at', label: t('admin.flash_sale_form.sale_end_label'), color: '#EF4444' },
];

// ─── Bootstrap ────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initDatePickers();
    initTimelineVisual();
    initFormSubmit();
    initPreviewUpdates();
    initTransitions();
    initCancelModal();
});

// ─── Date pickers ─────────────────────────────────────────────────────────────

function initDatePickers() {
    const commonOpts = {
        enableTime: true,
        dateFormat: 'Y-m-d H:i:S',
        time_24hr: true,
        allowInput: true,
        onChange: () => {
            updateChronologicalConstraints();
            renderTimelineVisual();
        },
    };

    TIMELINE_FIELDS().forEach(({ name }) => {
        const el = document.querySelector(`[name="${name}"]`);
        if (el && !el._flatpickr) {
            flatpickr(el, { ...commonOpts });
        }
    });
}

function updateChronologicalConstraints() {
    const vals = {};
    TIMELINE_FIELDS().forEach(({ name }) => {
        const el = document.querySelector(`[name="${name}"]`);
        vals[name] = el?._flatpickr?.selectedDates[0] ?? null;
    });

    const pairs = [
        ['submission_closes_at', 'submission_opens_at'],
        ['review_deadline_at', 'submission_closes_at'],
        ['sale_starts_at', 'review_deadline_at'],
        ['sale_ends_at', 'sale_starts_at'],
    ];

    pairs.forEach(([field, minField]) => {
        const fp = document.querySelector(`[name="${field}"]`)?._flatpickr;
        if (fp && vals[minField]) {
            fp.set('minDate', vals[minField]);
        }
    });
}

// ─── Timeline visual ──────────────────────────────────────────────────────────

function renderTimelineVisual() {
    const container = document.getElementById('timeline-visual');
    if (!container) return;

    const dates = TIMELINE_FIELDS().map(({ name, label, color }) => {
        const el = document.querySelector(`[name="${name}"]`);
        const fp = el?._flatpickr;
        const ts = fp?.selectedDates[0]?.getTime() ?? null;
        return { name, label, color, ts };
    }).filter(d => d.ts !== null);

    if (dates.length < 2) {
        container.innerHTML = `<p class="text-xs text-gray-400 mt-2">${t('admin.flash_sale_form.fill_dates_hint')}</p>`;
        return;
    }

    const min = Math.min(...dates.map(d => d.ts));
    const max = Math.max(...dates.map(d => d.ts));
    const range = max - min || 1;

    container.innerHTML = `
        <div class="relative mt-4 h-10">
            <div class="absolute top-5 left-0 right-0 h-1 bg-gray-200 rounded-full"></div>
            ${dates.map(({ label, color, ts }) => {
        const pct = ((ts - min) / range) * 100;
        return `
                    <div class="absolute flex flex-col items-center" style="left:${pct}%; transform:translateX(-50%)">
                        <div class="w-3 h-3 rounded-full border-2 border-white shadow" style="background:${color}; position:absolute; top:1rem; margin-top:-0.375rem;"></div>
                        <span class="text-xs text-gray-500 whitespace-nowrap" style="position:absolute; top:2.25rem;">${label}</span>
                    </div>`;
    }).join('')}
        </div>
        <div class="mt-12 text-xs text-gray-400 flex justify-between">
            <span>${new Date(min).toLocaleDateString()}</span>
            <span>${new Date(max).toLocaleDateString()}</span>
        </div>
    `;
}

function initTimelineVisual() {
    renderTimelineVisual();
}

// ─── Preview card ─────────────────────────────────────────────────────────────

function initPreviewUpdates() {
    const nameEn = document.querySelector('[name="name_en"]');
    const previewName = document.getElementById('preview-sale-name');
    if (nameEn && previewName) {
        nameEn.addEventListener('input', () => {
            previewName.textContent = nameEn.value || t('admin.flash_sale_form.flash_sale_name_label');
        });
    }

    const discountEl = document.querySelector('[name="min_discount_pct"]');
    const discountPreview = document.getElementById('preview-discount');
    if (discountEl && discountPreview) {
        discountEl.addEventListener('input', () => {
            discountPreview.textContent = discountEl.value ? discountEl.value + '%+' : '—';
        });
    }
}

// ─── Form submit ──────────────────────────────────────────────────────────────

function initFormSubmit() {
    // Details tab form
    const form = document.getElementById('flash-sale-form');
    if (form) {
        form.addEventListener('submit', handleFormSubmit);
        document.getElementById('flash-sale-submit-btn')?.addEventListener('click', (e) => {
            e.preventDefault();
            form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
        });
    }

    // Rules tab form (Discount Requirements, Vendor Eligibility, Eligible Categories)
    const rulesForm = document.getElementById('flash-sale-form-rules');
    if (rulesForm) {
        rulesForm.addEventListener('submit', handleFormSubmit);
        document.getElementById('flash-sale-rules-submit-btn')?.addEventListener('click', (e) => {
            e.preventDefault();
            rulesForm.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
        });
    }
}

function handleFormSubmit(e) {
    e.preventDefault();
    const form = e.target;
    // Find the submit button belonging to this specific form
    const btn = form.querySelector('[type="submit"]');

    const url = window.FLASH_SALE_STORE_URL || window.FLASH_SALE_UPDATE_URL;
    const method = window.FLASH_SALE_UPDATE_URL ? 'PUT' : 'POST';

    if (!url) {
        console.error('[flash-sale-form] No submit URL set.');
        return;
    }

    // Collect all form data
    const data = {};
    new FormData(form).forEach((value, key) => {
        // Multi-select / checkbox arrays
        if (key.endsWith('[]')) {
            const k = key.slice(0, -2);
            if (!data[k]) data[k] = [];
            data[k].push(value);
        } else {
            data[key] = value;
        }
    });

    // Collect checkbox groups (eligible_categories[], eligible_seller_tiers[]).
    // When nothing is checked FormData omits the key entirely; we must still send
    // an empty marker so the backend knows to clear the field (jQuery drops empty
    // arrays during serialisation, so we use a single empty-string entry which
    // Laravel's "nullable|array" rule treats as null/empty after filtering).
    ['eligible_categories', 'eligible_seller_tiers'].forEach(fieldName => {
        if (!form.querySelector(`[name="${fieldName}[]"]`)) return; // field not in this form
        const checked = Array.from(form.querySelectorAll(`[name="${fieldName}[]"]:checked`)).map(c => c.value);
        data[fieldName] = checked.length ? checked : null;
    });

    // Toggle booleans
    ['is_featured', 'is_exclusive', 'price_drop_required'].forEach(name => {
        const el = form.querySelector(`[name="${name}"]`);
        if (el) data[name] = el.checked ? 1 : 0;
    });

    if (btn) {
        btn.disabled = true;
        btn.textContent = t('shared.saving');
    }

    $.ajax({
        url,
        method,
        data,
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success(res) {
            if (res.redirect) {
                window.location.href = res.redirect;
            } else {
                window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: res.message || t('shared.saved') } }));
                if (btn) { btn.disabled = false; btn.textContent = t('admin.flash_sale_form.save_changes_label'); }
            }
        },
        error(xhr) {
            const body = xhr.responseJSON;
            if (xhr.status === 422 && body?.errors) {
                displayValidationErrors(form, body.errors);
            } else {
                window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: body?.message || t('admin.flash_sale_form.error_occurred') } }));
            }
            if (btn) { btn.disabled = false; btn.textContent = btn.dataset.originalText || t('admin.flash_sale_form.save_label'); }
        },
    });
}

function displayValidationErrors(form, errors) {
    // Clear old errors
    form.querySelectorAll('.field-error').forEach(el => el.remove());
    form.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));

    Object.entries(errors).forEach(([field, messages]) => {
        const input = form.querySelector(`[name="${field}"], [name="${field}[]"]`);
        if (!input) return;

        input.classList.add('input-error');
        const err = document.createElement('p');
        err.className = 'field-error text-xs text-danger-600 mt-1';
        err.textContent = messages[0];
        input.parentNode.appendChild(err);
    });

    // Scroll to first error
    const first = form.querySelector('.input-error');
    first?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// ─── Status transitions ───────────────────────────────────────────────────────

const TRANSITION_CONFIRMS = () => ({
    close_submissions: t('admin.flash_sale_form.close_submissions_confirm'),
    end_sale: t('admin.flash_sale_form.end_sale_confirm'),
    mark_approved: t('admin.flash_sale_form.approve_sale_confirm'),
    start_sale: t('admin.flash_sale_form.start_sale_confirm'),
    open_submissions: null,
});

function initTransitions() {
    // Expose global openModal / closeModal for inline onclick attributes in Blade
    window.openModal = id => $(`#${id}`).modal('open');
    window.closeModal = id => $(`#${id}`).modal('close');

    $(document).on('click', '[data-transition]', function () {
        const action = $(this).data('transition');
        if (!action) return;

        if (action === 'cancel') {
            $('#cancel-modal').modal('open');
            return;
        }

        const msg = TRANSITION_CONFIRMS()[action];
        if (msg && !confirm(msg)) return;

        doTransition(action);
    });
}

function doTransition(action, reason = '') {
    if (!window.URLS?.transition) {
        console.error('[flash-sale-form] window.URLS.transition not set.');
        return;
    }

    // Disable all transition buttons to prevent double-click
    $('[data-transition]').prop('disabled', true);

    $.ajax({
        url: window.URLS.transition,
        method: 'POST',
        data: {
            action,
            reason,
            _token: $('meta[name="csrf-token"]').attr('content'),
        },
        success(res) {
            const msg = res.message || t('admin.flash_sale_form.status_updated');
            // Use toast if available, otherwise alert
            if (window.dispatchEvent) {
                window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: msg } }));
            }
            // Short delay so the user can read the toast before reload
            setTimeout(() => window.location.reload(), 800);
        },
        error(xhr) {
            $('[data-transition]').prop('disabled', false);
            const msg = xhr.responseJSON?.message || t('admin.flash_sale_form.action_failed_retry');
            if (window.dispatchEvent) {
                window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: msg } }));
            } else {
                alert(msg);
            }
        },
    });
}

// ─── Cancel modal ─────────────────────────────────────────────────────────────

function initCancelModal() {
    const form = document.getElementById('cancel-form');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const reasonEl = form.querySelector('[name="cancellation_reason"]');
        const reason = reasonEl?.value?.trim() || '';

        if (!reason) {
            reasonEl?.classList.add('input-error');
            return;
        }
        reasonEl?.classList.remove('input-error');

        const btn = document.getElementById('cancel-submit-btn');
        if (btn) { btn.disabled = true; btn.textContent = t('admin.flash_sale_form.cancelling_label'); }

        $('#cancel-modal').modal('close');
        doTransition('cancel', reason);
    });
}
