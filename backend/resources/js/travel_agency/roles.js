/**
 * Travel Agency Portal → Roles Management JS
 * Mirrors resources/js/partner/roles.js with travel_agency role naming.
 */

function toast(message, ok = true) {
    window.Toastify?.({
        text: message,
        backgroundColor: ok ? '#22c55e' : '#ef4444',
        duration: 3500,
        gravity: 'top',
        position: 'center',
    }).showToast();
}

// ─── Role create/edit form ─────────────────────────────────────────────────

const roleForm = document.getElementById('role-form');
if (roleForm) {
    const isEdit = roleForm.querySelector('input[name="_method"]') !== null;

    roleForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const btn = document.getElementById('save-btn');
        const errorBox = document.getElementById('role-form-error');
        errorBox?.classList.add('hidden');

        const origText = btn ? btn.textContent : '';
        if (btn) { btn.disabled = true; btn.textContent = window.t('travel_agency.roles.saving'); }

        try {
            const fd = new FormData(roleForm);
            const res = await fetch(roleForm.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    Accept: 'application/json',
                },
                body: fd,
            });

            const data = await res.json().catch(() => ({}));

            if (res.ok && data.success) {
                toast(isEdit ? window.t('travel_agency.roles.saved') : window.t('travel_agency.roles.created'));
                if (!isEdit && data.redirect) {
                    window.location.href = data.redirect;
                }
            } else {
                const message = data.errors ? Object.values(data.errors).flat().join('\n') : (data.message || window.t('travel_agency.roles.save_failed'));
                if (errorBox) { errorBox.textContent = message; errorBox.classList.remove('hidden'); }
                toast(message, false);
            }
        } catch (err) {
            toast(window.t('travel_agency.roles.connection_error'), false);
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = origText; }
        }
    });
}

// ─── Role delete (index page) ──────────────────────────────────────────────

document.querySelectorAll('.btn-delete-role').forEach((btn) => {
    btn.addEventListener('click', async function () {
        const id = btn.dataset.id;
        const name = btn.dataset.name;
        if (!window.confirm(window.t('travel_agency.roles.confirm_delete', { name }))) return;

        try {
            const res = await fetch(window.ROLES.destroyUrl.replace(':id', id), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': window.ROLES.csrf,
                    Accept: 'application/json',
                },
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok && data.success) {
                toast(window.t('travel_agency.roles.deleted'));
                document.getElementById(`role-row-${id}`)?.remove();
            } else {
                toast(data.message || window.t('travel_agency.roles.delete_failed'), false);
            }
        } catch (err) {
            toast(window.t('travel_agency.roles.connection_error'), false);
        }
    });
});
