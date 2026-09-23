import $ from 'jquery';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function sendJson(url, method, data = {}) {
    const res = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(data),
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

function updateUrl(template, id) {
    return template.replace('__ID__', id);
}

function initModal() {
    const $modal = $('#free-period-rule-modal');
    const $form = $('#free-period-rule-form');

    $('#btn-add-rule').on('click', function () {
        $form[0].reset();
        $('#rule-id').val('');
        $('#rule-open-ended').prop('checked', false);
        $('#rule-max-weight').prop('disabled', false);
        $modal.modal('open');
    });

    $(document).on('click', '.btn-edit-rule', function () {
        const $btn = $(this);
        const max = $btn.data('max');

        $form[0].reset();
        $('#rule-id').val($btn.data('id'));
        $('#rule-min-weight').val($btn.data('min'));
        $('#rule-free-days').val($btn.data('days'));

        const isOpenEnded = max === null || max === undefined || max === '';
        $('#rule-open-ended').prop('checked', isOpenEnded);
        $('#rule-max-weight').prop('disabled', isOpenEnded).val(isOpenEnded ? '' : max);

        $modal.modal('open');
    });

    $(document).on('click', '.btn-delete-rule', async function () {
        const id = $(this).data('id');
        if (!confirm(window.FREE_PERIOD_RULES_I18N?.deleteConfirm ?? 'Delete this rule?')) return;

        try {
            await sendJson(updateUrl(window.FREE_PERIOD_RULES_ROUTES.destroy, id), 'DELETE');
            toast(window.FREE_PERIOD_RULES_I18N?.ruleDeleted ?? 'Rule deleted.');
            window.location.reload();
        } catch (err) {
            toast(err.message ?? 'Delete failed.', 'error');
        }
    });

    $form.on('submit', async function (e) {
        e.preventDefault();

        const id = $('#rule-id').val();
        const routes = window.FREE_PERIOD_RULES_ROUTES;
        const method = id ? 'PUT' : 'POST';
        const url = id ? updateUrl(routes.update, id) : routes.store;
        const openEnded = $('#rule-open-ended').is(':checked');

        const payload = {
            min_weight_grams: parseInt($('#rule-min-weight').val(), 10) || 0,
            max_weight_grams: openEnded ? null : (parseInt($('#rule-max-weight').val(), 10) || null),
            free_days: parseInt($('#rule-free-days').val(), 10) || 0,
        };

        try {
            await sendJson(url, method, payload);
            toast(window.FREE_PERIOD_RULES_I18N?.ruleSaved ?? 'Rule saved.');
            $modal.modal('close');
            window.location.reload();
        } catch (err) {
            if (err.errors) {
                window.injectValidationErrors?.($form, err.errors);
                const firstError = Object.values(err.errors)[0]?.[0];
                if (firstError) toast(firstError, 'error');
            } else {
                toast(err.message ?? 'Save failed.', 'error');
            }
        }
    });
}

$(function () {
    if (!document.getElementById('free-period-rules-table')) return;
    initModal();
});
