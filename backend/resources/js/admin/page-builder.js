/**
 * Admin Page Builder
 *
 * Drag-and-drop visual page composition tied to the /page-builder/* admin API.
 * Uses jQuery (already loaded globally) + SortableJS + Alpine.js for the
 * version-history drawer.
 */

import $ from 'jquery';
import Sortable from 'sortablejs';

const csrfToken = () => $('meta[name="csrf-token"]').attr('content');

/* ─── State ─────────────────────────────────────────────────────────────── */
const state = {
    currentPageId: null,
    currentPageMeta: null,
    selectedBlockId: null,
    autoSaveTimer: null,
    sortable: null,
    sections: [],
    sectionSortable: null,
    blockSortables: [],
    paletteSortables: [],
    editingSectionId: null,
};

const ROUTES = {
    load: '/page-builder/load',
    pages: '/page-builder/pages',
    publish: (id) => `/page-builder/pages/${id}/publish`,
    clearPageCache: (pageId) => `/page-builder/pages/${pageId}/clear-cache`,
    pageRevisions: (id) => `/page-builder/pages/${id}/revisions`,
    pageRevRestore: (id) => `/page-builder/page-revisions/${id}/restore`,

    blocks: '/page-builder/blocks',
    blockConfig: (id) => `/page-builder/blocks/${id}/config`,
    blockAnalytics: (id) => `/page-builder/blocks/${id}/analytics`,
    blockVisibility: (id) => `/page-builder/blocks/${id}/visibility`,
    blockRemove: (id) => `/page-builder/blocks/${id}`,
    blockRevisions: (id) => `/page-builder/blocks/${id}/revisions`,
    blockRevRestore: (id) => `/page-builder/revisions/${id}/restore`,
    reorder: '/page-builder/reorder',
    configForm: '/page-builder/config-form',

    sections: '/page-builder/sections',
    sectionUpdate: (id) => `/page-builder/sections/${id}`,
    sectionDelete: (id) => `/page-builder/sections/${id}`,
    sectionsReorder: '/page-builder/sections/reorder',
    blockAssignColumn: (id) => `/page-builder/blocks/${id}/assign-column`,

    slides: (id) => `/page-builder/blocks/${id}/slides`,
    slideSave: (id) => `/page-builder/blocks/${id}/slides`,
    slideDelete: (id) => `/page-builder/slides/${id}`,
    slideReorder: (id) => `/page-builder/blocks/${id}/slides/reorder`,
    slideUploadImage: '/page-builder/slides/upload-image',

    adImagesManagerPartial: (id) => `/page-builder/blocks/${id}/ad-images/panel`,
    adImages: (id) => `/page-builder/blocks/${id}/ad-images`,
    adImageSave: (id) => `/page-builder/blocks/${id}/ad-images`,
    adImageDelete: (id) => `/page-builder/ad-images/${id}`,
    adImageReorder: (id) => `/page-builder/blocks/${id}/ad-images/reorder`,
    adImageUploadImage: '/page-builder/ad-images/upload-image',

    sectionBackgroundUploadImage: '/page-builder/sections/upload-background-image',
    promoTileUploadImage: '/page-builder/promo-tiles/upload-image',

    pageUpdate: (id) => `/page-builder/pages/${id}`,
    pageDelete: (id) => `/page-builder/pages/${id}`,
    pageDuplicate: (id) => `/page-builder/pages/${id}/duplicate`,

    searchVendors: '/page-builder/search/vendors',
    searchFlashSales: '/page-builder/search/flash-sales',
    searchProducts: '/page-builder/search/products',
    searchCategories: '/page-builder/search/categories',

    blockProducts: (id) => `/page-builder/blocks/${id}/products`,
    blockProductRemove: (id) => `/page-builder/block-products/${id}`,
    blockProductReorder: (id) => `/page-builder/blocks/${id}/products/reorder`,

    blockCategories: (id) => `/page-builder/blocks/${id}/categories`,
    blockCategoryRemove: (id) => `/page-builder/block-categories/${id}`,
    blockCategoryReorder: (id) => `/page-builder/blocks/${id}/categories/reorder`,

    blockSellers: (id) => `/page-builder/blocks/${id}/sellers`,
    blockSellerRemove: (id) => `/page-builder/block-sellers/${id}`,
    blockSellerReorder: (id) => `/page-builder/blocks/${id}/sellers/reorder`,

    blockBrands: (id) => `/page-builder/blocks/${id}/brands`,
    blockBrandRemove: (id) => `/page-builder/block-brands/${id}`,
    blockBrandReorder: (id) => `/page-builder/blocks/${id}/brands/reorder`,
    searchBrands: '/page-builder/search/brands',
};

/* ─── Helpers ───────────────────────────────────────────────────────────── */
const Toast = window.Toast || { success: alert, error: alert, info: console.log, warning: console.warn };

function setSaveStatus(text, kind = '') {
    const $ind = $('#save-indicator');
    $ind.removeClass('saving saved error').addClass(kind || '').text(text);
}

function withLoading($btn, promise) {
    const originalHtml = $btn.html();
    const originalDisabled = $btn.prop('disabled');
    $btn.prop('disabled', true).addClass('opacity-60 cursor-wait');
    return Promise.resolve(promise).finally(() => {
        $btn.html(originalHtml).prop('disabled', originalDisabled).removeClass('opacity-60 cursor-wait');
    });
}

function ajax(options) {
    return $.ajax({
        ...options,
        headers: { 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) },
    });
}

/* ─── Rendering ─────────────────────────────────────────────────────────── */
function renderBlockCard(block) {
    const icon = block.icon || 'cube';
    const badges = [];
    if (!block.is_visible) badges.push(`<span class="text-xs font-medium px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">${window.TRANSLATIONS?.hidden || 'Hidden'}</span>`);
    if (block.visible_from || block.visible_until) badges.push(`<span class="text-xs font-medium px-1.5 py-0.5 rounded bg-amber-100 text-amber-700">${window.TRANSLATIONS?.scheduled || 'Scheduled'}</span>`);
    if (block.device_target && block.device_target !== 'all') badges.push(`<span class="text-xs font-medium px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700">${escapeHtml(block.device_target)}</span>`);

    return `
        <div class="block-card group" data-block-id="${block.id}" data-block-type="${escapeHtml(block.block_type || '')}">
            <div class="drag-handle" title="${window.TRANSLATIONS?.dragToReorder || 'Drag to reorder'}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </div>
            <div class="block-icon flex-shrink-0">
                ${heroiconSvg(icon)}
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-gray-900 truncate">${escapeHtml(block.label_en || block.block_type)}</div>
                <div class="text-xs text-gray-500 truncate" data-preview>${escapeHtml(block.preview_text || '')}</div>
            </div>
            <div class="flex items-center gap-1.5">${badges.join('')}</div>
            <div class="block-actions flex items-center gap-1">
                <button type="button" class="p-1.5 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded" data-action="edit-block" title="${window.TRANSLATIONS?.edit || 'Edit'}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h-6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-6m-6-9 6 6m-6-6L21 3l-4 4"/></svg>
                </button>
                <button type="button" class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded" data-action="delete-block" title="${window.TRANSLATIONS?.delete || 'Delete'}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166M5.84 19.673a2.25 2.25 0 0 0 2.244 2.077h7.832a2.25 2.25 0 0 0 2.244-2.077L19.228 5.79m-14.456 0a48.108 48.108 0 0 1 3.478-.397m11.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                </button>
            </div>
        </div>
    `;
}

function heroiconSvg(name) {
    // Minimal inline SVG (we don't have the full heroicon paths in JS — use a generic cube).
    const generic = '<path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/>';
    return `<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" data-icon="${name}">${generic}</svg>`;
}

function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

/* ─── Page loading ──────────────────────────────────────────────────────── */
function loadPage(pageId) {
    state.currentPageId = pageId;
    state.selectedBlockId = null;
    closeConfigPanel();

    $('#toolbar-actions-row').toggleClass('hidden', !state.currentPageId);

    if (!pageId) {
        $('#sections-container').empty();
        $('#block-canvas').empty().addClass('hidden');
        $('#ungrouped-title').addClass('hidden');
        $('#canvas-empty').removeClass('hidden');
        $('#home-page-banner').addClass('hidden').empty();
        return;
    }

    setSaveStatus(window.TRANSLATIONS?.loading || t('admin.page_builder.loading'), 'saving');
    return ajax({
        url: ROUTES.load,
        method: 'GET',
        data: { page_id: pageId },
    }).done((res) => {
        state.currentPageMeta = res.page;
        renderCanvas(res);
        renderHomeBanner(res.page);
        $('#toolbar-actions-row').toggleClass('hidden', !state.currentPageId);
        $('#preview-btn').attr('href', `/preview/page/${res.page.id}`);
        setSaveStatus('');
    }).fail(() => {
        setSaveStatus(window.TRANSLATIONS?.loadFailed || 'Load failed', 'error');
        Toast.error(window.TRANSLATIONS?.couldNotLoadPage || 'Could not load page.');
    });
}

function renderHomeBanner(page) {
    const $banner = $('#home-page-banner');
    const assignments = page.home_for || [];
    if (!assignments.length) {
        $banner.addClass('hidden').empty();
        return;
    }
    const list = assignments.map((a) => `${escapeHtml(a.context_name || '')} ${window.TRANSLATIONS.inCountry} ${escapeHtml(a.country_name || '')}`).join(', ');
    $banner.removeClass('hidden').text(
        `${window.TRANSLATIONS.homePageBannerPrefix} ${list}. ${window.TRANSLATIONS.homePageBannerSuffix}`
    );
}

/* ─── Sections ──────────────────────────────────────────────────────────── */
const COLUMN_WIDTH_CLASSES = {
    '1/2': 'flex-basis:50%;', '1/3': 'flex-basis:33.3333%;', '2/3': 'flex-basis:66.6667%;', '1/4': 'flex-basis:25%;',
};

function widthToStyle(w) {
    return `flex:0 0 auto;${COLUMN_WIDTH_CLASSES[w] || 'flex:1;'}min-width:0;`;
}

function renderSectionWrapper(section, blocks) {
    const isColumns = section.layout === 'columns';
    const cfg = section.columns_config || null;
    const widths = cfg && cfg.widths ? String(cfg.widths).trim().split(/\s+/) : [];

    let bodyHtml;
    if (!blocks.length && isColumns && widths.length) {
        const columns = widths.length;
        const colsHtml = widths.map((w, i) => `
            <div class="section-column" style="${widthToStyle(w)}">
                <div class="section-column-blocks is-empty text-xs text-gray-400" data-section-id="${section.id}" data-col-index="${i}" style="min-height: 40px;">
                    ${i === 0 ? 'No blocks in this section yet — drag one here or add from the left.' : ''}
                </div>
            </div>
        `).join('');
        bodyHtml = `<div class="section-body"><div class="section-columns-row">${colsHtml}</div></div>`;
    } else if (!blocks.length) {
        bodyHtml = `<div class="section-body is-empty text-xs text-gray-400 section-blocks" data-section-id="${section.id}">No blocks in this section yet — drag one here or add from the left.</div>`;
    } else if (isColumns && widths.length) {
        const columns = widths.length;
        const byCol = [];
        for (let i = 0; i < columns; i++) byCol.push([]);
        blocks.forEach((b) => {
            const idx = Math.min(Math.max(b.column_index || 0, 0), columns - 1);
            byCol[idx].push(b);
        });
        const colsHtml = byCol.map((colBlocks, i) => `
            <div class="section-column" style="${widthToStyle(widths[i])}">
                <div class="section-column-blocks" data-section-id="${section.id}" data-col-index="${i}" style="min-height: 40px;">
                    ${colBlocks.map(renderBlockCard).join('')}
                </div>
            </div>
        `).join('');
        bodyHtml = `<div class="section-body"><div class="section-columns-row">${colsHtml}</div></div>`;
    } else {
        bodyHtml = `<div class="section-body"><div class="section-blocks" data-section-id="${section.id}">${blocks.map(renderBlockCard).join('')}</div></div>`;
    }

    return `
        <div class="section-wrapper" data-section-id="${section.id}">
            <div class="section-header">
                <div class="drag-handle" title="${window.TRANSLATIONS?.dragToReorder || 'Drag to reorder'}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </div>
                <span class="section-name">${escapeHtml(section.name || 'Untitled section')}</span>
                ${isColumns ? '<span class="section-badge">columns</span>' : ''}
                ${!section.is_visible ? `<span class="section-badge" style="background:#f3f4f6;color:#6b7280;">${window.TRANSLATIONS?.hidden || 'Hidden'}</span>` : ''}
                <div class="section-actions">
                    <button type="button" data-action="edit-section" data-section-id="${section.id}"
                        class="inline-flex items-center gap-1 px-2 py-1 text-xs text-gray-500 hover:text-gray-800 hover:bg-gray-100 rounded"
                        title="${window.TRANSLATIONS?.sectionSettings || 'Section settings'}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.766.79.93.409.164.878.14 1.24-.12l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.26.362-.283.831-.12 1.24.164.406.505.72.93.79l.894.15c.542.09.94.56.94 1.109v1.094c0 .55-.398 1.02-.94 1.11l-.894.149c-.425.07-.766.384-.93.79-.164.409-.14.878.12 1.24l.526.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.362-.26-.83-.283-1.24-.12-.405.164-.719.506-.789.93l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.766-.79-.93-.409-.164-.878-.14-1.24.12l-.737.527a1.125 1.125 0 0 1-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.26-.362.283-.831.12-1.24-.165-.406-.506-.72-.93-.79l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.766-.384.93-.79.163-.409.14-.878-.12-1.24l-.527-.738a1.125 1.125 0 0 1 .12-1.45l.774-.773a1.125 1.125 0 0 1 1.449-.12l.738.527c.362.26.83.283 1.24.12.405-.164.72-.506.789-.93l.15-.894Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                        <span>${window.TRANSLATIONS?.settings || 'Settings'}</span>
                    </button>
                    <button type="button" data-action="delete-section" data-section-id="${section.id}" title="${window.TRANSLATIONS?.delete || 'Delete'}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166M5.84 19.673a2.25 2.25 0 0 0 2.244 2.077h7.832a2.25 2.25 0 0 0 2.244-2.077L19.228 5.79m-14.456 0a48.108 48.108 0 0 1 3.478-.397m11.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                    </button>
                </div>
            </div>
            ${bodyHtml}
        </div>
    `;
}

function renderCanvas(data) {
    const sections = (data.sections || []).slice().sort((a, b) => a.position - b.position);
    const blocks = data.blocks || [];
    state.sections = sections;

    const bySection = {};
    const ungrouped = [];
    blocks.forEach((b) => {
        if (b.section_id) {
            (bySection[b.section_id] = bySection[b.section_id] || []).push(b);
        } else {
            ungrouped.push(b);
        }
    });

    if (!sections.length && !ungrouped.length) {
        $('#sections-container').empty();
        $('#block-canvas').empty().addClass('hidden');
        $('#ungrouped-title').addClass('hidden');
        $('#canvas-empty').removeClass('hidden').find('p').text(window.TRANSLATIONS?.noBlocksYet || 'This page has no blocks yet. Pick one from the left.');
        return;
    }

    $('#canvas-empty').addClass('hidden');
    $('#sections-container').html(sections.map((s) => renderSectionWrapper(s, bySection[s.id] || [])).join(''));

    if (ungrouped.length) {
        $('#ungrouped-title').toggleClass('hidden', !sections.length);
        $('#block-canvas').removeClass('hidden').html(ungrouped.map(renderBlockCard).join(''));
    } else {
        $('#ungrouped-title').addClass('hidden');
        $('#block-canvas').empty().addClass('hidden');
    }

    initSortable();
}

function initSortable() {
    // Destroy previous instances
    if (state.sortable) { state.sortable.destroy(); state.sortable = null; }
    if (state.sectionSortable) { state.sectionSortable.destroy(); state.sectionSortable = null; }
    state.blockSortables.forEach((s) => s.destroy());
    state.blockSortables = [];

    // Section-level reordering
    const sectionsContainer = document.getElementById('sections-container');
    if (sectionsContainer) {
        state.sectionSortable = Sortable.create(sectionsContainer, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            draggable: '.section-wrapper',
            onEnd: () => { persistSectionOrder(); persistOrder(); },
        });
    }

    // Block-level reordering: one Sortable per block container (section stack/column bodies + ungrouped canvas)
    document.querySelectorAll('.section-blocks, .section-column-blocks').forEach((el) => {
        state.blockSortables.push(Sortable.create(el, {
            group: 'pb-blocks',
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: persistOrder,
            onAdd: handlePaletteDrop,
        }));
    });

    const canvas = document.getElementById('block-canvas');
    if (canvas) {
        state.sortable = Sortable.create(canvas, {
            group: 'pb-blocks',
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: persistOrder,
            onAdd: handlePaletteDrop,
        });
    }

    initPaletteSortable();
}

/* ─── Palette drag source ───────────────────────────────────────────────── */
function initPaletteSortable() {
    state.paletteSortables?.forEach((s) => s.destroy());
    state.paletteSortables = [];

    document.querySelectorAll('.pb-group').forEach((el) => {
        state.paletteSortables.push(Sortable.create(el, {
            group: { name: 'pb-blocks', pull: 'clone', put: false },
            sort: false,
            animation: 150,
            onClone: (evt) => { evt.clone.setAttribute('data-palette-clone', 'true'); },
        }));
    });
}

function handlePaletteDrop(evt) {
    const $item = $(evt.item);
    if (!$item.attr('data-palette-clone')) return; // ordinary block move — handled by onEnd/persistOrder

    if (!state.currentPageId) {
        $item.remove();
        Toast.warning(window.TRANSLATIONS?.selectOrCreatePageFirst || 'Select or create a page first.');
        return;
    }

    const $to = $(evt.to);
    const sectionId = $to.data('section-id') || null;
    const colIndex = $to.data('col-index');
    const code = $item.data('block-type');
    const position = evt.newIndex;
    $item.remove();

    ajax({
        url: ROUTES.blocks,
        method: 'POST',
        data: {
            page_id: state.currentPageId,
            block_type_code: code,
            position,
            section_id: sectionId,
            ...(colIndex !== undefined ? { column_index: colIndex } : {}),
        },
    }).done((res) => {
        Toast.success(`${res.label_en || res.block_type} added.`);
        loadPage(state.currentPageId);
    }).fail((xhr) => {
        const msg = xhr.responseJSON?.message || xhr.responseJSON?.errors?.block_type_code?.[0] || window.TRANSLATIONS?.couldNotAddBlock || 'Could not add block.';
        Toast.error(msg);
    });
}

function persistSectionOrder() {
    const sections = $('#sections-container .section-wrapper').map(function (i) {
        return { id: $(this).data('section-id'), position: i };
    }).get();
    if (!sections.length) return;

    ajax({
        url: ROUTES.sectionsReorder,
        method: 'POST',
        data: JSON.stringify({ page_id: state.currentPageId, sections }),
        contentType: 'application/json',
    });
}

function persistOrder() {
    const blocks = [];
    let pos = 0;

    $('#sections-container .section-wrapper').each(function () {
        const sectionId = $(this).data('section-id');
        $(this).find('.section-blocks, .section-column-blocks').each(function () {
            const colIndex = $(this).data('col-index');
            $(this).children('.block-card').each(function () {
                blocks.push({
                    id: $(this).data('block-id'),
                    position: pos++,
                    section_id: sectionId,
                    ...(colIndex !== undefined ? { column_index: colIndex } : {}),
                });
            });
        });
    });

    $('#block-canvas > .block-card').each(function () {
        blocks.push({ id: $(this).data('block-id'), position: pos++, section_id: null });
    });

    if (!blocks.length) return;

    setSaveStatus(window.TRANSLATIONS?.savingOrder || 'Saving order…', 'saving');
    ajax({
        url: ROUTES.reorder,
        method: 'POST',
        data: JSON.stringify({ page_id: state.currentPageId, blocks: blocks.map(({ column_index, ...b }) => b) }),
        contentType: 'application/json',
    }).done(() => setSaveStatus(window.TRANSLATIONS?.orderSaved || 'Order saved', 'saved'))
        .fail(() => { setSaveStatus(window.TRANSLATIONS?.orderSaveFailed || 'Order save failed', 'error'); Toast.error(window.TRANSLATIONS?.couldNotSaveOrder || 'Could not save order.'); });

    // Persist column assignment for any block dropped into a column container.
    blocks.filter((b) => b.column_index !== undefined).forEach((b) => {
        ajax({ url: ROUTES.blockAssignColumn(b.id), method: 'POST', data: { column_index: b.column_index } });
    });
}

/* ─── Add block ─────────────────────────────────────────────────────────── */
$(document).on('click', '.palette-btn', function () {
    if (!state.currentPageId) {
        Toast.warning(window.TRANSLATIONS?.selectOrCreatePageFirst || 'Select or create a page first.');
        return;
    }

    const $btn = $(this);
    const code = $btn.data('block-type');
    const position = $('.block-card').length;

    withLoading($btn, ajax({
        url: ROUTES.blocks,
        method: 'POST',
        data: { page_id: state.currentPageId, block_type_code: code, position },
    }).done((res) => {
        const block = {
            id: res.block_id, block_type: res.block_type, label_en: res.label_en,
            icon: res.icon, preview_text: res.preview_text, is_visible: true,
        };
        $('#canvas-empty').addClass('hidden');
        $('#block-canvas').removeClass('hidden').append(renderBlockCard(block));
        if (state.sections.length) $('#ungrouped-title').removeClass('hidden');
        initSortable();
        selectBlock(res.block_id);
        Toast.success(`${res.label_en || res.block_type} added.`);
    }).fail((xhr) => {
        const msg = xhr.responseJSON?.message || xhr.responseJSON?.errors?.block_type_code?.[0] || window.TRANSLATIONS?.couldNotAddBlock || 'Could not add block.';
        Toast.error(msg);
    }));
});

/* ─── Select / edit / delete blocks ─────────────────────────────────────── */
$(document).on('click', '.block-card', function (e) {
    if ($(e.target).closest('[data-action]').length) return;
    selectBlock($(this).data('block-id'));
});

$(document).on('click', '[data-action="edit-block"]', function () {
    selectBlock($(this).closest('.block-card').data('block-id'));
});

$(document).on('click', '[data-action="delete-block"]', async function () {
    const $card = $(this).closest('.block-card');
    const blockId = $card.data('block-id');

    const ok = await window.confirmDialog({
        title: window.TRANSLATIONS?.removeBlockTitle || 'Remove block?',
        message: window.TRANSLATIONS?.removeBlockMessage || 'This block (and its config) will be removed from the page. You can restore it from version history if the page has been published.',
        confirmLabel: window.TRANSLATIONS?.remove || 'Remove',
        danger: true,
    });
    if (!ok) return;

    ajax({ url: ROUTES.blockRemove(blockId), method: 'DELETE' })
        .done(() => {
            $card.remove();
            if (state.selectedBlockId === blockId) closeConfigPanel();
            if (!$('#block-canvas .block-card').length) {
                $('#block-canvas').addClass('hidden');
                $('#ungrouped-title').addClass('hidden');
            }
            if (!$('.block-card').length && !state.sections.length) {
                $('#canvas-empty').removeClass('hidden');
            }
            Toast.success(window.TRANSLATIONS?.blockRemoved || 'Block removed.');
        })
        .fail(() => Toast.error(window.TRANSLATIONS?.couldNotRemoveBlock || 'Could not remove block.'));
});

function selectBlock(blockId) {
    state.selectedBlockId = blockId;
    $('.block-card').removeClass('is-selected');
    $(`.block-card[data-block-id="${blockId}"]`).addClass('is-selected');
    resetAnalyticsTab();
    switchConfigTab('settings');
    openConfigPanel(blockId);
}

/* ─── Analytics tab ─────────────────────────────────────────────────────── */
const analyticsState = {
    cache: {}, // blockId -> parsed response, cleared whenever a new block is selected
    chart: null,
};

function switchConfigTab(tab) {
    $('.config-tab-btn').each(function () {
        const active = $(this).data('config-tab') === tab;
        $(this).toggleClass('border-primary-600 text-primary-700', active);
        $(this).toggleClass('border-transparent text-gray-500', !active);
    });
    $('[data-config-tab-panel]').addClass('hidden');
    $(`[data-config-tab-panel="${tab}"]`).removeClass('hidden');

    if (tab === 'analytics' && state.selectedBlockId) {
        loadBlockAnalytics(state.selectedBlockId);
    }
}

$(document).on('click', '.config-tab-btn', function () {
    switchConfigTab($(this).data('config-tab'));
});

function resetAnalyticsTab() {
    if (analyticsState.chart) {
        analyticsState.chart.destroy();
        analyticsState.chart = null;
    }
    analyticsState.cache = {};
    $('#analytics-content, #analytics-empty, #analytics-error').addClass('hidden');
    $('#analytics-loading').removeClass('hidden');
}

function loadBlockAnalytics(blockId) {
    if (analyticsState.cache[blockId]) {
        renderBlockAnalytics(analyticsState.cache[blockId]);
        return;
    }

    $('#analytics-content, #analytics-empty, #analytics-error').addClass('hidden');
    $('#analytics-loading').removeClass('hidden');

    ajax({ url: ROUTES.blockAnalytics(blockId), method: 'GET' })
        .done((res) => {
            analyticsState.cache[blockId] = res;
            if (state.selectedBlockId === blockId) renderBlockAnalytics(res);
        })
        .fail(() => {
            if (state.selectedBlockId === blockId) {
                $('#analytics-loading, #analytics-content, #analytics-empty').addClass('hidden');
                $('#analytics-error').removeClass('hidden');
            }
        });
}

function renderBlockAnalytics(res) {
    $('#analytics-loading, #analytics-error').addClass('hidden');

    const totals = res.totals || {};
    if (!totals.impressions && !totals.clicks) {
        $('#analytics-content').addClass('hidden');
        $('#analytics-empty').removeClass('hidden');
        return;
    }
    $('#analytics-empty').addClass('hidden');
    $('#analytics-content').removeClass('hidden');

    $('#analytics-stat-impressions').text(formatCompactNumber(totals.impressions));
    $('#analytics-stat-clicks').text(formatCompactNumber(totals.clicks));
    $('#analytics-stat-ctr').text(`${(Number(totals.ctr || 0) * 100).toFixed(2)}%`);
    $('#analytics-stat-add-to-cart').text(formatCompactNumber(totals.add_to_cart_count));
    $('#analytics-stat-orders').text(formatCompactNumber(totals.orders_attributed));
    $('#analytics-stat-revenue').text(formatMoney(totals.revenue_attributed));

    const targets = res.top_click_targets || [];
    const $list = $('#analytics-top-targets');
    $list.empty();
    if (!targets.length) {
        $list.append(`<li class="text-gray-400">${window.TRANSLATIONS?.analyticsNoData || 'No analytics recorded for this block in the selected range.'}</li>`);
    } else {
        targets.forEach((t) => {
            $list.append(`
                <li class="flex items-center justify-between gap-2 py-1 border-b border-gray-100 last:border-0">
                    <span class="truncate text-gray-700" title="${escapeHtml(t.click_target)}">${escapeHtml(t.click_target)}</span>
                    <span class="flex-shrink-0 text-gray-400">${escapeHtml(t.click_target_type)} · ${formatCompactNumber(t.count)}</span>
                </li>
            `);
        });
    }

    renderAnalyticsSparkline(res.chart || []);
}

function renderAnalyticsSparkline(chart) {
    const canvas = document.getElementById('analytics-sparkline');
    if (!canvas) return;

    if (analyticsState.chart) {
        analyticsState.chart.destroy();
        analyticsState.chart = null;
    }

    import('chart.js/auto').then(({ default: Chart }) => {
        analyticsState.chart = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: chart.map((d) => d.date),
                datasets: [
                    {
                        label: window.TRANSLATIONS?.analyticsImpressions || 'Impressions',
                        data: chart.map((d) => d.impressions),
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.08)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 0,
                        borderWidth: 1.5,
                    },
                    {
                        label: window.TRANSLATIONS?.analyticsClicks || 'Clicks',
                        data: chart.map((d) => d.clicks),
                        borderColor: '#10b981',
                        fill: false,
                        tension: 0.3,
                        pointRadius: 0,
                        borderWidth: 1.5,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
                scales: { x: { display: false }, y: { display: false } },
                elements: { point: { radius: 0 } },
            },
        });
    });
}

function formatCompactNumber(value) {
    const n = Number(value || 0);
    return new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 }).format(n);
}

function formatMoney(amount) {
    // Blocks can span multiple countries/currencies, so we show a plain compact
    // number (major units) rather than assuming a single currency symbol.
    return new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 })
        .format(Number(amount || 0));
}

function closeConfigPanel() {
    state.selectedBlockId = null;
    $('.block-card').removeClass('is-selected');
    $('#config-panel').removeClass('flex').addClass('hidden');
    $('#config-empty').removeClass('hidden');
    $('#config-form-body').empty();
    resetAnalyticsTab();
}

$('#close-config-btn').on('click', closeConfigPanel);

/* ─── Config panel ──────────────────────────────────────────────────────── */
function openConfigPanel(blockId) {
    $('#config-empty').addClass('hidden');
    $('#config-panel').removeClass('hidden').addClass('flex');
    $('#config-form-body').html(`<div class="text-sm text-gray-400 text-center py-8">${t('admin.page_builder.loading')}</div>`);
    $('#config-save-status').text('');

    const $card = $(`.block-card[data-block-id="${blockId}"]`);
    const blockType = $card.find('[data-preview]').length ? null : null;

    // Two parallel requests: the form HTML + the current config values.
    $.when(
        ajax({ url: ROUTES.configForm, method: 'GET', data: { block_id: blockId, block_type_code: getBlockTypeOf(blockId) } }),
        ajax({ url: ROUTES.blockConfig(blockId), method: 'GET' })
    ).done((formRes, configRes) => {
        const html = formRes[0];
        const cfg = configRes[0] || {};
        $('#config-title').text(($card.find('.text-sm.font-medium').text() || window.TRANSLATIONS?.blockSettings || 'Block settings').trim());
        $('#config-form-body').html(html);

        // Initialize Alpine on the injected HTML so x-data / x-show / x-model work
        if (window.Alpine) {
            try { window.Alpine.initTree(document.getElementById('config-form-body')); } catch (_) {}
        }

        // Initialize Select2 async-selects (category, flash-sale, brand, vendor pickers)
        if (window.initSelect2) window.initSelect2($('#config-form-body'));

        // Initialize date pickers (flatpickr, async — must come after Alpine init)
        if (window.initDatePickers) window.initDatePickers($('#config-form-body'));

        // Apply saved config values after both Alpine and Select2 have initialized
        Promise.resolve().then(() => {
            applyConfigToForm(cfg);
            if (getBlockTypeOf(blockId) === 'hero_slider') loadSlidesList(blockId);
            if ($('#config-form-body [data-block-products-list]').length) loadPickerList('products', blockId);
            if ($('#config-form-body [data-block-categories-list]').length) loadPickerList('categories', blockId);
            if ($('#config-form-body [data-block-sellers-list]').length) loadPickerList('sellers', blockId);
            if ($('#config-form-body [data-block-brands-list]').length) loadPickerList('brands', blockId);
            if ($('#config-form-body [data-ad-images-panel]').length) loadAdImagesPanel(blockId);
        });
    }).fail(() => {
        $('#config-form-body').html('<div class="text-sm text-rose-600 text-center py-8">Failed to load config form.</div>');
    });
}

/* ─── Manual picker managers: products (product_row), categories (category_pills), sellers (brand_strip) ── */
const PICKER_CONFIG = {
    products: {
        listUrl: ROUTES.blockProducts,
        removeUrl: ROUTES.blockProductRemove,
        reorderUrl: ROUTES.blockProductReorder,
        searchUrl: ROUTES.searchProducts,
        idField: 'product_variant_id',
        reorderPayloadKey: 'products',
        listSelector: '[data-block-products-list]',
        searchInputSelector: '[data-action="search-block-products"]',
        resultsSelector: '[data-block-product-search-results]',
        emptyText: window.TRANSLATIONS?.noProductsYet || 'No products added yet. Search above to add some.',
    },
    categories: {
        listUrl: ROUTES.blockCategories,
        removeUrl: ROUTES.blockCategoryRemove,
        reorderUrl: ROUTES.blockCategoryReorder,
        searchUrl: ROUTES.searchCategories,
        idField: 'category_id',
        reorderPayloadKey: 'categories',
        listSelector: '[data-block-categories-list]',
        searchInputSelector: '[data-action="search-block-categories"]',
        resultsSelector: '[data-block-category-search-results]',
        emptyText: window.TRANSLATIONS?.noCategoriesYet || 'No categories added yet. Search above to add some.',
    },
    sellers: {
        listUrl: ROUTES.blockSellers,
        removeUrl: ROUTES.blockSellerRemove,
        reorderUrl: ROUTES.blockSellerReorder,
        searchUrl: ROUTES.searchVendors,
        idField: 'seller_id',
        reorderPayloadKey: 'sellers',
        listSelector: '[data-block-sellers-list]',
        searchInputSelector: '[data-action="search-block-sellers"]',
        resultsSelector: '[data-block-seller-search-results]',
        emptyText: window.TRANSLATIONS?.noVendorsYet || 'No vendors added yet. Search above to add some.',
    },
    brands: {
        listUrl: ROUTES.blockBrands,
        removeUrl: ROUTES.blockBrandRemove,
        reorderUrl: ROUTES.blockBrandReorder,
        searchUrl: ROUTES.searchBrands,
        idField: 'brand_id',
        reorderPayloadKey: 'brands',
        listSelector: '[data-block-brands-list]',
        searchInputSelector: '[data-action="search-block-brands"]',
        resultsSelector: '[data-block-brand-search-results]',
        emptyText: window.TRANSLATIONS?.noBrandsYet || 'No brands added yet. Search above to add some.',
    },
};

function loadPickerList(kind, blockId) {
    const cfg = PICKER_CONFIG[kind];
    const $container = $(`#config-form-body ${cfg.listSelector}[data-block-id="${blockId}"]`);
    if (!$container.length) return;

    ajax({ url: cfg.listUrl(blockId), method: 'GET' }).done((res) => {
        const rows = res.results || [];
        if (!rows.length) {
            $container.html(`<div class="text-xs text-gray-400 px-2 py-3 text-center">${escapeHtml(cfg.emptyText)}</div>`);
            return;
        }
        $container.html(rows.map((row) => `
            <div class="flex items-center gap-2 px-2 py-1.5 border border-gray-100 rounded hover:bg-gray-50" data-picker-item-id="${row.id}">
                <span class="drag-handle text-gray-300 cursor-move">⠿</span>
                <span class="flex-1 truncate text-sm text-gray-700">${escapeHtml(row.text || '')}</span>
                <button type="button" class="text-xs text-rose-500 hover:text-rose-700" data-action="remove-picker-item" data-kind="${kind}" data-item-id="${row.id}" data-block-id="${blockId}">Remove</button>
            </div>
        `).join(''));

        if (window.Sortable) {
            Sortable.create($container[0], {
                handle: '.drag-handle',
                animation: 150,
                onEnd: () => {
                    const ordered = $container.find('[data-picker-item-id]').map(function (i) {
                        return { id: $(this).data('picker-item-id'), position: i };
                    }).get();
                    ajax({
                        url: cfg.reorderUrl(blockId), method: 'POST',
                        data: JSON.stringify({ [cfg.reorderPayloadKey]: ordered }), contentType: 'application/json',
                    });
                },
            });
        }
    });
}

$(document).on('input', Object.values(PICKER_CONFIG).map((c) => c.searchInputSelector).join(', '), function () {
    const $input = $(this);
    const kind = Object.keys(PICKER_CONFIG).find((k) => $input.is(PICKER_CONFIG[k].searchInputSelector));
    const cfg = PICKER_CONFIG[kind];
    const blockId = $input.data('block-id');
    const q = $input.val().trim();
    const $results = $(`#config-form-body ${cfg.resultsSelector}[data-block-id="${blockId}"]`);

    clearTimeout($input.data('searchTimer'));
    if (q.length < 2) { $results.addClass('hidden').empty(); return; }

    $input.data('searchTimer', setTimeout(() => {
        ajax({ url: cfg.searchUrl, method: 'GET', data: { q } }).done((res) => {
            const rows = res.results || [];
            if (!rows.length) {
                $results.removeClass('hidden').html(`<div class="px-3 py-2 text-gray-400">${t('admin.page_builder.no_results')}</div>`);
                return;
            }
            $results.removeClass('hidden').html(rows.map((row) => `
                <button type="button" class="w-full text-left px-3 py-2 hover:bg-gray-50" data-action="add-picker-item" data-kind="${kind}" data-item-id="${row.id}" data-block-id="${blockId}">
                    ${escapeHtml(row.text || '')}
                </button>
            `).join(''));
        });
    }, 300));
});

$(document).on('click', '[data-action="add-picker-item"]', function () {
    const $btn = $(this);
    const kind = $btn.data('kind');
    const cfg = PICKER_CONFIG[kind];
    const blockId = $btn.data('block-id');
    const itemId = $btn.data('item-id');

    ajax({
        url: cfg.listUrl(blockId), method: 'POST',
        data: JSON.stringify({ [cfg.idField]: itemId }), contentType: 'application/json',
    }).done(() => {
        $(`#config-form-body ${cfg.resultsSelector}[data-block-id="${blockId}"]`).addClass('hidden').empty();
        $(`#config-form-body ${cfg.searchInputSelector}[data-block-id="${blockId}"]`).val('');
        loadPickerList(kind, blockId);
    }).fail((xhr) => Toast.error(xhr.responseJSON?.message || t('admin.page_builder.could_not_add_item')));
});

$(document).on('click', '[data-action="remove-picker-item"]', function () {
    const $btn = $(this);
    const kind = $btn.data('kind');
    const cfg = PICKER_CONFIG[kind];
    const blockId = $btn.data('block-id');
    const itemId = $btn.data('item-id');

    ajax({ url: cfg.removeUrl(itemId), method: 'DELETE' }).done(() => {
        loadPickerList(kind, blockId);
    }).fail(() => Toast.error(t('admin.page_builder.could_not_remove_item')));
});

function getBlockTypeOf(blockId) {
    const $card = $(`.block-card[data-block-id="${blockId}"]`);
    return $card.attr('data-block-type') || '';
}

function inferBlockTypeFromAttrs($card) {
    return $card.attr('data-block-type') || '';
}

function applyConfigToForm(cfg) {
    const $form = $('#config-form-body form[data-config-form]');
    if (!$form.length) return;

    // Apply server-rendered data-selected-value for slot-based selects (before config override)
    $form.find('select[data-selected-value]').each(function () {
        $(this).val($(this).data('selected-value'));
    });

    Object.entries(cfg.config || {}).forEach(([key, val]) => {
        const $f = $form.find(`[name="${key}"]`);
        if (!$f.length) return;
        if ($f.is(':checkbox')) $f.prop('checked', !!val);
        else $f.val(val);
    });

    // Visibility section (prefixed)
    $form.find('[name="__vis_is_visible"]').prop('checked', !!cfg.is_visible);
    $form.find('[name="__vis_visible_from"]').val(cfg.visible_from || '');
    $form.find('[name="__vis_visible_until"]').val(cfg.visible_until || '');
    $form.find('[name="__vis_device_target"]').val(cfg.device_target || 'all');
    $form.find('[name="__vis_audience"]').val(cfg.audience || 'all');
}

/* ─── Clear schedule date buttons ───────────────────────────────────────── */
$(document).on('click', '[data-clear-date]', function () {
    const fieldName = $(this).data('clear-date');
    const $input = $(`[name="${fieldName}"]`);
    if (!$input.length) return;

    // Clear via flatpickr API if instance exists, else clear the value directly
    const fp = $input[0]?._flatpickr;
    if (fp) {
        fp.clear();
    } else {
        $input.val('');
        $input[0]?.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // Hide this clear button until a new date is picked
    $(this).addClass('hidden');
});

// Show the clear button whenever a date is picked for a scheduled field
$(document).on('change', '[name="__vis_visible_from"], [name="__vis_visible_until"]', function () {
    const $btn = $(`[data-clear-date="${$(this).attr('name')}"]`);
    if ($(this).val()) {
        $btn.removeClass('hidden');
    } else {
        $btn.addClass('hidden');
    }
});

/* ─── Auto-save on config change ────────────────────────────────────────── */
$(document).on('input change', '#config-form-body form[data-config-form] :input', function () {
    if (!state.selectedBlockId) return;
    clearTimeout(state.autoSaveTimer);
    $('#config-save-status').text(window.TRANSLATIONS?.saving || 'Saving…').removeClass('text-emerald-600 text-rose-600').addClass('text-blue-600');
    state.autoSaveTimer = setTimeout(saveConfig, 700);
});

function saveConfig() {
    const blockId = state.selectedBlockId;
    if (!blockId) return;

    const $form = $('#config-form-body form[data-config-form]');
    if (!$form.length) return;

    const { config, visibility } = collectFormData($form);

    // Save config
    ajax({
        url: ROUTES.blockConfig(blockId),
        method: 'POST',
        data: JSON.stringify({ config, change_type: 'config_updated' }),
        contentType: 'application/json',
    }).done((res) => {
        $('#config-save-status').text(t('admin.page_builder.saved_revision', { revision: res.revision_number })).removeClass('text-blue-600 text-rose-600').addClass('text-emerald-600');
        const $card = $(`.block-card[data-block-id="${blockId}"]`);
        if (res.preview_text != null) $card.find('[data-preview]').text(res.preview_text);
    }).fail(() => {
        $('#config-save-status').text(window.TRANSLATIONS?.saveFailed || 'Save failed').removeClass('text-blue-600 text-emerald-600').addClass('text-rose-600');
    });

    // Save visibility separately (different endpoint)
    ajax({
        url: ROUTES.blockVisibility(blockId),
        method: 'POST',
        data: visibility,
    });
}

function collectFormData($form) {
    const config = {};
    const visibility = {};
    $form.find(':input[name]').each(function () {
        const $f = $(this);
        const name = $f.attr('name');
        if (!name || name === '_token') return;
        let val;
        if ($f.is(':checkbox')) val = $f.is(':checked') ? 1 : 0;
        else val = $f.val();

        if (name === '__raw_json') {
            try { Object.assign(config, JSON.parse(val || '{}')); } catch (e) { /* ignore */ }
            return;
        }
        if (name.startsWith('__vis_')) {
            visibility[name.replace('__vis_', '')] = val;
            return;
        }
        setNestedValue(config, name, val);
    });
    // Convert booleans for visibility
    if ('is_visible' in visibility) visibility.is_visible = !!Number(visibility.is_visible);
    return { config, visibility };
}

// Parses bracket-notation field names like "tiles[0][label_en]" into nested
// arrays/objects on `target`, e.g. target.tiles[0].label_en = val.
function setNestedValue(target, name, val) {
    const keys = [];
    const re = /^[^\[\]]+|\[[^\[\]]*\]/g;
    let m;
    while ((m = re.exec(name))) {
        keys.push(m[0].startsWith('[') ? m[0].slice(1, -1) : m[0]);
    }
    if (keys.length <= 1) {
        target[name] = val;
        return;
    }
    let cursor = target;
    for (let i = 0; i < keys.length; i++) {
        const key = keys[i];
        const isLast = i === keys.length - 1;
        if (isLast) {
            cursor[key] = val;
        } else {
            const nextKey = keys[i + 1];
            const nextIsArray = nextKey === '' || /^\d+$/.test(nextKey);
            if (typeof cursor[key] !== 'object' || cursor[key] === null) {
                cursor[key] = nextIsArray ? [] : {};
            }
            cursor = cursor[key];
        }
    }
}

/* ─── Slides ────────────────────────────────────────────────────────────── */
function loadSlidesList(blockId) {
    const $container = $(`[data-slides-list][data-block-id="${blockId}"]`);
    if (!$container.length) return;

    ajax({ url: ROUTES.slides(blockId), method: 'GET' })
        .done((res) => {
            const slides = res.slides || [];
            if (!slides.length) {
                $container.html(`<div class="text-xs text-gray-400 px-2 py-3 text-center">${t('admin.page_builder.no_slides_yet')}</div>`);
                return;
            }
            const html = slides.map((s) => `
                <div class="flex items-center gap-2 px-2 py-1.5 border border-gray-100 rounded hover:bg-gray-50" data-slide-id="${s.id}">
                    <span class="text-xs text-gray-400">#${s.position + 1}</span>
                    <span class="flex-1 truncate text-sm text-gray-700">${escapeHtml(s.title_en || s.cta_label_en || 'Slide')}</span>
                    <button type="button" class="text-xs text-gray-500 hover:text-gray-900" data-action="edit-slide" data-slide-id="${s.id}" data-block-id="${blockId}">${t('admin.page_builder.edit_label')}</button>
                    <button type="button" class="text-xs text-rose-500 hover:text-rose-700" data-action="delete-slide" data-slide-id="${s.id}">${t('admin.page_builder.delete_label')}</button>
                </div>
            `).join('');
            $container.html(html);
        });
}

$(document).on('click', '[data-action="add-slide"]', function () {
    const blockId = $(this).data('block-id');
    openSlideModal(blockId, null, {});
});

$(document).on('click', '[data-action="edit-slide"]', function () {
    const blockId = $(this).data('block-id');
    const slideId = $(this).data('slide-id');
    // Re-fetch this slide's full data from the slides list response (cached lazily)
    ajax({ url: ROUTES.slides(blockId), method: 'GET' }).done((res) => {
        const slide = (res.slides || []).find((s) => s.id === slideId);
        openSlideModal(blockId, slideId, slide || {});
    });
});

$(document).on('click', '[data-action="delete-slide"]', async function () {
    const slideId = $(this).data('slide-id');
    const ok = await window.confirmDialog({
        title: window.TRANSLATIONS?.deleteSlideTitle || 'Delete slide?', message: window.TRANSLATIONS?.deleteSlideMessage || 'This slide will be removed.', confirmLabel: window.TRANSLATIONS?.delete || 'Delete', danger: true,
    });
    if (!ok) return;
    ajax({ url: ROUTES.slideDelete(slideId), method: 'DELETE' })
        .done(() => { Toast.success(window.TRANSLATIONS?.slideDeleted || 'Slide deleted.'); loadSlidesList(state.selectedBlockId); })
        .fail(() => Toast.error(window.TRANSLATIONS?.couldNotDeleteSlide || 'Could not delete slide.'));
});

function setSlideImagePreview(slot, fileId, url) {
    const $hidden = $(`#slide-${slot}-file-id`);
    const $preview = $(`#slide-${slot}-preview`);
    const $img = $(`#slide-${slot}-img`);
    $hidden.val(fileId || '');
    if (url) {
        $img.attr('src', url);
        $preview.removeClass('hidden');
    } else {
        $preview.addClass('hidden');
        $img.attr('src', '');
    }
}

function openSlideModal(blockId, slideId, slide) {
    $('#slide-block-id').val(blockId);
    $('#slide-id').val(slideId || '');
    const $form = $('#slide-form');
    $form[0].reset();

    // Reset image previews
    setSlideImagePreview('desktop', '', '');
    setSlideImagePreview('mobile', '', '');

    Object.entries(slide || {}).forEach(([k, v]) => {
        const $f = $form.find(`[name="${k}"]`);
        if (!$f.length) return;
        if ($f.is(':checkbox')) $f.prop('checked', !!v);
        else if ($f[0]?._flatpickr) $f[0]._flatpickr.setDate(v || '', true);
        else $f.val(v ?? '');
    });

    // Populate image previews when editing existing slide
    if (slide.desktop_file_url) setSlideImagePreview('desktop', slide.desktop_file_id, slide.desktop_file_url);
    if (slide.mobile_file_url) setSlideImagePreview('mobile', slide.mobile_file_id, slide.mobile_file_url);

    $('#slide-modal').modal('open');
}

$(document).on('change', '[data-slide-upload]', function () {
    const slot = $(this).data('slide-upload');
    const file = this.files[0];
    if (!file) return;

    const fd = new FormData();
    fd.append('image', file);
    fd.append('slot', slot);
    fd.append('_token', csrfToken());

    const $label = $(this).closest('label');
    $label.addClass('opacity-50 pointer-events-none');

    $.ajax({
        url: ROUTES.slideUploadImage,
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        headers: { 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
    }).done((res) => {
        setSlideImagePreview(slot, res.file_id, res.url);
        Toast.success(window.TRANSLATIONS?.imageUploaded || 'Image uploaded.');
    }).fail((xhr) => {
        Toast.error(xhr.responseJSON?.message || window.TRANSLATIONS?.uploadFailed || 'Upload failed.');
    }).always(() => {
        $label.removeClass('opacity-50 pointer-events-none');
        this.value = '';
    });
});

$(document).on('click', '[data-clear-image]', function (e) {
    e.preventDefault();
    const slot = $(this).data('clear-image');
    setSlideImagePreview(slot, '', '');
});

function setSectionBackgroundImagePreview(url) {
    $('#sd-background-image-url').val(url || '');
    if (url) {
        $('#sd-background-image-img').attr('src', url);
        $('#sd-background-image-preview').removeClass('hidden');
    } else {
        $('#sd-background-image-preview').addClass('hidden');
        $('#sd-background-image-img').attr('src', '');
    }
}

$(document).on('change', '[data-section-bg-upload]', function () {
    const file = this.files[0];
    if (!file) return;

    const fd = new FormData();
    fd.append('image', file);
    fd.append('_token', csrfToken());

    const $label = $(this).closest('label');
    $label.addClass('opacity-50 pointer-events-none');

    $.ajax({
        url: ROUTES.sectionBackgroundUploadImage,
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        headers: { 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
    }).done((res) => {
        setSectionBackgroundImagePreview(res.url);
        Toast.success(window.TRANSLATIONS?.imageUploaded || 'Image uploaded.');
    }).fail((xhr) => {
        Toast.error(xhr.responseJSON?.message || window.TRANSLATIONS?.uploadFailed || 'Upload failed.');
    }).always(() => {
        $label.removeClass('opacity-50 pointer-events-none');
        this.value = '';
    });
});

$(document).on('click', '[data-clear-section-bg-image]', function (e) {
    e.preventDefault();
    setSectionBackgroundImagePreview('');
});

function setPromoTileImagePreview($row, url) {
    $row.find('[data-tile-image-url]').val(url || '');
    if (url) {
        $row.find('[data-tile-image-img]').attr('src', url);
        $row.find('[data-tile-image-preview]').removeClass('hidden');
    } else {
        $row.find('[data-tile-image-preview]').addClass('hidden');
        $row.find('[data-tile-image-img]').attr('src', '');
    }
}

$(document).on('change', '[data-tile-image-upload]', function () {
    const file = this.files[0];
    if (!file) return;

    const $row = $(this).closest('.tile-row');
    const fd = new FormData();
    fd.append('image', file);
    fd.append('_token', csrfToken());

    const $label = $(this).closest('label');
    $label.addClass('opacity-50 pointer-events-none');

    $.ajax({
        url: ROUTES.promoTileUploadImage,
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        headers: { 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
    }).done((res) => {
        setPromoTileImagePreview($row, res.url);
        Toast.success(window.TRANSLATIONS?.imageUploaded || 'Image uploaded.');
    }).fail((xhr) => {
        Toast.error(xhr.responseJSON?.message || window.TRANSLATIONS?.uploadFailed || 'Upload failed.');
    }).always(() => {
        $label.removeClass('opacity-50 pointer-events-none');
        this.value = '';
    });
});

$(document).on('click', '[data-clear-tile-image]', function (e) {
    e.preventDefault();
    setPromoTileImagePreview($(this).closest('.tile-row'), '');
});

$('#slide-form').on('submit', function (e) {
    e.preventDefault();
    const $form = $(this);
    const blockId = $('#slide-block-id').val();
    const data = {};
    $form.find(':input[name]').each(function () {
        const $f = $(this);
        const name = $f.attr('name');
        if (!name || name === '_token') return;
        if ($f.is(':checkbox')) data[name] = $f.is(':checked') ? 1 : 0;
        else data[name] = $f.val();
    });
    if (!data.id) delete data.id;

    ajax({ url: ROUTES.slideSave(blockId), method: 'POST', data })
        .done(() => {
            Toast.success(window.TRANSLATIONS?.slideSaved || 'Slide saved.');
            $('#slide-modal').modal('close');
            loadSlidesList(blockId);
            // Trigger preview refresh on block card
            $(`.block-card[data-block-id="${blockId}"] [data-preview]`).text(t('admin.page_builder.slider_updated'));
        })
        .fail((xhr) => Toast.error(xhr.responseJSON?.message || window.TRANSLATIONS?.couldNotSaveSlide || 'Could not save slide.'));
});

/* ─── Ad images (ad_images_2col / ad_images_4col) ──────────────────────── */

function loadAdImagesPanel(blockId) {
    const $panel = $(`#config-form-body [data-ad-images-panel][data-block-id="${blockId}"]`);
    if (!$panel.length) return;

    ajax({ url: ROUTES.adImagesManagerPartial(blockId), method: 'GET' })
        .done((html) => {
            $panel.html(html);
            if (window.Alpine) {
                try { window.Alpine.initTree($panel[0]); } catch (_) {}
            }
            loadAdImagesList(blockId);
        })
        .fail(() => Toast.error(window.TRANSLATIONS?.couldNotLoadAdImages || 'Could not load ad images panel.'));
}

function loadAdImagesList(blockId) {
    const $container = $(`[data-ad-images-list][data-block-id="${blockId}"]`);
    if (!$container.length) return;

    ajax({ url: ROUTES.adImages(blockId), method: 'GET' })
        .done((res) => {
            const items = res.items || [];
            if (!items.length) {
                $container.html(`<div class="text-xs text-gray-400 px-2 py-3 text-center">${t('admin.page_builder.no_ad_images_yet')}</div>`);
                return;
            }
            const html = items.map((img) => `
                <div class="flex items-center gap-2 px-2 py-1.5 border border-gray-100 rounded hover:bg-gray-50" data-ad-image-id="${img.id}">
                    ${img.file_url
                        ? `<img src="${img.file_url}" alt="" class="w-10 h-8 object-cover rounded border border-gray-200 flex-shrink-0">`
                        : `<div class="w-10 h-8 bg-gray-100 rounded border border-gray-200 flex-shrink-0"></div>`}
                    <div class="flex-1 min-w-0">
                        <p class="truncate text-sm text-gray-700">${escapeHtml(img.title_en || img.link_url || 'Image')}</p>
                        ${img.subtitle_en ? `<p class="truncate text-xs text-gray-400">${escapeHtml(img.subtitle_en)}</p>` : ''}
                        ${img.badge_label_en ? `<span class="inline-block text-xs bg-gray-900 text-white px-1.5 py-0.5 rounded">${escapeHtml(img.badge_label_en)}</span>` : ''}
                    </div>
                    <button type="button" class="text-xs text-gray-500 hover:text-gray-900" data-action="edit-ad-image" data-image-id="${img.id}" data-block-id="${blockId}">${t('admin.page_builder.edit_label')}</button>
                    <button type="button" class="text-xs text-rose-500 hover:text-rose-700" data-action="delete-ad-image" data-image-id="${img.id}">${t('admin.page_builder.delete_label')}</button>
                </div>
            `).join('');
            $container.html(html);
        });
}

$(document).on('click', '[data-action="add-ad-image"]', function () {
    const blockId = $(this).data('block-id');
    openAdImageModal(blockId, null, {});
});

$(document).on('click', '[data-action="edit-ad-image"]', function () {
    const blockId = $(this).data('block-id');
    const imageId = $(this).data('image-id');
    ajax({ url: ROUTES.adImages(blockId), method: 'GET' }).done((res) => {
        const img = (res.items || []).find((i) => i.id === imageId);
        openAdImageModal(blockId, imageId, img || {});
    });
});

$(document).on('click', '[data-action="delete-ad-image"]', async function () {
    const imageId = $(this).data('image-id');
    const blockId = $(this).closest('[data-ad-images-list]').data('block-id');
    const ok = await window.confirmDialog({
        title: window.TRANSLATIONS?.deleteSlideTitle || 'Delete image?', message: window.TRANSLATIONS?.deleteSlideMessage || 'This image will be removed.', confirmLabel: window.TRANSLATIONS?.delete || 'Delete', danger: true,
    });
    if (!ok) return;
    ajax({ url: ROUTES.adImageDelete(imageId), method: 'DELETE' })
        .done(() => { Toast.success(window.TRANSLATIONS?.imageUploaded ? '' : 'Image deleted.'); loadAdImagesList(blockId); })
        .fail(() => Toast.error('Could not delete image.'));
});

function setAdImagePreview(fileId, url) {
    $('#ad-image-file-id').val(fileId || '');
    if (url) {
        $('#ad-image-preview-img').attr('src', url);
        $('#ad-image-preview').removeClass('hidden');
    } else {
        $('#ad-image-preview').addClass('hidden');
        $('#ad-image-preview-img').attr('src', '');
    }
}

function openAdImageModal(blockId, imageId, img) {
    $('#ad-image-block-id').val(blockId);
    $('#ad-image-id').val(imageId || '');
    const $form = $('#ad-image-form');
    $form[0].reset();
    setAdImagePreview('', '');

    Object.entries(img || {}).forEach(([k, v]) => {
        const $f = $form.find(`[name="${k}"]`);
        if (!$f.length) return;
        if ($f.is(':checkbox')) $f.prop('checked', !!v);
        else $f.val(v ?? '');
    });

    if (img?.file_url) setAdImagePreview(img.file_id, img.file_url);

    $('#ad-image-modal').modal('open');
}

$(document).on('change', '[data-ad-image-upload]', function () {
    const file = this.files[0];
    if (!file) return;

    const fd = new FormData();
    fd.append('image', file);
    fd.append('_token', csrfToken());

    const $label = $(this).closest('label');
    $label.addClass('opacity-50 pointer-events-none');

    $.ajax({
        url: ROUTES.adImageUploadImage,
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        headers: { 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
    }).done((res) => {
        setAdImagePreview(res.file_id, res.url);
        Toast.success(window.TRANSLATIONS?.imageUploaded || 'Image uploaded.');
    }).fail((xhr) => {
        Toast.error(xhr.responseJSON?.message || window.TRANSLATIONS?.uploadFailed || 'Upload failed.');
    }).always(() => {
        $label.removeClass('opacity-50 pointer-events-none');
        this.value = '';
    });
});

$(document).on('click', '[data-clear-ad-image]', function (e) {
    e.preventDefault();
    setAdImagePreview('', '');
});

$('#ad-image-form').on('submit', function (e) {
    e.preventDefault();
    const $form = $(this);
    const blockId = $('#ad-image-block-id').val();
    const data = {};
    $form.find(':input[name]').each(function () {
        const $f = $(this);
        const name = $f.attr('name');
        if (!name || name === '_token') return;
        if ($f.is(':checkbox')) data[name] = $f.is(':checked') ? 1 : 0;
        else data[name] = $f.val();
    });
    if (!data.id) delete data.id;

    ajax({ url: ROUTES.adImageSave(blockId), method: 'POST', data })
        .done(() => {
            Toast.success('Image saved.');
            $('#ad-image-modal').modal('close');
            loadAdImagesList(blockId);
            $(`.block-card[data-block-id="${blockId}"] [data-preview]`).text('Ad images — updated');
        })
        .fail((xhr) => Toast.error(xhr.responseJSON?.message || 'Could not save image.'));
});

/* ─── Section drawer ────────────────────────────────────────────────────── */
$('#sd-layout').on('change', function () {
    $('#sd-columns-config-row').toggleClass('hidden', $(this).val() !== 'columns');
});

function resetSectionForm() {
    $('#sd-section-id').val('');
    $('#sd-name').val('');
    $('#sd-layout').val('stack').trigger('change');
    $('#sd-columns-config').val('');
    $('#sd-is-visible').prop('checked', true);
    $('#sd-background-color').val('');
    $('#sd-background-image-type').val('section');
    setSectionBackgroundImagePreview('');
    $('#sd-padding-top').val('');
    $('#sd-padding-bottom').val('');
    $('#sd-max-width').val('');
    $('#sd-delete').addClass('hidden');
}

function openSectionDrawer(section) {
    resetSectionForm();
    state.editingSectionId = section ? section.id : null;

    if (section) {
        $('#sd-title').text('Edit section');
        $('#sd-section-id').val(section.id);
        $('#sd-name').val(section.name || '');
        $('#sd-layout').val(section.layout || 'stack').trigger('change');
        if (section.columns_config) {
            const cfg = typeof section.columns_config === 'string'
                ? JSON.parse(section.columns_config)
                : section.columns_config;

            // Never use JSON strings in CSS attribute selectors — iterate options directly
            let matched = false;
            $('#sd-columns-config option').each(function () {
                try {
                    const opt = JSON.parse($(this).val());
                    if (opt.columns === cfg.columns && opt.widths === cfg.widths) {
                        $('#sd-columns-config').val($(this).val());
                        matched = true;
                        return false; // break
                    }
                } catch (_) {}
            });
            if (!matched) {
                $('#sd-columns-config').val('');
            }
        } else {
            $('#sd-columns-config').val('');
        }
        $('#sd-is-visible').prop('checked', section.is_visible !== false);
        $('#sd-background-color').val(section.background_color || '');
        $('#sd-background-image-type').val(section.background_image_type || 'section');
        setSectionBackgroundImagePreview(section.background_image_url || '');
        $('#sd-padding-top').val(section.padding_top ?? '');
        $('#sd-padding-bottom').val(section.padding_bottom ?? '');
        $('#sd-max-width').val(section.max_width || '');
        $('#sd-delete').removeClass('hidden');
    } else {
        $('#sd-title').text('Add section');
        $('#sd-name').val('New Section');
    }

    window.dispatchEvent(new CustomEvent('open-section-drawer'));
}

$('#add-section-btn').on('click', function () {
    if (!state.currentPageId) {
        Toast.warning(window.TRANSLATIONS?.selectOrCreatePageFirst || 'Select or create a page first.');
        return;
    }
    openSectionDrawer(null);
});

$(document).on('click', '[data-action="edit-section"]', function () {
    const sectionId = $(this).data('section-id');
    const section = state.sections.find((s) => s.id === sectionId);
    if (section) openSectionDrawer(section);
});

$(document).on('click', '[data-action="delete-section"]', async function () {
    const sectionId = $(this).data('section-id');
    const ok = await window.confirmDialog({
        title: 'Delete section?',
        message: 'The section will be removed. Its blocks will not be deleted — they will become ungrouped.',
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;

    ajax({ url: ROUTES.sectionDelete(sectionId), method: 'DELETE' })
        .done(() => {
            Toast.success('Section deleted.');
            loadPage(state.currentPageId);
        })
        .fail((xhr) => Toast.error(xhr.responseJSON?.message || 'Could not delete section.'));
});

$('#sd-delete').on('click', async function () {
    const sectionId = $('#sd-section-id').val();
    if (!sectionId) return;
    const ok = await window.confirmDialog({
        title: 'Delete section?',
        message: 'The section will be removed. Its blocks will not be deleted — they will become ungrouped.',
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;

    ajax({ url: ROUTES.sectionDelete(sectionId), method: 'DELETE' })
        .done(() => {
            Toast.success('Section deleted.');
            window.dispatchEvent(new CustomEvent('close-section-drawer'));
            loadPage(state.currentPageId);
        })
        .fail((xhr) => Toast.error(xhr.responseJSON?.message || 'Could not delete section.'));
});

$('#sd-save').on('click', function () {
    if (!state.currentPageId) return;

    const sectionId = $('#sd-section-id').val();
    const payload = {
        name: $('#sd-name').val() || null,
        is_visible: $('#sd-is-visible').is(':checked') ? 1 : 0,
        background_color: $('#sd-background-color').val() || null,
        background_image_url: $('#sd-background-image-url').val() || null,
        background_image_type: $('#sd-background-image-type').val() || 'section',
        padding_top: $('#sd-padding-top').val() || null,
        padding_bottom: $('#sd-padding-bottom').val() || null,
        max_width: $('#sd-max-width').val() || null,
        layout: $('#sd-layout').val(),
        columns_config: $('#sd-layout').val() === 'columns' ? $('#sd-columns-config').val() : null,
    };

    const $btn = $(this);
    let request;
    if (sectionId) {
        request = ajax({ url: ROUTES.sectionUpdate(sectionId), method: 'PUT', data: payload });
    } else {
        payload.page_id = state.currentPageId;
        payload.position = state.sections.length;
        request = ajax({ url: ROUTES.sections, method: 'POST', data: payload });
    }

    withLoading($btn, request).then(() => {
        Toast.success(sectionId ? 'Section updated.' : 'Section added.');
        window.dispatchEvent(new CustomEvent('close-section-drawer'));
        loadPage(state.currentPageId);
    }).catch((xhr) => {
        const errs = xhr.responseJSON?.errors || {};
        const first = Object.values(errs)[0]?.[0] || xhr.responseJSON?.message || 'Could not save section.';
        Toast.error(first);
    });
});

/* ─── Page creation ─────────────────────────────────────────────────────── */
$('#create-page-btn').on('click', () => $('#create-page-modal').modal('open'));

function toggleReferenceField() {
    const type = $('#page_type').val();
    $('[data-reference-field]').addClass('hidden');
    $(`[data-reference-field="${type}"]`).removeClass('hidden');
}

$('#page_type').on('change', toggleReferenceField);
$('#create-page-modal').on('modal:opened', toggleReferenceField);

$('#create-page-form').on('submit', function (e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(this).entries());

    const type = data.page_type;
    data.reference_id = type ? data[`reference_${type}_id`] || null : null;
    delete data.reference_category_id;
    delete data.reference_brand_id;
    delete data.reference_vendor_id;

    ajax({ url: ROUTES.pages, method: 'POST', data })
        .done((res) => {
            Toast.success(window.TRANSLATIONS?.pageCreated || 'Page created.');
            $('#create-page-modal').modal('close');

            const p = res.page;
            const option = new Option(`${p.name} (${p.page_type})`, p.id, true, true);
            $('#page-select').append(option).val(p.id).trigger('change');
        })
        .fail((xhr) => {
            const errs = xhr.responseJSON?.errors || {};
            const first = Object.values(errs)[0]?.[0] || xhr.responseJSON?.message || window.TRANSLATIONS?.couldNotCreatePage || 'Could not create page.';
            Toast.error(first);
        });
});

/* ─── Page selection / publish / preview / history ──────────────────────── */
$('#page-select').on('change', function () {
    loadPage($(this).val());
});

$('#publish-btn').on('click', async function () {
    if (!state.currentPageId) return;
    const ok = await window.confirmDialog({
        title: window.TRANSLATIONS?.publishPageTitle || 'Publish page?',
        message: window.TRANSLATIONS?.publishPageMessage || 'This will make the current draft live. A new version snapshot will be created.',
        confirmLabel: window.TRANSLATIONS?.publish || 'Publish',
    });
    if (!ok) return;

    const $btn = $(this);
    withLoading($btn, ajax({
        url: ROUTES.publish(state.currentPageId), method: 'POST',
        data: { reason: 'Published from page builder' },
    }).done(() => Toast.success(window.TRANSLATIONS?.pagePublished || 'Page published.'))
        .fail((xhr) => Toast.error(xhr.responseJSON?.message || window.TRANSLATIONS?.couldNotPublish || 'Could not publish.')));
});

$('#clear-page-cache-btn').on('click', function () {
    if (!state.currentPageId) return;

    ajax({
        url: ROUTES.clearPageCache(state.currentPageId),
        method: 'POST',
    })
        .done(() => Toast.success('Cache cleared — changes are live.'))
        .fail(() => Toast.error('Failed to clear cache.'));
});

$('#version-history-btn').on('click', function () {
    if (!state.currentPageId) return;
    window.dispatchEvent(new CustomEvent('open-version-drawer'));
    ajax({ url: ROUTES.pageRevisions(state.currentPageId), method: 'GET' })
        .done((res) => {
            const drawerEl = document.getElementById('version-drawer');
            if (drawerEl && drawerEl._x_dataStack) {
                const data = drawerEl._x_dataStack[0];
                data.revisions = (res.data || []).map((r) => ({
                    id: r.id, version: r.version,
                    publish_reason: r.reason,
                    published_by: r.published_by,
                    created_at: r.created_at,
                }));
                data.loading = false;
            }
        });
});

$(document).on('click', '[data-action="restore-page-revision"]', async function () {
    const revId = $(this).data('revision-id');
    const ok = await window.confirmDialog({
        title: window.TRANSLATIONS?.restoreVersionTitle || 'Restore this version?',
        message: window.TRANSLATIONS?.restoreVersionMessage || 'The current page blocks will be replaced with the selected version. A new revision snapshot will be created.',
        confirmLabel: window.TRANSLATIONS?.restore || 'Restore',
        danger: true,
    });
    if (!ok) return;

    ajax({ url: ROUTES.pageRevRestore(revId), method: 'POST' })
        .done(() => { Toast.success(window.TRANSLATIONS?.versionRestored || 'Version restored.'); loadPage(state.currentPageId); })
        .fail((xhr) => Toast.error(xhr.responseJSON?.message || window.TRANSLATIONS?.couldNotRestore || 'Could not restore.'));
});

/* ─── Boot ──────────────────────────────────────────────────────────────── */
$(function () {
    setSaveStatus('');
});
