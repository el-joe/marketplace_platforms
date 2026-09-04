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
        options.callbacks.onImageUpload = function (files) {
            const fd = new FormData();
            fd.append('file', files[0]);
            $.ajax({
                url: uploadUrl, method: 'POST', data: fd,
                processData: false, contentType: false,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            }).done(function (res) {
                if (res.data?.url) {
                    $el.summernote('insertImage', res.data.url);
                }
            });
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
