const Toast = window.Toast || { success: console.log, error: console.warn, info: console.log };
const T = window.TRANSLATIONS || {};

const URLS = {
    approve: () => `/payouts/${window.PAYOUT_ID}/approve`,
    process: () => `/payouts/${window.PAYOUT_ID}/process`,
    hold: () => `/payouts/${window.PAYOUT_ID}/hold`,
    recalculate: () => `/payouts/${window.PAYOUT_ID}/recalculate`,
};

/**
 * Post a JSON payload to the given URL, then reload on success.
 */
function submitPayoutAction(url, data) {
    return $.ajax({
        url,
        method: 'POST',
        data,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        },
    });
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.dispatchEvent(new Event('close'));
    }
}

$(function () {

    // ── Approve ──────────────────────────────────────────────────────────────
    $('#approve-form').on('submit', function (e) {
        e.preventDefault();
        const $btn = $(this).find('[type=submit]').prop('disabled', true).text(T.payoutsApproving ?? 'Approving…');

        submitPayoutAction(URLS.approve(), $(this).serialize())
            .done(function (res) {
                Toast.success(res.message ?? t('admin.payouts.payout_approved'));
                closeModal('approve-modal');
                setTimeout(() => location.reload(), 600);
            })
            .fail(function (xhr) {
                const msg = xhr.responseJSON?.message ?? t('admin.payouts.failed_approve_payout');
                Toast.error(msg);
            })
            .always(function () {
                $btn.prop('disabled', false).text(T.payoutsApprove ?? 'Approve');
            });
    });

    // ── Process / Complete ───────────────────────────────────────────────────
    $('#process-form').on('submit', function (e) {
        e.preventDefault();
        const $btn = $(this).find('[type=submit]').prop('disabled', true).text(T.payoutsProcessing ?? 'Processing…');

        submitPayoutAction(URLS.process(), $(this).serialize())
            .done(function (res) {
                Toast.success(res.message ?? t('admin.payouts.payout_completed'));
                closeModal('process-modal');
                setTimeout(() => location.reload(), 600);
            })
            .fail(function (xhr) {
                const msg = xhr.responseJSON?.message ?? t('admin.payouts.failed_process_payout');
                Toast.error(msg);
            })
            .always(function () {
                $btn.prop('disabled', false).text(t('admin.payouts.mark_completed_label'));
            });
    });

    // ── Hold ─────────────────────────────────────────────────────────────────
    $('#hold-form').on('submit', function (e) {
        e.preventDefault();
        const reason = $(this).find('[name=reason]').val().trim();
        if (!reason) {
            Toast.warning(t('admin.payouts.hold_reason_required'));
            return;
        }
        const $btn = $(this).find('[type=submit]').prop('disabled', true).text(T.payoutsSaving ?? 'Saving…');

        submitPayoutAction(URLS.hold(), $(this).serialize())
            .done(function (res) {
                Toast.success(res.message ?? t('admin.payouts.payout_on_hold'));
                closeModal('hold-modal');
                setTimeout(() => location.reload(), 600);
            })
            .fail(function (xhr) {
                const msg = xhr.responseJSON?.message ?? t('admin.payouts.failed_hold_payout');
                Toast.error(msg);
            })
            .always(function () {
                $btn.prop('disabled', false).text(t('admin.payouts.put_on_hold_label'));
            });
    });

    // ── Recalculate ──────────────────────────────────────────────────────────
    $('#recalculate-form').on('submit', function (e) {
        e.preventDefault();
        const $btn = $(this).find('[type=submit]').prop('disabled', true).text(T.payoutsCalculating ?? 'Calculating…');

        submitPayoutAction(URLS.recalculate(), $(this).serialize())
            .done(function (res) {
                Toast.success(res.message ?? t('admin.payouts.payout_recalculated'));
                closeModal('recalculate-modal');
                setTimeout(() => location.reload(), 600);
            })
            .fail(function (xhr) {
                const msg = xhr.responseJSON?.message ?? t('admin.payouts.recalculation_failed');
                Toast.error(msg);
            })
            .always(function () {
                $btn.prop('disabled', false).text(T.payoutsRecalculate ?? 'Recalculate');
            });
    });

});
