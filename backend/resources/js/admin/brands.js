/**
 * brands.js — admin brand form & datatable JS
 */
function T() {
    return window.TRANSLATIONS || {};
}

document.addEventListener('DOMContentLoaded', () => {

    // ─── Slug auto-generation from name_en ───────────────────────────────────
    const nameInput = document.getElementById('name_en');
    const slugInput = document.getElementById('slug');

    if (nameInput && slugInput && slugInput.dataset.slugSource) {
        let userEditedSlug = !!slugInput.value;

        slugInput.addEventListener('input', () => {
            userEditedSlug = true;
        });

        nameInput.addEventListener('input', () => {
            if (userEditedSlug) return;
            slugInput.value = nameInput.value
                .toLowerCase()
                .trim()
                .replace(/[^\w\s-]/g, '')
                .replace(/[\s_]+/g, '-')
                .replace(/^-+|-+$/g, '');
        });
    }

    // ─── AJAX form submit on edit ─────────────────────────────────────────────
    const form = document.getElementById('brand-form');
    const modeInput = document.getElementById('form-mode');

    if (form && modeInput && modeInput.value === 'edit') {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            const btn = document.getElementById('brand-save-btn');
            if (btn) { btn.disabled = true; btn.textContent = T().savingEllipsis || 'Saving…'; }

            try {
                const fd = new FormData(form);
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                    body: fd,
                });

                const data = await res.json().catch(() => ({}));

                if (res.ok && data.success) {
                    window.Toast?.success(t('admin.brands.brand_saved'));
                } else {
                    const msg = data.message || data.errors
                        ? Object.values(data.errors || {}).flat().join('\n')
                        : t('admin.brands.save_failed');
                    window.Toast?.error(msg);
                }
            } catch (err) {
                window.Toast?.error(t('admin.brands.network_error'));
            } finally {
                if (btn) { btn.disabled = false; btn.textContent = t('admin.brands.save_changes_label'); }
            }
        });
    }
});
