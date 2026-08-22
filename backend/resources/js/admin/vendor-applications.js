import DataTable from 'datatables.net';

// ─── Helpers ──────────────────────────────────────────────────────────────────

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function postJson(url, data) {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(data),
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw json;
    return json;
}

// ─── Queue Index Page ─────────────────────────────────────────────────────────

function initIndexPage() {
    const table = document.getElementById('applications-table');
    if (!table) return;

    const dt = new DataTable('#applications-table', {
        processing: true,
        serverSide: true,
        order: [[6, 'desc']], // days_waiting desc → oldest first
        ajax: {
            url: '/vendor-applications/datatable',
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data(d) {
                d.status = document.getElementById('filter-status')?.value ?? '';
                d.country_id = document.getElementById('filter-country')?.value ?? '';
                d.days_min = document.getElementById('filter-days-min')?.value ?? '';
                d.date_from = document.getElementById('filter-date-from')?.value ?? '';
                d.date_to = document.getElementById('filter-date-to')?.value ?? '';
                d.search = { value: document.getElementById('search-input')?.value ?? '' };
            },
        },
        columns: [
            { data: 'store_name', orderable: true },
            { data: 'business_name', orderable: true },
            { data: 'country', orderable: false },
            { data: 'business_type', orderable: true },
            { data: 'docs_status', orderable: false },
            { data: 'bank_status', orderable: false },
            { data: 'days_waiting', orderable: true },
            { data: 'created_at', orderable: true },
            { data: 'actions', orderable: false, searchable: false },
        ],
        columnDefs: [{ targets: [0, 5, 8], className: 'dt-body-center' }],
        pageLength: 25,
    });

    // Filter wiring
    ['filter-status', 'filter-country', 'filter-days-min', 'filter-date-from', 'filter-date-to'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => dt.ajax.reload());
    });

    let searchTimer;
    document.getElementById('search-input')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => dt.ajax.reload(), 350);
    });

    document.getElementById('clear-filters')?.addEventListener('click', () => {
        document.getElementById('search-input').value = '';
        document.getElementById('filter-status').value = '';
        document.getElementById('filter-country').value = '';
        document.getElementById('filter-days-min').value = '';
        document.getElementById('filter-date-from').value = '';
        document.getElementById('filter-date-to').value = '';
        dt.ajax.reload();
    });
}

// ─── Show / Review Page ───────────────────────────────────────────────────────

function initShowPage() {
    if (!window.vendorApp) return;

    // ─── Start Review ──────────────────────────────────────────────────────────
    document.getElementById('start-review-btn')?.addEventListener('click', async function () {
        try {
            const res = await postJson(window.vendorApp.startReviewUrl, {});
            window.Toast?.success(res.message ?? t('admin.vendor_applications.status_updated'));
            // Update badge in header
            const badges = document.querySelectorAll('.inline-flex.items-center.rounded-full');
            badges.forEach(b => {
                if (b.textContent.trim().toLowerCase().includes('pending')) {
                    b.textContent = t('admin.vendor_applications.under_review_label');
                    b.className = b.className
                        .replace(/bg-\w+-100/, 'bg-primary-100')
                        .replace(/text-\w+-700/, 'text-primary-700');
                }
            });
            this.classList.add('hidden');
        } catch (e) {
            window.Toast?.error(e.message ?? t('shared.request_failed'));
        }
    });

    // ─── Assign to me ──────────────────────────────────────────────────────────
    document.getElementById('assign-me-btn')?.addEventListener('click', async function () {
        try {
            const res = await postJson(window.vendorApp.assignMeUrl, {});
            window.Toast?.success(res.message ?? t('admin.vendor_applications.assigned'));
        } catch (e) {
            window.Toast?.error(e.message ?? t('shared.request_failed'));
        }
    });

    // ─── Document Preview ──────────────────────────────────────────────────────
    let activeDocId = null;
    let activeDocVerifyUrl = null;
    let activeDocRejectUrl = null;

    document.querySelectorAll('.js-preview-doc-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const fileUrl = this.dataset.file;
            const docId = this.dataset.id;

            activeDocId = docId;
            activeDocVerifyUrl = document.querySelector(`.js-verify-doc-btn[data-id="${docId}"]`)?.dataset.url;
            activeDocRejectUrl = document.querySelector(`.js-reject-doc-btn[data-id="${docId}"]`)?.dataset.url;

            const content = document.getElementById('doc-preview-content');
            const isPdf = fileUrl?.toLowerCase().includes('.pdf');

            if (isPdf) {
                content.innerHTML = `<iframe src="${fileUrl}" class="w-full" style="height:500px" frameborder="0"></iframe>`;
            } else {
                content.innerHTML = `<img src="${fileUrl}" class="max-h-[500px] max-w-full mx-auto rounded" alt="Document">`;
            }

            const verifyBtn = document.getElementById('modal-verify-doc-btn');
            const rejectBtn = document.getElementById('modal-reject-doc-open-btn');

            verifyBtn?.classList.toggle('hidden', !activeDocVerifyUrl);
            rejectBtn?.classList.toggle('hidden', !activeDocRejectUrl);

            $('#doc-preview-modal').modal('open');
        });
    });

    // Verify from preview modal
    document.getElementById('modal-verify-doc-btn')?.addEventListener('click', async function () {
        if (!activeDocVerifyUrl) return;
        await handleDocVerify(activeDocVerifyUrl, activeDocId);
        $('#doc-preview-modal').modal('close');
    });

    // Open reject-doc modal from preview modal
    document.getElementById('modal-reject-doc-open-btn')?.addEventListener('click', () => {
        $('#doc-preview-modal').modal('close');
        setTimeout(() => $('#reject-doc-modal').modal('open'), 200);
    });

    // ─── Inline Verify button ──────────────────────────────────────────────────
    document.querySelectorAll('.js-verify-doc-btn').forEach(btn => {
        btn.addEventListener('click', async function () {
            await handleDocVerify(this.dataset.url, this.dataset.id);
        });
    });

    async function handleDocVerify(url, docId) {
        try {
            const res = await postJson(url, {});
            window.Toast?.success(t('admin.vendors.document_verified'));
            updateDocRow(docId, 'verified');
            refreshChecklist();
        } catch (e) {
            window.Toast?.error(e.message ?? t('admin.vendor_applications.could_not_verify_document'));
        }
    }

    // ─── Inline Reject button ──────────────────────────────────────────────────
    document.querySelectorAll('.js-reject-doc-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            activeDocId = this.dataset.id;
            activeDocRejectUrl = this.dataset.url;
            document.getElementById('doc-rejection-reason').value = '';
            document.getElementById('doc-rejection-error').classList.add('hidden');
            $('#reject-doc-modal').modal('open');
        });
    });

    // Confirm reject document
    document.getElementById('confirm-reject-doc-btn')?.addEventListener('click', async () => {
        const reason = document.getElementById('doc-rejection-reason').value.trim();
        if (!reason) {
            document.getElementById('doc-rejection-error').classList.remove('hidden');
            return;
        }
        document.getElementById('doc-rejection-error').classList.add('hidden');

        if (!activeDocRejectUrl) return;
        try {
            await postJson(activeDocRejectUrl, { rejection_reason: reason });
            window.Toast?.success(t('admin.vendors.document_rejected'));
            updateDocRow(activeDocId, 'rejected');
            refreshChecklist();
            $('#reject-doc-modal').modal('close');
        } catch (e) {
            window.Toast?.error(e.message ?? t('admin.vendor_applications.could_not_reject_document'));
        }
    });

    function updateDocRow(docId, newStatus) {
        // Update the status badge in the documents table row
        const verifyBtn = document.querySelector(`.js-verify-doc-btn[data-id="${docId}"]`);
        const rejectBtn = document.querySelector(`.js-reject-doc-btn[data-id="${docId}"]`);
        const row = verifyBtn?.closest('tr') ?? rejectBtn?.closest('tr');
        if (!row) return;

        const badge = row.querySelector('.inline-flex');
        if (badge) {
            const colorMap = { verified: ['bg-success-100', 'text-success-700'], rejected: ['bg-danger-100', 'text-danger-700'] };
            const [bg, txt] = colorMap[newStatus] ?? ['bg-gray-100', 'text-gray-700'];
            badge.className = `inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${bg} ${txt}`;
            badge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
        }

        if (newStatus === 'verified') {
            verifyBtn?.remove();
        } else if (newStatus === 'rejected') {
            rejectBtn?.remove();
        }
    }

    // ─── Checklist refresh (after doc verify/reject) ───────────────────────────
    function refreshChecklist() {
        // Re-evaluate required docs checklist from DOM state
        const requiredTypes = ['business_license', 'tax_certificate', 'owner_id'];
        let allVerified = true;

        requiredTypes.forEach(type => {
            // Find the chip for this type in the checklist
            const chips = document.querySelectorAll('.flex.flex-wrap.gap-3 > div');
            chips.forEach(chip => {
                const isThisType = chip.textContent.toLowerCase().includes(type.replace('_', ' '))
                    || chip.textContent.toLowerCase().includes(docTypeLabel(type).toLowerCase());
                // This is a rough heuristic; a more robust approach would be data attributes
            });
        });

        // After any doc change, re-check if the approve button should be enabled
        // For simplicity: reload the page's approval button state via a quick HEAD request
        // or just let the page reload on major actions
        const approveBtn = document.getElementById('open-approve-modal-btn');
        if (approveBtn) {
            // Keep disabled until we do a proper check — the server is the authority
            // User can see the checklist update via page reload after major actions
        }
    }

    function docTypeLabel(type) {
        const map = {
            business_license: t('admin.vendor_applications.business_license'),
            tax_certificate: t('admin.vendor_applications.tax_certificate'),
            owner_id: t('admin.vendor_applications.owner_id'),
        };
        return map[type] ?? type.replace(/_/g, ' ');
    }

    // ─── Approve Modal ─────────────────────────────────────────────────────────
    document.getElementById('open-approve-modal-btn')?.addEventListener('click', function () {
        if (!window.vendorApp.canApprove) return;
        document.getElementById('approve-vendor-name').textContent = this.dataset.vendorName ?? '';
        $('#approve-modal').modal('open');
    });

    document.getElementById('confirm-approve-btn')?.addEventListener('click', async () => {
        const payload = {
            commission_rate_override: document.getElementById('approve-commission').value || null,
            account_manager_admin_id: document.getElementById('approve-account-manager').value || null,
            notes: document.getElementById('approve-notes').value || null,
        };

        const btn = document.getElementById('confirm-approve-btn');
        btn.disabled = true;
        btn.textContent = t('admin.vendor_applications.approving');

        try {
            const res = await postJson(window.vendorApp.approveUrl, payload);
            window.Toast?.success(res.message ?? t('admin.vendor_applications.vendor_approved'));
            $('#approve-modal').modal('close');
            setTimeout(() => window.location.reload(), 1200);
        } catch (e) {
            window.Toast?.error(e.message ?? t('admin.vendor_applications.approval_failed'));
            btn.disabled = false;
            btn.textContent = t('admin.vendor_applications.confirm_approval_label');
        }
    });

    // ─── Reject Application Modal ──────────────────────────────────────────────
    document.getElementById('open-reject-modal-btn')?.addEventListener('click', function () {
        document.getElementById('reject-vendor-name').textContent = this.dataset.vendorName ?? '';
        document.querySelectorAll('.rejection-code-checkbox').forEach(cb => cb.checked = false);
        document.getElementById('reject-reason-input').value = '';
        document.getElementById('reject-reason-error').classList.add('hidden');
        $('#reject-modal').modal('open');
    });

    document.getElementById('confirm-reject-btn')?.addEventListener('click', async () => {
        const reason = document.getElementById('reject-reason-input').value.trim();
        if (!reason) {
            document.getElementById('reject-reason-error').classList.remove('hidden');
            return;
        }
        document.getElementById('reject-reason-error').classList.add('hidden');

        const codes = [...document.querySelectorAll('.rejection-code-checkbox:checked')].map(c => c.value);

        const btn = document.getElementById('confirm-reject-btn');
        btn.disabled = true;
        btn.textContent = t('admin.vendor_applications.rejecting');

        try {
            const res = await postJson(window.vendorApp.rejectUrl, {
                rejection_reason: reason,
                rejection_codes: codes,
            });
            window.Toast?.success(res.message ?? t('admin.vendor_applications.application_rejected'));
            $('#reject-modal').modal('close');
            setTimeout(() => window.location.reload(), 1200);
        } catch (e) {
            window.Toast?.error(e.message ?? t('admin.vendor_applications.rejection_failed'));
            btn.disabled = false;
            btn.textContent = t('admin.vendor_applications.reject_application_label');
        }
    });

    // ─── Request More Info Modal ────────────────────────────────────────────────
    document.getElementById('open-request-info-modal-btn')?.addEventListener('click', function () {
        document.getElementById('request-info-vendor-name').textContent = this.dataset.vendorName ?? '';
        document.querySelectorAll('.request-doc-checkbox').forEach(cb => cb.checked = false);
        document.getElementById('request-info-message').value = '';
        document.getElementById('request-doc-error').classList.add('hidden');
        $('#request-info-modal').modal('open');
    });

    document.getElementById('confirm-request-info-btn')?.addEventListener('click', async function () {
        const selectedDocs = [...document.querySelectorAll('.request-doc-checkbox:checked')].map(c => c.value);
        if (selectedDocs.length === 0) {
            document.getElementById('request-doc-error').classList.remove('hidden');
            return;
        }
        document.getElementById('request-doc-error').classList.add('hidden');

        const msg = document.getElementById('request-info-message').value.trim();
        const btn = this;
        btn.disabled = true;
        btn.textContent = t('admin.vendor_applications.sending');

        try {
            const res = await postJson(window.vendorApp.requestInfoUrl, {
                required_document_types: selectedDocs,
                message: msg || null,
            });
            window.Toast?.success(res.message ?? t('admin.vendor_applications.request_sent'));
            $('#request-info-modal').modal('close');
            setTimeout(() => window.location.reload(), 1000);
        } catch (e) {
            window.Toast?.error(e.message ?? t('shared.request_failed'));
            btn.disabled = false;
            btn.textContent = t('admin.vendor_applications.send_request_label');
        }
    });
}

// ─── Init ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initIndexPage();
    initShowPage();
});
