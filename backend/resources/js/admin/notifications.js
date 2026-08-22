/**
 * notifications.js — Notification Management JS
 *
 * Handles:
 *  - DataTable initialisation (index page)
 *  - Manual broadcast form (send page): target selector, character counters, submit
 */
document.addEventListener('DOMContentLoaded', () => {

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

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
        if (label) btn.textContent = loading ? (t('admin.notifications.please_wait')) : label;
    }

    /* ─────────────────────────────────────────────────────────────────────────
     * INDEX PAGE — DataTable
     * ─────────────────────────────────────────────────────────────────────── */

    const notificationsTable = document.getElementById('notifications-table');
    if (notificationsTable && window.notificationsTableUrl) {
        const dt = $(notificationsTable).DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: window.notificationsTableUrl,
                type: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                data(d) {
                    d.channel = document.getElementById('filter-channel')?.value || '';
                    d.type = document.getElementById('filter-type')?.value || '';
                    d.date_from = document.getElementById('filter-date-from')?.value || '';
                    d.date_to = document.getElementById('filter-date-to')?.value || '';
                },
            },
            columns: [
                { data: 'type' },
                { data: 'channel', orderable: false },
                { data: 'recipient', orderable: false, searchable: false },
                { data: 'title', orderable: false, searchable: false },
                { data: 'read', orderable: false, searchable: false },
                { data: 'sent_at' },
            ],
            order: [[5, 'desc']],
            pageLength: 25,
            language: { search: '', searchPlaceholder: t('shared.table_search_placeholder') },
        });

        ['filter-channel', 'filter-type', 'filter-date-from', 'filter-date-to'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', () => dt.draw());
        });
        document.getElementById('filter-type')?.addEventListener('keyup', () => dt.draw());

        document.getElementById('clear-filters')?.addEventListener('click', () => {
            ['filter-channel', 'filter-type', 'filter-date-from', 'filter-date-to'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            dt.draw();
        });
    }

    /* ─────────────────────────────────────────────────────────────────────────
     * SEND PAGE — target selector + character counters + submit
     * ─────────────────────────────────────────────────────────────────────── */

    const sendForm = document.getElementById('send-notification-form');
    if (sendForm) {
        const targetSelect = document.getElementById('target');
        const countryField = document.getElementById('country-field');
        const customerIdsField = document.getElementById('customer-ids-field');

        function updateTargetFields() {
            const target = targetSelect?.value;
            countryField?.classList.toggle('hidden', target !== 'country');
            customerIdsField?.classList.toggle('hidden', target !== 'specific');
        }
        targetSelect?.addEventListener('change', updateTargetFields);
        updateTargetFields();

        // Character counters
        [
            ['title_en', 100, 'title-en-count'],
            ['title_ar', 100, 'title-ar-count'],
            ['body_en', 500, 'body-en-count'],
            ['body_ar', 500, 'body-ar-count'],
        ].forEach(([inputId, max, counterId]) => {
            const input = document.getElementById(inputId);
            const counter = document.getElementById(counterId);
            if (!input || !counter) return;
            input.addEventListener('input', () => {
                counter.textContent = max - input.value.length;
            });
        });

        document.getElementById('send-broadcast-btn')?.addEventListener('click', async () => {
            const btn = document.getElementById('send-broadcast-btn');
            const url = btn.dataset.url;

            const target = targetSelect?.value;
            const countryId = document.getElementById('country_id')?.value || null;
            const customerIds = (document.getElementById('customer_ids')?.value || '')
                .split('\n')
                .map(s => s.trim())
                .filter(Boolean);
            const titleEn = document.getElementById('title_en')?.value?.trim();
            const titleAr = document.getElementById('title_ar')?.value?.trim();
            const bodyEn = document.getElementById('body_en')?.value?.trim();
            const bodyAr = document.getElementById('body_ar')?.value?.trim();
            const channels = [...document.querySelectorAll('.channel-checkbox:checked')].map(cb => cb.value);

            if (!titleEn || !titleAr || !bodyEn || !bodyAr) {
                window.Toast?.error(t('admin.notifications.fill_required_fields'));
                return;
            }
            if (!channels.length) {
                window.Toast?.error(t('admin.notifications.select_one_channel'));
                return;
            }

            setLoading(btn, true, t('admin.notifications.send_broadcast_label'));

            try {
                await postJson(url, {
                    target,
                    country_id: target === 'country' ? countryId : null,
                    customer_ids: target === 'specific' ? customerIds : [],
                    title_en: titleEn,
                    title_ar: titleAr,
                    body_en: bodyEn,
                    body_ar: bodyAr,
                    channels,
                });
                window.Toast?.success(t('admin.notifications.broadcast_queued'));
                sendForm.reset();
                updateTargetFields();
            } catch (err) {
                const msg = err?.message || Object.values(err?.errors || {}).flat().join(' ') || t('admin.notifications.failed_send_broadcast');
                window.Toast?.error(msg);
            } finally {
                setLoading(btn, false, t('admin.notifications.send_broadcast_label'));
            }
        });
    }

});
