/**
 * resources/js/admin/marketer-tiers.js
 * Dynamic commission tier editor used in admin/marketers/tiers/show.blade.php
 *
 * window.existingTiers must be set BEFORE this script runs:
 *   window.existingTiers = @json($tiers->map(...)->values());
 *
 * Format: [{ min_sales_count, max_sales_count, commission_rate }, ...]
 */

document.addEventListener('DOMContentLoaded', () => {
    const T = {
        minSales: t('admin.marketer_tiers.min_sales_label'),
        maxSalesBlank: t('admin.marketer_tiers.max_sales_blank_label'),
        leaveBlankForInfinity: t('admin.marketer_tiers.leave_blank_for_infinity'),
        commissionPercent: t('admin.marketer_tiers.commission_percent_label'),
        removeTier: t('admin.marketer_tiers.remove_tier_label'),
        addTier: t('admin.marketer_tiers.add_tier_label'),
        tierMinSalesCommissionRequired: t('admin.marketer_tiers.tier_min_sales_commission_required'),
        tierMaxSalesRequired: t('admin.marketer_tiers.tier_max_sales_required'),
        saving: t('shared.saving'),
        tiersSaved: t('admin.marketer_tiers.tiers_saved'),
        failedToSaveTiers: t('admin.marketer_tiers.save_tiers_failed'),
        networkErrorRetry: t('admin.marketer_tiers.network_error_retry'),
        saveTiers: t('admin.marketer_tiers.save_tiers_label'),
    };
    const container = document.getElementById('tiers-container');
    const saveBtn   = document.getElementById('save-tiers-btn');
    if (!container || !saveBtn) return;

    let tiers = Array.isArray(window.existingTiers) && window.existingTiers.length > 0
        ? JSON.parse(JSON.stringify(window.existingTiers))
        : [{ min_sales_count: 0, max_sales_count: 50, commission_rate: 5 }];

    function render() {
        container.innerHTML = '';

        tiers.forEach((tier, i) => {
            const row = document.createElement('div');
            row.className = 'flex items-center gap-3 mb-3 p-3 bg-gray-50 rounded-xl border border-gray-200';
            row.innerHTML = `
                <div class="w-7 h-7 rounded-full bg-primary-500 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">${i + 1}</div>
                <div class="flex-1 grid grid-cols-3 gap-3">
                    <div>
                        <label class="text-xs text-gray-400 block mb-1">${T.minSales}</label>
                        <input type="number" min="0" class="form-input text-sm py-1.5 tier-min"
                            value="${tier.min_sales_count}" placeholder="0">
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 block mb-1">${T.maxSalesBlank}</label>
                        <input type="number" min="0" class="form-input text-sm py-1.5 tier-max"
                            value="${tier.max_sales_count ?? ''}" placeholder="${T.leaveBlankForInfinity}">
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 block mb-1">${T.commissionPercent}</label>
                        <input type="number" min="0" max="100" step="0.01" class="form-input text-sm py-1.5 tier-rate"
                            value="${tier.commission_rate}" placeholder="e.g. 8">
                    </div>
                </div>
                <button type="button" class="text-red-400 hover:text-red-600 flex-shrink-0 text-lg leading-none btn-remove-tier" data-index="${i}" title="${T.removeTier}">×</button>
            `;
            container.appendChild(row);
        });

        // Add tier button
        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'mt-1 text-sm text-blue-600 hover:underline flex items-center gap-1';
        addBtn.innerHTML = '+ ' + T.addTier;
        addBtn.addEventListener('click', () => {
            const last = tiers[tiers.length - 1];
            const newMin = last?.max_sales_count ? last.max_sales_count + 1 : (last?.min_sales_count ?? 0) + 50;
            tiers.push({ min_sales_count: newMin, max_sales_count: null, commission_rate: (last?.commission_rate ?? 5) + 2 });
            render();
        });
        container.appendChild(addBtn);

        // Bind remove
        container.querySelectorAll('.btn-remove-tier').forEach((btn) => {
            btn.addEventListener('click', () => {
                tiers.splice(parseInt(btn.dataset.index), 1);
                render();
            });
        });

        // Bind input changes
        container.querySelectorAll('.tier-min').forEach((el, i) => {
            el.addEventListener('input', () => { tiers[i].min_sales_count = parseInt(el.value) || 0; });
        });
        container.querySelectorAll('.tier-max').forEach((el, i) => {
            el.addEventListener('input', () => { tiers[i].max_sales_count = el.value !== '' ? parseInt(el.value) : null; });
        });
        container.querySelectorAll('.tier-rate').forEach((el, i) => {
            el.addEventListener('input', () => { tiers[i].commission_rate = parseFloat(el.value) || 0; });
        });
    }

    render();

    // ── Save ──────────────────────────────────────────────────────────────────
    saveBtn.addEventListener('click', async () => {
        const url  = saveBtn.dataset.url;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        if (!url) return;

        // Basic validation
        for (let i = 0; i < tiers.length; i++) {
            if (tiers[i].min_sales_count < 0 || tiers[i].commission_rate <= 0) {
                alert(T.tierMinSalesCommissionRequired.replace(':n', i + 1));
                return;
            }
            if (tiers[i].max_sales_count !== null && tiers[i].max_sales_count < tiers[i].min_sales_count) {
                alert(T.tierMaxSalesRequired.replace(':n', i + 1));
                return;
            }
        }

        saveBtn.disabled = true;
        saveBtn.textContent = T.saving;

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ tiers }),
            });
            const data = await res.json();

            if (data.success ?? res.ok) {
                if (window.Toast) window.Toast.success(data.message ?? T.tiersSaved);
                else alert(data.message ?? T.tiersSaved);
            } else {
                const msg = data.message ?? T.failedToSaveTiers;
                if (window.Toast) window.Toast.error(msg);
                else alert(msg);
            }
        } catch {
            const msg = T.networkErrorRetry;
            if (window.Toast) window.Toast.error(msg);
            else alert(msg);
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = T.saveTiers;
        }
    });
});
