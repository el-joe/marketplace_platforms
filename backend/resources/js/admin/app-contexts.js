/**
 * App Contexts admin module
 * resources/js/admin/app-contexts.js
 */
import Sortable from 'sortablejs';

$(function () {
    'use strict';

    const csrf = () => $('meta[name="csrf-token"]').attr('content');

    const ajaxHeaders = () => ({
        'X-CSRF-TOKEN': csrf(),
        'Accept': 'application/json',
    });

    // ── General settings form ───────────────────────────────────────────────

    $('#general-settings-form').on('submit', function (e) {
        e.preventDefault();
        const $form = $(this);
        const formData = new FormData(this);
        formData.append('_method', 'PUT');

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: ajaxHeaders(),
        }).done(function (response) {
            window.Toast.success(response.message || t('shared.saved'));
        }).fail(function (xhr) {
            if (xhr.status === 422) {
                window.injectValidationErrors($form, xhr.responseJSON.errors);
            } else {
                window.Toast.error(t('admin.app_contexts.failed_to_save'));
            }
        });
    });

    // ── Country assignment ───────────────────────────────────────────────────

    $('.js-save-country-assignment').on('click', function () {
        const $row = $(this).closest('.js-country-row');
        const countryId = $row.data('country-id');
        const isActive = $row.find('.js-country-active').is(':checked');
        const homePageId = $row.find('.js-country-home-page').val();

        $.ajax({
            url: window.APP_CONTEXT.saveCountryUrl,
            method: 'POST',
            data: {
                country_id: countryId,
                home_page_id: homePageId || null,
                is_active: isActive ? 1 : 0,
                _token: csrf(),
            },
            headers: ajaxHeaders(),
        }).done(function (response) {
            window.Toast.success(response.message || t('shared.saved'));
        }).fail(function () {
            window.Toast.error(t('admin.app_contexts.failed_to_save_country_assignment'));
        });
    });

    // ── Bottom navigation ─────────────────────────────────────────────────────

    const $navModal = $('#nav-item-modal');
    const $navForm = $('#nav-item-form');

    function toggleDeepLinkField() {
        const isCustom = $navForm.find('[name="nav_type"]').val() === 'custom';
        $navForm.find('.js-deep-link-wrap').toggleClass('hidden', !isCustom);
    }

    $navForm.on('change', '[name="nav_type"]', toggleDeepLinkField);

    $(document).on('click', '.js-nav-slot', function () {
        const $slot = $(this);
        const $slots = $slot.closest('.js-nav-slots');

        $navForm.find('[name="item_id"]').val($slot.data('item-id') || '');
        $navForm.find('[name="country_id"]').val($slots.data('country-id') || '');
        $navForm.find('[name="position"]').val($slot.data('position'));
        $navForm.find('[name="nav_type"]').val($slot.data('nav-type') || 'home');
        $navForm.find('[name="label_en"]').val($slot.data('label-en') || '');
        $navForm.find('[name="label_ar"]').val($slot.data('label-ar') || '');
        $navForm.find('[name="icon_name"]').val($slot.data('icon-name') || '');
        $navForm.find('[name="deep_link"]').val($slot.data('deep-link') || '');
        $navForm.find('[name="is_center_featured"]').prop('checked', String($slot.data('is-center-featured')) === '1');

        toggleDeepLinkField();
        $navModal.modal('open');
    });

    $navForm.on('submit', function (e) {
        e.preventDefault();

        const payload = {
            country_id: $navForm.find('[name="country_id"]').val() || null,
            position: $navForm.find('[name="position"]').val(),
            nav_type: $navForm.find('[name="nav_type"]').val(),
            label_en: $navForm.find('[name="label_en"]').val(),
            label_ar: $navForm.find('[name="label_ar"]').val(),
            icon_name: $navForm.find('[name="icon_name"]').val(),
            deep_link: $navForm.find('[name="nav_type"]').val() === 'custom' ? $navForm.find('[name="deep_link"]').val() : null,
            is_center_featured: $navForm.find('[name="is_center_featured"]').is(':checked') ? 1 : 0,
            _token: csrf(),
        };

        $.ajax({
            url: window.APP_CONTEXT.saveNavUrl,
            method: 'POST',
            data: payload,
            headers: ajaxHeaders(),
        }).done(function () {
            window.Toast.success(t('admin.app_contexts.navigation_item_saved'));
            window.location.reload();
        }).fail(function (xhr) {
            if (xhr.status === 422) {
                window.injectValidationErrors($navForm, xhr.responseJSON.errors);
            } else {
                window.Toast.error(t('admin.app_contexts.failed_to_save_navigation_item'));
            }
        });
    });

    // Drag-to-reorder within each 5-slot row — updates sort_order only.
    $('.js-nav-slots').each(function () {
        Sortable.create(this, {
            animation: 150,
            onEnd: function (evt) {
                const $container = $(evt.target);
                $container.find('.js-nav-slot').each(function (index) {
                    const itemId = $(this).data('item-id');
                    if (!itemId) return;

                    $.ajax({
                        url: `${window.APP_CONTEXT.updateNavUrlBase}/${itemId}`,
                        method: 'PUT',
                        data: { sort_order: index + 1, _token: csrf() },
                        headers: ajaxHeaders(),
                    });
                });
            },
        });
    });

    // ── Country override ─────────────────────────────────────────────────────

    $('.js-add-country-override').on('click', function () {
        $('#country-override-modal').modal('open');
    });

    $('#country-override-form').on('submit', function (e) {
        e.preventDefault();
        const countryId = $(this).find('[name="country_id"]').val();
        if (!countryId) return;

        window.location.href = `?override_country=${countryId}`;
    });

    // ── Modal close ───────────────────────────────────────────────────────────

    $(document).on('click', '.js-close-modal', function () {
        $('#' + $(this).data('modal')).modal('close');
    });
});
