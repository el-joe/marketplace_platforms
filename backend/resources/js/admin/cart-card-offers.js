/**
 * cart-card-offers.js — admin cart card offer form JS
 */

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

document.addEventListener('DOMContentLoaded', () => {

    // ─── AJAX form submit ─────────────────────────────────────────────────────
    const form = document.getElementById('cart-card-offer-form');
    const modeInput = document.getElementById('form-mode');

    if (form) {
        const isEdit = modeInput && modeInput.value === 'edit';

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            const btn = document.getElementById('save-btn');
            const originalText = btn ? btn.textContent : '';
            if (btn) { btn.disabled = true; btn.textContent = window.TRANSLATIONS?.saving || 'Saving…'; }

            try {
                const fd = new FormData(form);
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: fd,
                });

                const data = await res.json().catch(() => ({}));

                if (res.ok && data.success) {
                    window.Toast?.success(isEdit ? t('admin.cart_card_offers.offer_saved') : t('admin.cart_card_offers.offer_created'));
                    if (!isEdit && data.redirect) {
                        window.location.href = data.redirect;
                    }
                } else {
                    const errors = data.errors ? Object.values(data.errors).flat().join('\n') : null;
                    window.Toast?.error(errors || data.message || t('admin.cart_card_offers.save_failed'));
                }
            } catch (err) {
                window.Toast?.error(t('admin.cart_card_offers.network_error'));
            } finally {
                if (btn) { btn.disabled = false; btn.textContent = originalText; }
            }
        });
    }

});
