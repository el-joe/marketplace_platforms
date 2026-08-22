/**
 * partner/payouts.js
 * Finance / Payouts — loads earnings summary cards via AJAX
 */

import './app.js';

function fmt(n, currency = '') {
    const num = parseFloat(n) || 0;
    return num.toLocaleString('ar-SA', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) +
        (currency ? ' ' + currency : '');
}

async function loadEarningsSummary() {
    const cfg = window.PAYOUTS;
    if (!cfg?.earningsSummaryUrl) return;

    try {
        const res = await fetch(cfg.earningsSummaryUrl, {
            headers: { 'Accept': 'application/json' },
        });
        const data = await res.json();

        // This month
        const elMonth = document.getElementById('stat-this-month');
        if (elMonth) {
            elMonth.className = 'text-2xl font-bold text-gray-900';
            elMonth.textContent = fmt(data.this_month, 'ر.س');
        }

        // Pending
        const elPending = document.getElementById('stat-pending');
        if (elPending) {
            elPending.className = `text-2xl font-bold ${data.pending > 0 ? 'text-yellow-500' : 'text-gray-400'}`;
            elPending.textContent = fmt(data.pending, 'ر.س');
        }

        // YTD
        const elYtd = document.getElementById('stat-ytd');
        if (elYtd) {
            elYtd.className = 'text-2xl font-bold text-green-600';
            elYtd.textContent = fmt(data.ytd, 'ر.س');
        }

        // Last payout
        const elAmount = document.getElementById('stat-last-amount');
        const elDate = document.getElementById('stat-last-date');
        if (elAmount) {
            if (data.last_payout) {
                elAmount.className = 'text-2xl font-bold text-gray-900';
                elAmount.textContent = fmt(data.last_payout.amount, 'ر.س');
                if (elDate) elDate.textContent = data.last_payout.date ?? '—';
            } else {
                elAmount.className = 'text-2xl font-bold text-gray-400';
                elAmount.textContent = '—';
                if (elDate) elDate.textContent = 'لم تُستلم أي دفعة بعد';
            }
        }
    } catch {
        // Silently ignore — static fallback values remain visible
    }
}

document.addEventListener('DOMContentLoaded', loadEarningsSummary);
