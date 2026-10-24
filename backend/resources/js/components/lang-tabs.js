/**
 * Generic EN/AR tab switcher for <x-form.lang-tabs>.
 * Delegated on document so it also works for dynamically injected tab groups.
 * Safe to import from several entry points: it only binds once.
 */
import $ from 'jquery';

export function activateLangTab(tabsId, lang) {
    const $group = $('[data-lang-tabs][data-tabs-id="' + tabsId + '"]');
    if (!$group.length) return;

    $group.find('[data-lang-tab]').removeClass('active border-primary-500 text-primary-600')
        .addClass('border-transparent text-gray-500');
    $group.find('[data-lang-tab="' + lang + '"][data-tabs-id="' + tabsId + '"]')
        .addClass('active border-primary-500 text-primary-600')
        .removeClass('border-transparent text-gray-500');

    $group.find('[data-lang-panel]').addClass('hidden');
    $group.find('[data-lang-panel="' + lang + '"][data-tabs-id="' + tabsId + '"]').removeClass('hidden');
}

if (!window.__langTabsBound) {
    window.__langTabsBound = true;
    $(document).on('click', '[data-lang-tab]', function () {
        activateLangTab($(this).data('tabs-id'), $(this).data('lang-tab'));
    });
}
