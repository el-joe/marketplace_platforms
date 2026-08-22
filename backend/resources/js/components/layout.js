/**
 * Admin layout chrome: sidebar collapse, mobile sidebar overlay,
 * modal interactions, flash message auto-dismiss, notification polling,
 * country switcher.
 */
import $ from 'jquery';

$(function () {
    /* ---------- Sidebar collapse ---------- */
    const SIDEBAR_KEY = 'sidebar_collapsed';

    if (localStorage.getItem(SIDEBAR_KEY) === 'true') {
        $('body').addClass('sidebar-collapsed');
    }

    $('#sidebar-toggle').off('click.sidebar').on('click.sidebar', function () {
        $('body').toggleClass('sidebar-collapsed');
        localStorage.setItem(SIDEBAR_KEY, $('body').hasClass('sidebar-collapsed'));
    });

    /* ---------- Sidebar group dropdowns ---------- */
    // Only the group containing the active route starts open (server-rendered);
    // toggling here is session-only and never persisted to storage.
    $('#sidebar-nav').off('click.navgroup').on('click.navgroup', '.nav-group-header', function () {
        const $group = $(this).closest('.nav-group');
        const isOpen = $group.toggleClass('is-open').hasClass('is-open');
        $(this).attr('aria-expanded', isOpen ? 'true' : 'false');
    });

    /* ---------- Mobile sidebar ---------- */
    function openMobileSidebar() { $('body').addClass('sidebar-open'); }
    function closeMobileSidebar() { $('body').removeClass('sidebar-open'); }

    $('#mobile-menu-btn').off('click.sidebar').on('click.sidebar', openMobileSidebar);
    $('#sidebar-backdrop').off('click.sidebar').on('click.sidebar', closeMobileSidebar);

    $(document).off('keydown.sidebar').on('keydown.sidebar', function (e) {
        if (e.key === 'Escape') closeMobileSidebar();
    });

    /* ---------- Modal interactions ---------- */
    $(document).off('click.modal-open').on('click.modal-open', '[data-modal-open]', function () {
        $('#' + $(this).data('modal-open')).modal('open');
    });

    $(document).off('click.modal-close').on('click.modal-close', '[data-modal-close]', function () {
        $(this).closest('.modal-backdrop').modal('close');
    });

    // Backdrop click closes (unless persistent)
    $(document).off('mousedown.modal-backdrop').on('mousedown.modal-backdrop', '.modal-backdrop', function (e) {
        if (e.target !== this) return;
        if ($(this).data('persistent')) return;
        $(this).modal('close');
    });

    // Escape key closes top-most open modal
    $(document).off('keydown.modal').on('keydown.modal', function (e) {
        if (e.key !== 'Escape') return;
        const $open = $('.modal-backdrop.flex').last();
        if (!$open.length) return;
        if ($open.data('persistent')) return;
        $open.modal('close');
    });

    /* ---------- Flash message auto-dismiss ---------- */
    $('[data-flash-message]').each(function () {
        const $el = $(this);
        const dismiss = () => $el.fadeOut(200, () => $el.remove());

        setTimeout(dismiss, 5000);
        $el.find('[data-flash-dismiss]').on('click', dismiss);
    });

    /* ---------- Notifications ---------- */
    // Handled entirely by <x-notification-bell> Alpine component + Echo.
    // No jQuery polling needed here.

    /* ---------- Locale / direction switcher ---------- */
    $(document).off('click.locale').on('click.locale', '.locale-switch', function () {
        const locale = $(this).data('locale');
        $.ajax({ url: '/set-locale', method: 'POST', data: { locale } })
            .done(function () { window.location.reload(); })
            .fail(function (xhr) {
                xhr.handled = true;
                window.Toast && window.Toast.error(t('shared.layout.could_not_switch_language'));
            });
    });

    /* ---------- Country switcher ---------- */
    $(document).off('click.country').on('click.country', '.country-switch', function () {
        const code = $(this).data('country');
        $.ajax({
            url: '/country',
            method: 'POST',
            data: { country: code },
        })
            .done(function () { window.location.reload(); })
            .fail(function (xhr) {
                if (xhr.status >= 500) return; // global handler shows toast
                xhr.handled = true;
                window.Toast && window.Toast.error(t('shared.layout.could_not_switch_country'));
            });
    });

    /* ---------- Global search shortcut (⌘K / Ctrl+K) ---------- */
    $(document).off('keydown.search').on('keydown.search', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            $('#global-search-modal').modal('open');
        }
    });
    $('#global-search-btn').off('click.search').on('click.search', function () {
        $('#global-search-modal').modal('open');
    });
});
