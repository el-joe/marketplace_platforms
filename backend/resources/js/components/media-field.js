/**
 * media-field.js — behaviour for <x-form.media-field> (client item #27).
 *
 * All handlers are delegated on `document`, so fields injected later via AJAX
 * (page-builder config forms, modals) work without explicit init.
 *
 * Public API (window.MediaField):
 *   setValue(input, value, url, { silent })  – set one slot by its hidden input (element / selector / jQuery)
 *   load(root, { slotKey: { value, url } })  – fill a whole field (e.g. when a modal opens) and recompute "same for all"
 *   clear(root)                              – empty every slot
 *   refresh(scope)                           – re-render previews from hidden values (url-mode fields)
 */
import $ from 'jquery';

const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];
const ALLOWED_EXT = /\.(jpe?g|png|webp|gif|avif)$/i;

const toEl = (x) => (x && x.jquery ? x[0] : (typeof x === 'string' ? document.querySelector(x) : x));
const rootOf = (el) => el?.closest('[data-media-field]');
const slotOf = (el) => el?.closest('[data-media-slot]');
const slotsOf = (root) => Array.from(root.querySelectorAll('[data-media-slot]'));
const i18n = (root) => {
    if (!root._i18n) {
        try { root._i18n = JSON.parse(root.dataset.i18n || '{}'); } catch (_) { root._i18n = {}; }
    }
    return root._i18n;
};
const sameOn = (root) => !!root.querySelector('[data-media-same]')?.checked;
const toast = (kind, msg) => (window.Toast?.[kind] ? window.Toast[kind](msg) : null);

function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return '';
    if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function showError(slot, message) {
    const el = slot.querySelector('[data-media-error]');
    if (!el) return;
    el.textContent = message || '';
    el.classList.toggle('hidden', !message);
}

function setMeta(slot, text) {
    const el = slot.querySelector('[data-media-meta]');
    if (!el) return;
    el.textContent = text || '';
    el.classList.toggle('hidden', !text);
}

/** Render one slot (no events, no propagation). */
function paint(slot, value, url) {
    const input = slot.querySelector('[data-media-value]');
    const img = slot.querySelector('[data-media-preview]');
    const has = !!url;
    if (input) input.value = value ?? '';
    if (img) {
        if (has) img.setAttribute('src', url); else img.removeAttribute('src');
        img.classList.toggle('hidden', !has);
    }
    slot.querySelector('[data-media-empty]')?.classList.toggle('hidden', has);
    slot.querySelector('[data-media-has-image]')?.classList.toggle('hidden', !has);
    slot.querySelector('[data-media-no-image]')?.classList.toggle('hidden', has);
    slot.querySelector('[data-media-action="remove"]')?.classList.toggle('hidden', !has);
    const urlInput = slot.querySelector('[data-media-url-input]');
    const root = rootOf(slot);
    if (urlInput && root?.dataset.store === 'url') urlInput.value = value ?? '';
    slot.dataset.previewUrl = url || '';
    if (!has) setMeta(slot, '');
}

function emitChange(slot) {
    const input = slot.querySelector('[data-media-value]');
    if (input) input.dispatchEvent(new Event('change', { bubbles: true }));
}

/** Set a slot; when "same for all" is on and this is the first slot, copy to the others. */
function setSlot(slot, value, url, { silent = false } = {}) {
    const root = rootOf(slot);
    paint(slot, value, url);
    showError(slot, '');
    const targets = [slot];
    if (root && sameOn(root)) {
        const [primary, ...rest] = slotsOf(root);
        if (primary === slot) {
            rest.forEach((other) => { paint(other, value, url); targets.push(other); });
        }
    }
    if (!silent) targets.forEach(emitChange);
}

function applySameMode(root) {
    const on = sameOn(root);
    const [primary, ...rest] = slotsOf(root);
    rest.forEach((s) => s.classList.toggle('hidden', on));
    // While merged the first slot stands for all of them — drop its device/locale label.
    primary?.classList.toggle('col-span-full', on);
    const label = primary?.querySelector('[data-media-slot-label]');
    if (label) label.classList.toggle('invisible', on);
}

function recomputeSame(root) {
    const toggle = root.querySelector('[data-media-same]');
    if (!toggle) return;
    const values = slotsOf(root).map((s) => s.querySelector('[data-media-value]')?.value || '');
    const filled = values.filter(Boolean);
    toggle.checked = filled.length === 0 || (filled.length === values.length && new Set(filled).size === 1);
    applySameMode(root);
}

function validate(root, file) {
    const t = i18n(root);
    const typeOk = ALLOWED_TYPES.includes(file.type) || (!file.type && ALLOWED_EXT.test(file.name));
    if (!typeOk) return t.errorType;
    const maxKb = parseInt(root.dataset.maxKb, 10) || 8192;
    if (file.size > maxKb * 1024) return t.errorSize;
    return null;
}

function progress(slot, percent) {
    const box = slot.querySelector('[data-media-progress]');
    if (!box) return;
    const root = rootOf(slot);
    if (percent === null) { box.classList.add('hidden'); return; }
    box.classList.remove('hidden');
    box.querySelector('[data-media-progress-bar]').style.width = `${percent}%`;
    box.querySelector('[data-media-progress-text]').textContent = (i18n(root).uploading || '… :percent%').replace(':percent', percent);
}

function upload(slot, file) {
    const root = rootOf(slot);
    const t = i18n(root);
    const error = validate(root, file);
    if (error) { showError(slot, error); return; }
    showError(slot, '');

    const fd = new FormData();
    fd.append('image', file);
    fd.append('context', root.dataset.context);
    if (slot.dataset.device) fd.append('device', slot.dataset.device);

    progress(slot, 0);
    $.ajax({
        url: root.dataset.uploadUrl,
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        xhr() {
            const xhr = new window.XMLHttpRequest();
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) progress(slot, Math.round((e.loaded / e.total) * 100));
            });
            return xhr;
        },
    }).done((res) => {
        const value = root.dataset.store === 'id' ? res.file_id : res.url;
        setSlot(slot, value, res.url);
        const dims = res.width && res.height ? ` · ${res.width}×${res.height}` : '';
        setMeta(slot, `${res.name || file.name} · ${formatBytes(res.size ?? file.size)}${dims}`);
        toast('success', t.uploaded);
    }).fail((xhr) => {
        const msg = xhr.status === 403
            ? t.errorForbidden
            : (xhr.responseJSON?.errors?.image?.[0] || xhr.responseJSON?.message || t.errorUpload);
        showError(slot, msg);
    }).always(() => progress(slot, null));
}

/* ─── Library modal (one shared instance) ─────────────────────────────── */
let libraryModal = null;
let libraryState = null;

function ensureLibraryModal() {
    if (libraryModal) return libraryModal;
    libraryModal = document.createElement('div');
    libraryModal.className = 'fixed inset-0 z-[1100] hidden items-center justify-center bg-black/40 p-4';
    libraryModal.setAttribute('role', 'dialog');
    libraryModal.setAttribute('aria-modal', 'true');
    libraryModal.innerHTML = `
        <div class="flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl bg-white shadow-xl">
            <div class="flex items-center gap-3 border-b border-gray-200 px-4 py-3">
                <h3 data-lib-title class="flex-1 text-sm font-semibold text-gray-800"></h3>
                <input type="search" data-lib-search class="w-48 rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm">
                <button type="button" data-lib-close class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700" aria-label="close">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-4">
                <div data-lib-grid class="grid grid-cols-2 gap-3 sm:grid-cols-4"></div>
                <p data-lib-status class="py-6 text-center text-sm text-gray-400"></p>
                <div class="text-center"><button type="button" data-lib-more class="hidden rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"></button></div>
            </div>
        </div>`;
    document.body.appendChild(libraryModal);

    const close = () => { libraryModal.classList.add('hidden'); libraryModal.classList.remove('flex'); libraryState = null; };
    libraryModal.addEventListener('click', (e) => {
        if (e.target === libraryModal || e.target.closest('[data-lib-close]')) close();
        const item = e.target.closest('[data-lib-item]');
        if (item && libraryState) {
            const { slot, root } = libraryState;
            const value = root.dataset.store === 'id' ? item.dataset.id : item.dataset.url;
            setSlot(slot, value, item.dataset.url);
            setMeta(slot, item.dataset.name || '');
            close();
        }
        if (e.target.closest('[data-lib-more]') && libraryState?.nextPage) loadLibrary(libraryState.nextPage);
    });
    let searchTimer = null;
    libraryModal.querySelector('[data-lib-search]').addEventListener('input', (e) => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { if (libraryState) { libraryState.q = e.target.value; loadLibrary(1); } }, 300);
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && libraryState) close(); });
    return libraryModal;
}

function escapeAttr(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function loadLibrary(page) {
    const state = libraryState;
    if (!state) return;
    const t = i18n(state.root);
    const grid = libraryModal.querySelector('[data-lib-grid]');
    const status = libraryModal.querySelector('[data-lib-status]');
    const more = libraryModal.querySelector('[data-lib-more]');
    if (page === 1) grid.innerHTML = '';
    status.textContent = t.libraryLoading;
    status.classList.remove('hidden');
    more.classList.add('hidden');

    $.ajax({ url: state.root.dataset.libraryUrl, method: 'GET', data: { context: state.root.dataset.context, page, q: state.q || '' }, headers: { Accept: 'application/json' } })
        .done((res) => {
            if (libraryState !== state) return;
            const items = res.items || [];
            grid.insertAdjacentHTML('beforeend', items.map((it) => `
                <button type="button" data-lib-item data-id="${escapeAttr(it.id)}" data-url="${escapeAttr(it.url)}" data-name="${escapeAttr(it.name)}"
                    class="group overflow-hidden rounded-lg border border-gray-200 text-start hover:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-300">
                    <span class="block aspect-[4/3] bg-gray-50"><img src="${escapeAttr(it.url)}" alt="" loading="lazy" class="h-full w-full object-contain"></span>
                    <span class="block truncate px-2 py-1 text-[11px] text-gray-500" dir="ltr">${escapeAttr(it.name)} · ${formatBytes(it.size)}</span>
                </button>`).join(''));
            state.nextPage = res.next_page;
            const empty = !grid.children.length;
            status.textContent = empty ? t.libraryEmpty : '';
            status.classList.toggle('hidden', !empty);
            more.textContent = t.libraryLoadMore;
            more.classList.toggle('hidden', !res.next_page);
        })
        .fail(() => { status.textContent = t.libraryFailed; });
}

function openLibrary(slot) {
    const root = rootOf(slot);
    if (!root?.dataset.libraryUrl) return;
    const t = i18n(root);
    ensureLibraryModal();
    libraryState = { slot, root, q: '', nextPage: null };
    libraryModal.querySelector('[data-lib-title]').textContent = t.libraryTitle;
    const search = libraryModal.querySelector('[data-lib-search]');
    search.value = '';
    search.placeholder = t.librarySearch;
    libraryModal.querySelector('[data-lib-close]').setAttribute('aria-label', t.close);
    libraryModal.classList.remove('hidden');
    libraryModal.classList.add('flex');
    loadLibrary(1);
}

/* ─── Delegated events ────────────────────────────────────────────────── */
function bind() {
    if (window.__mediaFieldBound) return;
    window.__mediaFieldBound = true;

    document.addEventListener('click', (e) => {
        const slot = slotOf(e.target);
        if (!slot || !rootOf(slot)) return;
        const action = e.target.closest('[data-media-action]')?.dataset.mediaAction;
        const onDrop = e.target.closest('[data-media-drop]');
        if (!action && !onDrop) return;
        e.preventDefault();

        if (onDrop || action === 'pick') {
            slot.querySelector('[data-media-file]')?.click();
        } else if (action === 'remove') {
            setSlot(slot, '', '');
        } else if (action === 'library') {
            openLibrary(slot);
        } else if (action === 'url') {
            slot.querySelector('[data-media-url-row]')?.classList.toggle('hidden');
            slot.querySelector('[data-media-url-input]')?.focus();
        } else if (action === 'apply-url') {
            const url = (slot.querySelector('[data-media-url-input]')?.value || '').trim();
            if (url && !/^(https?:\/\/|\/)/i.test(url)) { showError(slot, i18n(rootOf(slot)).urlInvalid); return; }
            setSlot(slot, url, url);
            slot.querySelector('[data-media-url-row]')?.classList.add('hidden');
        }
    });

    document.addEventListener('keydown', (e) => {
        const drop = e.target.closest?.('[data-media-drop]');
        if (drop && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            slotOf(drop)?.querySelector('[data-media-file]')?.click();
        }
        if (e.target.matches?.('[data-media-url-input]') && e.key === 'Enter') {
            e.preventDefault();
            slotOf(e.target)?.querySelector('[data-media-action="apply-url"]')?.click();
        }
    });

    document.addEventListener('change', (e) => {
        if (e.target.matches('[data-media-file]')) {
            e.stopPropagation();
            const file = e.target.files?.[0];
            if (file) upload(slotOf(e.target), file);
            e.target.value = '';
        } else if (e.target.matches('[data-media-same]')) {
            e.stopPropagation();
            const root = rootOf(e.target);
            applySameMode(root);
            if (e.target.checked) {
                const [primary] = slotsOf(root);
                const value = primary.querySelector('[data-media-value]').value;
                if (value) setSlot(primary, value, primary.dataset.previewUrl || primary.querySelector('[data-media-preview]')?.getAttribute('src'));
            }
        } else if (e.target.matches('[data-media-url-input]')) {
            e.stopPropagation(); // applied explicitly via "Apply"
        }
    }, true);

    ['dragenter', 'dragover'].forEach((type) => document.addEventListener(type, (e) => {
        const drop = e.target.closest?.('[data-media-drop]');
        if (!drop) return;
        e.preventDefault();
        drop.classList.add('border-primary-500', 'bg-primary-50');
    }));
    ['dragleave', 'drop'].forEach((type) => document.addEventListener(type, (e) => {
        const drop = e.target.closest?.('[data-media-drop]');
        if (!drop) return;
        e.preventDefault();
        drop.classList.remove('border-primary-500', 'bg-primary-50');
        if (type === 'drop') {
            const file = e.dataTransfer?.files?.[0];
            if (file) upload(slotOf(drop), file);
        }
    }));
}

const MediaField = {
    setValue(input, value, url, options = {}) {
        const el = toEl(input);
        const slot = slotOf(el);
        if (slot) setSlot(slot, value, url ?? (rootOf(slot)?.dataset.store === 'url' ? value : ''), options);
    },
    load(root, map = {}) {
        root = toEl(root);
        if (!root) return;
        slotsOf(root).forEach((slot) => {
            const entry = map[slot.dataset.mediaSlot] || {};
            paint(slot, entry.value ?? '', entry.url ?? '');
            showError(slot, '');
            setMeta(slot, '');
            slot.querySelector('[data-media-url-row]')?.classList.add('hidden');
        });
        recomputeSame(root);
    },
    clear(root) { MediaField.load(root, {}); },
    refresh(scope = document) {
        toEl(scope)?.querySelectorAll('[data-media-field]').forEach((root) => {
            if (root.dataset.store === 'url') {
                slotsOf(root).forEach((slot) => {
                    const v = slot.querySelector('[data-media-value]')?.value || '';
                    paint(slot, v, v);
                });
            }
            recomputeSame(root);
        });
    },
};

bind();
window.MediaField = MediaField;

export default MediaField;
