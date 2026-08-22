import FilePond, { registerPlugin } from 'filepond';
import FilePondPluginImagePreview from 'filepond-plugin-image-preview';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size';

try { registerPlugin(FilePondPluginImagePreview, FilePondPluginFileValidateType, FilePondPluginFileValidateSize); } catch (_) { }

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function initSupplyImageFilePond() {
    const inputEl = document.getElementById('supply-image-filepond');
    if (!inputEl) return;

    const uploadUrl = inputEl.dataset.uploadUrl;
    const deleteUrl = inputEl.dataset.deleteUrl;
    const hiddenPathField = document.getElementById('image_path');

    FilePond.create(inputEl, {
        allowMultiple: false,
        maxFiles: 1,
        acceptedFileTypes: ['image/jpeg', 'image/png', 'image/webp'],
        maxFileSize: '5MB',
        labelIdle: 'Drag &amp; drop an image or <span class="filepond--label-action">Browse</span>',

        server: {
            process: {
                url: uploadUrl,
                method: 'POST',
                name: 'image',
                headers: { 'X-CSRF-TOKEN': csrfToken() },
                onload: function (response) {
                    let payload = response;
                    if (typeof response === 'string') {
                        try { payload = JSON.parse(response); } catch (_) { payload = {}; }
                    }
                    if (hiddenPathField) hiddenPathField.value = payload.path || '';
                    return payload.path || '';
                },
            },
            revert: function (uniqueFileId, load, error) {
                fetch(deleteUrl, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken(),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ path: uniqueFileId }),
                })
                    .then(() => {
                        if (hiddenPathField) hiddenPathField.value = '';
                        load();
                    })
                    .catch(() => error('Failed to remove image.'));
            },
        },
    });
}

document.addEventListener('DOMContentLoaded', initSupplyImageFilePond);
