import './app.js';

// ─────────────────────────────────────────────────────────────────────────────
// Countdown timers
// ─────────────────────────────────────────────────────────────────────────────

function initCountdowns() {
    document.querySelectorAll('[data-countdown]').forEach(el => {
        const target = new Date(el.dataset.countdown).getTime();
        if (isNaN(target)) return;

        const update = () => {
            const diff = target - Date.now();
            if (diff <= 0) {
                el.textContent = 'انتهى';
                return;
            }
            const h = Math.floor(diff / 3_600_000);
            const m = Math.floor((diff % 3_600_000) / 60_000);
            const s = Math.floor((diff % 60_000) / 1_000);
            el.textContent = `${h}س ${String(m).padStart(2, '0')}د ${String(s).padStart(2, '0')}ث`;
        };

        update();
        setInterval(update, 1000);
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// Submit form (show page)
// ─────────────────────────────────────────────────────────────────────────────

let eligibleListings = [];

async function loadEligibleListings() {
    const cfg = window.FLASH_SALES;
    if (!cfg?.eligibleListingsUrl) return;

    const sel = document.getElementById('input-listing');
    if (sel) sel.innerHTML = '<option value="">-- جاري التحميل... --</option>';

    try {
        const res = await fetch(cfg.eligibleListingsUrl, {
            headers: { 'Accept': 'application/json' },
        });
        const data = await res.json();
        eligibleListings = data.listings || [];
        renderListingOptions();
    } catch (e) {
        console.error('Failed to load eligible listings', e);
        const sel = document.getElementById('input-listing');
        if (sel) sel.innerHTML = '<option value="">-- تعذَّر التحميل --</option>';
    }
}

function renderListingOptions() {
    const sel = document.getElementById('input-listing');
    if (!sel) return;

    if (eligibleListings.length === 0) {
        sel.innerHTML = '<option value="">-- لا توجد منتجات مؤهلة --</option>';
        return;
    }

    sel.innerHTML = '<option value="">-- اختر منتجًا --</option>';
    eligibleListings.forEach(l => {
        const opt = document.createElement('option');
        opt.value = l.id;
        opt.textContent = `${l.product_name}${l.sku ? ' (' + l.sku + ')' : ''}`;
        opt.dataset.currentPrice = l.current_price_display;
        opt.dataset.avg30d = l.avg_price_30d_display ?? '';
        opt.dataset.currency = l.currency ?? '';
        sel.appendChild(opt);
    });
}

function initSubmitForm() {
    const modal = document.getElementById('submit-modal');
    if (!modal) return;

    const openBtn = document.getElementById('btn-add-product');
    const closeBtn = document.getElementById('btn-close-submit-modal');
    const cancelBtn = document.getElementById('btn-cancel-submit');
    const form = document.getElementById('form-submit');
    const listingSel = document.getElementById('input-listing');
    const flashPriceInput = document.getElementById('input-flash-price');
    const origPriceInput = document.getElementById('input-original-price');
    const discountDisplay = document.getElementById('discount-pct-display');
    const avgPriceHint = document.getElementById('avg-price-hint');
    const submitBtn = document.getElementById('btn-submit-product');

    const openModal = () => {
        modal.classList.remove('hidden');
        if (eligibleListings.length === 0) loadEligibleListings();
    };

    const closeModal = () => modal.classList.add('hidden');

    openBtn?.addEventListener('click', openModal);
    closeBtn?.addEventListener('click', closeModal);
    cancelBtn?.addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

    // Prefill original price when a listing is selected
    listingSel?.addEventListener('change', () => {
        const opt = listingSel.options[listingSel.selectedIndex];
        if (opt?.dataset.currentPrice) {
            origPriceInput.value = opt.dataset.currentPrice;
            if (avgPriceHint && opt.dataset.avg30d) {
                avgPriceHint.textContent = `متوسط 30 يوم: ${opt.dataset.avg30d} ${opt.dataset.currency}`;
                avgPriceHint.classList.remove('hidden');
            } else if (avgPriceHint) {
                avgPriceHint.classList.add('hidden');
            }
        }
        updateDiscountDisplay();
    });

    function updateDiscountDisplay() {
        if (!discountDisplay) return;
        const flash = parseFloat(flashPriceInput?.value || 0);
        const orig = parseFloat(origPriceInput?.value || 0);

        if (orig > 0 && flash > 0 && flash < orig) {
            const pct = ((orig - flash) / orig * 100).toFixed(1);
            discountDisplay.textContent = `الخصم: ${pct}%`;
            discountDisplay.className = 'text-sm font-medium text-green-600';
        } else {
            discountDisplay.textContent = 'الخصم: —';
            discountDisplay.className = 'text-sm font-medium text-gray-400';
        }
    }

    flashPriceInput?.addEventListener('input', updateDiscountDisplay);
    origPriceInput?.addEventListener('input', updateDiscountDisplay);

    form?.addEventListener('submit', async e => {
        e.preventDefault();

        const cfg = window.FLASH_SALES;
        const flash = parseFloat(flashPriceInput.value);
        const orig = parseFloat(origPriceInput.value);
        const qty = parseInt(document.getElementById('input-max-qty')?.value || '0', 10);

        const toast = (text, bg = '#ef4444') =>
            window.Toastify?.({ text, backgroundColor: bg, duration: 4000 }).showToast();

        if (!listingSel.value) {
            toast('يرجى اختيار منتج');
            return;
        }
        if (isNaN(flash) || flash <= 0) {
            toast('يرجى إدخال سعر الفلاش');
            return;
        }
        if (isNaN(orig) || orig <= 0 || flash >= orig) {
            toast('سعر الفلاش يجب أن يكون أقل من السعر الأصلي');
            return;
        }
        if (qty < 1) {
            toast('يرجى إدخال الكمية الإجمالية');
            return;
        }

        const discountPct = ((orig - flash) / orig * 100);
        if (discountPct < cfg.minDiscountPct) {
            toast(`الخصم يجب أن يكون ${cfg.minDiscountPct}% على الأقل`);
            return;
        }

        const maxPerCustomer = parseInt(document.getElementById('input-max-per-customer')?.value || '0', 10);
        const payload = {
            vendor_listing_id: listingSel.value,
            flash_price: Math.round(flash),
            original_price: Math.round(orig),
            max_quantity_total: qty,
            max_quantity_per_customer: maxPerCustomer > 0 ? maxPerCustomer : null,
            vendor_notes: document.getElementById('input-vendor-notes')?.value || null,
        };

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'جاري التقديم...';
        }

        try {
            const res = await fetch(cfg.submitUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': cfg.csrf,
                },
                body: JSON.stringify(payload),
            });
            const data = await res.json();

            if (res.ok) {
                toast(data.message || 'تم تقديم المنتج بنجاح', '#22c55e');
                closeModal();
                setTimeout(() => location.reload(), 1500);
            } else {
                toast(data.message || 'حدث خطأ، يرجى المحاولة مجدداً');
            }
        } catch {
            toast('خطأ في الاتصال، يرجى المحاولة مجدداً');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = `<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg> تقديم المنتج`;
            }
        }
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// Live stats polling (show page — only when isLive)
// ─────────────────────────────────────────────────────────────────────────────

function initLiveStats() {
    const cfg = window.FLASH_SALES;
    if (!cfg?.liveStatsUrl) return;

    const container = document.getElementById('live-stats-container');
    if (!container) return;

    const countdown = document.getElementById('live-sale-countdown');

    async function fetchStats() {
        try {
            const res = await fetch(cfg.liveStatsUrl, {
                headers: { 'Accept': 'application/json' },
            });
            if (!res.ok) return;

            const data = await res.json();

            // Update per-submission rows
            (data.submissions || []).forEach(sub => {
                const row = container.querySelector(`[data-submission-id="${sub.submission_id}"]`);
                if (!row) return;

                const fmtAr = n => Number(n).toLocaleString('ar-SA');

                const qtyS = row.querySelector('.stat-qty-sold');
                const qtyR = row.querySelector('.stat-qty-remaining');
                const rev = row.querySelector('.stat-revenue');

                if (qtyS) qtyS.textContent = fmtAr(sub.quantity_sold);
                if (qtyR) qtyR.textContent = fmtAr(sub.quantity_remaining);
                if (rev) rev.textContent = Number(sub.revenue_display).toLocaleString('ar-SA', { minimumFractionDigits: 2 }) + ' ' + (sub.currency ?? '');
            });

            // Update the sale-ends countdown
            if (countdown) {
                const secs = data.time_remaining_s ?? 0;
                if (secs <= 0) {
                    countdown.textContent = 'انتهى';
                } else {
                    const h = Math.floor(secs / 3600);
                    const m = Math.floor((secs % 3600) / 60);
                    const s = secs % 60;
                    countdown.textContent = `${h}س ${String(m).padStart(2, '0')}د ${String(s).padStart(2, '0')}ث`;
                }
            }
        } catch {
            // Silent — network blip; next poll will retry
        }
    }

    fetchStats();
    setInterval(fetchStats, 10_000);
}

// ─────────────────────────────────────────────────────────────────────────────
// Bootstrap
// ─────────────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initCountdowns();
    initSubmitForm();
    initLiveStats();
});
