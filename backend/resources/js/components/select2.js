/**
 * select2.js — Select2 integration (standard + AJAX async-select).
 *
 * Requires (install via yarn/npm):
 *   yarn add select2
 *
 * Note: Select2 needs jQuery available globally (window.$).
 *
 * Standard select:   <select data-select2="true">
 * Async select:      <select data-async-select data-config='{...}'>
 */
import $ from 'jquery';
import select2Factory from 'select2';
// Select2 (CJS) exports a factory function — call it to attach $.fn.select2
if (typeof select2Factory === 'function') {
    select2Factory(window, $);
}

/* =========================================================
   STANDARD SELECT2
   ========================================================= */
function initStandardSelect2($scope) {
    const $els = $scope.find('[data-select2-init]');
    if (!$els.length) return;

    $els.each(function () {
        const $el = $(this);
        if ($el.data('select2')) {
            try { $el.select2('destroy'); } catch (_) { }
        }
        $el.select2({
            width: '100%',
            placeholder: $el.find('option[value=""]').text() || t('shared.select.placeholder'),
            allowClear: true,
        });
    });
}

/* =========================================================
   ASYNC SELECT2 (data-async-select)
   ========================================================= */
function initAsyncSelect2($scope) {
    const $els = $scope.find('[data-async-select]');
    if (!$els.length) return;

    $els.each(function () {
        const $el = $(this);
        // Use .attr() not .data() — jQuery auto-parses JSON data-* attributes
        // into objects, causing JSON.parse(object) to silently return {}
        const config = (() => {
            try { return JSON.parse($el.attr('data-config') || '{}'); } catch { return {}; }
        })();

        if ($el.data('select2')) {
            try { $el.select2('destroy'); } catch (_) { }
        }

        $el.select2({
            width: '100%',
            placeholder: $el.attr('placeholder') || config.placeholder || t('shared.select.search_placeholder'),
            allowClear: !$el.prop('required'),
            minimumInputLength: config.minLength ?? 0,
            ajax: {
                url: config.url,
                dataType: 'json',
                delay: config.delay ?? 300,
                data: function (params) {
                    // Re-read data-config on every request (not just at init) so callers can
                    // update extra static filter params (e.g. vendor_id) after initialization.
                    let liveConfig = config;
                    try { liveConfig = JSON.parse($el.attr('data-config') || '{}'); } catch (_) { }
                    const { url, param, minLength, delay, placeholder, ...extra } = liveConfig;
                    const query = { ...extra };
                    query[param || 'q'] = params.term;
                    query.page = params.page || 1;
                    return query;
                },
                processResults: function (response, params) {
                    const items = response.data ?? response.results ?? response;
                    return {
                        results: Array.isArray(items) ? items : [],
                        pagination: { more: response.meta?.current_page < response.meta?.last_page },
                    };
                },
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            },
        });
    });
}

/* =========================================================
   Public init
   ========================================================= */


function initSelect2($scope) {
    $scope = $scope || $('body');

    initStandardSelect2($scope);
    initAsyncSelect2($scope);
}

window.initSelect2 = initSelect2;

$(function () {
    initSelect2($('body'));
});
