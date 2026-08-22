/**
 * partner/listing-create-campaign.js
 * Campaign section behavior for the listing create page (partner/listings/create.blade.php)
 */

document.addEventListener('DOMContentLoaded', function () {
    const section = document.getElementById('campaign-section');
    if (!section) return;

    const enabledCheckbox = document.getElementById('campaign-enabled-checkbox');
    const notFbnWarning = document.getElementById('campaign-not-fbn-warning');
    const details = document.getElementById('campaign-details');
    const marketerSelect = document.getElementById('marketer_vendor_ids');
    const commissionTypeSelect = document.getElementById('commission-type-select');
    const tieredSection = document.getElementById('tiered-rules-section');
    const tieredList = document.getElementById('tiered-rules-list');
    const addTierBtn = document.getElementById('add-tier-btn');
    const totalSamplesEl = document.getElementById('total-samples-value');
    const sampleBreakdownEl = document.getElementById('sample-breakdown-text');
    const fmSelect = document.querySelector('select[name="fulfillment_model"]');
    const countryInput = document.querySelector('input[name="country_id"]');
    const feeBreakdownEl = document.getElementById('fee-breakdown-text');
    const feeTotalWrapEl = document.getElementById('fee-total-wrap');
    const feeTotalValueEl = document.getElementById('fee-total-value');
    const feeCurrencyEl = document.getElementById('fee-currency');
    const commissionBreakdownEl = document.getElementById('commission-breakdown-text');
    const feeTableWrapEl = document.getElementById('marketer-fee-table-wrap');
    const feeTableBodyEl = document.getElementById('marketer-fee-table-body');
    const feeTableTotalEl = document.getElementById('marketer-fee-table-total');

    const state = {
        selectedMarketers: [], // [{id, name, type}]
        influencerCount: 0,
        affiliateCount: 0,
        influencerSampleQty: 0,
        affiliateSampleQty: 0,
        platformSampleQty: 0,
        samplesLoaded: false,
        tieredRules: [],
        feePerInfluencer: 0,
        influencerCommission: 0,
        affiliateCommission: 0,
        currency: '',
        pricingLoaded: false,
    };

    function isFbn() {
        return fmSelect ? fmSelect.value === 'fbn' : false;
    }

    function totalSamples() {
        return (state.influencerCount * state.influencerSampleQty)
             + (state.affiliateCount * state.affiliateSampleQty)
             + state.platformSampleQty;
    }

    function sampleBreakdown() {
        return "";
        if (!state.samplesLoaded) return 'سيتم حساب العينات بعد اختيار المنتج';
        const parts = [];
        if (state.influencerCount > 0)
            parts.push(`${state.influencerCount} إنفلوينسر × ${state.influencerSampleQty}`);
        if (state.affiliateCount > 0)
            parts.push(`${state.affiliateCount} أفيلييت × ${state.affiliateSampleQty}`);
        if (state.platformSampleQty > 0)
            parts.push(`منصة ${state.platformSampleQty}`);
        return parts.length
            ? `(${parts.join(' + ')}) = ${totalSamples()} عينة`
            : `${totalSamples()} عينة`;
    }

    function renderSamples() {
        if (totalSamplesEl) totalSamplesEl.textContent = totalSamples();
        if (sampleBreakdownEl) sampleBreakdownEl.textContent = sampleBreakdown();
    }

    function renderVisibility() {
        const enabled = !!(enabledCheckbox && enabledCheckbox.checked);
        const fbn = isFbn();
        notFbnWarning?.classList.toggle('hidden', fbn);
        details?.classList.toggle('hidden', !(enabled && fbn));
        if (enabled && fbn) window.initSelect2 && window.initSelect2();
    }

    function renderTieredVisibility() {
        tieredSection?.classList.toggle('hidden', commissionTypeSelect?.value !== 'tiered');
    }

    function renderTieredRules() {
        if (!tieredList) return;
        tieredList.innerHTML = '';
        state.tieredRules.forEach((rule, i) => {
            const row = document.createElement('div');
            row.className = 'flex gap-2 items-center';
            row.innerHTML = `
                <input type="number" name="tiered_rules[${i}][from_sale_number]"
                       value="${rule.from_sale_number}"
                       placeholder="رقم البيعة (مثال: 10)"
                       class="w-1/2 border border-gray-200 rounded-xl px-3 py-2 text-sm">
                <input type="number" name="tiered_rules[${i}][commission_amount]"
                       value="${rule.commission_amount}"
                       placeholder="مبلغ الكوميشن"
                       class="w-1/2 border border-gray-200 rounded-xl px-3 py-2 text-sm">
                <button type="button" class="text-red-500 hover:text-red-700" data-remove-tier="${i}">
                    <i class="fas fa-times"></i>
                </button>
            `;
            const [fromInput, amountInput] = row.querySelectorAll('input');
            fromInput.addEventListener('input', (e) => { rule.from_sale_number = e.target.value; });
            amountInput.addEventListener('input', (e) => { rule.commission_amount = e.target.value; });
            row.querySelector('[data-remove-tier]').addEventListener('click', () => {
                state.tieredRules.splice(i, 1);
                renderTieredRules();
            });
            tieredList.appendChild(row);
        });
    }

    function renderPricing() {
        if (!feeBreakdownEl || !commissionBreakdownEl) return;

        if (!state.pricingLoaded) {
            feeBreakdownEl.textContent = 'اختر منتجاً وماركترز لرؤية الرسوم';
            commissionBreakdownEl.textContent = 'سيتم تحديده بعد اختيار المنتج';
            feeTotalWrapEl?.classList.add('hidden');
            return;
        }

        const totalFee = state.influencerCount * state.feePerInfluencer;

        if (state.influencerCount === 0 && state.affiliateCount === 0) {
            feeBreakdownEl.textContent = 'لا توجد رسوم (لا يوجد إنفلوينسر مختار)';
            feeTotalWrapEl?.classList.add('hidden');
        } else {
            const parts = [];
            if (state.influencerCount > 0 && state.feePerInfluencer > 0) {
                parts.push(`${state.influencerCount} إنفلوينسر × ${state.feePerInfluencer} ${state.currency} = ${totalFee} ${state.currency}`);
            }
            if (state.affiliateCount > 0) {
                parts.push(`${state.affiliateCount} أفيلييت × 0 (مجاني)`);
            }
            feeBreakdownEl.textContent = parts.join(' | ') || 'لا توجد رسوم (لا يوجد إنفلوينسر مختار)';

            if (totalFee > 0) {
                feeTotalValueEl.textContent = totalFee;
                feeCurrencyEl.textContent = state.currency;
                feeTotalWrapEl?.classList.remove('hidden');
            } else {
                feeTotalWrapEl?.classList.add('hidden');
            }
        }

        const lines = [];
        if (state.influencerCommission > 0) lines.push(`الإنفلوينسر: ${state.influencerCommission} ${state.currency} لكل بيعة`);
        if (state.affiliateCommission > 0) lines.push(`الأفيلييت: ${state.affiliateCommission} ${state.currency} لكل بيعة`);
        commissionBreakdownEl.textContent = lines.length ? lines.join(' | ') : 'لم يتم تحديد الكوميشن لهذه الفئة بعد';
    }

    function renderMarketerFeeTable() {
        if (!feeTableWrapEl || !feeTableBodyEl || !feeTableTotalEl) return;

        if (state.selectedMarketers.length === 0) {
            feeTableWrapEl.classList.add('hidden');
            return;
        }
        feeTableWrapEl.classList.remove('hidden');

        feeTableBodyEl.innerHTML = '';
        state.selectedMarketers.forEach(m => {
            const isInfluencer = m.type === 'influencer';
            const fee = isInfluencer ? state.feePerInfluencer : 0;
            const row = document.createElement('tr');
            row.className = 'border-t border-gray-100';
            row.innerHTML = `
                <td class="px-4 py-2 text-gray-800"></td>
                <td class="px-4 py-2 text-center">
                    <span class="px-2 py-0.5 rounded-full text-xs ${isInfluencer ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'}">
                        ${isInfluencer ? 'إنفلوينسر' : 'أفيلييت'}
                    </span>
                </td>
                <td class="px-4 py-2 text-center font-medium ${isInfluencer && fee > 0 ? 'text-orange-700' : 'text-green-600'}"></td>
            `;
            row.querySelector('td').textContent = m.name;
            row.lastElementChild.textContent = (isInfluencer && fee > 0) ? `${fee} ${state.currency}` : 'مجاني';
            feeTableBodyEl.appendChild(row);
        });

        const total = state.influencerCount * state.feePerInfluencer;
        feeTableTotalEl.textContent = total > 0 ? `${total} ${state.currency}` : 'مجاني';
        feeTableTotalEl.classList.toggle('text-orange-700', total > 0);
        feeTableTotalEl.classList.toggle('text-green-600', total === 0);
    }

    async function fetchCampaignPricing(productId) {
        const url = window.LISTINGS_CREATE?.campaignPricingUrl;
        const countryId = countryInput?.value;
        if (!productId || !countryId || !url) return;
        try {
            const res = await fetch(`${url}?product_id=${productId}&country_id=${countryId}`, {
                headers: { 'Accept': 'application/json' },
            });
            const data = await res.json();
            state.feePerInfluencer = data.fee_per_influencer ?? 0;
            state.influencerCommission = data.influencer_commission_amount ?? 0;
            state.affiliateCommission = data.affiliate_commission_amount ?? 0;
            state.currency = data.currency ?? '';
            state.pricingLoaded = true;
            renderPricing();
            renderMarketerFeeTable();
        } catch (e) {
            console.error('Failed to fetch campaign pricing', e);
        }
    }

    async function fetchInfluencerFee() {
        const url = window.LISTINGS_CREATE?.influencerFeeUrl;
        if (!url) return;
        try {
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            state.feePerInfluencer = data.fee_per_influencer ?? 0;
            state.currency = data.currency ?? state.currency;
            renderMarketerFeeTable();
        } catch (e) {
            console.error('Failed to fetch influencer fee', e);
        }
    }

    async function fetchCategorySamples(productId) {
        const url = window.LISTINGS_CREATE?.categorySamplesUrl;
        if (!productId || !url) return;
        try {
            const res = await fetch(`${url}?product_id=${productId}`, {
                headers: { 'Accept': 'application/json' },
            });
            const data = await res.json();
            state.influencerSampleQty = data.influencer ?? 0;
            state.affiliateSampleQty = data.affiliate ?? 0;
            state.platformSampleQty = data.platform ?? 0;
            state.samplesLoaded = true;
            renderSamples();
        } catch (e) {
            console.error('Failed to fetch category samples', e);
        }
    }

    function updateMarketerCounts(event) {
        const select = event.target;
        state.influencerCount = 0;
        state.affiliateCount = 0;
        state.selectedMarketers = Array.from(select.selectedOptions || []).map(opt => {
            if (opt.dataset.type === 'influencer') state.influencerCount++;
            else if (opt.dataset.type === 'affiliate') state.affiliateCount++;
            return { id: opt.value, name: opt.dataset.name || opt.text, type: opt.dataset.type || 'affiliate' };
        });
        renderSamples();
        renderPricing();
        renderMarketerFeeTable();
    }

    enabledCheckbox?.addEventListener('change', renderVisibility);
    fmSelect?.addEventListener('change', renderVisibility);
    commissionTypeSelect?.addEventListener('change', renderTieredVisibility);
    marketerSelect?.addEventListener('change', updateMarketerCounts);
    // select2 (data-select2-init) fires jQuery 'change', not native DOM 'change'
    if (window.jQuery && marketerSelect) {
        jQuery(marketerSelect).on('change', updateMarketerCounts);
    }
    addTierBtn?.addEventListener('click', () => {
        state.tieredRules.push({ from_sale_number: '', commission_amount: '' });
        renderTieredRules();
    });
    document.addEventListener('listing:product-selected', (e) => {
        fetchCategorySamples(e.detail?.productId);
        fetchCampaignPricing(e.detail?.productId);
    });

    renderVisibility();
    renderTieredVisibility();
    renderSamples();
    renderPricing();
    renderMarketerFeeTable();
    fetchInfluencerFee();
});
