/**
 * resources/js/admin/products.js
 *
 * Product create / edit form JS:
 *   - FilePond image upload (custom server config)
 *   - Category → variant attributes AJAX
 *   - GTIN duplicate check
 *   - Generate variant combinations
 *   - Variant row add / remove
 *   - Character counters
 *   - Countries enable / disable all
 *   - SEO preview live update
 *   - AJAX PUT submit for edit mode
 */

import $ from 'jquery';
import Sortable from 'sortablejs';
window.Sortable = Sortable;
import FilePond, { registerPlugin } from 'filepond';
import FilePondPluginImagePreview from 'filepond-plugin-image-preview';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size';
// Safe re-registration (file-upload.js may already have registered some plugins)
try { registerPlugin(FilePondPluginImagePreview, FilePondPluginFileValidateType, FilePondPluginFileValidateSize); } catch (_) { }

// ─── Helpers ──────────────────────────────────────────────────────────────────

function esc(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(String(str ?? '')));
    return d.innerHTML;
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function isEditMode() {
    return $('#form-mode').val() === 'edit';
}

function parseUploadServerId(response) {
    let payload = response;


    if (typeof response === 'string') {
        try {
            payload = JSON.parse(response);
        } catch (_) {
            payload = response;
        }
    }

    if (payload && typeof payload === 'object') {
        if (typeof payload.id === 'string' && payload.id.length > 0) {
            return payload.id;
        }

        if (Array.isArray(payload.ids) && payload.ids.length > 0 && typeof payload.ids[0] === 'string') {
            return payload.ids[0];
        }

        if (payload.data && typeof payload.data.id === 'string' && payload.data.id.length > 0) {
            return payload.data.id;
        }
    }

    return typeof response === 'string' ? response.trim() : '';
}

// ─── Init ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {
    initCharCounters();
    initGtinCheck();
    initCategoryAttributes();
    initGenerateVariants();
    initVariantTableEvents();
    initCountryToggles();
    initSeoPreview();
    initFilePond();
    initVariantsRequiredGuard();
    initFormSubmit();
    initHighlightRows();
    initSpecificationRows();
});

// ─── Character counters ───────────────────────────────────────────────────────

function initCharCounters() {
    document.querySelectorAll('[data-char-counter]').forEach(function (counter) {
        const fieldId = counter.dataset.charCounter;
        const max = parseInt(counter.dataset.max, 10) || 0;
        const field = document.getElementById(fieldId);
        if (!field) return;

        function update() {
            const len = field.value.length;
            counter.textContent = len + ' / ' + max;
            counter.classList.toggle('text-red-600', max > 0 && len > max * 0.9);
        }

        field.addEventListener('input', update);
        update();
    });
}

// ─── GTIN duplicate check ─────────────────────────────────────────────────────

function initGtinCheck() {
    let gtinTimer;
    $(document).on('input', '#gtin-input', function () {
        clearTimeout(gtinTimer);
        const gtin = $(this).val().trim();
        $('#gtin-warning').addClass('hidden').empty();

        if (gtin.length !== 13) return;

        gtinTimer = setTimeout(function () {
            $.ajax({
                url: document.querySelector('meta[name="check-duplicate-url"]')?.content
                    || window.location.pathname.replace(/\/(create|[^/]+\/edit).*/, '') + '/check-duplicate',
                data: { gtin },
            }).done(function (res) {
                const data = res.data;
                if (!data.exists) return;

                const product = data.product;

                // Show inline warning
                const T = window.TRANSLATIONS || {};
                const $warn = $('#gtin-warning');
                $warn.html(
                    '<strong>' + esc(T.duplicateBarcodePrefix || 'Duplicate barcode:') + '</strong> ' + esc(product.name_en) +
                    ' <span class="text-xs opacity-70">(' + esc(product.status) + ')</span> — ' +
                    '<a href="' + esc(product.url) + '" target="_blank" class="underline font-medium">' + esc(T.viewProduct || 'View product') + '</a>'
                ).removeClass('hidden');

                // Also show Alpine modal
                const root = document.querySelector('[x-data]');
                if (root && root._x_dataStack) {
                    root._x_dataStack[0].duplicateProduct = product;
                    root._x_dataStack[0].showDuplicate = true;
                }
            });
        }, 600);
    });
}

// ─── Category → variant attributes ───────────────────────────────────────────

function initCategoryAttributes() {
    // Use Select2 change event bubbled to hidden input
    $(document).on('change', '[name="category_id"]', function () {
        const catId = $(this).val();
        if (!catId) {
            $('#variant-attributes-container').empty();
            return;
        }

        const url = '/categories/' + encodeURIComponent(catId) + '/attributes';

        $.get(url).done(function (res) {
            const attrs = res.data ?? [];
            const $container = $('#variant-attributes-container');
            $container.empty();

            if (attrs.length === 0) {
                const msg = (window.TRANSLATIONS || {}).noVariantAttrsForCategory || 'No variant attributes for this category.';
                $container.html('<p class="text-sm text-gray-400 italic col-span-3">' + esc(msg) + '</p>');
                return;
            }

            attrs.forEach(function (attr) {
                $container.append(
                    '<label class="flex items-center gap-2 px-3 py-2.5 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 text-sm">' +
                    '<input type="checkbox" name="variant_attributes[]" value="' + esc(attr.id) + '" ' +
                    'class="rounded border-gray-300 text-primary-600 variant-attr-cb" />' +
                    esc(attr.name_en) +
                    '</label>'
                );
            });
        });
    });
}

// ─── Generate variant combinations ───────────────────────────────────────────

function initGenerateVariants() {
    $(document).on('click', '#generate-variants-btn', function () {
        const T = window.TRANSLATIONS || {};
        const attrIds = [];
        document.querySelectorAll('.variant-attr-cb:checked').forEach(function (cb) {
            attrIds.push(cb.value);
        });

        if (attrIds.length === 0) {
            window.Toast && window.Toast.warning(T.selectVariantAttributeFirst || 'Select at least one variant attribute first.');
            return;
        }

        const $btn = $(this).prop('disabled', true).text(T.generatingEllipsis || 'Generating…');

        $.ajax({
            url: window.location.pathname.replace(/\/(create|[^/]+\/edit).*/, '') + '/generate-variants',
            method: 'POST',
            data: { attribute_ids: attrIds },
        })
            .done(function (res) {
                renderVariantRows(res.data ?? []);
            })
            .fail(function () {
                window.Toast && window.Toast.error(T.generateVariantsFailed || 'Failed to generate variants.');
            })
            .always(function () {
                $btn.prop('disabled', false).text(T.generateCombinations || 'Generate combinations');
            });
    });
}

function renderVariantRows(variants) {
    const $tbody = $('#variants-tbody');
    $tbody.empty();
    window.__pendingVariantImages = {};

    if (variants.length === 0) {
        $('#no-variants-msg').removeClass('hidden');
        return;
    }

    $('#no-variants-msg').addClass('hidden');

    const T = window.TRANSLATIONS || {};
    const skuPlaceholder = esc(T.skuAutoGeneratePlaceholder || 'Auto-generate');
    const removeLabel = esc(T.removeLabel || 'Remove');

    variants.forEach(function (v) {
        const i = v.index;
        const row = `
<tr class="variant-row hover:bg-gray-50">
  <td class="px-4 py-3 font-medium text-gray-800">${esc(v.name)}</td>
  <td class="px-4 py-3"><input type="text" name="variants[${i}][sku]" value="${esc(v.sku)}" placeholder="${skuPlaceholder}" class="form-input text-sm py-1.5 w-full" /></td>
  <td class="px-4 py-3"><input type="text" name="variants[${i}][slug]" value="${esc(v.slug || '')}" maxlength="255" class="form-input text-sm py-1.5 w-full variant-slug-input" /></td>
  <td class="px-4 py-3"><input type="text" name="variants[${i}][barcode]" value="${esc(v.barcode)}" class="form-input text-sm py-1.5 w-full" /></td>
  <td class="px-4 py-3"><input type="number" name="variants[${i}][weight_grams]" value="${esc(v.weight_grams)}" min="0" class="form-input text-sm py-1.5 w-full" /></td>
  <td class="px-4 py-3">
    <span class="text-xs text-gray-400 italic">${esc(T.pendingUrlHint || 'Save the product to generate a URL')}</span>
  </td>
  <td class="px-4 py-3 text-center">
    <input type="radio" name="variants_default" value="${i}" class="text-primary-600 border-gray-300 variant-default-radio" ${v.is_default ? 'checked' : ''} />
    <input type="hidden" name="variants[${i}][is_default]" value="${v.is_default ? '1' : '0'}" class="default-flag" />
  </td>
  <td class="px-4 py-3 text-center">
    <input type="checkbox" name="variants[${i}][is_active]" value="1" class="rounded text-primary-600 border-gray-300" ${v.is_active ? 'checked' : ''} />
  </td>
  <td class="px-4 py-3 text-center">
    <button type="button" class="manage-variant-images inline-flex items-center gap-1.5 px-2 py-1 rounded-lg border border-gray-200 text-xs text-gray-600 hover:border-primary-300 hover:text-primary-700 transition-colors"
      data-pending="1" data-variant-index="${i}" data-variant-name="${esc(v.name)}">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3 12v-3a3 3 0 0 1 3-3h3m12 0v3m0-3h-3m-9 0h3m6 0v3m0-3h-3M3 15v3a3 3 0 0 0 3 3h3m12-6v3a3 3 0 0 1-3 3h-3"/>
      </svg>
      <span class="variant-images-count">0</span>
    </button>
    <div class="variant-image-ids-container hidden" data-index="${i}"></div>
  </td>
  <td class="px-4 py-3">
    <button type="button" class="remove-variant-row text-gray-400 hover:text-red-600 transition-colors" title="${removeLabel}">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
      </svg>
    </button>
  </td>
</tr>`;
        $tbody.append(row);
    });
}

// ─── Variant table: row remove + default radio sync ──────────────────────────

function initVariantTableEvents() {
    // Remove row
    $(document).on('click', '.remove-variant-row', function () {
        $(this).closest('tr.variant-row').remove();
        reindexVariantRows();
        if ($('#variants-tbody tr.variant-row').length === 0) {
            $('#no-variants-msg').removeClass('hidden');
        }
    });

    // Sync default radio → hidden is_default flags
    $(document).on('change', '.variant-default-radio', function () {
        $('#variants-tbody .default-flag').val('0');
        $(this).closest('tr').find('.default-flag').val('1');
    });

    // Regenerate slug (AJAX)
    $(document).on('click', '.regenerate-variant-slug', function () {
        const T = window.TRANSLATIONS || {};
        const $btn = $(this).prop('disabled', true);
        const variantId = $btn.data('variant-id');
        const $slugInput = $btn.closest('tr').find('.variant-slug-input');

        const basePath = window.location.pathname.replace(/\/(create|[^/]+\/edit).*/, '');
        if (!variantId) {
            $btn.prop('disabled', false);
            return;
        }

        $.ajax({
            url: basePath + '/variants/' + variantId + '/regenerate-slug',
            method: 'PATCH',
        })
            .done(function (res) {
                if (res.success) {
                    $slugInput.val(res.new_slug);
                    window.Toast && window.Toast.success(T.regenerateSlugSuccess || 'Slug regenerated.');
                }
            })
            .fail(function () {
                window.Toast && window.Toast.error(T.regenerateSlugFailed || 'Failed to regenerate slug.');
            })
            .always(function () {
                $btn.prop('disabled', false);
            });
    });
}

function reindexVariantRows() {
    $('#variants-tbody tr.variant-row').each(function (i) {
        $(this).find('input[name^="variants["]').each(function () {
            this.name = this.name.replace(/variants\[\d+\]/, 'variants[' + i + ']');
        });
        $(this).find('[name="variants_default"]').val(i);
    });
}

// ─── Highlights repeater ──────────────────────────────────────────────────────

function initHighlightRows() {
    $(document).on('click', '#add-highlight-row', function () {
        const i = $('#highlights-rows .highlight-row').length;
        const removeLabel = esc((window.TRANSLATIONS || {}).removeLabel || 'Remove');
        const enLabel = esc((window.TRANSLATIONS || {}).highlightEnLabel || 'Highlight (EN)');
        const arLabel = esc((window.TRANSLATIONS || {}).highlightArLabel || 'Highlight (AR)');
        const enPlaceholder = esc((window.TRANSLATIONS || {}).highlightEnPlaceholder || 'e.g. Water resistant up to 50 meters');
        const arPlaceholder = esc((window.TRANSLATIONS || {}).highlightArPlaceholder || 'مثال: مقاوم للماء حتى 50 متر');
        const row = `
<div class="highlight-row flex gap-3 items-start">
  <input type="hidden" name="highlights[${i}][id]" value="">
  <div class="flex-1">
    <label class="block text-xs text-gray-500 mb-1">${enLabel}</label>
    <input type="text" name="highlights[${i}][text_en]" placeholder="${enPlaceholder}" dir="ltr" maxlength="500" class="form-input text-sm py-1.5 w-full" />
  </div>
  <div class="flex-1">
    <label class="block text-xs text-gray-500 mb-1">${arLabel}</label>
    <input type="text" name="highlights[${i}][text_ar]" placeholder="${arPlaceholder}" dir="rtl" maxlength="500" class="form-input text-sm py-1.5 w-full" />
  </div>
  <button type="button" class="remove-highlight-row mt-5 shrink-0 text-gray-400 hover:text-red-600 transition-colors p-2" title="${removeLabel}">
    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
      <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
    </svg>
  </button>
</div>`;
        $('#highlights-rows').append(row);
    });

    $(document).on('click', '.remove-highlight-row', function () {
        $(this).closest('.highlight-row').remove();
        reindexRows('#highlights-rows', '.highlight-row', 'highlights');
    });
}

// ─── Specifications repeater ─────────────────────────────────────────────────

function initSpecificationRows() {
    $(document).on('click', '#add-specification-row', function () {
        const i = $('#specifications-rows .specification-row').length;
        const removeLabel = esc((window.TRANSLATIONS || {}).removeLabel || 'Remove');
        const row = `
<div class="flex gap-3 items-start specification-row">
  <input type="hidden" name="specifications[${i}][id]" value="">
  <div class="flex-1">
    <label class="block text-xs text-gray-500 mb-1">Key (EN)</label>
    <input type="text" name="specifications[${i}][key_en]" dir="ltr" maxlength="255" placeholder="e.g. Material" class="w-full border-gray-300 rounded-md text-sm" />
  </div>
  <div class="flex-1">
    <label class="block text-xs text-gray-500 mb-1">Key (AR)</label>
    <input type="text" name="specifications[${i}][key_ar]" dir="rtl" maxlength="255" placeholder="مثال: المادة" class="w-full border-gray-300 rounded-md text-sm" />
  </div>
  <div class="flex-1">
    <label class="block text-xs text-gray-500 mb-1">Value (EN)</label>
    <input type="text" name="specifications[${i}][value_en]" dir="ltr" maxlength="500" placeholder="e.g. 100% Cotton" class="w-full border-gray-300 rounded-md text-sm" />
  </div>
  <div class="flex-1">
    <label class="block text-xs text-gray-500 mb-1">Value (AR)</label>
    <input type="text" name="specifications[${i}][value_ar]" dir="rtl" maxlength="500" placeholder="مثال: قطن 100%" class="w-full border-gray-300 rounded-md text-sm" />
  </div>
  <button type="button" class="remove-specification-row mt-5 shrink-0 text-gray-400 hover:text-red-600 transition-colors p-2" title="${removeLabel}">
    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
      <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
    </svg>
  </button>
</div>`;
        $('#specifications-rows').append(row);
    });

    $(document).on('click', '.remove-specification-row', function () {
        $(this).closest('.specification-row').remove();
        reindexRows('#specifications-rows', '.specification-row', 'specifications');
    });
}

function reindexRows(containerSelector, rowSelector, fieldPrefix) {
    $(containerSelector).find(rowSelector).each(function (i) {
        $(this).find('input[name^="' + fieldPrefix + '["]').each(function () {
            this.name = this.name.replace(new RegExp(fieldPrefix.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\[\\d+\\]'), fieldPrefix + '[' + i + ']');
        });
    });
}

// ─── Country enable / disable all ────────────────────────────────────────────

function initCountryToggles() {
    $('#enable-all-countries').on('click', function () {
        setAllCountries(true);
    });

    $('#disable-all-countries').on('click', function () {
        setAllCountries(false);
    });
}

function setAllCountries(enable) {
    document.querySelectorAll('.country-avail-cb').forEach(function (cb) {
        cb.checked = enable;

        // Sync Alpine x-model
        cb.dispatchEvent(new Event('change', { bubbles: true }));
    });
}

// ─── SEO preview live update ──────────────────────────────────────────────────

function initSeoPreview() {
    const T = window.TRANSLATIONS || {};
    const titlePlaceholder = T.productTitlePlaceholder || 'Product title';
    const slugPlaceholder = T.productSlugPlaceholder || 'product-slug';
    const descPlaceholder = T.seoSearchPreviewPlaceholder || 'Add a meta description to improve search engine visibility.';

    $(document).on('input', '#seo_title', function () {
        const val = $(this).val().trim();
        $('#seo-preview-title').text(val || $('[name="name_en"]').val() || titlePlaceholder);
    });

    $(document).on('input', '#seo_description', function () {
        const val = $(this).val().trim();
        $('#seo-preview-desc').text(val || descPlaceholder);
    });

    $(document).on('input', '[name="name_en"]', function () {
        const title = $('#seo_title').val().trim();
        if (!title) {
            $('#seo-preview-title').text($(this).val() || titlePlaceholder);
        }
    });

    // Update slug preview from the slug-input component's hidden field
    $(document).on('input', '[name="slug"]', function () {
        $('#seo-preview-slug').text($(this).val() || slugPlaceholder);
    });
}

// ─── FilePond image upload ────────────────────────────────────────────────────

function initFilePond() {

    const inputEl = document.getElementById('product-images-filepond');
    if (!inputEl) return;

    const uploadUrl = inputEl.dataset.uploadUrl || buildAdminUrl('upload-image');
    const revertBase = inputEl.dataset.revertBase || buildAdminUrl('delete-image');
    const processField = inputEl.dataset.processField || 'file';
    const existing = window.existingProductImages ?? [];
    const existingMap = new Map(existing.map(function (img) {
        return [String(img.id), img];
    }));


    const T = window.TRANSLATIONS || {};

    const pond = FilePond.create(inputEl, {
        allowMultiple: true,
        allowReorder: true,
        maxFiles: 20,
        acceptedFileTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'],
        maxFileSize: '5MB',
        labelIdle: T.filepondLabelIdle || 'Drag &amp; drop images or <span class="filepond--label-action">Browse</span>',

        server: {
            process: {
                url: uploadUrl,
                method: 'POST',
                name: processField,
                headers: { 'X-CSRF-TOKEN': csrfToken() },
                onload: function (response) {
                    return parseUploadServerId(response);
                },
            },
            load: function (source, load, error, progress, abort) {

                const existingImage = existingMap.get(String(source));
                if (!existingImage || !existingImage.url) {
                    error(T.imageUrlNotFound || 'Image URL not found.');
                    return { abort: function () { abort(); } };
                }

                const request = new XMLHttpRequest();
                request.open('GET', existingImage.url);
                request.responseType = 'blob';

                request.onload = function () {
                    if (request.status >= 200 && request.status < 300) {
                        load(request.response);
                    } else {
                        error(T.imagePreviewLoadFailed || 'Failed to load image preview.');
                    }
                };

                request.onerror = function () {
                    error(T.imagePreviewLoadFailed || 'Failed to load image preview.');
                };

                request.onprogress = function (e) {
                    progress(e.lengthComputable, e.loaded, e.total);
                };

                request.send();

                return {
                    abort: function () {
                        request.abort();
                        abort();
                    },
                };
            },
            revert: function (uniqueFileId, load, error) {
                $.ajax({
                    url: revertBase + '/' + encodeURIComponent(uniqueFileId),
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken() },
                }).done(load).fail(function () { error(T.imageRevertFailed || 'Failed to revert upload.'); });
            },
        },
    });

    // Load existing images in edit mode
    if (existing.length > 0) {

        pond.addFiles(existing.map(function (img) {
            return {
                source: img.id,
                options: {
                    type: 'local',
                    file: {
                        name: img.name || 'image',
                        size: Number(img.size) || 0,
                        type: img.mime_type || 'image/jpeg',
                    },
                    metadata: { url: img.url },
                },
            };
        }));
    }

    // Expose pond instance for debugging / external access
    window.productImagePond = pond;
}

/** Build the admin products URL base from current pathname. */
function buildAdminUrl(segment) {
    return window.location.pathname.replace(/\/(create|[^/]+\/edit).*/, '') + '/' + segment;
}

// ─── "Has Variant" requires at least one variant row ─────────────────────────

function initVariantsRequiredGuard() {
    const $form = $('#product-form');
    const T = window.TRANSLATIONS || {};

    $form.on('submit', function (e) {
        const hasVariants = $('#has_variants').is(':checked');
        const variantCount = $('#variants-tbody tr.variant-row').length;

        if (hasVariants && variantCount === 0) {
            e.preventDefault();
            e.stopImmediatePropagation();

            $('[x-data]').each(function () {
                const alpineData = window.Alpine?.$data?.(this);
                if (alpineData) alpineData.activeTab = 'variants';
            });

            window.Toast && window.Toast.error(
                T.hasVariantsRequiresVariant || 'Please add at least one variant, or turn off "Has Variant".'
            );
        }
    });
}

// ─── Form submit (AJAX validate-before-submit, then AJAX PUT for edit) ───────

function showValidationErrors(xhr, T) {
    if (xhr.status === 422) {
        const errors = xhr.responseJSON?.errors ?? {};
        const msgs = Object.values(errors).flat();
        window.Toast && window.Toast.error(msgs[0] || T.validationError || 'Validation error.');
    } else {
        window.Toast && window.Toast.error(xhr.responseJSON?.message || T.saveFailedRetry || 'Save failed. Please try again.');
    }
}

function initFormSubmit() {
    const $form = $('#product-form');
    const validateUrl = $form.data('validate-url');
    let validated = false; // set once the AJAX validation call has passed

    $form.on('submit', function (e) {
        if (validated) return; // already validated — let the real submit through

        e.preventDefault();

        const T = window.TRANSLATIONS || {};
        const $btn = $('#submit-btn').prop('disabled', true).text(T.validatingEllipsis || 'Validating…');
        const validateData = new FormData(this);
        validateData.append('_method', 'POST'); // ensure it's POST for validation
        $.ajax({
            url: validateUrl,
            method: 'POST',
            data: validateData,
            processData: false,
            contentType: false,
        })
            .done(function () {
                if (isEditMode()) {
                    submitEditViaAjax($form, $btn, T);
                    return;
                }

                validated = true;
                $btn.prop('disabled', false).text(T.saveChangesBtn || 'Save changes');
                $form.trigger('submit');
            })
            .fail(function (xhr) {
                $btn.prop('disabled', false).text(T.saveChangesBtn || 'Save changes');
                showValidationErrors(xhr, T);
            });
    });
}

function submitEditViaAjax($form, $btn, T) {
    $btn.prop('disabled', true).text(T.savingEllipsis || 'Saving…');
    const formData = new FormData($form[0]);
    formData.set('_method', 'PUT');

    $.ajax({
        url: $form.attr('action'),
        method: 'POST', // tunnelled via _method=PUT
        data: formData,
        processData: false,
        contentType: false,
    })
        .done(function (res) {
            window.Toast && window.Toast.success(res.message || T.productSaved || 'Product saved.');
        })
        .fail(function (xhr) {
            showValidationErrors(xhr, T);
        })
        .always(function () {
            $btn.prop('disabled', false).text(T.saveChangesBtn || 'Save changes');
        });
}
