function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function submitReview(action) {
    const form = document.getElementById('change-request-review-form');
    if (!form) return;

    const requestId = form.dataset.requestId;
    const adminNote = form.querySelector('[name=admin_note]').value;

    if (action === 'reject' && !adminNote.trim()) {
        window.Toast?.error(t('admin.vendor_change_requests_show.note_required_to_reject'));
        return;
    }

    if (!confirm(action === 'approve'
        ? t('admin.vendor_change_requests_show.approve_change_request_confirm')
        : t('admin.vendor_change_requests_show.reject_change_request_confirm'))) {
        return;
    }

    fetch(`/vendor-change-requests/${requestId}/${action}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
        },
        body: JSON.stringify({ admin_note: adminNote }),
    })
        .then(res => res.json().then(data => ({ ok: res.ok, data })))
        .then(({ ok, data }) => {
            if (!ok) throw new Error(data.message || 'Failed.');
            window.Toast?.success(data.message);
            setTimeout(() => location.reload(), 700);
        })
        .catch(err => window.Toast?.error(err.message || 'Failed.'));
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('btn-approve-request')?.addEventListener('click', () => submitReview('approve'));
    document.getElementById('btn-reject-request')?.addEventListener('click', () => submitReview('reject'));
});
