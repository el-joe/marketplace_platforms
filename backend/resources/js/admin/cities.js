/**
 * resources/js/admin/cities.js
 *
 * Handles:
 *  - Delete city confirmation
 *  - Bulk CSV import modal + progress feedback
 *  - Shipping zone warning highlight
 */

$(function () {
    // ─────────────────────────────────────────────────────────────────────────
    // Filter shipping zones by selected country
    // ─────────────────────────────────────────────────────────────────────────

    function filterZonesByCountry() {
        const $country = $('#country_id');
        const $zone = $('#shipping_zone_id');
        if (!$country.length || !$zone.length) return;

        const countryId = $country.val();
        const selectedZone = $zone.val();
        let selectedStillValid = false;

        $zone.find('option[data-country-id]').each(function () {
            const matches = !countryId || $(this).data('country-id') === countryId;
            $(this).toggle(matches);
            if (matches && this.value === selectedZone) selectedStillValid = true;
        });

        if (!selectedStillValid) $zone.val('');
    }

    $(document).on('change', '#country_id', filterZonesByCountry);
    filterZonesByCountry();

    // ─────────────────────────────────────────────────────────────────────────
    // Delete city
    // ─────────────────────────────────────────────────────────────────────────

    $(document).on('click', '#btn-delete-city', async function () {
        const name = $(this).data('city-name');
        const url = $(this).data('url');
        const message = (window.TRANSLATIONS?.deleteCityConfirm || 'Delete ":name"? This cannot be undone.').replace(':name', name);
        const confirmed = window.confirmDelete
            ? await window.confirmDelete(message, { title: window.TRANSLATIONS?.deleteCityTitle || 'Delete city?' })
            : confirm(message);
        if (!confirmed) return;

        $.ajax({ url, method: 'DELETE', headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } })
            .done(function (res) {
                window.Toast && window.Toast.success(res.message);
                setTimeout(() => window.location.href = '/cities/', 900);
            })
            .fail(function (xhr) {
                window.Toast && window.Toast.error(xhr.responseJSON?.message || window.TRANSLATIONS?.cityDeleteFailed || 'Delete failed.');
            });
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Bulk Import Modal
    // ─────────────────────────────────────────────────────────────────────────

    function openModal(id) { window.openModal ? window.openModal(id) : $(`#${id}`).removeClass('hidden').show(); }
    function closeModal(id) { window.closeModal ? window.closeModal(id) : $(`#${id}`).addClass('hidden').hide(); }

    $(document).on('click', '#btn-bulk-import', function () {
        // Reset modal state
        $('#import-progress').addClass('hidden');
        $('#import-result').addClass('hidden').text('');
        $('#bulk-import-form')[0].reset();
        openModal('bulk-import-modal');
    });

    $('#bulk-import-form').on('submit', function (e) {
        e.preventDefault();

        const fileInput = $('input[name="file"]')[0];
        if (!fileInput || !fileInput.files[0]) {
            window.Toast && window.Toast.error(window.TRANSLATIONS?.selectCsvFile || 'Please select a CSV file.');
            return;
        }

        const formData = new FormData(this);

        $('#btn-start-import').prop('disabled', true).text(window.TRANSLATIONS?.importingEllipsis || 'Importing…');
        $('#import-progress').removeClass('hidden');
        $('#import-result').addClass('hidden');

        $.ajax({
            url: window.citiesBulkImportUrl || '/cities/bulk-import',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        })
            .done(function (res) {
                let html = `<div class="text-success-700 font-medium">${res.message}</div>`;
                if (res.inserted > 0) {
                    const insertedMsg = (window.TRANSLATIONS?.citiesInserted || ':count cities inserted.').replace(':count', res.inserted);
                    html += `<p class="mt-1">✓ ${insertedMsg}</p>`;
                }
                if (res.errors && res.errors.length > 0) {
                    const rowErrorsLabel = (window.TRANSLATIONS?.rowErrorsCount || 'Row errors (:count):').replace(':count', res.errors.length);
                    html += `<div class="mt-2 text-warning-700 font-medium">${rowErrorsLabel}</div>`;
                    html += `<ul class="mt-1 space-y-0.5 text-xs text-warning-600">`;
                    res.errors.slice(0, 20).forEach(err => {
                        html += `<li>• ${err}</li>`;
                    });
                    if (res.errors.length > 20) {
                        const moreMsg = (window.TRANSLATIONS?.moreErrors || '…and :count more').replace(':count', res.errors.length - 20);
                        html += `<li class="text-gray-400">${moreMsg}</li>`;
                    }
                    html += '</ul>';
                }
                $('#import-result').html(html).removeClass('hidden');
                window.reloadDataTable && window.reloadDataTable('cities-table');
            })
            .fail(function (xhr) {
                const msg = xhr.responseJSON?.message || window.TRANSLATIONS?.importFailed || 'Import failed.';
                $('#import-result')
                    .html(`<div class="text-danger-700">${msg}</div>`)
                    .removeClass('hidden');
            })
            .always(function () {
                $('#import-progress').addClass('hidden');
                $('#btn-start-import').prop('disabled', false).text(window.TRANSLATIONS?.importBtn || 'Import');
            });
    });
});
