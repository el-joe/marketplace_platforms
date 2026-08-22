/**
 * file-upload.js — FilePond integration.
 *
 * Requires (install via yarn/npm):
 *   yarn add filepond
 *            filepond-plugin-image-preview
 *            filepond-plugin-file-validate-type
 *            filepond-plugin-file-validate-size
 *
 * Initialised on: input[data-filepond]
 */
import * as FilePond from 'filepond';
import FilePondPluginImagePreview from 'filepond-plugin-image-preview';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size';

FilePond.registerPlugin(
    FilePondPluginImagePreview,
    FilePondPluginFileValidateType,
    FilePondPluginFileValidateSize,
);

const pondInstances = new Map();

function buildServerConfig(uploadUrl, deleteUrl) {
    return {
        process: {
            url: uploadUrl,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            onload: (response) => {
                const data = JSON.parse(response);
                return data.data?.id ?? response;
            },
            onerror: (response) => {
                const data = JSON.parse(response);
                return data.message ?? t('shared.upload.upload_failed');
            },
        },
        revert: deleteUrl ? {
            url: deleteUrl,
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        } : null,
        load: null,
        restore: null,
        fetch: null,
    };
}

function buildExistingFiles(existing) {
    if (!existing || !existing.length) return [];

    return existing.map((item) => ({
        source: item.id,
        options: {
            type: 'local',
            file: {
                name: item.name || item.url?.split('/').pop() || item.id,
                size: item.size || 0,
            },
            metadata: { url: item.url },
        },
    }));
}

function initFilePond(input) {
    const $input = $(input);
    const name = $input.data('filepond');
    const uploadUrl = $input.data('upload-url');
    const deleteUrl = $input.data('delete-url');
    const maxFiles = parseInt($input.data('max-files'), 10) || 10;
    const maxSizeBytes = parseInt($input.data('max-size'), 10) || 10 * 1024 * 1024;
    const existing = (() => {
        try { return JSON.parse($input.data('existing') || '[]'); } catch { return []; }
    })();

    if (pondInstances.has(name)) {
        pondInstances.get(name).destroy();
    }

    const pond = FilePond.create(input, {
        name,
        storeAsFile: true, // Add this line to force standard form submission behavior
        allowMultiple: input.hasAttribute('multiple'),
        maxFiles,
        maxFileSize: maxSizeBytes,
        allowFileTypeValidation: !!input.accept && input.accept !== '*',
        acceptedFileTypes: input.accept !== '*' ? input.accept.split(',').map((s) => s.trim()) : null,
        allowImagePreview: true,
        imagePreviewHeight: 100,
        labelIdle: t('shared.upload.drop_files_label'),
        server: uploadUrl ? buildServerConfig(uploadUrl, deleteUrl) : null,
        files: buildExistingFiles(existing),
    });

    pondInstances.set(name, pond);
    return pond;
}

function initFileUploads($scope) {
    $scope = $scope || $('body');
    $scope.find('[data-filepond]').each(function () {
        initFilePond(this);
    });
}

window.initFileUploads = initFileUploads;

$(function () {
    initFileUploads($('body'));
});
