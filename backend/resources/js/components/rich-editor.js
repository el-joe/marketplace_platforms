/**
 * rich-editor.js — Summernote WYSIWYG editor.
 *
 * Requires: summernote (yarn add summernote)
 * Initialised on: textarea[data-rich-editor]
 *
 * Profiles:  minimal | default | full
 * Data attrs:
 *   data-rich-editor="<profile>"
 *   data-min-height="<px>"          (optional, default 200)
 *   data-upload-url="<url>"         (optional, enables image upload on 'full')
 */
import $ from 'jquery';
import 'summernote/dist/summernote-lite.js';
import 'summernote/dist/lang/summernote-ar-AR.js';

/** Toolbar tooltips/dialogs follow the admin panel language. */
const EDITOR_LANG = document.documentElement.lang === 'ar' ? 'ar-AR' : 'en-US';

/* =========================================================
   Toolbar presets per profile
   ========================================================= */
const TOOLBARS = {
    minimal: [
        ['font', ['bold', 'italic']],
        ['para', ['ul', 'ol']],
        ['insert', ['link']],
        ['view', ['undo', 'redo']],
    ],
    default: [
        ['style', ['style']],
        ['font', ['bold', 'italic', 'clear']],
        ['para', ['ul', 'ol', 'paragraph']],
        ['insert', ['link', 'hr']],
        ['view', ['codeview', 'undo', 'redo']],
    ],
    full: [
        ['style', ['style']],
        ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
        ['para', ['ul', 'ol', 'paragraph']],
        ['table', ['table']],
        ['insert', ['link', 'picture', 'hr']],
        ['view', ['codeview', 'undo', 'redo']],
    ],
};

/* =========================================================
   Image upload (profile "full" + data-upload-url)
   ========================================================= */
const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];
const MAX_IMAGE_BYTES = 8 * 1024 * 1024;
const tr = (key, fallback) => window.TRANSLATIONS?.[key] || fallback;

function uploadEditorImage($el, uploadUrl, file) {
    if (!IMAGE_TYPES.includes(file.type)) {
        window.Toast?.error(tr('mediaErrorType', 'This file type is not allowed. Use JPG, PNG, WEBP, GIF, AVIF.'));
        return;
    }
    if (file.size > MAX_IMAGE_BYTES) {
        window.Toast?.error(tr('mediaErrorSize', 'The image is larger than 8 MB.'));
        return;
    }
    const fd = new FormData();
    fd.append('image', file);
    $.ajax({
        url: uploadUrl, method: 'POST', data: fd,
        processData: false, contentType: false,
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), Accept: 'application/json' },
    }).done(function (res) {
        const url = res.url || res.data?.url;
        if (url) $el.summernote('insertImage', url, ($img) => $img.attr('alt', ''));
    }).fail(function (xhr) {
        window.Toast?.error(xhr.responseJSON?.errors?.image?.[0] || xhr.responseJSON?.message || tr('mediaErrorUpload', 'Upload failed. Please try again.'));
    });
}

/* =========================================================
   Init a single textarea element
   ========================================================= */
function initEditor(el) {
    const $el = $(el);

    // Destroy any existing instance first (e.g. re-mount)
    if ($el.next('.note-editor').length) {
        $el.summernote('destroy');
    }

    const profile = $el.attr('data-rich-editor') || 'default';
    const uploadUrl = $el.data('upload-url');
    const minHeight = parseInt($el.attr('data-min-height'), 10) || 200;

    const options = {
        lang: EDITOR_LANG,
        toolbar: TOOLBARS[profile] || TOOLBARS['default'],
        minHeight: minHeight,
        disableResizeEditor: true,
        placeholder: $el.attr('placeholder') || '',
        styleTags: ['p', 'h2', 'h3', 'h4', 'blockquote', 'pre'],
        callbacks: {
            // Keep the underlying textarea's value in sync on every edit (Summernote
            // only writes back to it on form submit otherwise) and fire a native
            // 'change' event so listeners like the page-builder auto-save pick it up.
            onChange(contents) {
                $el.val(contents).trigger('change');
            },
        },
    };

    if (profile === 'full' && uploadUrl) {
        // Upload pasted/dropped/picked images to the server instead of Summernote's default
        // base64 embedding (data: URIs are stripped by the server-side HTML sanitizer).
        options.callbacks.onImageUpload = function (files) {
            Array.from(files || []).forEach((file) => uploadEditorImage($el, uploadUrl, file));
        };
    }

    $el.summernote(options);
}

/* =========================================================
   Init on a scope (body by default)
   ========================================================= */
function initRichEditors($scope) {
    $scope = $scope || $('body');
    $scope.find('textarea[data-rich-editor]').each(function () {
        initEditor(this);
    });
}

window.initRichEditors = initRichEditors;

$(function () {
    initRichEditors($('body'));
});
