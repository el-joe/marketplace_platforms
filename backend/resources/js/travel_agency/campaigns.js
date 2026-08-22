/**
 * Travel Agency → Campaign Offers JS
 * Handles: 3-step create wizard, show-page actions (status transitions,
 * marketer search + invite queue, revoke invitation).
 * Mirrors resources/js/partner/campaign-offers.js with product/listing
 * terminology swapped for travel packages.
 */

const cfg = window.CAMPAIGN_OFFER_CONFIG || {};

// ─── Utilities ────────────────────────────────────────────────────────────────

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function apiPost(url, body = {}, method = 'POST') {
    const res = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
        },
        body: JSON.stringify(body),
    });
    return { ok: res.ok, status: res.status, data: await res.json() };
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('action-toast');
    if (!toast) return;
    toast.textContent = message;
    toast.className = [
        'fixed top-5 end-5 z-50 max-w-sm rounded-xl border px-4 py-3 text-sm shadow-lg transition-all',
        type === 'success'
            ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
            : 'bg-red-50 border-red-200 text-red-800',
    ].join(' ');
    toast.classList.remove('hidden');
    setTimeout(() => toast.classList.add('hidden'), 3500);
}

function debounce(fn, delay = 300) {
    let timer;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), delay); };
}

// ─── CREATE WIZARD ────────────────────────────────────────────────────────────

function initCreateWizard() {
    const form = document.getElementById('campaign-offer-form');
    if (!form) return;

    let currentStep = 1;
    const totalSteps = 3;
    const selectedPackages = new Map(); // packageId → { name, override }

    // Step navigation helpers
    function showStep(step) {
        document.querySelectorAll('.step-panel').forEach(p => {
            p.classList.toggle('hidden', parseInt(p.dataset.panel) !== step);
        });

        document.querySelectorAll('.step-circle').forEach(el => {
            const s = parseInt(el.dataset.step);
            el.classList.toggle('bg-primary-600', s <= step);
            el.classList.toggle('text-white', s <= step);
            el.classList.toggle('border-primary-600', s <= step);
            el.classList.toggle('border-gray-300', s > step);
            el.classList.toggle('text-gray-400', s > step);
            el.classList.toggle('bg-white', s > step);
        });

        document.querySelectorAll('.step-label').forEach(el => {
            const s = parseInt(el.dataset.step);
            el.classList.toggle('text-primary-600', s <= step);
            el.classList.toggle('text-gray-400', s > step);
        });

        document.querySelectorAll('.step-connector').forEach(el => {
            const after = parseInt(el.dataset.after);
            el.classList.toggle('bg-primary-500', after < step);
            el.classList.toggle('bg-gray-200', after >= step);
        });

        document.getElementById('btn-prev').classList.toggle('hidden', step === 1);
        document.getElementById('btn-next').classList.toggle('hidden', step === totalSteps);
        document.getElementById('btn-save-draft').classList.toggle('hidden', step !== totalSteps);
        document.getElementById('btn-save-invite').classList.toggle('hidden', step !== totalSteps);

        if (step === totalSteps) updateSummary();
    }

    function validateStep(step) {
        if (step === 1) {
            const name = form.querySelector('#name').value.trim();
            const type = form.querySelector('#campaign_type').value;
            const starts = form.querySelector('#starts_at').value;
            const ends = form.querySelector('#ends_at').value;
            const attr = form.querySelector('#attribution_model').value;
            if (!name) { alert(window.TRAVEL_AGENCY_TRANSLATIONS?.enterCampaignName ?? 'Please enter a campaign name.'); return false; }
            if (!type) { alert(window.TRAVEL_AGENCY_TRANSLATIONS?.selectCampaignType ?? 'Please select a campaign type.'); return false; }
            if (!starts || !ends) { alert(window.TRAVEL_AGENCY_TRANSLATIONS?.selectCampaignDates ?? 'Please select the campaign dates.'); return false; }
            if (!attr) { alert(window.TRAVEL_AGENCY_TRANSLATIONS?.selectAttributionModel ?? 'Please select an attribution model.'); return false; }
            return true;
        }
        if (step === 2) {
            const rate = form.querySelector('#offered_commission_rate').value;
            const commType = form.querySelector('[name="commission_type"]:checked')?.value;
            if (!rate || isNaN(rate) || rate < 0 || rate > 100) {
                alert(window.TRAVEL_AGENCY_TRANSLATIONS?.enterValidCommissionRate ?? 'Please enter a valid commission rate between 0 and 100.');
                return false;
            }
            if (!commType) { alert(window.TRAVEL_AGENCY_TRANSLATIONS?.selectCommissionType ?? 'Please select a commission type.'); return false; }
            return true;
        }
        if (step === 3) {
            if (selectedPackages.size === 0) {
                document.getElementById('no-packages-msg').classList.remove('hidden');
                return false;
            }
            return true;
        }
        return true;
    }

    document.getElementById('btn-next').addEventListener('click', () => {
        if (!validateStep(currentStep)) return;
        currentStep++;
        showStep(currentStep);
    });

    document.getElementById('btn-prev').addEventListener('click', () => {
        currentStep--;
        showStep(currentStep);
    });

    // Commission rate live preview
    const rateInput = form.querySelector('#offered_commission_rate');
    const preview = document.getElementById('commission-preview');
    if (rateInput && preview) {
        rateInput.addEventListener('input', () => {
            preview.textContent = (parseFloat(rateInput.value) || 0).toFixed(2) + '%';
        });
    }

    // WhatsApp toggle
    const waToggle = document.getElementById('whatsapp-toggle');
    const waInput  = document.getElementById('whatsapp_sharing_enabled_input');
    if (waToggle && waInput) {
        waToggle.addEventListener('click', () => {
            const isOn = waToggle.getAttribute('aria-checked') === 'true';
            waToggle.setAttribute('aria-checked', !isOn);
            waToggle.classList.toggle('bg-primary-600', !isOn);
            waToggle.classList.toggle('bg-gray-200', isOn);
            waToggle.querySelector('span').classList.toggle('translate-x-5', !isOn);
            waToggle.querySelector('span').classList.toggle('translate-x-0', isOn);
            waInput.value = !isOn ? '1' : '0';
        });
    }

    // Package search (Step 3)
    let packageSearchTimer;
    const packageInput = document.getElementById('package-search-input');
    const packageResults = document.getElementById('package-results');

    if (packageInput) {
        packageInput.addEventListener('input', () => {
            clearTimeout(packageSearchTimer);
            const q = packageInput.value.trim();
            if (q.length < 2) { packageResults.classList.add('hidden'); return; }
            packageSearchTimer = setTimeout(() => searchPackages(q), 300);
        });
    }

    async function searchPackages(q) {
        const res = await fetch(`${cfg.packagesUrl}?q=${encodeURIComponent(q)}`, {
            headers: { 'Accept': 'application/json' },
        });
        if (!res.ok) return;
        const rows = await res.json();
        renderPackageResults(rows ?? []);
    }

    function renderPackageResults(rows) {
        packageResults.innerHTML = '';
        if (!rows.length) {
            packageResults.innerHTML = `<p class="px-4 py-3 text-xs text-gray-400">${window.t('travel_agency.campaigns.no_results')}</p>`;
            packageResults.classList.remove('hidden');
            return;
        }
        rows.forEach(row => {
            const id = row.id;
            const name = row.name ?? window.t('travel_agency.campaigns.unnamed_package');
            if (selectedPackages.has(id)) return; // already selected
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'w-full text-right px-4 py-2.5 text-sm hover:bg-gray-50 flex items-center justify-between gap-3';
            btn.innerHTML = `
                <span class="min-w-0 truncate">
                    <span class="block truncate">${name}</span>
                    <span class="block text-xs text-gray-400 truncate">${row.destination ?? ''}</span>
                </span>
                <span class="text-xs text-primary-600 font-medium flex-shrink-0">${window.t('travel_agency.campaigns.add_label')}</span>`;
            btn.addEventListener('click', () => {
                addPackage(id, name);
                packageResults.classList.add('hidden');
                packageInput.value = '';
            });
            packageResults.appendChild(btn);
        });
        packageResults.classList.remove('hidden');
    }

    function addPackage(id, name) {
        if (selectedPackages.has(id)) return;
        selectedPackages.set(id, { name, override: null });
        renderSelectedPackages();
        syncHiddenInputs();
    }

    function removePackage(id) {
        selectedPackages.delete(id);
        renderSelectedPackages();
        syncHiddenInputs();
    }

    function renderSelectedPackages() {
        const container = document.getElementById('selected-packages');
        document.getElementById('no-packages-msg').classList.add('hidden');
        container.innerHTML = '';
        selectedPackages.forEach((data, id) => {
            const card = document.createElement('div');
            card.className = 'flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3';
            card.innerHTML = `
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-900 truncate">${data.name}</p>
                    <div class="mt-1 flex items-center gap-2">
                        <label class="text-xs text-gray-500">${window.t('travel_agency.campaigns.custom_commission_label')}</label>
                        <input type="number" min="0" max="100" step="0.01" placeholder="—"
                               class="w-20 rounded border border-gray-300 px-1.5 py-0.5 text-xs focus:border-primary-500 focus:outline-none"
                               data-override-id="${id}" value="${data.override ?? ''}">
                        <span class="text-xs text-gray-400">%</span>
                    </div>
                </div>
                <button type="button" class="text-gray-400 hover:text-red-500 flex-shrink-0" data-remove-id="${id}">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            `;
            card.querySelector(`[data-remove-id]`).addEventListener('click', () => removePackage(id));
            card.querySelector(`[data-override-id]`).addEventListener('input', e => {
                const v = parseFloat(e.target.value);
                selectedPackages.get(id).override = isNaN(v) ? null : v;
                syncHiddenInputs();
            });
            container.appendChild(card);
        });
        syncHiddenInputs();
    }

    function syncHiddenInputs() {
        const packageContainer  = document.getElementById('package-inputs');
        const overrideContainer = document.getElementById('commission-override-inputs');
        packageContainer.innerHTML = '';
        overrideContainer.innerHTML = '';
        let i = 0;
        selectedPackages.forEach((data, id) => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = `package_ids[]`;
            inp.value = id;
            packageContainer.appendChild(inp);

            if (data.override !== null) {
                ['package_id', 'rate'].forEach(field => {
                    const ov = document.createElement('input');
                    ov.type = 'hidden';
                    ov.name = `commission_overrides[${i}][${field}]`;
                    ov.value = field === 'package_id' ? id : data.override;
                    overrideContainer.appendChild(ov);
                });
                i++;
            }
        });
    }

    function updateSummary() {
        const typeLabels = {
            product_promotion: window.t('travel_agency.campaigns.types.product_promotion'),
            store_promotion: window.t('travel_agency.campaigns.types.store_promotion'),
            brand_deal: window.t('travel_agency.campaigns.types.brand_deal'),
            product_specific: window.t('travel_agency.campaigns.types.product_specific'),
            flash_sale: window.t('travel_agency.campaigns.types.flash_sale'),
        };
        const safeSet = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
        safeSet('summary-name', form.querySelector('#name').value || '—');
        safeSet('summary-type', typeLabels[form.querySelector('#campaign_type').value] || '—');
        safeSet('summary-commission', (form.querySelector('#offered_commission_rate').value || '0') + '%');
        safeSet('summary-dates', (form.querySelector('#starts_at').value || '—') + ' – ' + (form.querySelector('#ends_at').value || '—'));
        safeSet('summary-packages', selectedPackages.size + ' ' + (window.TRAVEL_AGENCY_TRANSLATIONS?.packagesWord ?? 'packages'));
    }

    showStep(1);
}

// ─── SHOW PAGE ─────────────────────────────────────────────────────────────────

function initShowPage() {
    if (!cfg.offerId) return;

    // Status action buttons
    bindAction('btn-submit-review', cfg.submitUrl, window.TRAVEL_AGENCY_TRANSLATIONS?.confirmSubmitReview ?? 'Submit this offer for review?', onStatusChange);
    bindAction('btn-pause',         cfg.pauseUrl,  window.TRAVEL_AGENCY_TRANSLATIONS?.confirmPauseOffer ?? 'Pause this offer?', onStatusChange);
    bindAction('btn-resume',        cfg.resumeUrl, window.TRAVEL_AGENCY_TRANSLATIONS?.confirmResumeOffer ?? 'Resume this offer?', onStatusChange);

    function bindAction(btnId, url, confirm_msg, callback) {
        const btn = document.getElementById(btnId);
        if (!btn) return;
        btn.addEventListener('click', async () => {
            if (!confirm(confirm_msg)) return;
            btn.disabled = true;
            const { ok, data } = await apiPost(url);
            btn.disabled = false;
            if (ok && data.success) {
                showToast(data.message);
                if (callback) callback(data.status);
            } else {
                showToast(data.message ?? window.t('travel_agency.campaigns.generic_error'), 'error');
            }
        });
    }

    function onStatusChange(newStatus) {
        // Reload so buttons / badge reflect new state
        setTimeout(() => location.reload(), 800);
    }

    // Delete draft offer
    const deleteBtn = document.getElementById('btn-delete-offer');
    if (deleteBtn && cfg.deleteUrl) {
        deleteBtn.addEventListener('click', async () => {
            if (!confirm(window.TRAVEL_AGENCY_TRANSLATIONS?.confirmDeleteOffer ?? 'Delete this offer?')) return;
            deleteBtn.disabled = true;
            const { ok, data } = await apiPost(cfg.deleteUrl, {}, 'DELETE');
            deleteBtn.disabled = false;
            if (ok && data.success) {
                showToast(data.message);
                setTimeout(() => { window.location.href = cfg.indexUrl || document.referrer || '/'; }, 800);
            } else {
                showToast(data.message ?? window.t('travel_agency.campaigns.generic_error'), 'error');
            }
        });
    }

    // Revoke invitation buttons
    document.querySelectorAll('.btn-revoke').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm(window.TRAVEL_AGENCY_TRANSLATIONS?.confirmWithdrawInvitation ?? 'Withdraw this invitation?')) return;
            const invId = btn.dataset.invitationId;
            const { ok, data } = await apiPost(`${cfg.revokeBaseUrl}/${invId}/revoke`, {}, 'DELETE');
            if (ok && data.success) {
                showToast(data.message);
                const row = btn.closest('[data-invitation-id]');
                if (row) {
                    row.querySelector('[class*="bg-amber"]')?.classList.replace('bg-amber-100', 'bg-gray-100');
                    row.querySelector('[class*="text-amber"]')?.classList.replace('text-amber-700', 'text-gray-500');
                    btn.remove();
                }
            } else {
                showToast(data.message ?? window.t('travel_agency.campaigns.generic_error'), 'error');
            }
        });
    });

    // Marketer search & invite
    initMarketerSearch();
}

function initMarketerSearch() {
    const searchInput  = document.getElementById('marketer-search');
    const typeFilter   = document.getElementById('marketer-type-filter');
    const nicheFilter  = document.getElementById('marketer-niche-filter');
    const resultsBox   = document.getElementById('marketer-results');
    const queue        = document.getElementById('invite-queue');
    const queueList    = document.getElementById('invite-queue-list');
    const queueCount   = document.getElementById('invite-queue-count');
    const clearQueueBtn = document.getElementById('btn-clear-queue');
    const sendBtn      = document.getElementById('btn-send-invites');

    if (!searchInput) return;

    const pendingInvites = new Map(); // id → { name, type }
    const existingIds = new Set(
        [...document.querySelectorAll('[data-invitation-id]')].map(el => el.dataset.invitationId)
    );

    async function doSearch() {
        const q     = searchInput.value.trim();
        const type  = typeFilter?.value;
        const niche = nicheFilter?.value?.trim();

        const params = new URLSearchParams({ q });
        if (type)  params.set('type', type);
        if (niche) params.set('niche', niche);

        const res = await fetch(`${cfg.marketerSearchUrl}?${params}`);
        if (!res.ok) return;
        const marketers = await res.json();
        renderResults(marketers);
    }

    function renderResults(marketers) {
        resultsBox.innerHTML = '';
        if (!marketers.length) {
            resultsBox.innerHTML = `<p class="px-4 py-3 text-xs text-gray-400 text-center">${window.t('travel_agency.campaigns.no_results')}</p>`;
            resultsBox.classList.remove('hidden');
            return;
        }
        marketers.forEach(m => {
            const alreadyInvited = existingIds.has(m.id);
            const inQueue = pendingInvites.has(m.id);

            const card = document.createElement('div');
            card.className = 'flex items-center gap-3 rounded-lg px-3 py-2.5 hover:bg-gray-50';

            const avatar = m.photo_url
                ? `<img src="${m.photo_url}" alt="${m.name}" class="h-9 w-9 rounded-full object-cover flex-shrink-0">`
                : `<div class="h-9 w-9 rounded-full bg-indigo-100 flex items-center justify-center text-sm font-bold text-indigo-600 flex-shrink-0">${m.avatar_initial}</div>`;

            card.innerHTML = `
                ${avatar}
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-900 truncate">${m.name}</p>
                    <p class="text-xs text-gray-400">${m.type}${m.niche ? ' · ' + m.niche : ''}${m.followers ? ' · ' + m.followers : ''}</p>
                </div>
                <button type="button" class="btn-add-invite flex-shrink-0 inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors
                    ${inQueue || alreadyInvited ? 'bg-gray-100 text-gray-400 cursor-default' : 'bg-primary-50 text-primary-700 hover:bg-primary-100'}"
                    data-marketer-id="${m.id}" data-marketer-name="${m.name}" data-marketer-type="${m.type}"
                    ${inQueue || alreadyInvited ? 'disabled' : ''}>
                    ${inQueue ? window.t('travel_agency.campaigns.in_queue_label') : alreadyInvited ? window.t('travel_agency.campaigns.already_invited_label') : window.t('travel_agency.campaigns.invite_label')}
                </button>
            `;
            card.querySelector('.btn-add-invite').addEventListener('click', () => {
                if (inQueue || alreadyInvited) return;
                addToQueue(m.id, m.name, m.type);
                doSearch(); // refresh result buttons
            });
            resultsBox.appendChild(card);
        });
        resultsBox.classList.remove('hidden');
    }

    function addToQueue(id, name, type) {
        if (pendingInvites.has(id)) return;
        pendingInvites.set(id, { name, type });
        renderQueue();
    }

    function removeFromQueue(id) {
        pendingInvites.delete(id);
        renderQueue();
    }

    function renderQueue() {
        queueList.innerHTML = '';
        pendingInvites.forEach((data, id) => {
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between gap-2 rounded-lg bg-gray-50 px-3 py-1.5 text-xs';
            item.innerHTML = `
                <span class="font-medium text-gray-800 truncate">${data.name}</span>
                <button type="button" data-queue-remove="${id}" class="text-gray-400 hover:text-red-500 flex-shrink-0">✕</button>
            `;
            item.querySelector('[data-queue-remove]').addEventListener('click', () => removeFromQueue(id));
            queueList.appendChild(item);
        });
        queueCount.textContent = pendingInvites.size;
        queue.classList.toggle('hidden', pendingInvites.size === 0);
    }

    clearQueueBtn?.addEventListener('click', () => {
        pendingInvites.clear();
        renderQueue();
    });

    sendBtn?.addEventListener('click', async () => {
        if (!pendingInvites.size) return;
        sendBtn.disabled = true;
        const note = document.getElementById('vendor-note')?.value ?? '';
        const { ok, data } = await apiPost(cfg.inviteUrl, {
            marketer_ids: [...pendingInvites.keys()],
            vendor_note: note || null,
        });
        sendBtn.disabled = false;
        if (ok && data.success) {
            showToast(data.message);
            pendingInvites.clear();
            renderQueue();
            resultsBox.classList.add('hidden');
            searchInput.value = '';
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast(data.message ?? window.t('travel_agency.campaigns.send_error'), 'error');
        }
    });

    const debouncedSearch = debounce(doSearch, 350);
    searchInput.addEventListener('input', debouncedSearch);
    typeFilter?.addEventListener('change', debouncedSearch);
    nicheFilter?.addEventListener('input', debouncedSearch);

    // Close results on outside click
    document.addEventListener('click', e => {
        if (!searchInput.contains(e.target) && !resultsBox.contains(e.target)) {
            resultsBox.classList.add('hidden');
        }
    });
}

// ─── Bootstrap ────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    if (cfg.mode === 'create') initCreateWizard();
    if (cfg.mode === 'show')   initShowPage();
});
