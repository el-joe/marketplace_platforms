/**
 * resources/js/admin/categories.js
 *
 * Category module:
 *  - Tree expand/collapse
 *  - Fixed commission modal
 *  - Featured toggle (AJAX)
 *  - Delete (AJAX)
 *  - Category form: one "Save all" (create + edit), inline errors, section nav, unsaved guard
 */

import $ from 'jquery';

// ─── Helpers ──────────────────────────────────────────────────────────────────

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function isEditMode() {
    return $('#form-mode').val() === 'edit';
}

// ─── Init ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('categories-table')) {
        initTree();
        initFeaturedToggle();
        initVisibleToggle();
        initFooterVisibleToggle();
        initDeleteCategory();
        initSearch();
    }

    if (document.getElementById('category-form')) {
        initFormSubmit();
    }
});

// ─── Tree expand / collapse ───────────────────────────────────────────────────

const expanded = new Set();

window.catTree = {
    toggle(id) {
        if (expanded.has(id)) {
            collapseNode(id);
        } else {
            expandNode(id);
        }
    },
};

function expandNode(id) {
    expanded.add(id);

    // Show immediate children (rows whose data-parent === id)
    document.querySelectorAll(`tr[data-parent="${id}"]`).forEach(function (row) {
        row.style.display = '';
    });

    // Rotate chevron
    const btn = document.querySelector(`.expand-btn[data-id="${id}"] svg`);
    if (btn) btn.style.transform = 'rotate(90deg)';
}

function collapseNode(id) {
    expanded.delete(id);

    // Find all descendants recursively and hide them
    collapseDescendants(id);

    // Rotate chevron back
    const btn = document.querySelector(`.expand-btn[data-id="${id}"] svg`);
    if (btn) btn.style.transform = '';
}

function collapseDescendants(id) {
    document.querySelectorAll(`tr[data-parent="${id}"]`).forEach(function (row) {
        row.style.display = 'none';
        const childId = row.dataset.id;
        if (childId && expanded.has(childId)) {
            expanded.delete(childId);
            collapseDescendants(childId);
        }
        // Reset child chevrons
        const btn = row.querySelector(`.expand-btn svg`);
        if (btn) btn.style.transform = '';
    });
}

function initTree() {
    document.querySelector('#categories-table')?.addEventListener('click', function (e) {
        const btn = e.target.closest('.expand-btn[data-id]');
        if (!btn) return;
        window.catTree.toggle(btn.dataset.id);
    });
}

// ─── Search ────────────────────────────────────────────────────────────────────

function initSearch() {
    const input = document.getElementById('categories-search');
    const table = document.getElementById('categories-table');
    const clearBtn = document.getElementById('categories-search-clear');
    if (!input || !table) return;

    input.addEventListener('input', function () {
        const query = input.value.trim().toLowerCase();
        clearBtn?.classList.toggle('hidden', query === '');
        applySearch(table, query);
    });

    clearBtn?.addEventListener('click', function () {
        input.value = '';
        clearBtn.classList.add('hidden');
        applySearch(table, '');
        input.focus();
    });
}

function applySearch(table, query) {
    const rows = Array.from(table.querySelectorAll('tr[data-id]'));

    // Reset to the default collapsed view (root rows only, respecting manual expand state).
    if (!query) {
        rows.forEach(function (row) {
            row.classList.remove('bg-amber-50');
            const depth = parseInt(row.dataset.depth, 10);
            row.style.display = depth === 0 || expanded.has(row.dataset.parent) ? '' : 'none';
        });
        return;
    }

    const rowById = new Map(rows.map(function (row) { return [row.dataset.id, row]; }));
    const visibleIds = new Set();
    const matchedIds = new Set();

    rows.forEach(function (row) {
        if (!(row.dataset.search || '').includes(query)) return;

        matchedIds.add(row.dataset.id);
        visibleIds.add(row.dataset.id);

        // Walk up so a matched child category always shows its parent chain.
        let parentId = row.dataset.parent;
        while (parentId) {
            visibleIds.add(parentId);
            const parentRow = rowById.get(parentId);
            parentId = parentRow ? parentRow.dataset.parent : '';
        }
    });

    rows.forEach(function (row) {
        const id = row.dataset.id;
        row.style.display = visibleIds.has(id) ? '' : 'none';
        row.classList.toggle('bg-amber-50', matchedIds.has(id));
    });
}

// ─── Featured toggle ──────────────────────────────────────────────────────────

function initFeaturedToggle() {
    document.getElementById('categories-table')?.addEventListener('click', function (e) {
        const btn = e.target.closest('.featured-btn[data-id]');
        if (!btn) return;

        const id = btn.dataset.id;
        const url = btn.dataset.url;

        $.ajax({
            url: url,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        }).done(function (res) {
            const isFeatured = res.is_featured;
            btn.dataset.featured = isFeatured ? '1' : '0';

            if (isFeatured) {
                btn.classList.remove('bg-gray-100', 'text-gray-500');
                btn.classList.add('bg-amber-100', 'text-amber-700');
                btn.querySelector('span').textContent = t('admin.categories.featured_badge');
            } else {
                btn.classList.remove('bg-amber-100', 'text-amber-700');
                btn.classList.add('bg-gray-100', 'text-gray-500');
                btn.querySelector('span').textContent = t('admin.categories.add_badge');
            }

            window.Toast?.success(isFeatured ? (t('admin.categories.marked_featured')) : (t('admin.categories.removed_featured')));
        }).fail(function (xhr) {
            window.Toast?.error(xhr.responseJSON?.message || t('admin.categories.toggle_featured_failed'));
        });
    });
}

// ─── Visible toggle ───────────────────────────────────────────────────────────

function initVisibleToggle() {
    document.getElementById('categories-table')?.addEventListener('click', function (e) {
        const btn = e.target.closest('.visible-btn[data-id]');
        if (!btn) return;

        const url = btn.dataset.url;

        $.ajax({
            url: url,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        }).done(function (res) {
            const isVisible = res.is_visible;
            btn.dataset.visible = isVisible ? '1' : '0';

            btn.querySelector('.visible-icon-on')?.classList.toggle('hidden', !isVisible);
            btn.querySelector('.visible-icon-off')?.classList.toggle('hidden', isVisible);

            const span = btn.querySelector('span');
            if (span) span.textContent = isVisible ? t('admin.categories.visible_label') : t('admin.categories.hidden_label');

            if (isVisible) {
                btn.classList.remove('bg-amber-100', 'text-amber-700', 'hover:bg-green-50', 'hover:text-green-600');
                btn.classList.add('bg-green-100', 'text-green-700', 'hover:bg-red-50', 'hover:text-red-600');
            } else {
                btn.classList.remove('bg-green-100', 'text-green-700', 'hover:bg-red-50', 'hover:text-red-600');
                btn.classList.add('bg-amber-100', 'text-amber-700', 'hover:bg-green-50', 'hover:text-green-600');
            }

            window.Toast?.success(isVisible ? t('admin.categories.made_visible') : t('admin.categories.made_hidden'));
        }).fail(function (xhr) {
            window.Toast?.error(xhr.responseJSON?.message || t('admin.categories.toggle_visible_failed'));
        });
    });
}

// ─── Footer visibility toggle ──────────────────────────────────────────────────

function initFooterVisibleToggle() {
    document.getElementById('categories-table')?.addEventListener('click', function (e) {
        const btn = e.target.closest('.footer-visible-btn[data-id]');
        if (!btn) return;

        const url = btn.dataset.url;

        $.ajax({
            url: url,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        }).done(function (res) {
            const showInFooter = res.show_in_footer;
            btn.dataset.footerVisible = showInFooter ? '1' : '0';

            btn.querySelector('.footer-visible-icon-on')?.classList.toggle('hidden', !showInFooter);
            btn.querySelector('.footer-visible-icon-off')?.classList.toggle('hidden', showInFooter);

            const span = btn.querySelector('span');
            if (span) span.textContent = showInFooter ? t('admin.categories.shown_in_footer') : t('admin.categories.hidden_from_footer');

            if (showInFooter) {
                btn.classList.remove('bg-gray-100', 'text-gray-500', 'hover:bg-blue-50', 'hover:text-blue-600');
                btn.classList.add('bg-blue-100', 'text-blue-700', 'hover:bg-red-50', 'hover:text-red-600');
            } else {
                btn.classList.remove('bg-blue-100', 'text-blue-700', 'hover:bg-red-50', 'hover:text-red-600');
                btn.classList.add('bg-gray-100', 'text-gray-500', 'hover:bg-blue-50', 'hover:text-blue-600');
            }

            window.Toast?.success(showInFooter ? t('admin.categories.footer_shown') : t('admin.categories.footer_hidden'));
        }).fail(function (xhr) {
            window.Toast?.error(xhr.responseJSON?.message || t('admin.categories.toggle_footer_failed'));
        });
    });
}

// ─── Delete category ──────────────────────────────────────────────────────────

function removeDescendantRows(id) {
    document.querySelectorAll(`tr[data-parent="${id}"]`).forEach(function (row) {
        const childId = row.dataset.id;
        if (childId) removeDescendantRows(childId);
        row.remove();
    });
}

function initDeleteCategory() {
    document.getElementById('categories-table')?.addEventListener('click', async function (e) {
        const btn = e.target.closest('.delete-cat-btn[data-id]');
        if (!btn) return;

        const name = btn.dataset.name;
        const url = btn.dataset.url;
        const id = btn.dataset.id;

        const confirmMessage = t('admin.categories.delete_confirm_message', { name });
        const confirmed = window.confirmDelete
            ? await window.confirmDelete(confirmMessage, { title: t('admin.categories.delete_category_title') })
            : window.confirm(confirmMessage);
        if (!confirmed) return;

        $.ajax({
            url: url,
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        }).done(function (res) {
            window.Toast?.success(res.message || t('admin.categories.category_deleted'));
            removeDescendantRows(id);
            btn.closest('tr')?.remove();
        }).fail(function (xhr) {
            window.Toast?.error(xhr.responseJSON?.message || t('admin.categories.delete_category_failed'));
        });
    });
}

// ─── Category form: one "Save all" for every section ─────────────────────────
//
// Create and edit both post the whole form (all sections + image) in one AJAX
// request; the server saves it in one transaction. On a 422 each message is
// shown next to its field (or at the top of its section when the field has no
// input of its own), the section nav gets an error badge, and the page scrolls
// to the first problem. A snapshot of the form drives the unsaved-changes guard.

function formI18n(key) {
    return (window.CATEGORY_FORM_I18N || {})[key] || '';
}

/** "tiers.2.price_from" → "tiers[2][price_from]" */
function errorKeyToName(key) {
    const [head, ...rest] = key.split('.');
    return head + rest.map(part => `[${part}]`).join('');
}

function serializeForm(form) {
    const pairs = [];
    new FormData(form).forEach((value, key) => {
        if (key === '_token') return;
        pairs.push(key + '=' + (value instanceof File ? `file:${value.name}:${value.size}` : value));
    });
    return pairs.join('&');
}

function sectionFor(form, key, field) {
    if (field) {
        const host = field.closest('[data-form-section]');
        if (host) return host;
    }
    const head = key.split('.')[0];
    return Array.from(form.querySelectorAll('[data-form-section]')).find(section =>
        (section.dataset.errorPrefixes || '').split(',').includes(head)
    ) || null;
}

function clearFormErrors(form) {
    form.querySelectorAll('.js-field-error').forEach(el => el.remove());
    form.querySelectorAll('[aria-invalid="true"]').forEach(el => {
        el.setAttribute('aria-invalid', 'false');
        el.classList.remove('!border-danger-500');
    });
    form.querySelectorAll('[data-form-section]').forEach(section => {
        section.removeAttribute('data-has-errors');
        const box = section.querySelector('[data-section-errors]');
        if (box) { box.innerHTML = ''; box.classList.add('hidden'); }
        const summary = section.querySelector('[data-section-error-summary]');
        if (summary) { summary.textContent = ''; summary.classList.add('hidden'); }
    });
    document.querySelectorAll('[data-section-error-badge]').forEach(badge => {
        badge.textContent = '';
        badge.classList.add('hidden');
    });
    const panel = document.getElementById('category-form-errors');
    if (panel) {
        panel.classList.add('hidden');
        panel.querySelector('[data-error-list]').innerHTML = '';
    }
}

function showFormErrors(form, errors) {
    const perSection = new Map();
    let firstTarget = null;
    const panelList = document.querySelector('#category-form-errors [data-error-list]');

    Object.entries(errors).forEach(([key, messages]) => {
        const message = Array.isArray(messages) ? messages[0] : String(messages);
        const name = errorKeyToName(key);
        const field = form.querySelector(`[name="${CSS.escape(name)}"]`)
            || form.querySelector(`[name="${CSS.escape(name)}[]"]`);
        const section = sectionFor(form, key, field);

        if (field && field.type !== 'hidden') {
            field.setAttribute('aria-invalid', 'true');
            field.classList.add('!border-danger-500');
            const p = document.createElement('p');
            p.className = 'js-field-error mt-1 text-xs text-danger-600';
            p.textContent = message;
            const anchor = field.closest('label, .space-y-1') || field;
            anchor.insertAdjacentElement('afterend', p);
            firstTarget = firstTarget || field;
        } else if (section) {
            const box = section.querySelector('[data-section-errors]');
            if (box) {
                const p = document.createElement('p');
                p.textContent = message;
                box.appendChild(p);
                box.classList.remove('hidden');
                firstTarget = firstTarget || box;
            }
        }

        if (section) {
            section.setAttribute('data-has-errors', '');
            const id = section.dataset.formSection;
            perSection.set(id, (perSection.get(id) || 0) + 1);
            firstTarget = firstTarget || section;
        }

        if (panelList) {
            const li = document.createElement('li');
            li.textContent = message;
            panelList.appendChild(li);
        }
    });

    perSection.forEach((count, id) => {
        const badge = document.querySelector(`[data-section-link="${id}"] [data-section-error-badge]`);
        if (badge) { badge.textContent = count; badge.classList.remove('hidden'); }
        const summary = form.querySelector(`[data-form-section="${id}"] [data-section-error-summary]`);
        if (summary) {
            summary.textContent = formI18n('errorsInSection').replace(':count', count);
            summary.classList.remove('hidden');
        }
    });

    document.getElementById('category-form-errors')?.classList.remove('hidden');

    // Scroll to the first problem in page order, not in response order.
    const targets = Array.from(form.querySelectorAll('[aria-invalid="true"], [data-section-errors]:not(.hidden), [data-has-errors]'));
    const first = targets.sort((a, b) => (a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1))[0] || firstTarget;
    if (first) {
        first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (typeof first.focus === 'function' && first.matches('input, select, textarea')) {
            first.focus({ preventScroll: true });
        }
    }
}

function initSectionNav() {
    const links = Array.from(document.querySelectorAll('[data-section-link]'));
    if (!links.length || !('IntersectionObserver' in window)) return;

    const setActive = (id) => links.forEach(link => link.toggleAttribute('data-active', link.dataset.sectionLink === id));
    const visible = new Map();

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => visible.set(entry.target.dataset.formSection, entry.isIntersecting ? entry.boundingClientRect.top : null));
        const current = Array.from(visible.entries())
            .filter(([, top]) => top !== null)
            .sort((a, b) => a[1] - b[1])[0];
        if (current) setActive(current[0]);
    }, { rootMargin: '-140px 0px -55% 0px' });

    links.forEach(link => {
        const section = document.getElementById(`sec-${link.dataset.sectionLink}`);
        if (section) observer.observe(section);
        link.addEventListener('click', (e) => {
            if (!section) return;
            e.preventDefault();
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            history.replaceState(null, '', `#sec-${link.dataset.sectionLink}`);
            setActive(link.dataset.sectionLink);
        });
    });

    setActive(links[0].dataset.sectionLink);
}

function initFormSubmit() {
    const form = document.getElementById('category-form');
    const submitBtn = document.getElementById('submit-btn');
    const dirtyBadge = document.querySelector('[data-dirty-indicator]');
    let snapshot = null;
    let submitting = false;

    const takeSnapshot = () => { snapshot = serializeForm(form); refreshDirty(); };
    const isDirty = () => snapshot !== null && serializeForm(form) !== snapshot;
    const refreshDirty = () => {
        if (!dirtyBadge) return;
        dirtyBadge.classList.toggle('hidden', !isDirty());
        dirtyBadge.classList.toggle('inline-flex', isDirty());
    };

    // Alpine renders the shipping/tier inputs, so snapshot once it has run.
    const ready = () => setTimeout(takeSnapshot, 0);
    if (window.Alpine?.version) ready(); else document.addEventListener('alpine:initialized', ready, { once: true });
    setTimeout(() => { if (snapshot === null) takeSnapshot(); }, 1500);

    ['input', 'change'].forEach(evt => form.addEventListener(evt, () => setTimeout(refreshDirty, 0)));
    form.addEventListener('category-form:changed', () => setTimeout(refreshDirty, 0));

    window.addEventListener('beforeunload', (e) => {
        if (!submitting && isDirty()) {
            e.preventDefault();
            e.returnValue = formI18n('leaveConfirm');
            return e.returnValue;
        }
    });

    initSectionNav();

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (submitting) return;

        submitting = true;
        clearFormErrors(form);
        submitBtn.disabled = true;
        submitBtn.textContent = formI18n('saving');

        $.ajax({
            url: form.getAttribute('action'),
            method: 'POST',
            data: new FormData(form),
            processData: false,
            contentType: false,
            headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        })
            .done(function (res) {
                window.Toast?.success(res.message || formI18n('saved'));
                if (res.warning) window.Toast?.warning(res.warning);

                if (!isEditMode() && res.redirect) {
                    window.location.assign(res.redirect);
                    return; // keep the guard off while navigating away
                }

                window.dispatchEvent(new CustomEvent('category-form:saved', { detail: res }));
                setTimeout(takeSnapshot, 0);
                submitting = false;
            })
            .fail(function (xhr) {
                xhr.handled = true;
                submitting = false;
                if (xhr.status === 422) {
                    showFormErrors(form, xhr.responseJSON?.errors ?? {});
                    window.Toast?.error(formI18n('fixErrors'));
                } else {
                    window.Toast?.error(xhr.responseJSON?.message || formI18n('saveFailed'));
                }
            })
            .always(function () {
                submitBtn.disabled = false;
                submitBtn.textContent = submitBtn.dataset.label;
            });
    });
}
