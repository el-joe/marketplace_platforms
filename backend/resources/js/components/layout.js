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

    /* ---------- Sidebar menu search ---------- */
    // Filters the menu by label (current + other language) or route name and opens the
    // groups that contain matches. Ctrl/⌘+K or "/" focuses it, Esc clears, Enter opens the
    // first match, ↓ moves into the results.
    const $sidebarSearch = $('#sidebar-search');
    const $sidebarNav = $('#sidebar-nav');

    function normalizeSearch(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/[\u064B-\u0652\u0640]/g, '')
            .replace(/[أإآ]/g, 'ا')
            .replace(/ى/g, 'ي')
            .replace(/ة/g, 'ه')
            .replace(/[_.\-—]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    $sidebarNav.find('.nav-group').each(function () {
        $(this).attr('data-initial-open', $(this).hasClass('is-open') ? '1' : '0');
    });

    function setGroupOpen($group, open) {
        $group.toggleClass('is-open', open);
        $group.children('.nav-group-header').attr('aria-expanded', open ? 'true' : 'false');
    }

    function filterSidebar(rawQuery) {
        const query = normalizeSearch(rawQuery);
        const terms = query === '' ? [] : query.split(' ');
        const matches = (el) => {
            const haystack = normalizeSearch($(el).attr('data-nav-search'));
            return terms.every((term) => haystack.includes(term));
        };
        let visibleCount = 0;

        $sidebarNav.children('.nav-item').each(function () {
            const show = terms.length === 0 || matches(this);
            $(this).toggle(show);
            visibleCount += show ? 1 : 0;
        });

        $sidebarNav.children('.nav-group').each(function () {
            const $group = $(this);
            const $items = $group.find('.nav-subitem');

            if (terms.length === 0) {
                $items.toggle(true);
                $group.toggle(true);
                setGroupOpen($group, $group.attr('data-initial-open') === '1');
                return;
            }

            const groupMatches = matches(this);
            let shown = 0;
            $items.each(function () {
                const show = groupMatches || matches(this);
                $(this).toggle(show);
                shown += show ? 1 : 0;
            });

            $group.toggle(shown > 0);
            setGroupOpen($group, shown > 0);
            visibleCount += shown;
        });

        $('#sidebar-search-empty').toggleClass('hidden', terms.length === 0 || visibleCount > 0);
    }

    function visibleSidebarLinks() {
        return $sidebarNav.find('a.nav-item').filter(function () {
            if (this.style.display === 'none') return false;
            const $group = $(this).closest('.nav-group');
            return !$group.length || ($group[0].style.display !== 'none' && $group.hasClass('is-open'));
        });
    }

    function focusSidebarSearch() {
        if (!$sidebarSearch.length) return false;
        if (window.matchMedia('(max-width: 767.98px)').matches) {
            openMobileSidebar();
        } else if ($('body').hasClass('sidebar-collapsed')) {
            // Expand for this session only; the saved collapse preference is untouched.
            $('body').removeClass('sidebar-collapsed');
        }
        $sidebarSearch.trigger('focus').trigger('select');
        return true;
    }

    $sidebarSearch.off('.navsearch')
        .on('input.navsearch', function () { filterSidebar(this.value); })
        .on('keydown.navsearch', function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                if (this.value !== '') {
                    this.value = '';
                    filterSidebar('');
                } else {
                    this.blur();
                    closeMobileSidebar();
                }
            } else if (e.key === 'Enter') {
                const first = visibleSidebarLinks().first();
                if (this.value !== '' && first.length) {
                    e.preventDefault();
                    window.location.href = first.attr('href');
                }
            } else if (e.key === 'ArrowDown') {
                const first = visibleSidebarLinks().first();
                if (first.length) {
                    e.preventDefault();
                    first.trigger('focus');
                }
            }
        });

    // Arrow keys move between visible results; Esc returns to the search box.
    $sidebarNav.off('keydown.navsearch').on('keydown.navsearch', 'a.nav-item', function (e) {
        if (!['ArrowDown', 'ArrowUp', 'Escape'].includes(e.key)) return;
        e.preventDefault();
        if (e.key === 'Escape') {
            e.stopPropagation();
            $sidebarSearch.trigger('focus');
            return;
        }
        const $links = visibleSidebarLinks();
        const index = $links.index(this);
        const next = e.key === 'ArrowDown' ? $links.eq(index + 1) : (index > 0 ? $links.eq(index - 1) : $sidebarSearch);
        if (next.length) next.trigger('focus');
    });

    /* ---------- Search shortcuts (⌘K / Ctrl+K, "/") ---------- */
    $(document).off('keydown.search').on('keydown.search', function (e) {
        const target = e.target;
        const isTyping = target && (target.isContentEditable || /^(input|textarea|select)$/i.test(target.tagName));

        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            if ($('#global-search-modal').length) {
                $('#global-search-modal').modal('open');
            } else {
                focusSidebarSearch();
            }
        } else if (e.key === '/' && !isTyping && !e.metaKey && !e.ctrlKey && !e.altKey) {
            if (focusSidebarSearch()) e.preventDefault();
        }
    });
    $('#global-search-btn').off('click.search').on('click.search', function () {
        $('#global-search-modal').modal('open');
    });
});

/* ---------- Client-side table search (<x-table.client-search>) ---------- */
// Filters the rows of server-rendered tables / lists that are not DataTables.
$(document).on('input', '[data-client-search]', function () {
    const term = this.value.trim().toLowerCase();
    const $rows = $($(this).data('client-search')).children().not('[data-client-search-ignore]');
    let visible = 0;

    $rows.each(function () {
        const matches = term === '' || $(this).text().toLowerCase().includes(term);
        $(this).toggle(matches);
        if (matches) visible++;
    });

    $(this).siblings('[data-client-search-empty]').toggleClass('hidden', visible > 0 || $rows.length === 0);
});
