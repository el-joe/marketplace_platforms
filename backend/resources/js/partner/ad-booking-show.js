import './app.js';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function toast(msg, type = 'success') {
    window.Toastify?.({
        text: msg,
        duration: 4000,
        gravity: 'top',
        position: 'left',
        style: { background: type === 'success' ? '#16a34a' : '#dc2626', borderRadius: '0.75rem' },
    }).showToast();
}

document.addEventListener('DOMContentLoaded', () => {
    const cfg = window.AD_BOOKING_CONFIG;
    if (!cfg) return;

    document.getElementById('btn-pay')?.addEventListener('click', async () => {
        const res = await fetch(cfg.payUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' } });
        const data = await res.json();
        if (!data.success) {
            toast(data.message + (data.balance !== undefined ? ` (balance ${data.balance} / required ${data.required} ${data.currency})` : ''), 'error');
            return;
        }
        toast('Payment collected.');
        window.location.reload();
    });

    document.getElementById('btn-cancel')?.addEventListener('click', async () => {
        const reason = prompt('Cancellation reason:');
        if (!reason) return;
        const res = await fetch(cfg.cancelUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ reason }),
        });
        const data = await res.json();
        toast(data.message, data.success ? 'success' : 'error');
        if (data.success) window.location.reload();
    });

    document.getElementById('creative-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const res = await fetch(cfg.creativeUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' }, body: fd });
        const data = await res.json();
        toast(data.message, data.success ? 'success' : 'error');
        if (data.success) window.location.reload();
    });

    const ctx = document.getElementById('stats-chart');
    if (ctx && window.Chart && cfg.stats?.daily?.length) {
        new window.Chart(ctx, {
            type: 'line',
            data: {
                labels: cfg.stats.daily.map((d) => d.date),
                datasets: [
                    { label: 'Impressions', data: cfg.stats.daily.map((d) => d.impressions), borderColor: '#6366f1' },
                    { label: 'Clicks', data: cfg.stats.daily.map((d) => d.clicks), borderColor: '#22c55e' },
                ],
            },
        });
    }
});
