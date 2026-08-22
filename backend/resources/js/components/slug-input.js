/**
 * slug-input.js — Auto-generate slugs from a source field.
 *
 * Source field: [data-slug-source]   (e.g. the title input)
 * Target field: [data-slug-target]   (the slug input)
 *
 * The source field is linked via the slug input's
 * [data-slug-source-field] attribute (matches the source input's name).
 *
 * Example:
 *   <input name="title" data-slug-source>
 *   <x-form-slug-input name="slug" source-field="title" />
 *   (renders: <input data-slug-target data-slug-source-field="title">)
 */
import $ from 'jquery';

function toSlug(str) {
    return String(str)
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')  // strip diacritics
        .replace(/[^\w\s-]/g, '')          // remove non-word chars
        .replace(/[\s_]+/g, '-')           // spaces/underscores → hyphens
        .replace(/-+/g, '-')               // collapse multiple hyphens
        .replace(/^-+|-+$/g, '');          // trim leading/trailing hyphens
}

function bindSlugSource($scope) {
    // For each slug target, find its source field by name and listen
    $scope.find('[data-slug-target]').each(function () {
        const $target = $(this);
        const sourceField = $target.data('slug-source-field');

        if (!sourceField) return;

        const $source = $scope.find(`[name="${sourceField}"]`).first();
        if (!$source.length) return;

        // Use a flag to track whether the slug has been manually edited
        let manuallyEdited = $target.val().length > 0;

        $source.off('input.slug keyup.slug').on('input.slug keyup.slug', function () {
            if (manuallyEdited) return;
            $target.val(toSlug($(this).val()));
        });

        // Once the user types in the slug field directly, stop auto-generating
        $target.off('input.slug-manual').on('input.slug-manual', function () {
            manuallyEdited = $(this).val().length > 0;
        });

        // Blur: clean up the slug the user typed
        $target.off('blur.slug').on('blur.slug', function () {
            $(this).val(toSlug($(this).val()));
        });
    });
}

function initSlugInputs($scope) {
    $scope = $scope || $('body');
    bindSlugSource($scope);
}

window.initSlugInputs = initSlugInputs;
window.toSlug = toSlug;

$(function () {
    initSlugInputs($('body'));
});
