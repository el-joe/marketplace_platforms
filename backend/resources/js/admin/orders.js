import $ from 'jquery';

$(function () {

    // ── Sub-order accordion ──────────────────────────────────────────────────

    $(document).on('click', '.sub-order-toggle, .sub-order-header', function (e) {
        if ($(e.target).closest('button').length && !$(e.target).closest('.sub-order-toggle').length) {
            return;
        }
        const $header = $(this).closest('.sub-order-header');
        const $body = $header.next('.sub-order-body');
        const $icon = $header.find('.toggle-icon');
        $body.slideToggle(200);
        $icon.toggleClass('rotate-180');
    });

    // Open first sub-order by default
    $('.sub-order-header').first().next('.sub-order-body').show();
    $('.sub-order-header').first().find('.toggle-icon').addClass('rotate-180');

    // ── Partial amount toggle ────────────────────────────────────────────────

    $('input[name="refund_type"]').on('change', function () {
        $('#partial-amount-field').toggleClass('hidden', $(this).val() !== 'partial');
    });

    // ── Generic AJAX form submitter ──────────────────────────────────────────

    function clearFormErrors($form) {
        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('[data-error]').text('').addClass('hidden');
    }

    function submitOrderAction(formId, url, onSuccess) {
        const $form = $('#' + formId);
        const $btn = $form.find('[type="submit"]');

        clearFormErrors($form);
        $btn.prop('disabled', true);

        $.ajax({
            url: url,
            method: 'POST',
            data: $form.serialize(),
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                $form.closest('[id$="-modal"]').find('[data-modal-close]').first().trigger('click');
                if (window.Toast) {
                    window.Toast.success(res.message || (window.TRANSLATIONS && window.TRANSLATIONS.action_completed) || 'Action completed.');
                }
                if (typeof onSuccess === 'function') {
                    onSuccess(res);
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false);
                const json = xhr.responseJSON || {};
                const message = typeof json.message === 'string'
                    ? json.message
                    : ((window.TRANSLATIONS && window.TRANSLATIONS.generic_error) || 'An error occurred. Please try again.');

                if (xhr.status === 422 && json.errors) {
                    Object.entries(json.errors).forEach(function ([field, msgs]) {
                        $form.find('[name="' + field + '"]').addClass('is-invalid');
                        $form.find('[data-error="' + field + '"]')
                            .text(msgs[0])
                            .removeClass('hidden');
                    });
                }
                if (window.Toast) {
                    window.Toast.error(message);
                }
            },
        });
    }

    // ── Form bindings ────────────────────────────────────────────────────────

    const orderId = window.ORDER_ID;

    $('#update-status-form').on('submit', function (e) {
        e.preventDefault();
        submitOrderAction('update-status-form', '/orders/' + orderId + '/update-status', function () {
            location.reload();
        });
    });

    $('#refund-form').on('submit', function (e) {
        e.preventDefault();
        submitOrderAction('refund-form', '/orders/' + orderId + '/refund', function () {
            location.reload();
        });
    });

    $('#force-cancel-form').on('submit', function (e) {
        e.preventDefault();
        submitOrderAction('force-cancel-form', '/orders/' + orderId + '/force-cancel', function () {
            location.reload();
        });
    });

    $('#cancel-items-form').on('submit', function (e) {
        e.preventDefault();
        submitOrderAction('cancel-items-form', '/orders/' + orderId + '/cancel-items', function () {
            location.reload();
        });
    });

    $('#dispute-form').on('submit', function (e) {
        e.preventDefault();
        submitOrderAction('dispute-form', '/orders/' + orderId + '/dispute', function () {
            location.reload();
        });
    });

    $('#fraud-form').on('submit', function (e) {
        e.preventDefault();
        submitOrderAction('fraud-form', '/orders/' + orderId + '/flag-fraud', function () {
            location.reload();
        });
    });

    // ── Shipping method assignment ───────────────────────────────────────────

    let selectedCarrierId = null;
    let assignUrl = null;

    function centsToAmount(cents) {
        return (Number(cents || 0) / 100).toFixed(2);
    }

    function renderCarriers(data) {
        const $container = $('#shipping-assign-methods');
        $container.empty();

        if (data.method) {
            $container.append(
                '<div class="rounded-lg bg-blue-50 border border-blue-200 p-3 mb-4">' +
                '<p class="text-xs font-semibold text-blue-600 uppercase tracking-wide mb-0.5">' + ((window.TRANSLATIONS && window.TRANSLATIONS.selected_shipping_method) || 'Selected Shipping Method') + '</p>' +
                '<p class="text-sm font-medium text-blue-900">' + data.method.name + '</p>' +
                '<p class="text-xs text-blue-500">' + data.method.min_delivery_days + '–' + data.method.max_delivery_days + ' days</p>' +
                '</div>'
            );
        }

        if (!data.method_assigned) {
            $container.append(
                '<div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">' +
                (data.message || 'No shipping method assigned yet.') +
                '</div>'
            );
            return;
        }

        const carriers = data.carriers || [];

        if (carriers.length === 0) {
            const noCarrierText = (window.TRANSLATIONS && window.TRANSLATIONS.no_eligible_shipping_methods) || 'No carriers available for this method and destination.';
            $container.append('<p class="text-sm text-gray-400 italic text-center py-4">' + noCarrierText + '</p>');
            return;
        }

        carriers.forEach(function (carrier) {
            const isCurrent = carrier.is_current;
            const $card = $('<div>', {
                class: 'carrier-card border rounded-xl p-4 cursor-pointer hover:border-primary-400 mb-2 '
                    + (isCurrent ? 'border-primary-500 ring-2 ring-primary-200' : 'border-gray-200'),
                'data-carrier-id': carrier.carrier_id || '',
            });

            if (isCurrent) {
                selectedCarrierId = carrier.carrier_id || null;
                $('#shipping-assign-confirm').prop('disabled', false);
            }

            const feeText = (carrier.base_fee ? centsToAmount(carrier.base_fee) : '—')
                + (carrier.cod_extra_fee ? ' (+' + centsToAmount(carrier.cod_extra_fee) + ' COD)' : '');

            let html = '<div class="flex items-center justify-between">';
            html += '<div>';
            html += '<span class="font-medium text-gray-900">' + (carrier.carrier_name || ((window.TRANSLATIONS && window.TRANSLATIONS.any_carrier) || 'Any carrier')) + '</span>';
            html += '<span class="text-xs text-gray-500 ml-2">' + feeText + '</span>';
            html += '</div>';
            if (isCurrent) {
                html += '<span class="text-xs bg-primary-50 text-primary-700 rounded px-1.5 py-0.5">Current</span>';
            }
            html += '</div>';

            $card.html(html);

            $card.on('click', function () {
                $('.carrier-card').removeClass('border-primary-500 ring-2 ring-primary-200').addClass('border-gray-200');
                $card.removeClass('border-gray-200').addClass('border-primary-500 ring-2 ring-primary-200');
                selectedCarrierId = carrier.carrier_id || null;
                $('#shipping-assign-confirm').prop('disabled', false);
            });

            $container.append($card);
        });
    }

    $(document).on('click', '.btn-assign-shipping', function () {
        const $btn = $(this);
        assignUrl = $btn.data('assign-url');
        const shippingUrl = $btn.data('shipping-url');

        selectedCarrierId = null;
        $('#shipping-assign-confirm').prop('disabled', true);
        $('#shipping-assign-zone-warning').addClass('hidden');
        $('#shipping-assign-error').addClass('hidden').text('');
        $('#shipping-assign-methods').addClass('hidden').empty();
        $('#shipping-assign-loading').removeClass('hidden');

        $('#shipping-assign-modal').modal('open');

        $.ajax({
            url: shippingUrl,
            method: 'GET',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                $('#shipping-assign-loading').addClass('hidden');
                $('#shipping-assign-methods').removeClass('hidden');
                if (!res.destination_zone) {
                    $('#shipping-assign-zone-warning').removeClass('hidden');
                }
                renderCarriers(res);
            },
            error: function (xhr) {
                $('#shipping-assign-loading').addClass('hidden');
                const json = xhr.responseJSON || {};
                $('#shipping-assign-error').removeClass('hidden').text(json.message || (window.TRANSLATIONS && window.TRANSLATIONS.failed_load_shipping_methods) || 'Failed to load shipping methods.');
            },
        });
    });

    $('#shipping-assign-confirm').on('click', function () {
        if (!assignUrl) return;
        const $btn = $(this);
        $btn.prop('disabled', true);
        $('#shipping-assign-error').addClass('hidden').text('');

        $.ajax({
            url: assignUrl,
            method: 'POST',
            data: {
                carrier_id: selectedCarrierId,
                _token: $('meta[name="csrf-token"]').attr('content'),
            },
            success: function (res) {
                $('[data-modal-close]').first().trigger('click');
                if (window.Toast) {
                    window.Toast.success((res && res.message) || (window.TRANSLATIONS && window.TRANSLATIONS.shipping_method_assigned) || 'Carrier assigned.');
                }
                location.reload();
            },
            error: function (xhr) {
                $btn.prop('disabled', false);
                const json = xhr.responseJSON || {};
                $('#shipping-assign-error').removeClass('hidden').text(json.message || (window.TRANSLATIONS && window.TRANSLATIONS.failed_assign_shipping_method) || 'Failed to assign carrier.');
            },
        });
    });

});
