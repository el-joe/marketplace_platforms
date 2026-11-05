/**
 * admins.js — Admin management form & datatable JS
 *
 * Handles:
 *  - Admin form AJAX submit (create/edit); new admins get a set-password email (no password shown)
 *  - Role form AJAX submit
 *  - Reset password button (edit mode)
 */
document.addEventListener('DOMContentLoaded', () => {

    // ─── Admin Form ───────────────────────────────────────────────────────────
    const adminForm = document.getElementById('admin-form');
    if (adminForm) {
        const isEdit = adminForm.querySelector('input[name="_method"]') !== null;

        adminForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const btn = document.getElementById('save-btn');
            const origText = btn ? btn.textContent : '';
            if (btn) { btn.disabled = true; btn.textContent = window.TRANSLATIONS?.saving || 'Saving…'; }

            try {
                const fd = new FormData(adminForm);
                const res = await fetch(adminForm.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                    body: fd,
                });

                const data = await res.json().catch(() => ({}));

                if (res.ok && data.success) {
                    if (!isEdit) {
                        // The password is never shown or returned: the new admin receives a
                        // single-use set-password link by email. A failed send is surfaced here.
                        if (data.warning) {
                            window.Toast?.warning(data.warning);
                        } else {
                            window.Toast?.success(t('admin.admins.admin_created'));
                        }
                        setTimeout(() => {
                            if (data.redirect) window.location.href = data.redirect;
                        }, data.warning ? 6000 : 1500);
                    } else {
                        window.Toast?.success(t('admin.admins.admin_saved'));
                    }
                } else {
                    const errors = data.errors
                        ? Object.values(data.errors).flat().join('\n')
                        : null;
                    window.Toast?.error(errors || data.message || t('admin.admins.save_failed'));
                }
            } catch (err) {
                window.Toast?.error(t('admin.admins.network_error'));
            } finally {
                if (btn) { btn.disabled = false; btn.textContent = origText; }
            }
        });
    }

    // ─── Role Form ────────────────────────────────────────────────────────────
    const roleForm = document.getElementById('role-form');
    if (roleForm) {
        const isEdit = roleForm.querySelector('input[name="_method"]') !== null;

        roleForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const btn = document.getElementById('save-btn');
            const origText = btn ? btn.textContent : '';
            if (btn) { btn.disabled = true; btn.textContent = window.TRANSLATIONS?.saving || 'Saving…'; }

            try {
                const fd = new FormData(roleForm);
                const res = await fetch(roleForm.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                    body: fd,
                });

                const data = await res.json().catch(() => ({}));

                if (res.ok && data.success) {
                    window.Toast?.success(isEdit ? t('admin.admins.role_saved') : t('admin.admins.role_created'));
                    if (!isEdit && data.redirect) {
                        window.location.href = data.redirect;
                    }
                } else {
                    const errors = data.errors
                        ? Object.values(data.errors).flat().join('\n')
                        : null;
                    window.Toast?.error(errors || data.message || t('admin.admins.save_failed'));
                }
            } catch (err) {
                window.Toast?.error(t('admin.admins.network_error'));
            } finally {
                if (btn) { btn.disabled = false; btn.textContent = origText; }
            }
        });
    }

    // ─── Reset Password Button (edit mode) ────────────────────────────────────
    const resetBtn = document.getElementById('reset-password-btn');
    if (resetBtn) {
        resetBtn.addEventListener('click', async function () {
            const url = resetBtn.dataset.url;
            if (!url) return;

            const confirmed = window.confirmBulkAction
                ? await window.confirmBulkAction(
                    window.TRANSLATIONS?.resetPasswordConfirmGeneric || 'Reset this administrator\'s password? They will receive a new password by email.',
                    { title: t('admin.admins.reset_password_title') }
                )
                : confirm(window.TRANSLATIONS?.resetPasswordConfirmGeneric || 'Reset this administrator\'s password?');

            if (!confirmed) return;

            resetBtn.disabled = true;
            resetBtn.textContent = window.TRANSLATIONS?.resetting || 'Resetting…';

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                });

                const data = await res.json().catch(() => ({}));

                if (res.ok && data.success && !data.warning) {
                    window.Toast?.success(data.message || t('admin.admins.password_reset_success'));
                } else if (res.ok && data.success) {
                    window.Toast?.warning(data.warning);
                } else {
                    window.Toast?.error(data.message || t('admin.admins.reset_failed'));
                }
            } catch (err) {
                window.Toast?.error(t('admin.admins.network_error'));
            } finally {
                resetBtn.disabled = false;
                resetBtn.textContent = t('admin.admins.reset_password_label');
            }
        });
    }

    // ─── Resend Credentials Button (edit mode) ────────────────────────────────
    const resendBtn = document.getElementById('resend-credentials-btn');
    if (resendBtn) {
        resendBtn.addEventListener('click', async function () {
            const url = resendBtn.dataset.url;
            if (!url) return;

            const originalLabel = resendBtn.textContent;
            resendBtn.disabled = true;

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                });

                const data = await res.json().catch(() => ({}));

                if (res.ok && data.success && !data.warning) {
                    window.Toast?.success(data.message);
                } else if (res.ok && data.success) {
                    window.Toast?.warning(data.warning);
                } else {
                    window.Toast?.error(data.message || t('admin.admins.reset_failed'));
                }
            } catch (err) {
                window.Toast?.error(t('admin.admins.network_error'));
            } finally {
                resendBtn.disabled = false;
                resendBtn.textContent = originalLabel;
            }
        });
    }

});
