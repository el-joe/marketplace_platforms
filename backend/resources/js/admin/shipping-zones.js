/**
 * Shipping Zones & Rates admin module
 * resources/js/admin/shipping-zones.js
 */
$(function () {
    'use strict';

    // ── Helpers ───────────────────────────────────────────────────────────────

    const csrf = () => $('meta[name="csrf-token"]').attr('content');

    const ajaxHeaders = () => ({
        'X-CSRF-TOKEN': csrf(),
        'Accept': 'application/json',
    });

    function openModal(id) {
        $('#' + id).modal('open');
    }

    function closeModal(id) {
        $('#' + id).modal('close');
    }

    function showFormErrors($form, errors) {
        window.injectValidationErrors($form, errors);
    }

    function withLoading(btnSelector, promise) {
        const $btn = typeof btnSelector === 'string'
            ? $(btnSelector)
            : $(btnSelector);
        const origText = $btn.text();
        $btn.prop('disabled', true).text(t('shared.saving'));
        return promise
            .always(() => {
                $btn.prop('disabled', false).text(origText);
            });
    }

    // ── STATE ─────────────────────────────────────────────────────────────────

    const SZ = {
        currentZoneId: null,
        currentZoneName: null,
        currentCountryId: null,
        selectedRateIds: new Set(),
        pendingCity: {
            assigned: [],
            available: [],
        },
        sortableAssigned: null,
        sortableAvailable: null,
    };

    // ═══════════════════════════════════════════════════════════════════════
    // ZONE SELECTION
    // ═══════════════════════════════════════════════════════════════════════

    $(document).on('click', '.zone-card', function () {
        const zoneId = $(this).data('zone-id');
        if (!zoneId) return;
        SZ.currentZoneId = zoneId;
        SZ.currentCountryId = $(this).data('country-id') || null;
        loadZoneRates(zoneId);
    });

    // ═══════════════════════════════════════════════════════════════════════
    // LOAD RATES
    // ═══════════════════════════════════════════════════════════════════════

    function loadZoneRates(zoneId) {
        $('#rates-loading').removeClass('hidden');
        $('#rates-tbody').empty();
        $('#rates-empty').addClass('hidden');
        SZ.selectedRateIds.clear();
        updateBulkActions();

        const params = {
            destination_zone_id: zoneId,
            shipping_method_id: $('#rate-filter-method').val() || '',
            carrier_id: $('#rate-filter-carrier').val() || '',
            is_active: $('#rate-filter-active').val(),
            _token: csrf(),
        };

        $.post('/shipping-zones/rates/datatable', params)
            .done(function (res) {
                $('#rates-loading').addClass('hidden');
                const rates = res.data || [];

                if (!rates.length) {
                    $('#rates-empty').removeClass('hidden');
                    return;
                }

                const rows = rates.map(function (r) {
                    const carrierHtml = r.carrier_name && r.carrier_name !== 'Any'
                        ? `<span class="text-sm text-gray-600">${r.carrier_name}</span>`
                        : `<span class="text-gray-300">${t('admin.shipping_zones.any_label')}</span>`;

                    const perKgHtml = parseFloat(r.rate_per_kg) > 0
                        ? `<span class="text-gray-600">${r.rate_per_kg_formatted}/kg</span>`
                        : `<span class="text-gray-300">—</span>`;

                    const freeHtml = r.free_threshold_formatted
                        ? `&gt; ${r.free_threshold_formatted}`
                        : `<span class="text-gray-300">—</span>`;

                    const codHtml = parseFloat(r.cod_extra_fee) > 0
                        ? r.cod_extra_fee_formatted
                        : `<span class="text-gray-300">—</span>`;

                    const statusClass = r.is_active ? 'badge-success' : 'badge-gray';
                    const statusText = r.is_active ? (t('admin.shipping_zones.active_label')) : (t('admin.shipping_zones.inactive_label'));

                    return `<tr class="hover:bg-gray-50 rate-row" data-rate-id="${r.id}">
                        <td class="px-4 py-3 w-8">
                            <input type="checkbox" class="form-checkbox rate-checkbox" value="${r.id}" />
                        </td>
                        <td class="px-4 py-3"><span class="text-sm font-medium">${r.method_name}</span></td>
                        <td class="px-4 py-3">${carrierHtml}</td>
                        <td class="px-4 py-3 text-right font-medium">${r.base_fee_formatted}</td>
                        <td class="px-4 py-3 text-right">${perKgHtml}</td>
                        <td class="px-4 py-3 text-right">${freeHtml}</td>
                        <td class="px-4 py-3 text-right">${codHtml}</td>
                        <td class="px-4 py-3 text-center">
                            <button class="toggle-rate-btn" data-id="${r.id}" data-active="${r.is_active ? 1 : 0}">
                                <span class="badge ${statusClass} text-xs cursor-pointer">${statusText}</span>
                            </button>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex gap-1 justify-end">
                                <button class="btn btn-xs btn-ghost edit-rate-btn"
                                    data-rate='${JSON.stringify(r).replace(/'/g, "&#39;")}'>${t('admin.shipping_zones.edit_label')}</button>
                                <button class="btn btn-xs btn-ghost text-red-400 hover:text-red-600 delete-rate-btn"
                                    data-id="${r.id}">✕</button>
                            </div>
                        </td>
                    </tr>`;
                });

                $('#rates-tbody').html(rows.join(''));
            })
            .fail(function () {
                $('#rates-loading').addClass('hidden');
                window.Toast.error(t('admin.shipping_zones.failed_load_rates'));
            });
    }

    $('#rate-filter-method, #rate-filter-carrier, #rate-filter-active')
        .on('change', function () {
            if (SZ.currentZoneId) loadZoneRates(SZ.currentZoneId);
        });

    // ═══════════════════════════════════════════════════════════════════════
    // BULK SELECTION
    // ═══════════════════════════════════════════════════════════════════════

    $(document).on('change', '.rate-checkbox', function () {
        const id = $(this).val();
        $(this).is(':checked') ? SZ.selectedRateIds.add(id) : SZ.selectedRateIds.delete(id);
        updateBulkActions();
    });

    $('#select-all-rates').on('change', function () {
        const checked = $(this).is(':checked');
        $('.rate-checkbox').prop('checked', checked);
        SZ.selectedRateIds.clear();
        if (checked) {
            $('.rate-checkbox').each(function () { SZ.selectedRateIds.add($(this).val()); });
        }
        updateBulkActions();
    });

    function updateBulkActions() {
        const n = SZ.selectedRateIds.size;
        if (n > 0) {
            $('#bulk-actions').removeClass('hidden');
            $('#selected-count').text(n);
        } else {
            $('#bulk-actions').addClass('hidden');
        }
    }

    function bulkAction(action) {
        const ids = Array.from(SZ.selectedRateIds);
        if (!ids.length) return;
        $.ajax({
            url: '/shipping-zones/rates/bulk',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ action, ids }),
            headers: ajaxHeaders(),
        })
            .done(function (res) {
                window.Toast.success(res.message);
                SZ.selectedRateIds.clear();
                $('#select-all-rates').prop('checked', false);
                loadZoneRates(SZ.currentZoneId);
            })
            .fail(() => window.Toast.error(t('admin.shipping_zones.bulk_action_failed')));
    }

    $('#bulk-activate').on('click', () => bulkAction('activate'));
    $('#bulk-deactivate').on('click', () => bulkAction('deactivate'));
    $('#bulk-delete').on('click', async function () {
        const n = SZ.selectedRateIds.size;
        const ok = await window.confirmDialog({
            title: t('admin.shipping_zones.delete_rates_confirm_title', { n }),
            text: t('admin.shipping_zones.action_cannot_be_undone'),
            confirmLabel: t('admin.shipping_zones.delete_all_label'),
            danger: true,
        });
        if (ok) bulkAction('delete');
    });

    // ═══════════════════════════════════════════════════════════════════════
    // ADD / EDIT RATE
    // ═══════════════════════════════════════════════════════════════════════

    $('#add-rate-btn').on('click', function () {
        if (!SZ.currentZoneId) {
            window.Toast.warning(t('admin.shipping_zones.select_zone_first'));
            return;
        }
        $('#rate-id').val('');
        $('#rate-zone-id').val(SZ.currentZoneId);
        $('#rate-form')[0].reset();
        $('[name="is_active"]', '#rate-form').prop('checked', true);
        $('[name="rate_per_kg"]', '#rate-form').val('0');
        $('[name="cod_extra_fee"]', '#rate-form').val('0');
        $('[name="volumetric_divisor"]', '#rate-form').val('5000');
        $('[name="carrier_rate"]', '#rate-form').val('');
        $('[name="carrier_rate_per_kg"]', '#rate-form').val('');
        $('#rate-modal-title').text(t('admin.shipping_zones.add_shipping_rate_label'));
        updateRatePreview();
        openModal('rate-modal');
    });

    $(document).on('click', '.edit-rate-btn', function () {
        const r = $(this).data('rate');
        $('#rate-id').val(r.id);
        $('#rate-zone-id').val(r.destination_zone_id);
        $('[name="shipping_method_id"]', '#rate-form').val(r.shipping_method_id);
        $('[name="carrier_id"]', '#rate-form').val(r.carrier_id || '');
        $('[name="origin_zone_id"]', '#rate-form').val(r.origin_zone_id || '');
        $('[name="base_fee"]', '#rate-form').val(r.base_fee);
        $('[name="carrier_rate"]', '#rate-form').val(r.carrier_rate ?? '');
        $('[name="carrier_rate_per_kg"]', '#rate-form').val(r.carrier_rate_per_kg ?? '');
        $('[name="rate_per_kg"]', '#rate-form').val(r.rate_per_kg);
        $('[name="cod_extra_fee"]', '#rate-form').val(r.cod_extra_fee);
        $('[name="free_shipping_threshold"]', '#rate-form').val(r.free_threshold || '');
        $('[name="min_weight_grams"]', '#rate-form').val(r.min_weight_grams);
        $('[name="volumetric_divisor"]', '#rate-form').val(r.volumetric_divisor);
        $('[name="is_active"]', '#rate-form').prop('checked', r.is_active);
        $('#rate-modal-title').text(t('admin.shipping_zones.edit_shipping_rate_label'));
        updateRatePreview();
        openModal('rate-modal');
    });

    $('#rate-form').on('submit', function (e) {
        e.preventDefault();
        const id = $('#rate-id').val();
        const url = id ? '/shipping-zones/rates/' + id : '/shipping-zones/rates';
        const method = id ? 'PUT' : 'POST';

        withLoading('#rate-save-btn',
            $.ajax({ url, method, data: $(this).serialize(), headers: ajaxHeaders() })
                .done(function (res) {
                    closeModal('rate-modal');
                    window.Toast.success(res.message);
                    loadZoneRates(SZ.currentZoneId);
                    refreshZoneRateCount(SZ.currentZoneId);
                })
                .fail(function (xhr) {
                    showFormErrors($('#rate-form'), xhr.responseJSON?.errors || {});
                    if (xhr.responseJSON?.message) window.Toast.error(xhr.responseJSON.message);
                })
        );
    });

    $(document).on('click', '.toggle-rate-btn', function () {
        const id = $(this).data('id');
        $.post('/shipping-zones/rates/' + id + '/toggle', { _token: csrf() })
            .done(function (res) {
                const $badge = $('[data-id="' + id + '"] .badge');
                if (res.data.is_active) {
                    $badge.attr('class', 'badge badge-success text-xs cursor-pointer').text(t('admin.shipping_zones.active_label'));
                } else {
                    $badge.attr('class', 'badge badge-gray text-xs cursor-pointer').text(t('admin.shipping_zones.inactive_label'));
                }
            })
            .fail(() => window.Toast.error(t('admin.shipping_zones.toggle_failed')));
    });

    $(document).on('click', '.delete-rate-btn', async function () {
        const id = $(this).data('id');
        const ok = await window.confirmDialog({
            title: t('admin.shipping_zones.delete_rate_confirm'), danger: true, confirmLabel: t('admin.shipping_zones.delete_label'),
        });
        if (!ok) return;
        $.ajax({ url: '/shipping-zones/rates/' + id, method: 'DELETE', headers: ajaxHeaders() })
            .done(function () {
                window.Toast.success(t('admin.shipping_zones.rate_deleted'));
                loadZoneRates(SZ.currentZoneId);
                refreshZoneRateCount(SZ.currentZoneId);
            })
            .fail(() => window.Toast.error(t('admin.shipping_zones.delete_failed')));
    });

    // Live rate preview
    function updateRatePreview() {
        const base = parseFloat($('[name="base_fee"]', '#rate-form').val()) || 0;
        const perKg = parseFloat($('[name="rate_per_kg"]', '#rate-form').val()) || 0;
        const free = parseFloat($('[name="free_shipping_threshold"]', '#rate-form').val()) || null;
        const weight = parseInt($('#preview-weight').val()) || 500;
        const cost = base + (weight / 1000) * perKg;
        $('#rate-cost-preview').text(cost.toFixed(2));
        $('#rate-free-preview').text(free ? free.toFixed(2) : '—');
    }

    $(document).on('input', '[name="base_fee"], [name="rate_per_kg"], [name="free_shipping_threshold"], #preview-weight',
        updateRatePreview);

    // ═══════════════════════════════════════════════════════════════════════
    // ZONE CRUD
    // ═══════════════════════════════════════════════════════════════════════

    $('#open-add-zone-btn, #first-zone-btn').on('click', function () {
        $('#zone-id').val('');
        $('#zone-form')[0].reset();
        $('[name="is_active"]', '#zone-form').prop('checked', true);
        $('#zone-modal-title').text(t('admin.shipping_zones.add_shipping_zone_label'));
        openModal('zone-modal');
    });

    $(document).on('click', '.edit-zone-btn', function () {
        const z = $(this).data('zone');
        $('#zone-id').val(z.id);
        $('[name="name"]', '#zone-form').val(z.name);
        $('[name="country_id"]', '#zone-form').val(z.country_id);
        $('[name="description"]', '#zone-form').val(z.description || '');
        $('[name="is_active"]', '#zone-form').prop('checked', z.is_active == 1);
        $('#zone-modal-title').text(t('admin.shipping_zones.edit_zone_title_prefix') + z.name);
        openModal('zone-modal');
    });

    $('#zone-form').on('submit', function (e) {
        e.preventDefault();
        const id = $('#zone-id').val();
        const url = id ? '/shipping-zones/' + id : '/shipping-zones';
        const method = id ? 'PUT' : 'POST';

        withLoading('#zone-save-btn',
            $.ajax({ url, method, data: $(this).serialize(), headers: ajaxHeaders() })
                .done(function () {
                    closeModal('zone-modal');
                    window.Toast.success(t('admin.shipping_zones.zone_saved'));
                    location.reload();
                })
                .fail(function (xhr) {
                    showFormErrors($('#zone-form'), xhr.responseJSON?.errors || {});
                    if (xhr.responseJSON?.message) window.Toast.error(xhr.responseJSON.message);
                })
        );
    });

    $(document).on('click', '.toggle-zone-btn', function () {
        const id = $(this).data('zone-id');
        $.post('/shipping-zones/' + id + '/toggle', { _token: csrf() })
            .done(() => location.reload())
            .fail(() => window.Toast.error(t('admin.shipping_zones.toggle_failed')));
    });

    $(document).on('click', '.delete-zone-btn', async function () {
        const id = $(this).data('zone-id');
        const name = $(this).data('zone-name');
        const ok = await window.confirmDialog({
            title: t('admin.shipping_zones.delete_zone_confirm_title', { name }),
            text: t('admin.shipping_zones.cities_unassigned_warning'),
            confirmLabel: t('admin.shipping_zones.delete_zone_label'),
            danger: true,
        });
        if (!ok) return;
        $.ajax({ url: '/shipping-zones/' + id, method: 'DELETE', headers: ajaxHeaders() })
            .done(function () {
                window.Toast.success(t('admin.shipping_zones.zone_deleted'));
                location.reload();
            })
            .fail(function (xhr) {
                const msg = xhr.responseJSON?.errors?.zone?.[0]
                    || xhr.responseJSON?.message
                    || (t('admin.shipping_zones.cannot_delete_zone_has_rates'));
                window.Toast.error(msg);
            });
    });

    // ═══════════════════════════════════════════════════════════════════════
    // CITY ASSIGNMENT (drag & drop with SortableJS)
    // ═══════════════════════════════════════════════════════════════════════

    $(document).on('click', '.assign-cities-btn', async function () {
        const zoneId = $(this).data('zone-id');
        const zoneName = $(this).data('zone-name');
        const countryId = $(this).data('country-id');

        SZ.currentZoneId = zoneId;
        SZ.currentZoneName = zoneName;
        SZ.currentCountryId = countryId;

        $('#assign-zone-id').val(zoneId);
        $('#assign-country-id').val(countryId);
        $('#assign-zone-name').text(zoneName);

        try {
            const [assignedRes, availableRes] = await Promise.all([
                $.get('/shipping-zones/' + zoneId + '/cities'),
                $.get('/shipping-zones/cities/unassigned', { country_id: countryId }),
            ]);

            SZ.pendingCity.assigned = assignedRes.data.cities || [];
            SZ.pendingCity.available = availableRes.data.cities || [];

            renderCityLists();
            openModal('assign-cities-modal');
        } catch (_) {
            window.Toast.error(t('admin.shipping_zones.failed_load_cities'));
        }
    });

    function cityChip(city, side) {
        const arrow = side === 'assigned' ? '→' : '←';
        const cod = city.cod_available
            ? '<span class="inline-block ml-1 px-1 py-0.5 text-xs bg-blue-100 text-blue-700 rounded">COD</span>'
            : '';
        return `<div class="city-chip flex items-center justify-between px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-sm cursor-grab select-none"
                     data-city-id="${city.id}" data-city-name="${city.name_en}">
            <div>
                <span class="font-medium">${city.name_en}</span>
                <span class="text-gray-400 text-xs ml-1">${city.name_ar}</span>
                ${cod}
            </div>
            <button class="move-city-btn text-gray-400 hover:text-gray-600 ml-2 text-xs px-1"
                    data-from="${side}"
                    data-city='${JSON.stringify(city).replace(/'/g, "&#39;")}'>${arrow}</button>
        </div>`;
    }

    function renderCityLists() {
        const assignedEl = document.getElementById('assigned-cities-sortable');
        const availableEl = document.getElementById('available-cities-sortable');
        if (!assignedEl || !availableEl) return;

        const search = ($('#assign-city-search').val() || '').toLowerCase();

        assignedEl.innerHTML = SZ.pendingCity.assigned
            .map(c => cityChip(c, 'assigned')).join('');

        const filtered = SZ.pendingCity.available.filter(c =>
            !search || c.name_en.toLowerCase().includes(search) || (c.name_ar || '').includes(search)
        );
        availableEl.innerHTML = filtered.map(c => cityChip(c, 'available')).join('');

        $('#assigned-count').text(SZ.pendingCity.assigned.length);

        if (SZ.sortableAssigned) SZ.sortableAssigned.destroy();
        if (SZ.sortableAvailable) SZ.sortableAvailable.destroy();

        SZ.sortableAssigned = Sortable.create(assignedEl, {
            group: 'cities', animation: 150,
            onAdd: function (evt) {
                const cityId = evt.item.dataset.cityId;
                const idx = SZ.pendingCity.available.findIndex(c => c.id === cityId);
                if (idx > -1) {
                    SZ.pendingCity.assigned.push(SZ.pendingCity.available.splice(idx, 1)[0]);
                }
                $('#assigned-count').text(SZ.pendingCity.assigned.length);
            },
        });

        SZ.sortableAvailable = Sortable.create(availableEl, {
            group: 'cities', animation: 150,
            onAdd: function (evt) {
                const cityId = evt.item.dataset.cityId;
                const idx = SZ.pendingCity.assigned.findIndex(c => c.id === cityId);
                if (idx > -1) {
                    SZ.pendingCity.available.push(SZ.pendingCity.assigned.splice(idx, 1)[0]);
                }
                $('#assigned-count').text(SZ.pendingCity.assigned.length);
            },
        });
    }

    $(document).on('click', '.move-city-btn', function () {
        const from = $(this).data('from');
        const city = $(this).data('city');

        if (from === 'available') {
            const idx = SZ.pendingCity.available.findIndex(c => c.id === city.id);
            if (idx > -1) {
                SZ.pendingCity.assigned.push(SZ.pendingCity.available.splice(idx, 1)[0]);
            }
        } else {
            const idx = SZ.pendingCity.assigned.findIndex(c => c.id === city.id);
            if (idx > -1) {
                SZ.pendingCity.available.push(SZ.pendingCity.assigned.splice(idx, 1)[0]);
            }
        }
        renderCityLists();
    });

    $('#remove-all-btn').on('click', function () {
        SZ.pendingCity.available.push(...SZ.pendingCity.assigned);
        SZ.pendingCity.assigned = [];
        renderCityLists();
    });

    $('#assign-city-search').on('input', renderCityLists);

    $('#save-assign-btn').on('click', function () {
        const cityIds = SZ.pendingCity.assigned.map(c => c.id);
        const $btn = $(this);
        $btn.prop('disabled', true).text(t('shared.saving'));

        $.ajax({
            url: '/shipping-zones/' + SZ.currentZoneId + '/cities',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ city_ids: cityIds }),
            headers: ajaxHeaders(),
        })
            .done(function (res) {
                closeModal('assign-cities-modal');
                window.Toast.success(res.message);
                location.reload();
            })
            .fail(() => window.Toast.error(t('admin.shipping_zones.failed_save_assignment')))
            .always(() => $btn.prop('disabled', false).text(t('admin.shipping_zones.save_label')));
    });

    // ═══════════════════════════════════════════════════════════════════════
    // DUPLICATE ZONE
    // ═══════════════════════════════════════════════════════════════════════

    let duplicatingZoneId = null;

    $(document).on('click', '.duplicate-zone-btn', function () {
        duplicatingZoneId = $(this).data('zone-id');
        const name = $(this).data('zone-name');
        $('#new-zone-name').val(t('admin.shipping_zones.copy_of_prefix') + name);
        openModal('duplicate-zone-modal');
    });

    $('#confirm-duplicate-btn').on('click', function () {
        const newName = $('#new-zone-name').val().trim();
        if (!newName) { window.Toast.warning(t('admin.shipping_zones.enter_a_name')); return; }

        withLoading('#confirm-duplicate-btn',
            $.post('/shipping-zones/' + duplicatingZoneId + '/duplicate', {
                name: newName,
                _token: csrf(),
            })
                .done(function (res) {
                    closeModal('duplicate-zone-modal');
                    window.Toast.success(res.message);
                    location.reload();
                })
                .fail(() => window.Toast.error(t('admin.shipping_zones.failed_duplicate')))
        );
    });

    // ═══════════════════════════════════════════════════════════════════════
    // COPY RATES
    // ═══════════════════════════════════════════════════════════════════════

    $('#copy-rates-btn').on('click', function () {
        if (!SZ.currentZoneId) { window.Toast.warning(t('admin.shipping_zones.select_zone_first')); return; }
        openModal('copy-rates-modal');
    });

    $('#confirm-copy-rates-btn').on('click', function () {
        const sourceZoneId = $('#copy-source-zone').val();
        if (!sourceZoneId) { window.Toast.warning(t('admin.shipping_zones.select_source_zone')); return; }
        if (sourceZoneId === SZ.currentZoneId) {
            window.Toast.warning(t('admin.shipping_zones.source_target_same'));
            return;
        }

        withLoading('#confirm-copy-rates-btn',
            $.ajax({
                url: '/shipping-zones/rates/copy',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ source_zone_id: sourceZoneId, target_zone_id: SZ.currentZoneId }),
                headers: ajaxHeaders(),
            })
                .done(function (res) {
                    closeModal('copy-rates-modal');
                    window.Toast.success(res.message);
                    loadZoneRates(SZ.currentZoneId);
                    refreshZoneRateCount(SZ.currentZoneId);
                })
                .fail(() => window.Toast.error(t('admin.shipping_zones.failed_copy_rates')))
        );
    });

    // ═══════════════════════════════════════════════════════════════════════
    // RATE CALCULATOR
    // ═══════════════════════════════════════════════════════════════════════

    $('#open-rate-calc-btn').on('click', function () {
        $('#calc-result').addClass('hidden');
        openModal('rate-calc-modal');
    });

    $('#run-estimate-btn').on('click', function () {
        const zoneId = $('[name="calc_zone_id"]').val();
        const methodId = $('[name="calc_method_id"]').val();
        const weight = parseInt($('#calc-weight').val()) || 500;
        const orderValue = parseFloat($('#calc-order-value').val()) || 0;
        const isCod = $('#calc-is-cod').is(':checked');

        if (!zoneId || !methodId) {
            window.Toast.warning(t('admin.shipping_zones.select_zone_and_method'));
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).text(t('admin.shipping_zones.calculating_label'));

        $.post('/shipping-zones/rates/estimate', {
            zone_id: zoneId,
            method_id: methodId,
            weight_grams: weight,
            order_value: orderValue,
            is_cod: isCod ? 1 : 0,
            _token: csrf(),
        })
            .done(function (res) {
                const d = res.data;
                $('#calc-result').removeClass('hidden');
                $('#calc-free-hint').addClass('hidden');
                $('#calc-breakdown').addClass('hidden').empty();

                if (!d.available) {
                    $('#calc-cost').text(t('admin.shipping_zones.no_rate_found')).removeClass('text-primary-700 text-green-600').addClass('text-red-600');
                    $('#calc-meta').text(t('admin.shipping_zones.no_active_rate_configured'));
                    return;
                }

                if (d.is_free) {
                    $('#calc-cost').text(t('admin.shipping_zones.free_label')).removeClass('text-primary-700 text-red-600').addClass('text-green-600');
                    $('#calc-free-hint').removeClass('hidden');
                } else {
                    $('#calc-cost').text(d.cost_formatted).removeClass('text-green-600 text-red-600').addClass('text-primary-700');
                }

                $('#calc-meta').text(
                    d.method_name
                    + (d.carrier_name !== 'Any' ? ' ' + (t('admin.shipping_zones.via_label')) + ' ' + d.carrier_name : '')
                    + ' · ' + d.delivery_days + ' ' + (t('admin.shipping_zones.day_label'))
                );

                if (d.free_threshold_formatted && !d.is_free) {
                    $('#calc-breakdown').removeClass('hidden').html(
                        '<p>🎁 ' + (t('admin.shipping_zones.free_shipping_from_label')) + ' <strong>' + d.free_threshold_formatted + '</strong></p>'
                    );
                }
            })
            .fail(() => window.Toast.error(t('admin.shipping_zones.calculation_failed')))
            .always(() => $btn.prop('disabled', false).text(t('admin.shipping_zones.calculate_label')));
    });

    // ═══════════════════════════════════════════════════════════════════════
    // HELPER: refresh rate-count badge on zone card
    // ═══════════════════════════════════════════════════════════════════════

    function refreshZoneRateCount(zoneId) {
        $.get('/shipping-zones/' + zoneId)
            .done(function (res) {
                $(`.zone-rate-count[data-zone="${zoneId}"]`)
                    .text(res.data?.active_rate_count ?? 0);
            });
    }

    // ═══════════════════════════════════════════════════════════════════════
    // INLINE CITIES TAB (detail panel, not modal)
    // ═══════════════════════════════════════════════════════════════════════

    // When the Alpine tab switches to 'cities', load the cities for the selected zone
    document.addEventListener('alpine:init', function () { });

    // Watch for Alpine tab change to 'cities' by polling the zone selection
    let cityTabLoaded = false;
    let lastZoneForCities = null;

    $(document).on('click', 'button[\\@click="activeTab = \'cities\'"]', function () {
        // Alpine handles the tab switching; we use a MutationObserver approach instead
    });

    // Use event delegation on the Alpine-managed tab buttons
    // Since Alpine renders synchronously, we can listen for clicks on the cities tab button
    $(document).on('click', function (e) {
        const $btn = $(e.target).closest('button');
        if ($btn.length && $btn.attr('@click') && $btn.attr('@click').includes("'cities'")) {
            if (SZ.currentZoneId && SZ.currentZoneId !== lastZoneForCities) {
                loadInlineCities(SZ.currentZoneId);
                lastZoneForCities = SZ.currentZoneId;
            }
        }
    });

    // Also reload when zone selection changes
    $(document).on('click', '.zone-card', function () {
        lastZoneForCities = null; // reset so cities reload on next tab switch
    });

    function loadInlineCities(zoneId) {
        Promise.all([
            $.get('/shipping-zones/' + zoneId + '/cities'),
            $.get('/shipping-zones/cities/unassigned', { country_id: SZ.currentCountryId }),
        ]).then(function ([assignedRes, availableRes]) {
            SZ.pendingCity.assigned = assignedRes.data.cities || [];
            SZ.pendingCity.available = availableRes.data.cities || [];
            renderInlineCityLists();
        }).catch(() => window.Toast.error(t('admin.shipping_zones.failed_load_cities')));
    }

    function renderInlineCityLists() {
        const $assigned = $('#assigned-cities-list');
        const $available = $('#available-cities-list');
        if (!$assigned.length) return;

        const search = ($('#city-search').val() || '').toLowerCase();

        $assigned.html(
            SZ.pendingCity.assigned.map(c => cityChip(c, 'assigned')).join('') ||
            `<p class="text-xs text-gray-400 p-2 text-center">${t('admin.shipping_zones.no_cities_assigned_yet')}</p>`
        );

        const filtered = SZ.pendingCity.available.filter(c =>
            !search || c.name_en.toLowerCase().includes(search)
        );
        $available.html(
            filtered.map(c => cityChip(c, 'available')).join('') ||
            `<p class="text-xs text-gray-400 p-2 text-center">${t('admin.shipping_zones.no_available_cities')}</p>`
        );

        // Init sortable on inline lists
        if (window.Sortable) {
            ['assigned-cities-list', 'available-cities-list'].forEach(function (elId) {
                const el = document.getElementById(elId);
                if (el && !el._sortable) {
                    el._sortable = Sortable.create(el, {
                        group: 'inline-cities', animation: 150,
                        onAdd: function (evt) {
                            // sync state on drop
                            const cityId = evt.item.dataset.cityId;
                            if (elId === 'assigned-cities-list') {
                                const idx = SZ.pendingCity.available.findIndex(c => c.id === cityId);
                                if (idx > -1) SZ.pendingCity.assigned.push(SZ.pendingCity.available.splice(idx, 1)[0]);
                            } else {
                                const idx = SZ.pendingCity.assigned.findIndex(c => c.id === cityId);
                                if (idx > -1) SZ.pendingCity.available.push(SZ.pendingCity.assigned.splice(idx, 1)[0]);
                            }
                        },
                    });
                }
            });
        }
    }

    $('#city-search').on('input', function () {
        if (SZ.currentZoneId) renderInlineCityLists();
    });

    $('#save-city-assignment-btn').on('click', function () {
        if (!SZ.currentZoneId) { window.Toast.warning(t('admin.shipping_zones.select_zone_first')); return; }
        const cityIds = SZ.pendingCity.assigned.map(c => c.id);
        const $btn = $(this);
        $btn.prop('disabled', true).text(t('shared.saving'));

        $.ajax({
            url: '/shipping-zones/' + SZ.currentZoneId + '/cities',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ city_ids: cityIds }),
            headers: ajaxHeaders(),
        })
            .done(function (res) {
                window.Toast.success(res.message);
                lastZoneForCities = null;
            })
            .fail(() => window.Toast.error(t('admin.shipping_zones.failed_to_save')))
            .always(() => $btn.prop('disabled', false).text(t('admin.shipping_zones.save_assignment_label')));
    });

});
