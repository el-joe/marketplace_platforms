/**
 * Distance-based delivery pricing tab on the Shipping Zones page (client item #19).
 * resources/js/admin/shipping-distance-pricing.js
 *
 * Server: ShippingDistancePricingController (GET/PUT/DELETE /shipping-zones/{zone}/distance-pricing[/{method}]).
 * The tier checks below mirror DistanceFeeCalculator::analyseTiers() so admins see overlaps/gaps while typing;
 * the server re-validates on save.
 */
$(function () {
    'use strict';

    const CFG = window.DISTANCE_PRICING || {};
    const I18N = CFG.i18n || {};
    if (!document.getElementById('distance-pricing-panel')) return;

    const DIGITS = { '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9', '٫': '.', '٬': '' };

    const state = { zoneId: null, data: null, methodId: null, map: null, overlays: [], mapsLoading: null };

    const tr = (path, params) => {
        let value = path.split('.').reduce((node, key) => (node == null ? undefined : node[key]), I18N);
        if (typeof value !== 'string') return path;
        Object.entries(params || {}).forEach(([key, val]) => { value = value.split(':' + key).join(String(val)); });
        return value;
    };

    const esc = (value) => $('<div>').text(value == null ? '' : String(value)).html();

    const toNumber = (raw) => {
        const text = String(raw == null ? '' : raw).replace(/[٠-٩٫٬]/g, (ch) => DIGITS[ch]).trim();
        if (text === '') return null;
        const num = Number(text);
        return Number.isFinite(num) ? num : NaN;
    };

    const tenths = (km) => Math.round(km * 10);

    $('.dp-currency').text(CFG.currency || '');

    // ── Loading ────────────────────────────────────────────────────────────

    $(document).on('click', '.zone-card', function () {
        const zoneId = $(this).data('zone-id');
        if (!zoneId || zoneId === state.zoneId) return;
        state.zoneId = zoneId;
        state.methodId = null;
        $('#dp-method').val('');
        $('#dp-form').addClass('hidden');
        loadZone();
    });

    function loadZone() {
        $.get('/shipping-zones/' + state.zoneId + '/distance-pricing').done(function (res) {
            state.data = res.data || { rules: {}, categories: [], sample_locations: [] };
            if (Array.isArray(state.data.rules)) state.data.rules = {};
            renderConfiguredSummary();
            renderCoverage();
            renderCategoryOptions();
            if (state.methodId) fillForm();
        });
    }

    function renderConfiguredSummary() {
        const names = Object.keys(state.data.rules).map((methodId) => {
            const label = $('#dp-method option[value="' + methodId + '"]').text() || methodId;
            const rule = state.data.rules[methodId];
            return esc(label) + (rule.is_active ? '' : ' (' + esc(window.t ? window.t('common.inactive') : 'inactive') + ')');
        });
        $('#dp-configured').html(names.length ? names.join(', ') : '—');
    }

    function renderCoverage() {
        const coverage = state.data.coverage;
        const $box = $('#dp-coverage');
        if (!coverage) {
            $box.addClass('hidden');
            return;
        }
        const missing = coverage.vendors_missing + coverage.warehouses_missing;
        $box.removeClass('hidden border-yellow-200 bg-yellow-50 border-green-200 bg-green-50')
            .addClass(missing ? 'border-yellow-200 bg-yellow-50' : 'border-green-200 bg-green-50');
        if (!missing) {
            $box.html('<p class="text-green-700">' + esc(tr('coverage_all_set')) + '</p>');
            return;
        }
        const list = (items, total) => {
            if (!items.length) return '';
            const links = items.map((item) => '<a class="text-primary-600 hover:underline" href="' + esc(item.url) + '" target="_blank" rel="noopener">' + esc(item.name) + '</a>');
            if (total > items.length) links.push(esc(tr('coverage_more', { count: total - items.length })));
            return '<p class="text-xs text-gray-600">' + esc(tr('coverage_fix')) + ': ' + links.join(', ') + '</p>';
        };
        let html = '<p class="font-medium text-yellow-800">' + esc(tr('coverage_title')) + '</p>'
            + '<p class="text-xs text-yellow-700">' + esc(tr('coverage_hint')) + '</p>';
        if (coverage.vendors_missing) {
            html += '<p class="text-yellow-800">' + esc(tr('coverage_vendors', { missing: coverage.vendors_missing, total: coverage.vendors_total })) + '</p>'
                + list(coverage.vendors, coverage.vendors_missing);
        }
        if (coverage.warehouses_missing) {
            html += '<p class="text-yellow-800">' + esc(tr('coverage_warehouses', { missing: coverage.warehouses_missing, total: coverage.warehouses_total })) + '</p>'
                + list(coverage.warehouses, coverage.warehouses_missing);
        }
        $box.html(html);
    }

    function renderCategoryOptions() {
        const $select = $('#dp-categories').empty();
        (state.data.categories || []).forEach((category) => {
            const prefix = category.depth ? '   — ' : '';
            $select.append($('<option>').val(category.id).text(prefix + category.name));
        });
    }

    $('#dp-method').on('change', function () {
        state.methodId = $(this).val() || null;
        if (!state.methodId || !state.data) {
            $('#dp-form').addClass('hidden');
            return;
        }
        fillForm();
    });

    // ── Form ───────────────────────────────────────────────────────────────

    function fillForm() {
        const rule = state.data.rules[state.methodId] || null;

        $('#dp-not-configured').toggleClass('hidden', !!rule);
        $('#dp-delete').toggleClass('hidden', !rule || !CFG.canEdit);
        $('#dp-is-active').prop('checked', rule ? !!rule.is_active : true);
        $('#dp-free-radius').val(rule && rule.free_radius_km != null ? rule.free_radius_km : '');
        $('#dp-max-radius').val(rule && rule.max_service_radius_km != null ? rule.max_service_radius_km : '');
        $('#dp-categories').val(rule ? rule.category_ids : []);

        $('#dp-tiers').empty();
        const tiers = rule && rule.tiers.length ? rule.tiers : [{ from_km: rule && rule.free_radius_km != null ? rule.free_radius_km : 0, to_km: null, price: '' }];
        tiers.forEach(addTierRow);

        $('#dp-form').removeClass('hidden');
        $('#dp-form :input').prop('disabled', !CFG.canEdit);
        $('#dp-save, #dp-add-tier').toggleClass('hidden', !CFG.canEdit);
        $('#dp-preview-location').prop('disabled', false);

        analyse();
        setupPreview();
    }

    function addTierRow(tier) {
        const row = $(
            '<tr class="dp-tier">' +
            '<td class="px-3 py-2 text-gray-400 dp-row-no"></td>' +
            '<td class="px-3 py-2"><input type="text" inputmode="decimal" dir="ltr" class="form-input w-24 text-sm dp-from"></td>' +
            '<td class="px-3 py-2"><input type="text" inputmode="decimal" dir="ltr" class="form-input w-24 text-sm dp-to" placeholder="' + esc(tr('to_km_open')) + '"></td>' +
            '<td class="px-3 py-2"><input type="text" inputmode="decimal" dir="ltr" class="form-input w-28 text-sm dp-price"></td>' +
            '<td class="px-3 py-2 text-end"><button type="button" class="btn btn-ghost btn-xs text-red-600 dp-remove-tier">' + esc(tr('remove_tier')) + '</button></td>' +
            '</tr>'
        );
        row.find('.dp-from').val(tier.from_km ?? '');
        row.find('.dp-to').val(tier.to_km ?? '');
        row.find('.dp-price').val(tier.price ?? '');
        $('#dp-tiers').append(row);
        renumber();
    }

    function renumber() {
        $('#dp-tiers .dp-row-no').each(function (index) { $(this).text(index + 1); });
    }

    $('#dp-add-tier').on('click', function () {
        const last = collectTiers().pop();
        addTierRow({ from_km: last && last.to_km != null ? last.to_km : '', to_km: '', price: '' });
        analyse();
        drawPreview();
    });

    $(document).on('click', '.dp-remove-tier', function () {
        $(this).closest('tr').remove();
        renumber();
        analyse();
        drawPreview();
    });

    $('#dp-form').on('input change', 'input, select', function () {
        analyse();
        drawPreview();
    });

    function collectTiers() {
        return $('#dp-tiers .dp-tier').map(function () {
            return {
                from_km: $(this).find('.dp-from').val(),
                to_km: $(this).find('.dp-to').val(),
                price: $(this).find('.dp-price').val(),
            };
        }).get();
    }

    /** Client mirror of DistanceFeeCalculator::analyseTiers(). */
    function analyse() {
        const errors = [];
        const warnings = [];
        const free = toNumber($('#dp-free-radius').val());
        const max = toNumber($('#dp-max-radius').val());

        if (free != null && max != null && !isNaN(free) && !isNaN(max) && tenths(free) >= tenths(max)) {
            errors.push(tr('errors.free_radius_not_below_max'));
        }

        const bands = [];
        collectTiers().forEach((tier, index) => {
            const from = toNumber(tier.from_km);
            const to = toNumber(tier.to_km);
            if (from == null || isNaN(from) || (to != null && isNaN(to))) return;
            if (to != null && tenths(to) <= tenths(from)) errors.push(tr('errors.tier_inverted', { row: index + 1 }));
            bands.push({ row: index + 1, from: tenths(from), to: to == null ? null : tenths(to) });
        });
        bands.sort((a, b) => a.from - b.from);

        const open = bands.filter((band) => band.to === null);
        if (open.length > 1) errors.push(tr('errors.tier_multiple_open_ended'));
        else if (open.length === 1 && open[0] !== bands[bands.length - 1]) errors.push(tr('errors.tier_open_ended_not_last', { row: open[0].row }));

        const start = free != null && !isNaN(free) ? tenths(free) : 0;
        if (bands.length && bands[0].from > start) warnings.push(tr('warnings.tier_gap', { from: start / 10, to: bands[0].from / 10 }));
        for (let i = 1; i < bands.length; i++) {
            const prev = bands[i - 1];
            const cur = bands[i];
            if (prev.to === null || cur.from < prev.to) errors.push(tr('errors.tier_overlap', { a: prev.row, b: cur.row }));
            else if (cur.from > prev.to) warnings.push(tr('warnings.tier_gap', { from: prev.to / 10, to: cur.from / 10 }));
        }
        if (max != null && !isNaN(max) && bands.length) {
            const last = bands[bands.length - 1];
            if (last.to !== null && last.to < tenths(max)) warnings.push(tr('warnings.tier_gap', { from: last.to / 10, to: tenths(max) / 10 }));
        }

        showList('#dp-errors', errors);
        showList('#dp-warnings', [...new Set(warnings)]);
        if (CFG.canEdit) $('#dp-save').prop('disabled', errors.length > 0);
        return errors;
    }

    function showList(selector, items) {
        $(selector).html(items.map((item) => '<li>' + esc(item) + '</li>').join('')).toggleClass('hidden', items.length === 0);
    }

    $('#dp-form').on('submit', function (e) {
        e.preventDefault();
        if (!CFG.canEdit || !state.methodId || analyse().length) return;

        const payload = {
            is_active: $('#dp-is-active').is(':checked') ? 1 : 0,
            free_radius_km: $('#dp-free-radius').val(),
            max_service_radius_km: $('#dp-max-radius').val(),
            tiers: collectTiers().filter((tier) => String(tier.from_km).trim() !== '' || String(tier.price).trim() !== ''),
            category_ids: $('#dp-categories').val() || [],
        };

        const $btn = $('#dp-save').prop('disabled', true);
        $.ajax({
            url: '/shipping-zones/' + state.zoneId + '/distance-pricing/' + state.methodId,
            method: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify(payload),
        }).done(function (res) {
            state.data.rules[state.methodId] = res.data.rule;
            window.Toast.success(res.message);
            renderConfiguredSummary();
            fillForm();
            showList('#dp-warnings', res.data.warnings || []);
        }).fail(function (xhr) {
            const errors = Object.values(xhr.responseJSON?.errors || {}).flat();
            showList('#dp-errors', errors.length ? errors : [xhr.responseJSON?.message || 'Error']);
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    $('#dp-delete').on('click', async function () {
        if (!state.methodId) return;
        const ok = await window.confirmDialog({ title: tr('delete'), text: tr('delete_confirm'), confirmLabel: tr('delete'), danger: true });
        if (!ok) return;
        $.ajax({ url: '/shipping-zones/' + state.zoneId + '/distance-pricing/' + state.methodId, method: 'DELETE' })
            .done(function (res) {
                delete state.data.rules[state.methodId];
                window.Toast.success(res.message);
                renderConfiguredSummary();
                fillForm();
            })
            .fail(function (xhr) { window.Toast.error(xhr.responseJSON?.message || 'Error'); });
    });

    // ── Map preview (only when a Google Maps key is configured) ─────────────

    function setupPreview() {
        const locations = state.data.sample_locations || [];
        $('#dp-preview-no-key').toggleClass('hidden', !!CFG.mapsKey);
        $('#dp-preview-no-locations').toggleClass('hidden', !CFG.mapsKey || locations.length > 0);
        $('#dp-preview-controls').toggleClass('hidden', !CFG.mapsKey || locations.length === 0);
        if (!CFG.mapsKey || !locations.length) return;

        const $select = $('#dp-preview-location');
        const current = $select.val();
        $select.empty();
        locations.forEach((loc) => $select.append($('<option>').val(loc.id).text(loc.name)));
        if (current && locations.some((loc) => loc.id === current)) $select.val(current);

        loadMaps().then(drawPreview);
    }

    $('#dp-preview-location').on('change', drawPreview);

    function loadMaps() {
        if (window.google && window.google.maps) return Promise.resolve();
        if (state.mapsLoading) return state.mapsLoading;
        state.mapsLoading = new Promise((resolve, reject) => {
            window.__dpMapsReady = resolve;
            const script = document.createElement('script');
            script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(CFG.mapsKey) + '&callback=__dpMapsReady';
            script.async = true;
            script.onerror = reject;
            document.head.appendChild(script);
        });
        return state.mapsLoading;
    }

    function drawPreview() {
        if (!(window.google && window.google.maps) || !state.data) return;
        const loc = (state.data.sample_locations || []).find((l) => l.id === $('#dp-preview-location').val());
        if (!loc) return;

        const center = { lat: loc.lat, lng: loc.lng };
        if (!state.map) {
            state.map = new google.maps.Map(document.getElementById('dp-map'), { center, zoom: 11, mapTypeControl: false, streetViewControl: false });
        }
        state.overlays.forEach((overlay) => overlay.setMap(null));
        state.overlays = [new google.maps.Marker({ position: center, map: state.map, title: loc.name })];

        const circle = (km, color, fill) => {
            if (km == null || isNaN(km) || km <= 0) return null;
            const c = new google.maps.Circle({ map: state.map, center, radius: km * 1000, strokeColor: color, strokeWeight: 2, fillColor: color, fillOpacity: fill });
            state.overlays.push(c);
            return c;
        };

        const max = toNumber($('#dp-max-radius').val());
        const outer = circle(max, '#dc2626', 0.03);
        collectTiers().map((tier) => toNumber(tier.to_km)).filter((km) => km != null && !isNaN(km))
            .forEach((km) => circle(km, '#2563eb', 0.04));
        circle(toNumber($('#dp-free-radius').val()), '#16a34a', 0.15);

        if (outer) state.map.fitBounds(outer.getBounds());
        else state.map.setCenter(center);
    }
});
