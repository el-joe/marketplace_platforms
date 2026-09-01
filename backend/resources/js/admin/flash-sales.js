/**
 * resources/js/admin/flash-sales.js
 *
 * Covers three pages:
 *   1. Index   — status-filter tabs, DataTable, action dropdown
 *   2. Create  — two-tab form, timeline visual, store via AJAX
 *   3. Edit    — six tabs, update form, status transitions, invite vendors,
 *                submissions DataTable + review modal (price history chart),
 *                bulk review, live monitor polling, analytics chart
 *
 * Globals injected by each Blade view via inline <script> blocks.
 */

import $ from 'jquery';

const Toast = window.Toast || { success: console.log, error: console.warn, info: console.log };

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function ajax(method, url, data) {
    return $.ajax({
        url,
        type: method,
        data: data ?? {},
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken());
        },
    });
}

function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

/** Show a confirmation dialog; returns a Promise that resolves if confirmed, rejects if not. */
function confirm2(msg) {
    return new Promise((resolve, reject) => {
        if (window.confirm(msg)) resolve(); else reject();
    });
}

function fmtMoney(amount) {
    return Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtNum(n) { return Number(n).toLocaleString(); }

function showErrors(errors) {
    Object.values(errors ?? {}).flat().forEach(e => Toast.error(e));
}

// ─────────────────────────────────────────────────────────────────────────────
// Timeline visual (both create + edit pages with #timeline-visual)
// ─────────────────────────────────────────────────────────────────────────────

function renderTimelineVisual() {
    const $el = $('#timeline-visual');
    if (!$el.length) return;

    const fields = [
        { label: t('admin.flash_sales.submissions_open_label'), selector: '[name=submission_opens_at]' },
        { label: t('admin.flash_sales.submissions_close_label'), selector: '[name=submission_closes_at]' },
        { label: t('admin.flash_sales.review_deadline_label'), selector: '[name=review_deadline_at]' },
        { label: t('admin.flash_sales.sale_starts_label'), selector: '[name=sale_starts_at]' },
        { label: t('admin.flash_sales.sale_ends_label'), selector: '[name=sale_ends_at]' },
    ];

    const values = fields.map(f => ({
        label: f.label,
        value: $(f.selector).val() || null,
    }));

    const html = `
        <ol class="flex items-stretch gap-0 overflow-x-auto text-xs select-none">
            ${values.map((v, i) => `
                <li class="flex flex-col items-center flex-1 min-w-[100px]">
                    <div class="flex items-center w-full">
                        ${i > 0 ? '<div class="flex-1 h-0.5 bg-gray-300"></div>' : '<div class="flex-1"></div>'}
                        <div class="w-3 h-3 rounded-full flex-shrink-0 ${v.value ? 'bg-primary-500' : 'bg-gray-300'}"></div>
                        ${i < values.length - 1 ? '<div class="flex-1 h-0.5 bg-gray-300"></div>' : '<div class="flex-1"></div>'}
                    </div>
                    <span class="mt-1 text-center text-gray-700 font-medium leading-tight">${v.label}</span>
                    <span class="text-gray-400 leading-tight">${v.value ? v.value.replace('T', ' ') : '—'}</span>
                </li>
            `).join('')}
        </ol>`;
    $el.html(html);
}

// ─────────────────────────────────────────────────────────────────────────────
// Create form  (create.blade.php)
// ─────────────────────────────────────────────────────────────────────────────

$(function () {
    const $form = $('#flash-sale-form');
    if (!$form.length || typeof window.FLASH_SALE_STORE_URL === 'undefined') return;

    // timeline visual on date change
    $form.on('change', '[name$="_at"]', renderTimelineVisual);
    renderTimelineVisual();

    $form.on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#flash-sale-submit-btn').prop('disabled', true).text(t('admin.flash_sales.creating'));

        ajax('POST', window.FLASH_SALE_STORE_URL, $(this).serialize())
            .done(function (res) {
                Toast.success(res.message ?? t('admin.flash_sales.flash_sale_created'));
                if (res.redirect) setTimeout(() => { window.location.href = res.redirect; }, 500);
            })
            .fail(function (xhr) {
                const json = xhr.responseJSON ?? {};
                if (json.errors) showErrors(json.errors);
                else Toast.error(json.message ?? t('admin.flash_sales.failed_create_flash_sale'));
            })
            .always(() => $btn.prop('disabled', false).text(t('admin.flash_sales.create_flash_sale_label')));
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Edit page  (edit.blade.php)
// ─────────────────────────────────────────────────────────────────────────────

$(function () {
    if (typeof window.FLASH_SALE_ID === 'undefined') return;

    // ── Save details / rules form ─────────────────────────────────────────────
    $(document).on('submit', '#flash-sale-form, #flash-sale-form-rules', function (e) {
        e.preventDefault();
        const $btn = $(this).find('[type=submit]').prop('disabled', true).text(t('shared.saving'));
        const data = {};
        // Collect form values, aggregating checkbox[] groups into arrays
        $(this).serializeArray().forEach(p => {
            const isArr = p.name.endsWith('[]');
            const key = isArr ? p.name.slice(0, -2) : p.name;
            if (isArr) {
                if (!Array.isArray(data[key])) data[key] = [];
                data[key].push(p.value);
            } else {
                data[key] = p.value;
            }
        });
        // Ensure unchecked checkbox groups are sent as empty arrays
        $(this).find('input[type=checkbox][name$="[]"]').each(function () {
            const key = $(this).attr('name').slice(0, -2);
            if (!Array.isArray(data[key])) data[key] = [];
        });

        ajax('PUT', window.FLASH_SALE_UPDATE_URL, data)
            .done(function (res) { Toast.success(res.message ?? t('shared.saved')); })
            .fail(function (xhr) {
                const json = xhr.responseJSON ?? {};
                if (json.errors) showErrors(json.errors);
                else Toast.error(json.message ?? t('admin.flash_sales.failed_to_save'));
            })
            .always(function () { $btn.prop('disabled', false).text(t('admin.flash_sales.save_changes_label')); });
    });

    // timeline visual on date change
    $(document).on('change', '[name$="_at"]', renderTimelineVisual);
    renderTimelineVisual();

    // ── Status transitions ────────────────────────────────────────────────────
    $(document).on('click', '[data-transition]', function (e) {
        e.preventDefault();
        const action = $(this).data('transition');
        const $btn = $(this);

        if (action === 'cancel') {
            openModal('cancel-modal');
            return;
        }

        const messages = {
            open_submissions: t('admin.flash_sales.open_submissions_confirm'),
            close_submissions: t('admin.flash_sales.close_submissions_confirm'),
            mark_approved: t('admin.flash_sales.approve_flash_sale_confirm'),
            start_sale: t('admin.flash_sales.start_sale_confirm'),
            end_sale: t('admin.flash_sales.end_sale_confirm'),
        };
        const msg = messages[action] ?? t('admin.flash_sales.confirm_action');

        confirm2(msg).then(() => {
            $btn.prop('disabled', true);
            ajax('POST', window.TRANSITION_URL, { action })
                .done(res => { Toast.success(res.message ?? t('admin.flash_sales.status_updated')); setTimeout(() => location.reload(), 600); })
                .fail(xhr => { Toast.error(xhr.responseJSON?.message ?? t('admin.flash_sales.transition_failed')); $btn.prop('disabled', false); });
        }).catch(() => { });
    });

    // Cancel form
    $('#cancel-form').on('submit', function (e) {
        e.preventDefault();
        const reason = $('[name=cancellation_reason]', this).val().trim();
        if (!reason) { Toast.error(t('admin.flash_sales.cancellation_reason_required')); return; }
        const $btn = $('#cancel-submit-btn').prop('disabled', true);
        ajax('POST', window.TRANSITION_URL, { action: 'cancel', reason })
            .done(res => { Toast.success(res.message ?? t('admin.flash_sales.cancelled_message')); setTimeout(() => location.reload(), 600); })
            .fail(xhr => { Toast.error(xhr.responseJSON?.message ?? t('admin.flash_sales.cancel_failed')); $btn.prop('disabled', false); });
    });

    // ── Invite vendors ────────────────────────────────────────────────────────
    $('#invite-vendors-btn').on('click', function () {
        const $btn = $(this).prop('disabled', true).text(t('admin.flash_sales.loading_count'));

        ajax('GET', window.ELIGIBLE_COUNT_URL)
            .done(res => {
                const count = res.count ?? 0;
                const inviteBtnLabel = t('admin.flash_sales.invite_eligible_vendors_label');
                if (!count) { Toast.info(t('admin.flash_sales.no_eligible_vendors_found')); $btn.prop('disabled', false).text(inviteBtnLabel); return; }
                confirm2(t('admin.flash_sales.invite_eligible_vendors_confirm', { count }))
                    .then(() => {
                        $btn.text(t('admin.flash_sales.sending_invitations'));
                        ajax('POST', window.INVITE_URL)
                            .done(r => { Toast.success(r.message ?? t('admin.flash_sales.vendors_invited_result', { count: r.count })); if (window._invitationsTable) window._invitationsTable.ajax.reload(); })
                            .fail(xhr => Toast.error(xhr.responseJSON?.message ?? t('admin.flash_sales.invite_failed')))
                            .always(() => $btn.prop('disabled', false).text(inviteBtnLabel));
                    })
                    .catch(() => $btn.prop('disabled', false).text(inviteBtnLabel));
            })
            .fail(() => { Toast.error(t('admin.flash_sales.failed_fetch_eligible_count')); $btn.prop('disabled', false).text(t('admin.flash_sales.invite_eligible_vendors_label')); });
    });

    // ── Invitations DataTable ─────────────────────────────────────────────────
    if ($('#invitations-table').length) {
        window._invitationsTable = initDataTable('invitations-table', {
            url: window.INVITATIONS_DATATABLE_URL,
            columns: [
                { data: 'vendor_name', title: t('admin.flash_sales.vendor_label') },
                {
                    data: 'status', title: t('admin.flash_sales.status_label'),
                    render: d => `<span class="badge badge-gray">${d}</span>`
                },
                { data: 'notified_at', title: t('admin.flash_sales.notified_label') },
                { data: 'responded_at', title: t('admin.flash_sales.responded_label') },
            ],
        });
    }

    // ── Submissions DataTable + stats ─────────────────────────────────────────
    if ($('#submissions-table').length) {
        loadSubmissionStats();

        window._submissionsTable = initDataTable('submissions-table', {
            url: window.SUBMISSIONS_DATATABLE_URL,
            selectable: true,
            columns: [
                {
                    data: null, title: '<input type="checkbox" id="submissions-table-select-all">', orderable: false,
                    render: (d, t, r) => `<input type="checkbox" class="row-check" value="${r.id}">`
                },
                { data: 'product_name', title: t('admin.flash_sales.product_label') },
                { data: 'vendor_name', title: t('admin.flash_sales.vendor_label') },
                { data: 'flash_price_formatted', title: t('admin.flash_sales.flash_price_label') },
                { data: 'original_price_formatted', title: t('admin.flash_sales.original_short_label') },
                {
                    data: 'discount_pct', title: t('admin.flash_sales.discount_label'),
                    render: (d, t, r) => {
                        const minDisc = window.FLASH_SALE_MIN_DISC ?? 0;
                        const ok = parseFloat(d) >= minDisc;
                        return `<span class="font-medium ${ok ? 'text-success-700' : 'text-danger-700'}">${d}%</span>`;
                    }
                },
                {
                    data: 'is_suspect', title: '⚠',
                    render: (d) => d ? `<span title="${t('admin.flash_sales.fake_discount_suspected')}" class="text-warning-500 font-bold">⚠</span>` : ''
                },
                {
                    data: 'status', title: t('admin.flash_sales.status_label'),
                    render: (d) => `<span class="badge badge-gray">${d}</span>`
                },
                { data: 'quantity_approved', title: t('admin.flash_sales.qty_label') },
                {
                    data: null, title: '', orderable: false,
                    render: (d, t, r) => {
                        if (!['submitted', 'under_review'].includes(r.status)) return '';
                        return `<button class="btn btn-primary btn-xs review-btn" data-id="${r.id}">${t('admin.flash_sales.review_label')}</button>`;
                    }
                },
            ],
            serverSideFilters: {
                status: () => $('#filter-submission-status').val(),
                suspect: () => $('#filter-suspect').is(':checked') ? '1' : '',
            },
        });

        $('#filter-submission-status, #filter-suspect').on('change', function () {
            if (window._submissionsTable) window._submissionsTable.ajax.reload();
        });
    }

    // ── Review modal ──────────────────────────────────────────────────────────
    $(document).on('click', '.review-btn', function () {
        const id = $(this).data('id');
        openReviewModal(id);
    });

    function openReviewModal(submissionId) {
        $('#review-form [name=submission_id]').val(submissionId);
        $('#review-form [name=decision]').val('');
        $('#review-form [name=rejection_code]').val('');
        $('#review-form [name=rejection_reason]').val('');
        $('#review-form [name=admin_notes]').val('');
        $('#review-product-img').attr('src', '');
        $('#review-product-name').text(t('shared.loading'));
        $('#review-variant-name, #review-vendor-name, #review-vendor-country, #review-vendor-rating').text('');
        $('#review-stock-tbody').html(`<tr><td colspan="3" class="text-center py-2">${t('shared.loading')}</td></tr>`);
        $('#review-original-price, #review-flash-price, #review-discount-pct').text('');
        $('#review-discount-check').html('');
        $('#review-30d-row').addClass('hidden');
        $('#fake-discount-warnings').addClass('hidden').html('');
        destroyPriceHistoryChart();
        openModal('review-modal');

        // Fetch submission data from the datatable row cache
        const row = window._submissionsTable?.row(`[data-id="${submissionId}"]`)?.data()
            ?? getSubmissionRowData(submissionId);

        if (row) populateReviewModal(row);
    }

    function getSubmissionRowData(id) {
        // Fallback: find from DOM data
        return null;
    }

    function populateReviewModal(row) {
        $('#review-product-img').attr('src', row.product_image ?? '');
        $('#review-product-name').text(row.product_name ?? '');
        $('#review-variant-name').text(row.variant_name ?? '');
        $('#review-vendor-name').text(row.vendor_name ?? '');
        $('#review-vendor-country').text(row.vendor_country ?? '');
        $('#review-vendor-rating').text(row.vendor_rating ?? '—');

        const stock = row.stock ?? [];
        if (stock.length) {
            $('#review-stock-tbody').html(stock.map(s =>
                `<tr><td>${s.warehouse}</td><td class="text-right">${fmtNum(s.on_hand)}</td><td class="text-right">${fmtNum(s.available)}</td></tr>`
            ).join(''));
        }

        $('#review-original-price').text(row.original_price_formatted ?? '');
        $('#review-flash-price').text(row.flash_price_formatted ?? '');

        const disc = parseFloat(row.discount_pct ?? 0);
        const minDisc = window.FLASH_SALE_MIN_DISC ?? 0;
        const ok = disc >= minDisc;
        $('#review-discount-pct').text(disc.toFixed(2) + '%')
            .removeClass('text-success-700 text-danger-700').addClass(ok ? 'text-success-700' : 'text-danger-700');
        $('#review-discount-check').html(
            ok ? `<span class="badge badge-success">${t('admin.flash_sales.meets_minimum')}</span>`
                : `<span class="badge badge-danger">${t('admin.flash_sales.below_minimum', { pct: minDisc })}</span>`
        );

        // 30-day avg
        const avgPrice = row.avg_price_30d;
        if (avgPrice) {
            const diff = (((row.flash_price / avgPrice) - 1) * 100).toFixed(1);
            const sign = diff >= 0 ? '+' : '';
            $('#review-30d-price').text(fmtMoney(avgPrice));
            $('#review-30d-diff').text(t('admin.flash_sales.vs_avg', { diff: `${sign}${diff}` }));
            $('#review-30d-row').removeClass('hidden');
        }

        // Fake discount
        if (row.is_suspect) {
            const reasons = row.fraud_reasons ?? [];
            $('#fake-discount-warnings')
                .removeClass('hidden')
                .html(`<div class="bg-warning-50 border border-warning-300 rounded-lg p-3">
                    <p class="text-sm font-semibold text-warning-800 mb-1">${t('admin.flash_sales.potential_fake_discount_detected')}</p>
                    <ul class="text-xs text-warning-700 list-disc list-inside space-y-0.5">
                        ${reasons.map(r => `<li>${r}</li>`).join('')}
                    </ul>
                </div>`);
        }

        // Price history chart
        if (row.vendor_listing_id) {
            loadPriceHistoryChart(row.vendor_listing_id, row.flash_price ?? 0, row.original_price ?? 0);
        }
    }

    // ── Price history chart (Chart.js) ────────────────────────────────────────
    let _priceHistoryChart = null;

    function destroyPriceHistoryChart() {
        if (_priceHistoryChart) { _priceHistoryChart.destroy(); _priceHistoryChart = null; }
    }

    function loadPriceHistoryChart(listingId, flashPrice, originalPrice) {
        ajax('GET', window.PRICE_HISTORY_URL + '?vendor_listing_id=' + encodeURIComponent(listingId))
            .done(res => {
                const labels = res.map(p => p.date);
                const prices = res.map(p => p.price_raw ?? p.price);

                destroyPriceHistoryChart();
                const ctx = document.getElementById('price-history-chart');
                if (!ctx) return;

                import('chart.js').then(({ Chart, registerables }) => {
                    Chart.register(...registerables);
                    _priceHistoryChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels,
                            datasets: [
                                { label: t('admin.flash_sales.historical_price_label'), data: prices, borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.1)', fill: true, tension: 0.3, pointRadius: 2 },
                                { label: t('admin.flash_sales.flash_price_label'), data: Array(labels.length).fill(flashPrice / 100), borderColor: '#f59e0b', borderDash: [4, 4], pointRadius: 0 },
                                { label: t('admin.flash_sales.original_price_label'), data: Array(labels.length).fill(originalPrice / 100), borderColor: '#6b7280', borderDash: [2, 4], pointRadius: 0 },
                            ],
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } },
                            scales: { y: { ticks: { font: { size: 10 } } }, x: { ticks: { font: { size: 9 }, maxRotation: 45 } } },
                        },
                    });
                });
            });
    }

    // ── Review form submit ────────────────────────────────────────────────────
    $('#review-form').on('submit', function (e) {
        e.preventDefault();
        const submissionId = $('[name=submission_id]', this).val();
        const decision = $('[name=decision]:checked', this).val();
        if (!decision) { Toast.error(t('admin.flash_sales.select_decision')); return; }
        if (decision === 'rejected' && !$('[name=rejection_code]', this).val()) {
            Toast.error(t('admin.flash_sales.select_rejection_reason')); return;
        }

        const data = {};
        $(this).serializeArray().forEach(p => { data[p.name] = p.value; });
        const $btn = $('#review-submit-btn').prop('disabled', true).text(t('shared.saving'));

        ajax('POST', `/flash-sales/submissions/${submissionId}/review`, data)
            .done(res => {
                Toast.success(res.message ?? t('admin.flash_sales.decision_saved'));
                closeModal('review-modal');
                if (window._submissionsTable) window._submissionsTable.ajax.reload();
                loadSubmissionStats();
            })
            .fail(xhr => {
                const json = xhr.responseJSON ?? {};
                if (json.errors) showErrors(json.errors);
                else Toast.error(json.message ?? t('admin.flash_sales.failed_save_decision'));
            })
            .always(() => $btn.prop('disabled', false).text(t('admin.flash_sales.save_decision_label')));
    });

    // ── Bulk approve ──────────────────────────────────────────────────────────
    $('#bulk-approve-submissions').on('click', function () {
        const ids = getSelectedSubmissionIds();
        if (!ids.length) { Toast.info(t('admin.flash_sales.select_one_submission')); return; }
        confirm2(t('admin.flash_sales.approve_selected_confirm', { count: ids.length })).then(() => {
            const $btn = $(this).prop('disabled', true);
            ajax('POST', window.BULK_REVIEW_URL, { ids, decision: 'approved' })
                .done(res => {
                    Toast.success(t('admin.flash_sales.bulk_review_result', { approved: res.approved, rejected: res.rejected, failed: res.failed }));
                    window._submissionsTable?.ajax.reload();
                    loadSubmissionStats();
                })
                .fail(xhr => Toast.error(xhr.responseJSON?.message ?? t('admin.flash_sales.bulk_approve_failed')))
                .always(() => $btn.prop('disabled', false));
        }).catch(() => { });
    });

    // ── Bulk reject ───────────────────────────────────────────────────────────
    $('#bulk-reject-submissions').on('click', function () {
        const ids = getSelectedSubmissionIds();
        if (!ids.length) { Toast.info(t('admin.flash_sales.select_one_submission')); return; }
        $('[name=bulk_ids]', '#bulk-reject-form').val(ids.join(','));
        openModal('bulk-reject-modal');
    });

    $('#bulk-reject-form').on('submit', function (e) {
        e.preventDefault();
        const ids = $('[name=bulk_ids]', this).val().split(',').filter(Boolean);
        const code = $('[name=bulk_rejection_code]', this).val();
        const reason = $('[name=bulk_rejection_reason]', this).val();
        if (!code) { Toast.error(t('admin.flash_sales.select_rejection_reason_bulk')); return; }
        const $btn = $('#bulk-reject-submit').prop('disabled', true);
        ajax('POST', window.BULK_REVIEW_URL, { ids, decision: 'rejected', rejection_code: code, rejection_reason: reason })
            .done(res => {
                Toast.success(t('admin.flash_sales.bulk_review_result', { approved: res.approved, rejected: res.rejected, failed: res.failed }));
                closeModal('bulk-reject-modal');
                window._submissionsTable?.ajax.reload();
                loadSubmissionStats();
            })
            .fail(xhr => Toast.error(xhr.responseJSON?.message ?? t('admin.flash_sales.bulk_reject_failed')))
            .always(() => $btn.prop('disabled', false));
    });

    function getSelectedSubmissionIds() {
        return $('#submissions-table tbody input.row-check:checked').map(function () {
            return $(this).val();
        }).get();
    }

    function loadSubmissionStats() {
        if (!window.SUBMISSIONS_DATATABLE_URL) return;
        const statsUrl = window.SUBMISSIONS_DATATABLE_URL.replace('/datatable', '/stats')
            .replace('submissions/datatable', 'submission-stats');
        ajax('GET', statsUrl.includes('submission-stats')
            ? statsUrl
            : window.FLASH_SALE_UPDATE_URL.replace('/update', '').replace('/flash-sales/' + window.FLASH_SALE_ID, '/flash-sales/' + window.FLASH_SALE_ID + '/submission-stats'))
            .done(res => {
                $('#stat-submitted').find('[data-stat-value]').text(fmtNum(res.submitted ?? 0));
                $('#stat-approved').find('[data-stat-value]').text(fmtNum(res.approved ?? 0));
                $('#stat-rejected').find('[data-stat-value]').text(fmtNum(res.rejected ?? 0));
                $('#stat-pending').find('[data-stat-value]').text(fmtNum(res.pending ?? 0));
            });
    }

    // ── Live monitor polling ──────────────────────────────────────────────────
    if (window.IS_LIVE && $('#live-monitor-section').length) {
        function refreshLiveData() {
            ajax('GET', window.LIVE_DATA_URL)
                .done(res => {
                    $('#live-units').find('[data-stat-value]').text(fmtNum(res.units_sold ?? 0));
                    $('#live-revenue').find('[data-stat-value]').text(fmtMoney(res.gross_revenue ?? 0));
                    $('#live-soldout').find('[data-stat-value]').text(fmtNum(res.sold_out_count ?? 0));

                    const $tbody = $('#live-submissions-tbody');
                    $tbody.empty();
                    (res.top_submissions ?? []).forEach(s => {
                        $tbody.append(`<tr>
                            <td class="py-1 px-3">${s.product_name}</td>
                            <td class="py-1 px-3 text-right">${fmtNum(s.units_sold)}</td>
                            <td class="py-1 px-3 text-right">${fmtNum(s.quantity_remaining)}</td>
                            <td class="py-1 px-3 text-right">${fmtMoney(s.revenue)}</td>
                            <td class="py-1 px-3"><span class="badge badge-${s.status === 'sold_out' ? 'danger' : 'success'}">${s.status}</span></td>
                        </tr>`);
                    });
                });
        }

        refreshLiveData();
        setInterval(refreshLiveData, 10000);
    }

    // ── Analytics chart ───────────────────────────────────────────────────────
    if (window.IS_ENDED && $('#analytics-section').length) {
        const analyticsUrl = window.FLASH_SALE_UPDATE_URL.replace('/flash-sales/' + window.FLASH_SALE_ID, '/flash-sales/' + window.FLASH_SALE_ID + '/analytics-data');
        ajax('GET', analyticsUrl)
            .done(res => {
                const s = res.summary ?? {};
                $('#an-units').find('[data-stat-value]').text(fmtNum(s.units_sold ?? 0));
                $('#an-revenue').find('[data-stat-value]').text(fmtMoney(s.gross_revenue ?? 0));
                $('#an-discount').find('[data-stat-value]').text(fmtMoney(s.discount_given ?? 0));
                $('#an-commission').find('[data-stat-value]').text(fmtMoney(s.platform_commission ?? 0));
                $('#an-payout').find('[data-stat-value]').text(fmtMoney(s.vendor_payout ?? 0));
                $('#an-conversion').find('[data-stat-value]').text(((s.avg_conversion_rate ?? 0) * 100).toFixed(1) + '%');

                const byDay = res.by_day ?? [];
                const ctx = document.getElementById('analytics-chart');
                if (!ctx || !byDay.length) return;

                import('chart.js').then(({ Chart, registerables }) => {
                    Chart.register(...registerables);
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: byDay.map(d => d.date),
                            datasets: [
                                { label: t('admin.flash_sales.gross_revenue_label'), data: byDay.map(d => d.gross_revenue ?? 0), backgroundColor: 'rgba(99,102,241,0.7)', yAxisID: 'y' },
                                { label: t('admin.flash_sales.discount_label'), data: byDay.map(d => (d.discount_given ?? 0) / 100), backgroundColor: 'rgba(245,158,11,0.7)', yAxisID: 'y' },
                                { label: t('admin.flash_sales.units_sold_label'), data: byDay.map(d => d.units_sold ?? 0), type: 'line', borderColor: '#10b981', backgroundColor: 'transparent', yAxisID: 'y2', tension: 0.3 },
                            ],
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            scales: {
                                y: { position: 'left', ticks: { font: { size: 10 } } },
                                y2: { position: 'right', grid: { drawOnChartArea: false }, ticks: { font: { size: 10 } } },
                                x: { ticks: { font: { size: 9 }, maxRotation: 45 } },
                            },
                            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } },
                        },
                    });
                });
            });
    }
});
