/**
 * customers.js — Customer Management JS
 *
 * Handles:
 *  - DataTable initialisation (index page)
 *  - Suspend / Ban / Reactivate actions (index + show pages)
 *  - Adjust loyalty points modal with live preview
 *  - Send notification modal
 *  - Ban confirmation ("type CONFIRM") gate
 *  - Copyable fields (.js-copy)
 */
document.addEventListener('DOMContentLoaded', () => {

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    /* ─────────────────────────────────────────────────────────────────────────
     * HELPERS
     * ─────────────────────────────────────────────────────────────────────── */

    async function postJson(url, body = {}) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify(body),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw data;
        return data;
    }

    function setLoading(btn, loading, label = null) {
        if (!btn) return;
        btn.disabled = loading;
        if (label) btn.textContent = loading ? (window.TRANSLATIONS?.pleaseWait || 'Please wait…') : label;
    }

    /* ─────────────────────────────────────────────────────────────────────────
     * INDEX PAGE — DataTable
     * ─────────────────────────────────────────────────────────────────────── */

    const customersTable = document.getElementById('customers-table');
    if (customersTable && window.customersTableUrl) {
        const dt = $(customersTable).DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: window.customersTableUrl,
                type: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                data(d) {
                    d.status = document.getElementById('filter-status')?.value || '';
                    d.country_id = document.getElementById('filter-country')?.value || '';
                    d.date_from = document.getElementById('filter-date-from')?.value || '';
                    d.date_to = document.getElementById('filter-date-to')?.value || '';
                    d.min_orders = document.getElementById('filter-min-orders')?.value || '';
                    d.verified_only = document.getElementById('filter-verified')?.checked ? '1' : '';
                },
            },
            columns: [
                {
                    data: null, orderable: false, searchable: false,
                    render(data, type, row) {
                        return `<input type="checkbox" class="row-select rounded border-gray-300" value="${row.DT_RowData?.id || ''}">`;
                    }
                },
                { data: 'name' },
                { data: 'email' },
                { data: 'phone' },
                { data: 'country' },
                { data: 'status' },
                { data: 'total_orders' },
                { data: 'total_spent' },
                { data: 'loyalty_points' },
                { data: 'created_at' },
                { data: 'actions', orderable: false, searchable: false, className: 'text-right' },
            ],
            order: [[9, 'desc']],
            pageLength: 25,
            language: { search: '', searchPlaceholder: t('shared.table_search_placeholder') },
        });

        // Global search input
        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            let searchTimer;
            searchInput.addEventListener('input', () => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    dt.search(searchInput.value).draw();
                }, 400);
            });
        }

        // Filter controls
        ['filter-status', 'filter-country'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', () => dt.draw());
        });
        ['filter-date-from', 'filter-date-to', 'filter-min-orders'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', () => dt.draw());
        });
        document.getElementById('filter-verified')?.addEventListener('change', () => dt.draw());

        // Reset filters
        document.getElementById('clear-filters')?.addEventListener('click', () => {
            ['filter-status', 'filter-country'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            ['filter-date-from', 'filter-date-to', 'filter-min-orders'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            const verifiedEl = document.getElementById('filter-verified');
            if (verifiedEl) verifiedEl.checked = false;
            if (searchInput) { searchInput.value = ''; dt.search('').draw(); }
            else dt.draw();
        });

        // ── Bulk selection ──────────────────────────────────────────────────
        const bulkBar = document.getElementById('bulk-bar');
        const selectedCount = document.getElementById('selected-count');

        function updateBulkBar() {
            const checked = document.querySelectorAll('.row-select:checked');
            const count = checked.length;
            if (bulkBar) bulkBar.classList.toggle('hidden', count === 0);
            if (selectedCount) selectedCount.textContent = count;
        }

        document.getElementById('select-all')?.addEventListener('change', (e) => {
            document.querySelectorAll('.row-select').forEach(cb => { cb.checked = e.target.checked; });
            updateBulkBar();
        });

        $(customersTable).on('change', '.row-select', updateBulkBar);

        document.getElementById('clear-selection')?.addEventListener('click', () => {
            document.querySelectorAll('.row-select, #select-all').forEach(cb => { cb.checked = false; });
            updateBulkBar();
        });

        // ── Bulk suspend ───────────────────────────────────────────────────
        document.querySelector('[data-bulk="suspend"]')?.addEventListener('click', () => {
            const ids = [...document.querySelectorAll('.row-select:checked')].map(cb => cb.value);
            if (!ids.length) return;

            const reason = prompt((window.TRANSLATIONS?.suspendCountPrompt || 'Suspend :count customer(s) — enter reason:').replace(':count', ids.length));
            if (!reason) return;

            Promise.all(ids.map(id => {
                const url = window.customersTableUrl.replace('/datatable', `/${id}/suspend`);
                return postJson(url, { reason }).catch(() => null);
            })).then(() => {
                window.Toast?.success((window.TRANSLATIONS?.customersSuspended || ':count customer(s) suspended.').replace(':count', ids.length));
                dt.draw(false);
                document.querySelectorAll('.row-select, #select-all').forEach(cb => { cb.checked = false; });
                updateBulkBar();
            }).catch(() => window.Toast?.error(window.TRANSLATIONS?.someActionsFailed || 'Some actions failed.'));
        });

        // ── Export selected / all ──────────────────────────────────────────
        document.querySelector('[data-bulk="export"]')?.addEventListener('click', () => {
            const ids = [...document.querySelectorAll('.row-select:checked')].map(cb => cb.value);
            ids.forEach(id => {
                const url = window.customersTableUrl.replace('/datatable', `/${id}/export`);
                window.open(url, '_blank');
            });
        });

        document.getElementById('export-all-btn')?.addEventListener('click', () => {
            window.Toast?.success(window.TRANSLATIONS?.useFiltersExportHint || 'Use the table filters and "Export Selected" for bulk export.');
        });

        // ── Row actions (suspend/ban/reactivate) delegated ─────────────────
        $(customersTable).on('click', '.js-suspend-btn', function () {
            openSuspendModal(this.dataset.url, this.dataset.name);
        });
        $(customersTable).on('click', '.js-ban-btn', function () {
            openBanModal(this.dataset.url, this.dataset.name);
        });
        $(customersTable).on('click', '.js-reactivate-btn', function () {
            handleReactivate(this.dataset.url, this.dataset.name);
        });
    }

    /* ─────────────────────────────────────────────────────────────────────────
     * SHOW PAGE — static row action buttons in sidebar
     * ─────────────────────────────────────────────────────────────────────── */

    document.querySelectorAll('.js-suspend-btn[data-modal="suspend-modal"]').forEach(btn => {
        btn.addEventListener('click', () => openSuspendModal(btn.dataset.url, btn.dataset.name));
    });

    document.querySelectorAll('.js-ban-btn[data-modal="ban-modal"]').forEach(btn => {
        btn.addEventListener('click', () => openBanModal(btn.dataset.url, btn.dataset.name));
    });

    document.querySelectorAll('.js-reactivate-btn:not([data-modal])').forEach(btn => {
        btn.addEventListener('click', () => handleReactivate(btn.dataset.url, btn.dataset.name));
    });

    /* ─────────────────────────────────────────────────────────────────────────
     * SUSPEND MODAL
     * ─────────────────────────────────────────────────────────────────────── */

    let activeSuspendUrl = null;

    function openSuspendModal(url, name) {
        activeSuspendUrl = url;
        const nameEl = document.getElementById('suspend-customer-name');
        if (nameEl) nameEl.textContent = name || '';
        const reasonEl = document.getElementById('suspend-reason');
        if (reasonEl) reasonEl.value = '';
        $('#suspend-modal').modal('open');
    }

    document.getElementById('suspend-confirm-btn')?.addEventListener('click', async () => {
        const url = activeSuspendUrl || document.getElementById('suspend-confirm-btn')?.dataset.url;
        if (!url) return;

        const reason = document.getElementById('suspend-reason')?.value?.trim();
        if (!reason) {
            window.Toast?.error(window.TRANSLATIONS?.enterReason || 'Please enter a reason.');
            return;
        }

        const btn = document.getElementById('suspend-confirm-btn');
        setLoading(btn, true, window.TRANSLATIONS?.suspend || 'Suspend');

        try {
            await postJson(url, { reason });
            $('#suspend-modal').modal('close');
            window.Toast?.success(window.TRANSLATIONS?.customerSuspended || 'Customer suspended.');
            setTimeout(() => location.reload(), 800);
        } catch (err) {
            const msg = err?.message || Object.values(err?.errors || {}).flat().join(' ') || window.TRANSLATIONS?.actionFailed || 'Action failed.';
            window.Toast?.error(msg);
        } finally {
            setLoading(btn, false, window.TRANSLATIONS?.suspend || 'Suspend');
        }
    });

    /* ─────────────────────────────────────────────────────────────────────────
     * BAN MODAL — requires typed "CONFIRM"
     * ─────────────────────────────────────────────────────────────────────── */

    let activeBanUrl = null;

    function openBanModal(url, name) {
        activeBanUrl = url;
        const nameEl = document.getElementById('ban-customer-name');
        if (nameEl) nameEl.textContent = name || '';
        const reasonEl = document.getElementById('ban-reason');
        if (reasonEl) reasonEl.value = '';
        const confirmInput = document.getElementById('ban-confirm-input');
        if (confirmInput) confirmInput.value = '';
        const confirmBtn = document.getElementById('ban-confirm-btn');
        if (confirmBtn) confirmBtn.disabled = true;
        $('#ban-modal').modal('open');
    }

    // Live enable/disable of ban button based on typed confirmation
    document.getElementById('ban-confirm-input')?.addEventListener('input', (e) => {
        const confirmBtn = document.getElementById('ban-confirm-btn');
        if (confirmBtn) {
            confirmBtn.disabled = e.target.value.trim() !== 'CONFIRM';
        }
    });

    document.getElementById('ban-confirm-btn')?.addEventListener('click', async () => {
        const url = activeBanUrl || document.getElementById('ban-confirm-btn')?.dataset.url;
        if (!url) return;

        const reason = document.getElementById('ban-reason')?.value?.trim();
        const confirmText = document.getElementById('ban-confirm-input')?.value?.trim();

        if (!reason) {
            window.Toast?.error(window.TRANSLATIONS?.enterReason || 'Please enter a reason.');
            return;
        }
        if (confirmText !== 'CONFIRM') {
            window.Toast?.error(window.TRANSLATIONS?.typeConfirmToProceed || 'Please type CONFIRM to proceed.');
            return;
        }

        const btn = document.getElementById('ban-confirm-btn');
        setLoading(btn, true, window.TRANSLATIONS?.banCustomer || 'Ban Customer');

        try {
            await postJson(url, { reason });
            $('#ban-modal').modal('close');
            window.Toast?.success(window.TRANSLATIONS?.customerBanned || 'Customer banned.');
            setTimeout(() => location.reload(), 800);
        } catch (err) {
            const msg = err?.message || Object.values(err?.errors || {}).flat().join(' ') || window.TRANSLATIONS?.actionFailed || 'Action failed.';
            window.Toast?.error(msg);
        } finally {
            setLoading(btn, false, window.TRANSLATIONS?.banCustomer || 'Ban Customer');
        }
    });

    /* ─────────────────────────────────────────────────────────────────────────
     * REACTIVATE
     * ─────────────────────────────────────────────────────────────────────── */

    async function handleReactivate(url, name) {
        if (!confirm((window.TRANSLATIONS?.reactivateConfirm || 'Reactivate :name?').replace(':name', name || (window.TRANSLATIONS?.thisCustomer || 'this customer')))) return;
        try {
            await postJson(url, {});
            window.Toast?.success(window.TRANSLATIONS?.customerReactivated || 'Customer reactivated.');
            setTimeout(() => location.reload(), 800);
        } catch (err) {
            window.Toast?.error(err?.message || window.TRANSLATIONS?.actionFailed || 'Action failed.');
        }
    }

    /* ─────────────────────────────────────────────────────────────────────────
     * ADJUST LOYALTY POINTS — live preview + modal
     * ─────────────────────────────────────────────────────────────────────── */

    document.getElementById('open-loyalty-modal')?.addEventListener('click', () => {
        const adjustmentInput = document.getElementById('adjustment-input');
        if (adjustmentInput) adjustmentInput.value = '';
        const previewEl = document.getElementById('preview-loyalty');
        const currentEl = document.getElementById('current-loyalty');
        if (previewEl && currentEl) previewEl.textContent = currentEl.textContent;
        $('#adjust-loyalty-modal').modal('open');
    });

    document.getElementById('adjustment-input')?.addEventListener('input', (e) => {
        const currentBalance = parseFloat(e.target.dataset.current || '0');
        const adjustment = parseFloat(e.target.value) || 0;
        const newBalance = currentBalance + adjustment;
        const previewEl = document.getElementById('preview-loyalty');
        if (previewEl) {
            previewEl.textContent = newBalance.toFixed(2);
            previewEl.className = newBalance < 0
                ? 'font-bold text-danger-700'
                : 'font-bold text-primary-900';
        }
    });

    document.getElementById('loyalty-confirm-btn')?.addEventListener('click', async () => {
        const url = document.getElementById('loyalty-confirm-btn')?.dataset.url;
        if (!url) return;

        const adjustment = parseInt(document.getElementById('adjustment-input')?.value, 10);
        const reason = document.getElementById('loyalty-reason')?.value?.trim();

        if (!adjustment || adjustment === 0) {
            window.Toast?.error(window.TRANSLATIONS?.enterNonZeroAdjustment || 'Please enter a non-zero adjustment.');
            return;
        }
        if (!reason) {
            window.Toast?.error(window.TRANSLATIONS?.enterReason || 'Please enter a reason.');
            return;
        }

        const btn = document.getElementById('loyalty-confirm-btn');
        setLoading(btn, true, window.TRANSLATIONS?.apply || 'Apply');

        try {
            const data = await postJson(url, { adjustment, reason });
            $('#adjust-loyalty-modal').modal('close');
            window.Toast?.success(window.TRANSLATIONS?.loyaltyPointsAdjusted || 'Loyalty points adjusted.');

            // Update displayed balance
            const display = document.getElementById('loyalty-balance-display');
            if (display && data.new_balance) display.textContent = data.new_balance;
            const currentInput = document.getElementById('adjustment-input');
            if (currentInput && data.new_balance) currentInput.dataset.current = data.new_balance.replace(/,/g, '');
            const currentEl = document.getElementById('current-loyalty');
            if (currentEl && data.new_balance) currentEl.textContent = data.new_balance;
        } catch (err) {
            const msg = err?.message || Object.values(err?.errors || {}).flat().join(' ') || window.TRANSLATIONS?.actionFailed || 'Action failed.';
            window.Toast?.error(msg);
        } finally {
            setLoading(btn, false, window.TRANSLATIONS?.apply || 'Apply');
        }
    });

    /* ─────────────────────────────────────────────────────────────────────────
     * SEND NOTIFICATION MODAL
     * ─────────────────────────────────────────────────────────────────────── */

    document.getElementById('open-notification-modal')?.addEventListener('click', () => {
        document.getElementById('notif-title').value = '';
        document.getElementById('notif-message').value = '';
        document.getElementById('notif-channel').value = 'database';
        $('#send-notification-modal').modal('open');
    });

    document.getElementById('notif-send-btn')?.addEventListener('click', async () => {
        const url = document.getElementById('notif-send-btn')?.dataset.url;
        if (!url) return;

        const channel = document.getElementById('notif-channel')?.value;
        const title = document.getElementById('notif-title')?.value?.trim();
        const message = document.getElementById('notif-message')?.value?.trim();

        if (!title || !message) {
            window.Toast?.error(window.TRANSLATIONS?.fillAllFields || 'Please fill in all fields.');
            return;
        }

        const btn = document.getElementById('notif-send-btn');
        setLoading(btn, true, window.TRANSLATIONS?.send || 'Send');

        try {
            await postJson(url, { channel, title, message });
            $('#send-notification-modal').modal('close');
            window.Toast?.success(window.TRANSLATIONS?.notificationSent || 'Notification sent.');
        } catch (err) {
            const msg = err?.message || Object.values(err?.errors || {}).flat().join(' ') || window.TRANSLATIONS?.failedToSend || 'Failed to send.';
            window.Toast?.error(msg);
        } finally {
            setLoading(btn, false, window.TRANSLATIONS?.send || 'Send');
        }
    });

    /* ─────────────────────────────────────────────────────────────────────────
     * SHOW PAGE — Orders full DataTable
     * ─────────────────────────────────────────────────────────────────────── */

    const showOrdersDtBtn = document.getElementById('show-orders-datatable-btn');
    if (showOrdersDtBtn) {
        let ordersDtInitialized = false;
        showOrdersDtBtn.addEventListener('click', () => {
            const section = document.getElementById('orders-datatable-section');
            if (!section) return;
            const isHidden = section.classList.toggle('hidden');
            showOrdersDtBtn.textContent = isHidden ? (window.TRANSLATIONS?.fullListDatatable || 'Full list (DataTable)') : (window.TRANSLATIONS?.hideDatatable || 'Hide DataTable');
            if (!ordersDtInitialized && !isHidden) {
                ordersDtInitialized = true;
                $('#orders-datatable').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: showOrdersDtBtn.dataset.url,
                        type: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                    },
                    columns: [
                        { data: 'order_number' },
                        { data: 'total' },
                        { data: 'status', orderable: false },
                        { data: 'items', orderable: false },
                        { data: 'placed_at' },
                    ],
                    order: [[4, 'desc']],
                    pageLength: 25,
                    language: { search: '', searchPlaceholder: t('shared.table_search_placeholder') },
                });
            }
        });
    }

    /* ─────────────────────────────────────────────────────────────────────────
     * SHOW PAGE — Wallet & Credits tab
     * ─────────────────────────────────────────────────────────────────────── */

    const walletTransactionsTable = document.getElementById('wallet-transactions-datatable');
    if (walletTransactionsTable) {
        let walletDtInitialized = false;
        const walletTabBtn = document.querySelector('[\\@click="tab = \'wallet_credits\'"]');

        function initWalletDataTable() {
            if (walletDtInitialized) return;
            walletDtInitialized = true;
            $(walletTransactionsTable).DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: walletTransactionsTable.dataset.url,
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                },
                columns: [
                    { data: 'created_at' },
                    { data: 'type', orderable: false },
                    { data: 'direction', orderable: false },
                    { data: 'amount' },
                    { data: 'balance_after' },
                    { data: 'reference', orderable: false },
                    { data: 'note', orderable: false },
                ],
                order: [[0, 'desc']],
                pageLength: 25,
                language: { search: '', searchPlaceholder: t('shared.table_search_placeholder') },
            });
        }

        if (walletTabBtn) {
            walletTabBtn.addEventListener('click', initWalletDataTable);
        } else {
            // Fallback: table may already be visible (e.g. deep-linked tab)
            initWalletDataTable();
        }
    }

    document.getElementById('wallet-adjust-submit-btn')?.addEventListener('click', async function () {
        const url = this.dataset.url;
        if (!url) return;

        const direction = document.getElementById('wallet-adjust-direction')?.value;
        const amount = parseInt(document.getElementById('wallet-adjust-amount')?.value, 10);
        const note = document.getElementById('wallet-adjust-note')?.value?.trim();

        if (!amount || amount < 1) {
            window.Toast?.error(window.TRANSLATIONS?.enterValidAmount || 'Please enter a valid amount (at least 1).');
            return;
        }
        if (!note) {
            window.Toast?.error(window.TRANSLATIONS?.enterNote || 'Please enter a note explaining this adjustment.');
            return;
        }

        setLoading(this, true, window.TRANSLATIONS?.submitAdjustment || 'Submit Adjustment');

        try {
            const data = await postJson(url, { direction, amount, note });
            window.Toast?.success(window.TRANSLATIONS?.walletAdjusted || 'Wallet adjusted successfully.');

            const balanceEl = document.getElementById('wallet-current-balance');
            if (balanceEl && typeof data.new_balance !== 'undefined') {
                const currencySuffix = balanceEl.textContent.trim().split(' ').slice(1).join(' ');
                balanceEl.textContent = `${Number(data.new_balance).toLocaleString()} ${currencySuffix}`.trim();
            }

            document.getElementById('wallet-adjust-amount').value = '';
            document.getElementById('wallet-adjust-note').value = '';

            if ($.fn.DataTable.isDataTable('#wallet-transactions-datatable')) {
                $('#wallet-transactions-datatable').DataTable().draw(false);
            }
        } catch (err) {
            const msg = err?.message || Object.values(err?.errors || {}).flat().join(' ') || window.TRANSLATIONS?.actionFailed || 'Action failed.';
            window.Toast?.error(msg);
        } finally {
            setLoading(this, false, window.TRANSLATIONS?.submitAdjustment || 'Submit Adjustment');
        }
    });

    /* ─────────────────────────────────────────────────────────────────────────
     * COPYABLE FIELDS
     * ─────────────────────────────────────────────────────────────────────── */

    /* ─────────────────────────────────────────────────────────────────────────
     * REGENERATE QR CODE
     * ─────────────────────────────────────────────────────────────────────── */

    document.getElementById('regenerate-qr-btn')?.addEventListener('click', async function () {
        const url = this.dataset.url;
        if (!url) return;

        setLoading(this, true, window.TRANSLATIONS?.regenerateQrCode || 'Regenerate QR Code');

        try {
            const data = await postJson(url);
            const img = this.parentElement.querySelector('img');
            if (img && data.qr_url) {
                img.src = data.qr_url + '?t=' + Date.now();
            }
            window.Toast?.success(window.TRANSLATIONS?.qrCodeRegenerated || 'QR code regenerated.');
        } catch (err) {
            window.Toast?.error(err?.message || window.TRANSLATIONS?.actionFailed || 'Action failed.');
        } finally {
            setLoading(this, false, window.TRANSLATIONS?.regenerateQrCode || 'Regenerate QR Code');
        }
    });

    /* ─────────────────────────────────────────────────────────────────────────
     * REVOKE ALL DEVICES
     * ─────────────────────────────────────────────────────────────────────── */

    document.getElementById('revoke-devices-btn')?.addEventListener('click', async function () {
        const url = this.dataset.url;
        if (!url) return;
        if (!confirm(window.TRANSLATIONS?.revokeDevicesConfirm || 'Log out all devices for this customer?')) return;

        setLoading(this, true, window.TRANSLATIONS?.revokeAllDevices || 'Log Out All Devices');

        try {
            await postJson(url);
            document.querySelectorAll('.js-device-row').forEach(row => row.remove());
            const empty = document.getElementById('devices-empty-state');
            if (empty) empty.classList.remove('hidden');
            window.Toast?.success(window.TRANSLATIONS?.devicesRevoked || 'All devices logged out.');
        } catch (err) {
            window.Toast?.error(err?.message || window.TRANSLATIONS?.actionFailed || 'Action failed.');
        } finally {
            setLoading(this, false, window.TRANSLATIONS?.revokeAllDevices || 'Log Out All Devices');
        }
    });

    document.querySelectorAll('.js-copy').forEach(btn => {
        btn.addEventListener('click', () => {
            const value = btn.dataset.value;
            if (!value) return;
            navigator.clipboard.writeText(value).then(() => {
                const original = btn.textContent;
                btn.textContent = window.TRANSLATIONS?.copied || 'Copied!';
                setTimeout(() => { btn.textContent = original; }, 1500);
            }).catch(() => window.Toast?.error(window.TRANSLATIONS?.copyFailed || 'Copy failed.'));
        });
    });

});
