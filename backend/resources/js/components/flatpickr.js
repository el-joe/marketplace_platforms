/**
 * flatpickr.js — Flatpickr date/time picker integration.
 *
 * Requires (install via yarn/npm):
 *   yarn add flatpickr
 *
 * Initialised on: [data-flatpickr]
 * Options passed via data attributes:
 *   data-enable-time="true"
 *   data-mode="range|multiple|single|month"   // "month" renders a month/year-only picker
 *   data-min-date="2024-01-01"
 *   data-max-date="today"
 *   data-time-only="true"   // hides the calendar, HH:MM picker only
 */
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import monthSelectPlugin from 'flatpickr/dist/plugins/monthSelect';
import 'flatpickr/dist/plugins/monthSelect/style.css';
import $ from 'jquery';

// Locale map — add more as needed
const LOCALES = {};

async function loadLocale(lang) {
    if (lang === 'en' || !lang) return null;
    try {
        const mod = await import(`flatpickr/dist/l10n/${lang}.js`);
        return mod.default[lang] ?? null;
    } catch {
        return null;
    }
}

async function initDatePicker(el) {
    const $el = $(el);
    const lang = document.documentElement.lang || 'en';
    const locale = await loadLocale(lang);
    const timeOnly = $el.data('time-only') === true || $el.data('time-only') === 'true';
    const enableTime = timeOnly || $el.data('enable-time') === true || $el.data('enable-time') === 'true';
    const mode = $el.data('mode') || 'single';
    const minDate = $el.data('min-date') || null;
    const maxDate = $el.data('max-date') || null;

    // Destroy any existing instance
    if (el._flatpickr) {
        el._flatpickr.destroy();
    }

    const isMonthMode = mode === 'month';

    flatpickr(el, {
        enableTime,
        noCalendar: timeOnly,
        mode: isMonthMode ? 'single' : mode,
        minDate: minDate || undefined,
        maxDate: maxDate || undefined,
        ...(locale ? { locale } : {}),
        dateFormat: isMonthMode ? 'Y-m' : (timeOnly ? 'H:i' : (enableTime ? 'Y-m-d H:i' : 'Y-m-d')),
        time_24hr: true,
        allowInput: false,
        disableMobile: false,
        ...(isMonthMode ? { plugins: [monthSelectPlugin({ shorthand: true, dateFormat: 'Y-m', altFormat: 'F Y' })] } : {}),
        // Emit native change event so jQuery listeners still fire
        onChange: (selectedDates, dateStr) => {
            el.dispatchEvent(new Event('change', { bubbles: true }));
        },
    });
}

function initDatePickers($scope) {
    $scope = $scope || $('body');
    $scope.find('[data-flatpickr]').each(function () {
        initDatePicker(this);
    });
}

window.initDatePickers = initDatePickers;

$(function () {
    initDatePickers($('body'));
});
