/**
 * Flash Sale Detail (show) page JS.
 *
 * Handles:
 *  - Submissions DataTable (load, filter, row actions)
 *  - Review modal (single approve/reject with price history mini-chart)
 *  - Bulk reject modal
 *  - Status transitions (with cancel modal for cancellation)
 *  - Invitations DataTable
 *  - Auto-invite / manual invite
 *  - Live monitor: 10s polling, countdown timer
 *  - Analytics data loading
 */

/* global initDataTable, window */

// ─── State ────────────────────────────────────────────────────────────────────

let selectedSubmissionIds = new Set();
let livePollingInterval = null;
let countdownInterval = null;

// ─── Bootstrap ────────────────────────────────────────────────────────────────

$(function () {
    const status = window.FLASH_SALE_STATUS;

    initSubmissionsTable();
    initInvitationsTable();
    initTransitionButtons();
    initReviewModal();
    initBulkReject();
    initAutoInvite();
    initManualInvite();
    initCancelModal();
    initAddProduct();

    if (status === 'live') {
        startLiveMonitor();
    }

    if (['live', 'ended'].includes(status)) {
        loadAnalytics();
    }
});

// ─── Submissions DataTable ────────────────────────────────────────────────────

const T = () => window.TRANSLATIONS || {};

const STATUS_BADGES = () => ({
    submitted: { label: T().statusSubmitted || 'Submitted', color: 'gray' },
    under_review: { label: t('admin.flash_sale_detail.under_review_label'), color: 'warning' },
    approved: { label: T().statusApproved || 'Approved', color: 'primary' },
    live: { label: T().statusLive || 'Live', color: 'success' },
    sold_out: { label: t('admin.flash_sale_detail.sold_out_label'), color: 'danger' },
    rejected: { label: T().statusRejected || 'Rejected', color: 'danger' },
    withdrawn: { label: T().statusWithdrawn || 'Withdrawn', color: 'gray' },
    ended: { label: T().statusEnded || 'Ended', color: 'gray' },
});

function badge(status) {
    const b = STATUS_BADGES()[status] || { label: status, color: 'gray' };
    return `<span class="badge badge-${b.color}">${b.label}</span>`;
}

function initSubmissionsTable() {
    if (!document.getElementById('submissions-table')) return;

    initDataTable('submissions-table', {
        url: window.URLS.submissionsDt,
        method: 'POST',
        columns: [
            {
                data: null,
                orderable: false,
                className: 'w-8',
                render: (d, t, row) =>
                    `<input type="checkbox" class="sub-check form-checkbox" data-id="${row.id}">`,
            },
            {
                data: 'product_name',
                title: T().product || 'Product',
                render: (d, t, row) => {
                    const img = row.product_image_url
                        ? `<img src="${row.product_image_url}" class="w-8 h-8 rounded object-cover flex-shrink-0">`
                        : `<div class="w-8 h-8 rounded bg-gray-100"></div>`;
                    const suspect = row.is_suspect
                        ? `<span class="ml-1 text-amber-500" title="${t('admin.flash_sale_detail.possible_fake_discount')}">⚠</span>`
                        : '';
                    const subtitle = row.type === 'admin'
                        ? (row.platform_sku || '')
                        : (row.vendor_store_name || '');
                    return `<div class="flex items-center gap-2">${img}<div>
                        <span class="font-medium text-gray-900 text-sm">${row.product_name}${suspect}</span>
                        <div class="text-xs text-gray-400">${subtitle}</div>
                    </div></div>`;
                },
            },
            {
                data: 'type',
                title: T().typeLabel || 'Type',
                render: (d, t, row) => row.type === 'admin'
                    ? `<span class="badge badge-info">${T().typeAdmin || 'Admin'}</span>`
                    : `<span class="badge badge-gray">${(T().typeVendorPrefix || 'Vendor')}${row.vendor_store_name ? ' ' + row.vendor_store_name : ''}</span>`,
            },
            {
                data: 'flash_price_formatted',
                title: t('admin.flash_sale_detail.flash_price_label'),
                className: 'text-right font-semibold',
                render: (d, t, row) => {
                    const ok = row.discount_ok;
                    return `<span class="${ok ? 'text-primary-700' : 'text-danger-600'}">${d}</span>`;
                },
            },
            {
                data: 'original_price_formatted',
                title: T().originalShort || 'Original',
                className: 'text-right text-gray-400 line-through',
            },
            {
                data: 'calculated_discount_pct',
                title: T().discShort || 'Disc.',
                className: 'text-right',
                render: (d, t, row) => {
                    const ok = row.discount_ok;
                    const minLabel = (T().minDiscountShort || 'Min: :pct%').replace(':pct', row.min_discount_pct);
                    return `<span class="font-mono font-medium ${ok ? 'text-emerald-600' : 'text-danger-600'}">${d}%</span>
                            ${!ok ? `<div class="text-xs text-danger-500">${minLabel}</div>` : ''}`;
                },
            },
            {
                data: 'quantity_sold',
                title: T().qty || 'Qty',
                orderable: false,
                className: 'text-right font-mono',
                render: (d, t, row) =>
                    `${row.quantity_sold} / ${row.max_quantity_total}`,
            },
            {
                data: 'status',
                title: T().status || 'Status',
                render: d => badge(d),
            },
            {
                data: 'submitted_at_human',
                title: T().submitted || 'Submitted',
                render: d => d ? `<span class="text-gray-400 text-xs">${d}</span>` : '—',
            },
            {
                data: null,
                title: '',
                orderable: false,
                className: 'text-right',
                render: (d, t, row) => {
                    const canReview = ['submitted', 'under_review'].includes(row.status);
                    if (!canReview) return '';
                    return `<button type="button" class="btn btn-secondary btn-xs btn-review-submission"
                        data-id="${row.id}">${T().reviewBtn || 'Review'}</button>`;
                },
            },
        ],
        serverSideFilters: {
            status: () => $('#sub-filter-status').val() || null,
        },
    });

    // Filter change
    $('#sub-filter-status').on('change', function () {
        if ($.fn.DataTable.isDataTable('#submissions-table')) {
            $('#submissions-table').DataTable().ajax.reload();
        }
    });

    // Checkbox events (delegated)
    $(document).on('change', '.sub-check', function () {
        const id = $(this).data('id');
        if (this.checked) {
            selectedSubmissionIds.add(id);
        } else {
            selectedSubmissionIds.delete(id);
        }
        updateBulkRejectVisibility();
    });

    // Review button (delegated)
    $(document).on('click', '.btn-review-submission', function () {
        openReviewModal($(this).data('id'));
    });
}

function updateBulkRejectVisibility() {
    const btn = document.getElementById('btn-bulk-reject');
    if (!btn) return;
    if (selectedSubmissionIds.size > 0) {
        btn.classList.remove('hidden');
        btn.textContent = t('admin.flash_sale_detail.bulk_reject_label', { count: selectedSubmissionIds.size });
    } else {
        btn.classList.add('hidden');
    }
}

// ─── Review modal ─────────────────────────────────────────────────────────────

function initReviewModal() {
    $('#btn-confirm-review').on('click', confirmReview);
}

function openReviewModal(submissionId) {
    document.getElementById('review-submission-id').value = submissionId;

    // Reset fields
    document.getElementById('review-admin-notes').value = '';
    document.getElementById('review-rejection-reason').value = '';
    document.getElementById('review-rejection-code').value = 'manual_rejection';
    document.getElementById('fraud-warning').classList.add('hidden');
    document.getElementById('review-price-chart').innerHTML = `<span class="text-xs text-gray-400">${T().loading || 'Loading…'}</span>`;
    document.getElementById('review-product-info').classList.add('hidden');
    document.getElementById('review-stock-info').classList.add('hidden');

    // Set decision back to approved
    const approveRadio = document.querySelector('input[name="decision"][value="approved"], input[type="radio"][value="approved"]');
    if (approveRadio) approveRadio.click();

    // Open modal
    $('#review-modal').modal('open');

    // Fetch detail
    const detailUrl = `${window.URLS.submissionDetail}/${submissionId}/detail`;
    $.get(detailUrl)
        .done(res => populateReviewModal(res.data))
        .fail(() => {
            document.getElementById('review-price-chart').innerHTML =
                `<span class="text-xs text-danger-600">${t('admin.flash_sale_detail.failed_load_pricing')}</span>`;
        });
}

function populateReviewModal(data) {
    // Product info
    if (data.product_name) {
        document.getElementById('review-product-name').textContent = data.product_name;
        document.getElementById('review-flash-price').textContent = data.flash_price_formatted;
        document.getElementById('review-original-price').textContent = data.original_price_formatted;
        document.getElementById('review-discount-pct').textContent = data.calculated_discount_pct + '%';
        if (data.product_image_url) {
            document.getElementById('review-product-img').src = data.product_image_url;
        }
        document.getElementById('review-product-info').classList.remove('hidden');
    }

    // Stock
    if (data.stock) {
        document.getElementById('review-max-qty').textContent = data.stock.max_quantity_total;
        document.getElementById('review-qty-sold').textContent = data.stock.quantity_sold;
        document.getElementById('review-qty-remaining').textContent = data.stock.quantity_remaining;
        document.getElementById('review-stock-info').classList.remove('hidden');
    }

    // Fraud warning
    if (data.analysis?.is_suspect && data.analysis.reasons?.length) {
        const warn = document.getElementById('fraud-warning');
        const list = document.getElementById('fraud-reasons');
        list.innerHTML = data.analysis.reasons.map(r => `<li>${r}</li>`).join('');
        warn.classList.remove('hidden');
    }

    // Price history mini-chart (simple bar chart)
    renderMiniPriceChart(data.price_history || []);
}

function renderMiniPriceChart(history) {
    const container = document.getElementById('review-price-chart');
    if (!history.length) {
        container.innerHTML = `<span class="text-xs text-gray-400">${t('admin.flash_sale_detail.no_price_history')}</span>`;
        return;
    }

    const prices = history.map(h => h.price_raw);
    const maxPrice = Math.max(...prices);
    const minPrice = Math.min(...prices);
    const range = maxPrice - minPrice || 1;

    container.innerHTML = history.map(h => {
        const heightPct = ((h.price_raw - minPrice) / range) * 100;
        const barH = Math.max(4, Math.round((heightPct / 100) * 60));
        return `<div class="flex-1 flex flex-col justify-end" title="${h.date}: ${h.price_formatted}">
            <div class="bg-primary-400 rounded-t" style="height:${barH}px; min-height:4px;"></div>
        </div>`;
    }).join('');
}

function confirmReview() {
    const submissionId = document.getElementById('review-submission-id').value;
    const decision = document.querySelector('#review-decision')?.value || 'approved';
    const adminNotes = document.getElementById('review-admin-notes').value;
    const rejectCode = document.getElementById('review-rejection-code').value;
    const rejectReason = document.getElementById('review-rejection-reason').value;

    if (decision === 'approved') {
        const fraudWarn = document.getElementById('fraud-warning');
        const override = document.getElementById('override-fraud-check');
        if (!fraudWarn.classList.contains('hidden') && !override?.checked) {
            alert(t('admin.flash_sale_detail.acknowledge_warning'));
            return;
        }
    }

    const btn = document.getElementById('btn-confirm-review');
    btn.disabled = true;
    btn.textContent = T().saving || 'Saving…';

    const url = `${window.URLS.submissionDetail}/${submissionId}/review`;

    $.ajax({
        url,
        method: 'POST',
        data: {
            decision,
            admin_notes: adminNotes,
            rejection_code: rejectCode,
            rejection_reason: rejectReason,
            _token: $('meta[name="csrf-token"]').attr('content'),
        },
        success(res) {
            btn.disabled = false;
            btn.textContent = t('admin.flash_sale_detail.confirm_decision_label');
            $('#review-modal').modal('close');
            window.Toast.success(res.message || t('admin.flash_sale_detail.submission_reviewed'));
            if ($.fn.DataTable.isDataTable('#submissions-table')) {
                $('#submissions-table').DataTable().ajax.reload(null, false);
            }
        },
        error(xhr) {
            btn.disabled = false;
            btn.textContent = T().confirmDecision || 'Confirm Decision';
            const msg = xhr.responseJSON?.message || t('admin.flash_sale_detail.review_failed');
            window.Toast.error(msg);
        },
    });
}

// ─── Bulk reject ──────────────────────────────────────────────────────────────

function initBulkReject() {
    document.getElementById('btn-bulk-reject')?.addEventListener('click', () => {
        if (!selectedSubmissionIds.size) return;
        document.getElementById('bulk-reject-count').textContent = selectedSubmissionIds.size;
        $('#bulk-reject-modal').modal('open');
    });

    document.getElementById('btn-confirm-bulk-reject')?.addEventListener('click', () => {
        const code = document.getElementById('bulk-rejection-code').value;
        const reason = document.getElementById('bulk-rejection-reason').value;

        $.ajax({
            url: window.URLS.bulkReview,
            method: 'POST',
            data: {
                ids: [...selectedSubmissionIds],
                decision: 'rejected',
                rejection_code: code,
                rejection_reason: reason,
                _token: $('meta[name="csrf-token"]').attr('content'),
            },
            success(res) {
                $('#bulk-reject-modal').modal('close');
                const d = res.data;
                const msg = (T().bulkRejectResult || 'Rejected: :rejected, Failed: :failed')
                    .replace(':rejected', d.rejected).replace(':failed', d.failed);
                window.Toast.success(msg);
                selectedSubmissionIds.clear();
                updateBulkRejectVisibility();
                if ($.fn.DataTable.isDataTable('#submissions-table')) {
                    $('#submissions-table').DataTable().ajax.reload(null, false);
                }
            },
            error(xhr) {
                const msg = xhr.responseJSON?.message || t('admin.flash_sale_detail.bulk_reject_failed');
                window.Toast.error(msg);
            },
        });
    });
}

// ─── Status transitions ───────────────────────────────────────────────────────

function initTransitionButtons() {
    $(document).on('click', '.flash-sale-transition', function () {
        const action = $(this).data('action');
        const needsConfirm = $(this).data('needs-confirm') === '1' || $(this).data('needs-confirm') === 1;

        if (action === 'cancel') {
            $('#cancel-modal').modal('open');
            return;
        }

        if (needsConfirm) {
            if (!confirm(t('admin.flash_sale_detail.confirm_proceed'))) return;
        }

        doTransition(action);
    });
}

function doTransition(action, reason = '') {
    $.ajax({
        url: window.URLS.transition,
        method: 'POST',
        data: {
            action,
            reason,
            _token: $('meta[name="csrf-token"]').attr('content'),
        },
        success(res) {
            window.Toast.success(res.message || t('admin.flash_sale_detail.status_updated'));
            setTimeout(() => window.location.reload(), 800);
        },
        error(xhr) {
            const msg = xhr.responseJSON?.message || t('admin.flash_sale_detail.transition_failed');
            window.Toast.error(msg);
        },
    });
}

// ─── Cancel modal ─────────────────────────────────────────────────────────────

function initCancelModal() {
    document.getElementById('btn-confirm-cancel')?.addEventListener('click', () => {
        const reason = document.getElementById('cancel-reason').value;
        $('#cancel-modal').modal('close');
        doTransition('cancel', reason);
    });
}

// ─── Invitations DataTable ────────────────────────────────────────────────────

const INV_STATUS_BADGES = () => ({
    pending:   { label: T().statusPending || 'Pending',   color: 'warning' },
    accepted:  { label: T().statusAccepted || 'Accepted',  color: 'success' },
    declined:  { label: T().statusDeclined || 'Declined',  color: 'danger' },
    submitted: { label: T().statusSubmitted || 'Submitted', color: 'primary' },
});

function initInvitationsTable() {
    if (!document.getElementById('invitations-table')) return;

    initDataTable('invitations-table', {
        url: window.URLS.invitationsDt,
        method: 'POST',
        columns: [
            {
                data: 'store_name',
                title: T().vendor || 'Vendor',
                render: (d, t, row) =>
                    `<span class="font-medium text-gray-900">${d}</span>`
                    + (row.vendor_id ? `<div class="text-xs text-gray-400 font-mono">${row.vendor_id.slice(0, 8)}…</div>` : ''),
            },
            {
                data: 'invitation_type',
                title: T().typeLabel || 'Type',
                render: d => d === 'manual'
                    ? `<span class="text-xs font-medium text-purple-700">${T().manualInviteType || '✋ Manual'}</span>`
                    : `<span class="text-xs font-medium text-blue-600">${T().autoInviteType || '🤖 Auto'}</span>`,
            },
            {
                data: 'status',
                title: T().status || 'Status',
                render: (d, t, row) => {
                    const b = INV_STATUS_BADGES()[d] || { label: d, color: 'gray' };
                    let html = `<span class="badge badge-${b.color}">${b.label}</span>`;
                    if (d === 'declined' && row.decline_reason) {
                        html += ` <button type="button" class="btn-view-decline text-xs text-gray-400 underline ml-1"
                            data-vendor="${row.store_name}" data-reason="${row.decline_reason.replace(/"/g, '&quot;')}">${T().viewReason || 'view reason'}</button>`;
                    }
                    return html;
                },
            },
            {
                data: 'slots_allocated',
                title: T().slots || 'Slots',
                className: 'text-right font-mono text-sm',
                render: d => d != null ? d : '—',
            },
            {
                data: 'invited_at',
                title: T().invitedLabel || 'Invited',
                render: d => d ? `<span class="text-xs text-gray-500">${new Date(d).toLocaleDateString()}</span>` : '—',
            },
            {
                data: 'responded_at',
                title: T().respondedLabel || 'Responded',
                render: d => d ? `<span class="text-xs text-gray-500">${new Date(d).toLocaleDateString()}</span>` : '—',
            },
            {
                data: null,
                title: '',
                orderable: false,
                className: 'text-right',
                render: (d, t, row) => {
                    if (!row.can_resend) return '';
                    return `<button type="button" class="btn btn-ghost btn-xs btn-resend-invitation"
                        data-id="${row.id}" title="${t('admin.flash_sale_detail.resend_notification_label')}">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        ${T().resend || 'Resend'}
                    </button>`;
                },
            },
        ],
        serverSideFilters: {
            status: () => $('#inv-filter-status').val() || null,
        },
    });

    // Status filter
    $('#inv-filter-status').on('change', function () {
        if ($.fn.DataTable.isDataTable('#invitations-table')) {
            $('#invitations-table').DataTable().ajax.reload();
        }
    });

    // Decline reason viewer (delegated)
    $(document).on('click', '.btn-view-decline', function () {
        document.getElementById('decline-reason-vendor').textContent = $(this).data('vendor');
        document.getElementById('decline-reason-text').textContent = $(this).data('reason');
        $('#decline-reason-modal').modal('open');
    });

    // Resend invitation (delegated)
    $(document).on('click', '.btn-resend-invitation', function () {
        const id = $(this).data('id');
        const btn = this;
        btn.disabled = true;

        $.ajax({
            url: `${window.URLS.resendInvitation}/${id}/resend`,
            method: 'POST',
            data: { _token: $('meta[name="csrf-token"]').attr('content') },
            success(res) {
                btn.disabled = false;
                window.Toast.success(res.message || t('admin.flash_sale_detail.notification_queued'));
            },
            error(xhr) {
                btn.disabled = false;
                const msg = xhr.responseJSON?.message || t('admin.flash_sale_detail.resend_failed');
                window.Toast.error(msg);
            },
        });
    });
}

// ─── Auto-invite ──────────────────────────────────────────────────────────────

function initAutoInvite() {
    document.getElementById('btn-auto-invite')?.addEventListener('click', () => {
        // Reset modal state
        document.getElementById('auto-invite-loading').classList.remove('hidden');
        document.getElementById('auto-invite-content').classList.add('hidden');
        document.getElementById('btn-confirm-auto-invite').classList.add('hidden');
        document.getElementById('auto-invite-zero-msg').classList.add('hidden');

        $('#auto-invite-modal').modal('open');

        // Fetch eligible count first
        $.get(window.URLS.eligibleCount)
            .done(res => {
                const count = res.data?.count ?? 0;
                document.getElementById('auto-invite-loading').classList.add('hidden');
                document.getElementById('auto-invite-content').classList.remove('hidden');
                document.getElementById('auto-invite-count').textContent = count;

                if (count === 0) {
                    document.getElementById('auto-invite-zero-msg').classList.remove('hidden');
                    document.getElementById('auto-invite-confirm-area').classList.add('hidden');
                    buildCriteriaHint();
                } else {
                    document.getElementById('auto-invite-zero-msg').classList.add('hidden');
                    document.getElementById('auto-invite-confirm-area').classList.remove('hidden');
                    document.getElementById('btn-confirm-auto-invite').classList.remove('hidden');
                }
            })
            .fail(() => {
                document.getElementById('auto-invite-loading').textContent = t('admin.flash_sale_detail.failed_load_eligible_count');
            });
    });

    document.getElementById('btn-confirm-auto-invite')?.addEventListener('click', () => {
        const btn = document.getElementById('btn-confirm-auto-invite');
        btn.disabled = true;
        btn.textContent = T().inviting || 'Inviting…';

        $.ajax({
            url: window.URLS.inviteVendors,
            method: 'POST',
            data: { _token: $('meta[name="csrf-token"]').attr('content') },
            success(res) {
                btn.disabled = false;
                btn.textContent = t('admin.flash_sale_detail.send_invitations_label');
                $('#auto-invite-modal').modal('close');
                window.Toast.success(res.message || (T().vendorsInvitedResult || ':count vendor(s) invited.').replace(':count', res.count));
                if ($.fn.DataTable.isDataTable('#invitations-table')) {
                    $('#invitations-table').DataTable().ajax.reload(null, false);
                }
            },
            error(xhr) {
                btn.disabled = false;
                btn.textContent = T().sendInvitations || 'Send Invitations';
                const msg = xhr.responseJSON?.message || t('admin.flash_sale_detail.invite_failed');
                window.Toast.error(msg);
            },
        });
    });
}

function buildCriteriaHint() {
    const parts = [];
    if (window.MIN_DISCOUNT_PCT) parts.push((T().minDiscountCriteria || 'min discount :pct%').replace(':pct', window.MIN_DISCOUNT_PCT));
    const hint = parts.length
        ? t('admin.flash_sale_detail.active_criteria_label', { criteria: parts.join(', ') })
        : t('admin.flash_sale_detail.all_eligible_invited');
    const el = document.getElementById('auto-invite-criteria-hint');
    if (el) el.textContent = hint;
}

// ─── Manual invite ────────────────────────────────────────────────────────────

function initManualInvite() {
    document.getElementById('btn-confirm-manual-invite')?.addEventListener('click', () => {
        const raw = document.getElementById('manual-invite-ids').value.trim();
        if (!raw) return;

        const ids = raw.split('\n').map(s => s.trim()).filter(Boolean);
        if (!ids.length) return;

        $.ajax({
            url: window.URLS.inviteVendors,
            method: 'POST',
            data: {
                vendor_ids: ids,
                type: 'manual',
                _token: $('meta[name="csrf-token"]').attr('content'),
            },
            success(res) {
                $('#manual-invite-modal').modal('close');
                window.Toast.success(res.message || t('admin.flash_sale_detail.vendors_invited'));
                if ($.fn.DataTable.isDataTable('#invitations-table')) {
                    $('#invitations-table').DataTable().ajax.reload(null, false);
                }
            },
            error(xhr) {
                const msg = xhr.responseJSON?.message || t('admin.flash_sale_detail.failed_invite_vendors');
                window.Toast.error(msg);
            },
        });
    });
}

// ─── Add product (manual submission) ─────────────────────────────────────────

function initAddProduct() {
    const modal = document.getElementById('add-product-modal');
    if (!modal) return;

    $(document).on('change', 'input[name="submission-type"]', function () {
        const isAdmin = $('input[name="submission-type"]:checked').val() === 'admin';
        $('#vendor-listing-fields').toggleClass('hidden', isAdmin);
        $('#admin-listing-fields').toggleClass('hidden', !isAdmin);
    });

    // Vendor listing selector is scoped to the chosen vendor.
    $(document).on('change', '#add-product-vendor', function () {
        const vendorId = $(this).val();
        const $listingSelect = $('#add-product-vendor-listing');
        $listingSelect.val(null).trigger('change');
        $listingSelect.prop('disabled', !vendorId);

        const cfg = JSON.parse($listingSelect.attr('data-config') || '{}');
        $listingSelect.attr('data-config', JSON.stringify({ ...cfg, vendor_id: vendorId }));
    });

    document.getElementById('btn-confirm-add-product')?.addEventListener('click', submitAddProduct);
}

function resetAddProductForm() {
    document.querySelector('input[name="submission-type"][value="vendor"]').checked = true;
    $('#vendor-listing-fields').removeClass('hidden');
    $('#admin-listing-fields').addClass('hidden');
    $('#add-product-vendor, #add-product-vendor-listing, #add-product-admin-listing').val(null).trigger('change');
    $('#add-product-vendor-listing').prop('disabled', true);
    document.getElementById('add-product-original-price').value = '';
    document.getElementById('add-product-flash-price').value = '';
    document.getElementById('add-product-max-qty').value = '';
    document.getElementById('add-product-max-qty-customer').value = '';
    document.getElementById('add-product-admin-notes').value = '';
}

$(document).on('click', '[data-modal-open="add-product-modal"]', resetAddProductForm);

function submitAddProduct() {
    const submissionType = $('input[name="submission-type"]:checked').val();
    const btn = document.getElementById('btn-confirm-add-product');

    const data = {
        submission_type: submissionType,
        original_price: document.getElementById('add-product-original-price').value,
        flash_price: document.getElementById('add-product-flash-price').value,
        max_quantity_total: document.getElementById('add-product-max-qty').value,
        max_quantity_per_customer: document.getElementById('add-product-max-qty-customer').value || null,
        admin_notes: document.getElementById('add-product-admin-notes').value,
        _token: $('meta[name="csrf-token"]').attr('content'),
    };

    if (submissionType === 'admin') {
        data.admin_product_listing_id = $('#add-product-admin-listing').val();
    } else {
        data.vendor_id = $('#add-product-vendor').val();
        data.vendor_listing_id = $('#add-product-vendor-listing').val();
    }

    btn.disabled = true;
    btn.textContent = T().adding || 'Adding…';

    $.ajax({
        url: window.URLS.submissionsStore,
        method: 'POST',
        data,
        success(res) {
            btn.disabled = false;
            btn.textContent = t('admin.flash_sale_detail.add_product_label');
            $('#add-product-modal').modal('close');
            window.Toast.success(res.message || t('admin.flash_sale_detail.submission_added'));
            if ($.fn.DataTable.isDataTable('#submissions-table')) {
                $('#submissions-table').DataTable().ajax.reload(null, false);
            }
        },
        error(xhr) {
            btn.disabled = false;
            btn.textContent = T().addProduct || 'Add Product';
            const msg = xhr.responseJSON?.message
                || Object.values(xhr.responseJSON?.errors || {})[0]?.[0]
                || t('admin.flash_sale_detail.failed_add_submission');
            window.Toast.error(msg);
        },
    });
}

// ─── Live monitor ─────────────────────────────────────────────────────────────

function startLiveMonitor() {
    fetchLiveData();
    livePollingInterval = setInterval(fetchLiveData, 10000);
}

function fetchLiveData() {
    $.get(window.URLS.liveData)
        .done(res => updateLiveStats(res.data))
        .fail(() => {
            if (livePollingInterval) clearInterval(livePollingInterval);
        });
}

function updateLiveStats(data) {
    setText('live-units', data.total_units_sold?.toLocaleString() ?? '—');
    setText('live-revenue', data.total_revenue != null ? data.total_revenue.toFixed(2) : '—');
    setText('live-sold-out', data.sold_out_count ?? '—');

    // Countdown
    let secs = data.time_remaining_seconds ?? 0;
    if (countdownInterval) clearInterval(countdownInterval);
    countdownInterval = setInterval(() => {
        if (secs <= 0) {
            clearInterval(countdownInterval);
            setText('live-countdown', T().ended || 'Ended');
            return;
        }
        secs--;
        const h = Math.floor(secs / 3600);
        const m = Math.floor((secs % 3600) / 60);
        const s = secs % 60;
        setText('live-countdown', `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`);
    }, 1000);

    // Top products table
    const tbody = document.getElementById('live-top-tbody');
    if (tbody && data.top_submissions) {
        tbody.innerHTML = data.top_submissions.map(s => `
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm font-medium text-gray-900">${s.product_name}</td>
                <td class="px-4 py-3 text-right font-mono text-sm">${s.quantity_sold}</td>
                <td class="px-4 py-3 text-right font-mono text-sm">${s.quantity_remaining}</td>
                <td class="px-4 py-3 text-right font-mono text-sm">${s.revenue_formatted}</td>
            </tr>`).join('') || `<tr><td colspan="4" class="py-6 text-center text-gray-400 text-sm">${t('admin.flash_sale_detail.no_data_yet')}</td></tr>`;
    }
}

// ─── Analytics ────────────────────────────────────────────────────────────────

function loadAnalytics() {
    $.get(window.URLS.analyticsData)
        .done(res => renderAnalytics(res.data))
        .fail(() => {
            const el = document.getElementById('analytics-tbody');
            if (el) el.innerHTML = `<tr><td colspan="4" class="py-6 text-center text-gray-400 text-sm">${t('admin.flash_sale_detail.analytics_unavailable')}</td></tr>`;
        });
}

function renderAnalytics(data) {
    if (!data) return;

    const s = data.summary;
    if (s) {
        setText('an-units', (s.units_sold ?? 0).toLocaleString());
        setText('an-revenue', formatMoney(s.gross_revenue));
        setText('an-discount', formatMoney(s.discount_given));
        setText('an-commission', formatMoney(s.platform_commission));
        setText('an-payout', formatMoney(s.vendor_payout));
        setText('an-conversion', (s.avg_conversion_pct ?? 0).toFixed(2) + '%');
    }

    const tbody = document.getElementById('analytics-tbody');
    if (tbody && data.by_day) {
        tbody.innerHTML = data.by_day.map(row => `
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 text-sm text-gray-700">${row.date}</td>
                <td class="px-4 py-2 text-right font-mono text-sm">${row.units_sold}</td>
                <td class="px-4 py-2 text-right font-mono text-sm">${formatMoney(row.gross_revenue)}</td>
                <td class="px-4 py-2 text-right font-mono text-sm">${formatMoney(row.discount_given)}</td>
            </tr>`).join('') || `<tr><td colspan="4" class="py-4 text-center text-gray-400 text-sm">${t('admin.flash_sale_detail.no_daily_data')}</td></tr>`;
    }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) {
        // If it's a stat-card, update the value span inside
        const valueEl = el.querySelector('[data-stat-value]') || el.querySelector('.text-2xl') || el;
        valueEl.textContent = value;
    }
}

function formatMoney(cents) {
    if (cents == null) return '—';
    return (cents / 100).toFixed(2);
}
