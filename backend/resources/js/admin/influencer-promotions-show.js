function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function postAction(url) {
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
        },
        body: JSON.stringify({}),
    })
        .then(res => res.json().then(data => ({ ok: res.ok, data })))
        .then(({ ok, data }) => {
            if (!ok) throw new Error(data.message || t('shared.failed_generic'));
            window.Toast?.success(data.message);
            setTimeout(() => location.reload(), 700);
        })
        .catch(err => window.Toast?.error(err.message || t('shared.failed_generic')));
}

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('promotion-actions');
    if (!container) return;

    const requestId = container.dataset.requestId;

    document.getElementById('btn-cancel-request')?.addEventListener('click', () => {
        if (!confirm(t('admin.influencer_promotions.cancel_promotion_confirm'))) return;
        postAction(`/influencer-promotions/${requestId}/cancel`);
    });

    document.getElementById('btn-confirm-warehouse')?.addEventListener('click', () => {
        if (!confirm(t('admin.influencer_promotions.confirm_stock_received'))) return;
        postAction(`/influencer-promotions/${requestId}/confirm-warehouse-receipt`);
    });

    document.getElementById('btn-settle-debt')?.addEventListener('click', () => {
        if (!confirm(t('admin.influencer_promotions.settle_debt_confirm'))) return;
        postAction(`/influencer-promotions/${requestId}/settle-sample-debt`);
    });

    document.getElementById('promotion-slots')?.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-force-reassign');
        if (!btn) return;
        if (!confirm(t('admin.influencer_promotions.force_reassign_confirm'))) return;
        postAction(`/influencer-promotions/${requestId}/items/${btn.dataset.itemId}/force-reassign`);
    });
});
