import './app.js';

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

const cfg = () => window.TEAM;

function csrfHeaders(json = true) {
    const h = { 'X-CSRF-TOKEN': cfg().csrf };
    if (json) h['Accept'] = 'application/json';
    return h;
}

function urlFor(template, id) {
    return template.replace(':id', id);
}

function toast(message, ok = true) {
    window.Toastify?.({
        text: message,
        backgroundColor: ok ? '#22c55e' : '#ef4444',
        duration: 3500,
        gravity: 'top',
        position: 'center',
    }).showToast();
}

function setLoading(btn, loading, label = 'جارٍ...') {
    btn.disabled = loading;
    btn.dataset.orig = btn.dataset.orig ?? btn.textContent;
    btn.textContent = loading ? label : btn.dataset.orig;
}

// ─────────────────────────────────────────────────────────────────────────────
// Invite modal form
// ─────────────────────────────────────────────────────────────────────────────

function initInviteModal() {
    const form = document.getElementById('form-invite');
    const errorEl = document.getElementById('invite-error');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('btn-send-invite');

        if (errorEl) errorEl.classList.add('hidden');
        setLoading(btn, true, 'جارٍ الإرسال...');

        const body = new URLSearchParams(new FormData(form));

        try {
            const res = await fetch(cfg().storeUrl, {
                method: 'POST',
                headers: { ...csrfHeaders(), 'Content-Type': 'application/x-www-form-urlencoded' },
                body,
            });

            const data = await res.json();

            if (!res.ok) {
                const msg = data.errors
                    ? Object.values(data.errors).flat().join(' — ')
                    : (data.message ?? 'حدث خطأ');
                if (errorEl) { errorEl.textContent = msg; errorEl.classList.remove('hidden'); }
                return;
            }

            toast(data.message);
            form.reset();
            // Close modal via Alpine (dispatch a custom event)
            document.dispatchEvent(new CustomEvent('close-invite-modal'));

            // Append new row to table
            if (data.member) appendMemberRow(data.member);

        } catch (err) {
            toast(err.message, false);
        } finally {
            setLoading(btn, false);
        }
    });

    // Listen to close event from JS (complement to Alpine's @click)
    document.addEventListener('close-invite-modal', () => {
        // Reach into Alpine component and set showInviteModal = false
        const el = document.querySelector('[x-data]');
        if (el?._x_dataStack?.[0]) {
            el._x_dataStack[0].showInviteModal = false;
        }
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// Toggle active
// ─────────────────────────────────────────────────────────────────────────────

function initTeamActions() {
    const tbody = document.getElementById('team-tbody');
    if (!tbody) return;

    tbody.addEventListener('change', async (e) => {
        // ── Change role ──────────────────────────────────────────────────────
        const roleSelect = e.target.closest('.select-change-role');
        if (roleSelect) {
            const id = roleSelect.dataset.id;
            const url = urlFor(cfg().updateRoleUrl, id);
            const role = roleSelect.value;

            roleSelect.disabled = true;

            try {
                const res = await fetch(url, {
                    method: 'PUT',
                    headers: { ...csrfHeaders(), 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ role }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message ?? 'فشل تحديث الدور');

                toast(data.message);
            } catch (err) {
                toast(err.message, false);
            } finally {
                roleSelect.disabled = false;
            }
        }
    });

    tbody.addEventListener('click', async (e) => {
        // ── Toggle active ────────────────────────────────────────────────────
        const toggleBtn = e.target.closest('.btn-toggle-active');
        if (toggleBtn) {
            const id = toggleBtn.dataset.id;
            const url = urlFor(cfg().toggleActiveUrl, id);

            setLoading(toggleBtn, true);

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: csrfHeaders(),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message ?? 'فشلت العملية');

                toast(data.message);
                updateMemberRow(id, data.is_active);
            } catch (err) {
                toast(err.message, false);
            } finally {
                setLoading(toggleBtn, false);
            }
            return;
        }

        // ── Delete member ────────────────────────────────────────────────────
        const deleteBtn = e.target.closest('.btn-delete-member');
        if (deleteBtn) {
            const id = deleteBtn.dataset.id;
            const name = deleteBtn.dataset.name;

            if (!confirm(`هل أنت متأكد من حذف "${name}"؟ لا يمكن التراجع عن هذا الإجراء.`)) return;

            const url = urlFor(cfg().destroyUrl, id);
            deleteBtn.disabled = true;

            try {
                const res = await fetch(url, {
                    method: 'DELETE',
                    headers: csrfHeaders(),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message ?? 'فشل الحذف');

                toast(data.message);
                // Remove row from DOM
                document.getElementById(`member-row-${id}`)?.remove();
            } catch (err) {
                toast(err.message, false);
                deleteBtn.disabled = false;
            }
        }
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// DOM helpers
// ─────────────────────────────────────────────────────────────────────────────

function updateMemberRow(id, isActive) {
    const row = document.getElementById(`member-row-${id}`);
    if (!row) return;

    // Update status badge
    const badge = row.querySelector('.member-status-badge');
    if (badge) {
        badge.textContent = isActive ? 'نشط' : 'معطَّل';
        badge.className = badge.className
            .replace(/bg-\S+|text-\S+(?=\s)/g, '')
            .trim();
        badge.classList.add(
            ...(isActive ? ['bg-green-100', 'text-green-700'] : ['bg-red-100', 'text-red-600'])
        );
    }

    // Update toggle button
    const toggleBtn = row.querySelector('.btn-toggle-active');
    if (toggleBtn) {
        toggleBtn.dataset.active = isActive ? '1' : '0';
        toggleBtn.textContent = isActive ? 'تعطيل' : 'تفعيل';
        // Swap border/text classes
        toggleBtn.classList.remove('border-amber-300', 'text-amber-700', 'hover:bg-amber-50',
            'border-green-300', 'text-green-700', 'hover:bg-green-50');
        if (isActive) {
            toggleBtn.classList.add('border-amber-300', 'text-amber-700', 'hover:bg-amber-50');
        } else {
            toggleBtn.classList.add('border-green-300', 'text-green-700', 'hover:bg-green-50');
        }
    }
}

function appendMemberRow(member) {
    const tbody = document.getElementById('team-tbody');
    if (!tbody) return;

    // Remove empty state row if present
    tbody.querySelectorAll('tr td[colspan]').forEach(td => td.closest('tr').remove());

    const roleLabelMap = { vendor_owner: 'مالك', vendor_manager: 'مدير', vendor_staff: 'موظف' };
    const roleColorMap = { vendor_owner: 'bg-purple-100 text-purple-700', vendor_manager: 'bg-blue-100 text-blue-700', vendor_staff: 'bg-gray-100 text-gray-600' };

    const tr = document.createElement('tr');
    tr.id = `member-row-${member.id}`;
    tr.className = 'hover:bg-gray-50 transition-colors';
    tr.innerHTML = `
        <td class="px-6 py-4">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 h-9 w-9 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-semibold text-sm select-none">
                    ${escHtml(member.name.charAt(0))}
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900">${escHtml(member.name)}</p>
                    <p class="text-xs text-gray-500">${escHtml(member.email)}</p>
                </div>
            </div>
        </td>
        <td class="px-6 py-4">
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${roleColorMap[member.role] ?? 'bg-gray-100 text-gray-600'}">
                ${roleLabelMap[member.role] ?? member.role}
            </span>
        </td>
        <td class="px-6 py-4 hidden sm:table-cell">
            <span class="text-sm text-gray-500">—</span>
        </td>
        <td class="px-6 py-4">
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium member-status-badge bg-green-100 text-green-700">
                نشط
            </span>
        </td>
        <td class="px-6 py-4 text-left">
            <div class="flex items-center justify-end gap-2">
                <button type="button"
                    class="btn-toggle-active text-xs font-medium px-3 py-1.5 rounded-lg border border-amber-300 text-amber-700 hover:bg-amber-50 transition-colors"
                    data-id="${member.id}" data-active="1">
                    تعطيل
                </button>
                <button type="button"
                    class="btn-delete-member text-xs font-medium px-3 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 transition-colors"
                    data-id="${member.id}" data-name="${escHtml(member.name)}">
                    حذف
                </button>
            </div>
        </td>`;

    tbody.appendChild(tr);
}

function escHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ─────────────────────────────────────────────────────────────────────────────
// Bootstrap
// ─────────────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initInviteModal();
    initTeamActions();
});
