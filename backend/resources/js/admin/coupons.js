/**
 * coupons.js — admin coupon form & datatable JS
 */

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

document.addEventListener('DOMContentLoaded', () => {

    // ─── Generate Code ───────────────────────────────────────────────────────
    const generateBtn = document.getElementById('btn-generate-code');
    if (generateBtn) {
        generateBtn.addEventListener('click', async () => {
            generateBtn.disabled = true;
            try {
                const res = await fetch(generateBtn.dataset.url, {
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                });
                const data = await res.json();
                if (data.code) {
                    document.getElementById('code').value = data.code;
                }
            } catch {
                window.Toast?.error(window.TRANSLATIONS?.generateCodeFailed || 'Could not generate code.');
            } finally {
                generateBtn.disabled = false;
            }
        });
    }

    // ─── AJAX form submit ─────────────────────────────────────────────────────
    const form = document.getElementById('coupon-form');
    const modeInput = document.getElementById('form-mode');

    if (form) {
        const isEdit = modeInput && modeInput.value === 'edit';
        const timesUsed = parseInt(document.getElementById('form-times-used')?.value || '0', 10);
        const originalCode = document.getElementById('form-original-code')?.value || '';
        const originalType = document.getElementById('form-original-type')?.value || '';
        const originalValue = document.getElementById('form-original-value')?.value || '';

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            if (isEdit && timesUsed > 0) {
                const code = form.querySelector('#code')?.value || '';
                const type = form.querySelector('#type')?.value || '';
                const value = form.querySelector('#value')?.value || '';
                const changedSensitiveField = code !== originalCode || type !== originalType || value !== originalValue;

                if (changedSensitiveField) {
                    const message = (window.TRANSLATIONS?.editValueWarningBody || 'This coupon has been used :count time(s). Changing its code, type, or value may cause confusion for customers who copied it.')
                        .replace(':count', timesUsed);
                    const confirmed = window.confirmDelete
                        ? await window.confirmDelete(message, { title: window.TRANSLATIONS?.editValueWarningTitle || 'This coupon has already been used', confirmButtonText: window.TRANSLATIONS?.proceedAnyway || 'Save Anyway' })
                        : confirm(message);
                    if (!confirmed) return;
                }
            }

            const btn = document.getElementById('save-btn');
            const originalText = btn ? btn.textContent : '';
            if (btn) { btn.disabled = true; btn.textContent = window.TRANSLATIONS?.saving || 'Saving\u2026'; }

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
                    const savedMessage = isEdit ? window.TRANSLATIONS?.couponSaved : window.TRANSLATIONS?.couponCreated;
                    window.Toast?.success(savedMessage || (isEdit ? 'Coupon saved.' : 'Coupon created.'));
                    if (!isEdit && data.redirect) {
                        window.location.href = data.redirect;
                    }
                } else {
                    const errors = data.errors ? Object.values(data.errors).flat().join('\n') : null;
                    window.Toast?.error(errors || data.message || window.TRANSLATIONS?.saveFailed || 'Save failed.');
                }
            } catch (err) {
                window.Toast?.error(window.TRANSLATIONS?.networkErrorRetry || 'Network error. Please try again.');
            } finally {
                if (btn) { btn.disabled = false; btn.textContent = originalText; }
            }
        });
    }

});
