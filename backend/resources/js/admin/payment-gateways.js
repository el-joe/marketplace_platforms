import $ from 'jquery';

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function api(url, method = 'GET', data = null) {
    const opts = {
        method,
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
    };
    if (data) {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(data);
    }
    const res = await fetch(url, opts);
    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw json;
    return json;
}

function toast(msg, type = 'success') {
    window.showToast?.(msg, type) ?? alert(msg);
}

// ── Render credential fields from required_fields array ─────────────────────

function renderCredentialFields(requiredFields) {
    const $container = $('#gw-credentials-fields');
    $container.empty();

    if (!requiredFields || requiredFields.length === 0) {
        $container.html('<p class="text-xs text-gray-400 italic">No API credentials required for this gateway.</p>');
        return;
    }

    requiredFields.forEach(field => {
        $container.append(`
            <div class="grid grid-cols-3 items-center gap-2">
                <label class="text-xs font-medium text-gray-600">
                    ${field.label}
                    ${field.secret ? ' <span class="text-gray-300">(encrypted)</span>' : ''}
                </label>
                <input
                    type="${field.secret ? 'password' : 'text'}"
                    name="credentials[${field.key}]"
                    placeholder="${field.placeholder ?? (field.secret ? 'Leave blank to keep existing' : '')}"
                    autocomplete="off"
                    class="col-span-2 block w-full rounded-lg border border-gray-300 py-1.5 px-2.5 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-500"
                >
            </div>
        `);
    });
}

// ── Gateway selector change ──────────────────────────────────────────────────

$(document).on('change', '#gw-gateway-id', function () {
    const $opt = $(this).find(':selected');
    let fields = $opt.data('fields') || [];
    if (typeof fields === 'string') {
        fields = JSON.parse(fields || '[]');
    }
    const nameEn = $opt.data('name-en') ?? '';
    const nameAr = $opt.data('name-ar') ?? '';

    // Pre-fill display names from gateway defaults
    if (!$('#gw-display-name-en').val()) {
        $('#gw-display-name-en').val(nameEn);
    }
    if (!$('#gw-display-name-ar').val()) {
        $('#gw-display-name-ar').val(nameAr);
    }

    renderCredentialFields(fields);
    $('#gw-test-result').text('');
});

// ── Add modal ────────────────────────────────────────────────────────────────

$(document).on('click', '.btn-add-country-gateway', function () {
    const countryId   = $(this).data('country-id');
    const countryName = $(this).data('country-name');
    const existing    = $(this).data('existing') || [];

    // Reset form
    $('#gateway-form')[0].reset();
    $('#gw-id').val('');
    $('#gw-http').val('POST');
    $('#gw-country-id').val(countryId);

    // Show gateway selector, hide name display
    $('#gw-gateway-select-wrapper').removeClass('hidden');
    $('#gw-gateway-name-wrapper').addClass('hidden');

    // Hide options already configured for this country
    $('#gw-gateway-id option').each(function () {
        const id = $(this).val();
        $(this).prop('disabled', existing.includes(id));
    });

    renderCredentialFields([]);
    $('#btn-test-gateway-connection').prop('disabled', true);
    $('#gw-test-result').text('');
    $('#gateway-modal').modal?.('open');
});

// ── Edit modal ───────────────────────────────────────────────────────────────

$(document).on('click', '.btn-edit-gateway', function () {
    const row = JSON.parse($(this).attr('data-row'));

    $('#gateway-form')[0].reset();
    $('#gw-id').val(row.id);
    $('#gw-http').val('PUT');
    $('#gw-country-id').val(row.country_id);

    // In edit mode: hide gateway selector, show read-only name
    $('#gw-gateway-select-wrapper').addClass('hidden');
    $('#gw-gateway-name-wrapper').removeClass('hidden');
    $('#gw-gateway-name-display').text(row.gateway_name ?? '');

    // Populate fields
    $('#gw-display-name-en').val(row.display_name_en ?? '');
    $('#gw-display-name-ar').val(row.display_name_ar ?? '');
    $('#gw-environment').val(row.environment ?? 'sandbox');
    $('#gw-sort-order').val(row.sort_order ?? 0);
    $('#gw-fee-pct').val(row.fee_pct ?? 0);
    $('#gw-fee-fixed').val(row.fee_fixed ?? 0);

    // Render credential fields from gateway's required_fields
    // (never pre-fill values — credentials are secret and never sent to client)
    renderCredentialFields(row.required_fields ?? []);

    $('#btn-test-gateway-connection').prop('disabled', false);
    $('#gw-test-result').text('');
    $('#gateway-modal').modal?.('open');
});

// ── Form submit ──────────────────────────────────────────────────────────────

$('#gateway-form').on('submit', async function (e) {
    e.preventDefault();

    const id     = $('#gw-id').val();
    const method = $('#gw-http').val();
    const url    = id ? `/payment-gateways/${id}` : '/payment-gateways';

    // Collect credentials — only non-empty values (keep existing if blank)
    const credentials = {};
    $(this).find('[name^="credentials["]').each(function () {
        const val = $(this).val().trim();
        if (val) {
            const key = /credentials\[(.+)\]/.exec($(this).attr('name'))?.[1];
            if (key) credentials[key] = val;
        }
    });

    const payload = {
        country_id:      $('#gw-country-id').val(),
        gateway_id:      $('#gw-gateway-id').val() || undefined,
        display_name_en: $('#gw-display-name-en').val(),
        display_name_ar: $('#gw-display-name-ar').val() || null,
        environment:     $('#gw-environment').val(),
        sort_order:      parseInt($('#gw-sort-order').val()) || 0,
        fee_pct:         parseFloat($('#gw-fee-pct').val()) || 0,
        fee_fixed:       parseInt($('#gw-fee-fixed').val()) || 0,
        credentials:     Object.keys(credentials).length ? credentials : undefined,
        webhook_secret:  $('#gw-webhook-secret').val().trim() || null,
    };

    try {
        await api(url, method, payload);
        toast(id ? 'Gateway updated.' : 'Gateway added.');
        $('#gateway-modal').modal?.('close');
        setTimeout(() => location.reload(), 400);
    } catch (err) {
        toast(err.message ?? 'Save failed.', 'error');
    }
});

// ── Toggle active ────────────────────────────────────────────────────────────

$(document).on('click', '.btn-toggle-gateway', async function () {
    const id   = $(this).data('id');
    const $btn = $(this);
    try {
        const res = await api(`/payment-gateways/${id}/toggle`, 'POST');
        $btn.text(res.is_active ? 'Active' : 'Inactive')
            .toggleClass('bg-success-50 text-success-700 hover:bg-success-100', res.is_active)
            .toggleClass('bg-gray-100 text-gray-500 hover:bg-gray-200', !res.is_active);
    } catch {
        toast('Failed to update status.', 'error');
    }
});

// ── Test connection ──────────────────────────────────────────────────────────

$(document).on('click', '.btn-test-gateway', async function () {
    const id      = $(this).data('id');
    const $result = $(`.test-result-${id}`);
    $(this).prop('disabled', true);
    $result.text('Testing…');
    try {
        const res = await api(`/payment-gateways/${id}/test-connection`, 'POST');
        $result.text((res.success ? '✅ ' : '❌ ') + (res.message ?? ''));
    } catch (err) {
        $result.text('❌ ' + (err.message ?? 'Error'));
    } finally {
        $(this).prop('disabled', false);
    }
});

$('#btn-test-gateway-connection').on('click', async function () {
    const id      = $('#gw-id').val();
    const $result = $('#gw-test-result');
    if (!id) { $result.text('Save first to test connection.'); return; }
    $(this).prop('disabled', true);
    $result.text('Testing…');
    try {
        const res = await api(`/payment-gateways/${id}/test-connection`, 'POST');
        $result.text((res.success ? '✅ ' : '❌ ') + (res.message ?? ''));
    } catch (err) {
        $result.text('❌ ' + (err.message ?? 'Error'));
    } finally {
        $(this).prop('disabled', false);
    }
});

// ── Delete ───────────────────────────────────────────────────────────────────

$(document).on('click', '.btn-delete-gateway', function () {
    const id   = $(this).data('id');
    const name = $(this).data('name');
    $('#delete-gateway-message').text(`Remove "${name}" from this country?`);
    $('#delete-gateway-id').val(id);
    $('#delete-gateway-modal').modal?.('open');
});

$('#btn-confirm-delete-gateway').on('click', async function () {
    const id = $('#delete-gateway-id').val();
    try {
        await api(`/payment-gateways/${id}`, 'DELETE');
        toast('Gateway removed.');
        $('#delete-gateway-modal').modal?.('close');
        setTimeout(() => location.reload(), 400);
    } catch (err) {
        toast(err.message ?? 'Delete failed.', 'error');
    }
});
