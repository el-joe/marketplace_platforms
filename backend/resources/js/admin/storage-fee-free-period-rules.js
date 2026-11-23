import $ from 'jquery';

const I18N = () => window.FREE_PERIOD_RULES_I18N ?? {};
const ROUTES = () => window.FREE_PERIOD_RULES_ROUTES;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function sendJson(url, method, data = null) {
    const res = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: data === null ? undefined : JSON.stringify(data),
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw json;
    return json;
}

function toast(message, type = 'success') {
    if (window.Toast) {
        type === 'error' ? window.Toast.error(message) : window.Toast.success(message);
    } else {
        alert(message);
    }
}

function errorMessage(err) {
    const first = err?.errors ? Object.values(err.errors)[0]?.[0] : null;
    return first ?? err?.message ?? I18N().error ?? 'Error';
}

function updateUrl(template, id) {
    return template.replace('__ID__', id);
}

/** Sends what the admin typed (incl. Arabic-Indic digits); the server normalizes and validates. */
function rawValue(selector) {
    const value = String($(selector).val() ?? '').trim();
    return value === '' ? null : value;
}

function setOpenEnded(isOpenEnded) {
    $('#rule-open-ended').prop('checked', isOpenEnded);
    $('#rule-max-weight').prop('disabled', isOpenEnded);
    if (isOpenEnded) $('#rule-max-weight').val('');
}

function setEffectiveMode(mode, date = '') {
    $(`input[name="effective_mode"][value="${mode}"]`).prop('checked', true);
    $('#rule-effective-date-wrap').toggleClass('hidden', mode !== 'from_date');
    $('#rule-effective-from').val(date);
}

/** After a rule change: offer an explicit, previewed recalculation of pending fees. */
function showPendingImpact(impact) {
    if (!impact || !impact.count) {
        window.location.reload();
        return;
    }

    $('#pending-impact-text').text(I18N().impactText.replace(':count', impact.count));
    const $totals = $('#pending-impact-totals').empty();
    Object.entries(impact.totals ?? {}).forEach(([currency, t]) => {
        $('<li>').text(I18N().impactTotals
            .replace(':currency', currency)
            .replace(':current', Number(t.current).toFixed(2))
            .replace(':recalculated', Number(t.recalculated).toFixed(2))).appendTo($totals);
    });
    $('#pending-impact-modal').modal('open');
}

function initModal() {
    const $modal = $('#free-period-rule-modal');
    const $form = $('#free-period-rule-form');

    $('#rule-open-ended').on('change', function () {
        setOpenEnded(this.checked);
    });
    $('input[name="effective_mode"]').on('change', function () {
        setEffectiveMode(this.value, $('#rule-effective-from').val());
    });

    $('#btn-add-rule').on('click', function () {
        $form[0].reset();
        $('#rule-id').val('');
        setOpenEnded(false);
        setEffectiveMode('from_creation');
        $modal.modal('open');
    });

    $(document).on('click', '.btn-edit-rule', function () {
        const $btn = $(this);
        const max = $btn.attr('data-max') ?? '';
        const effectiveFrom = $btn.attr('data-effective-from') ?? '';
        const created = $btn.attr('data-created') ?? '';

        $form[0].reset();
        $('#rule-id').val($btn.data('id'));
        $('#rule-min-weight').val($btn.attr('data-min'));
        $('#rule-free-days').val($btn.attr('data-days'));
        $('#rule-max-weight').val(max);
        setOpenEnded(max === '');

        if (effectiveFrom !== '' && effectiveFrom === created) {
            setEffectiveMode('from_creation');
        } else {
            setEffectiveMode('from_date', effectiveFrom);
        }

        $modal.modal('open');
    });

    $(document).on('click', '.btn-delete-rule', async function () {
        const id = $(this).data('id');
        if (!confirm(I18N().deleteConfirm ?? 'Delete this rule?')) return;

        try {
            const res = await sendJson(updateUrl(ROUTES().destroy, id), 'DELETE');
            toast(I18N().ruleDeleted ?? res.message);
            showPendingImpact(res.pending_impact);
        } catch (err) {
            toast(errorMessage(err), 'error');
        }
    });

    $form.on('submit', async function (e) {
        e.preventDefault();

        const id = $('#rule-id').val();
        const openEnded = $('#rule-open-ended').is(':checked');
        const effectiveMode = $('input[name="effective_mode"]:checked').val() ?? 'from_creation';

        const payload = {
            min_weight_grams: rawValue('#rule-min-weight'),
            max_weight_grams: openEnded ? null : rawValue('#rule-max-weight'),
            open_ended: openEnded,
            free_days: rawValue('#rule-free-days'),
            effective_mode: effectiveMode,
            effective_from: effectiveMode === 'from_date' ? rawValue('#rule-effective-from') : null,
        };

        try {
            const res = await sendJson(id ? updateUrl(ROUTES().update, id) : ROUTES().store, id ? 'PUT' : 'POST', payload);
            toast(I18N().ruleSaved ?? res.message);
            $modal.modal('close');
            showPendingImpact(res.pending_impact);
        } catch (err) {
            if (err.errors) window.injectValidationErrors?.($form, err.errors);
            toast(errorMessage(err), 'error');
        }
    });
}

function initRecalculation() {
    $('#btn-recalculate-pending').on('click', async function () {
        try {
            const res = await sendJson(ROUTES().pendingImpact, 'GET');
            if (!res.pending_impact?.count) {
                toast(I18N().noImpact);
                return;
            }
            showPendingImpact(res.pending_impact);
        } catch (err) {
            toast(errorMessage(err), 'error');
        }
    });

    $('#btn-impact-skip').on('click', () => window.location.reload());

    $('#btn-impact-confirm').on('click', async function () {
        const $btn = $(this).prop('disabled', true);
        try {
            const res = await sendJson(ROUTES().recalculatePending, 'POST', { confirm: true });
            toast(res.message);
            window.location.reload();
        } catch (err) {
            toast(errorMessage(err), 'error');
            $btn.prop('disabled', false);
        }
    });
}

$(function () {
    if (!document.getElementById('free-period-rules-table')) return;
    initModal();
    initRecalculation();
});
