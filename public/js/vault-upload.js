"use strict";
/* global FileUpload, Dropzone, mediaSettings, trans, launchToast */
/* exported VaultUpload */

// Uploads go straight to the library, never into a composer's attachment selection.
var VaultUpload = {
    dropzone: null,
    savedCount: 0,

    init: function (onSaved) {
        this.root = document.querySelector('.vault-upload-zone');
        this.queue = this.root.querySelector('.vault-upload-queue');
        this.announcement = this.root.querySelector('.vault-upload-announcement');
        const dropzone = this.dropzone = FileUpload.createDropzone(this.root, '/attachment/upload/vault', mediaSettings.use_chunked_uploads, {
            previewsContainer: this.root.querySelector('.vault-upload-list'),
            previewTemplate: document.getElementById('vault-upload-row-template').innerHTML,
            createImageThumbnails: false,
            addRemoveLinks: false,
        });
        dropzone.on('addedfile', file => {
            const row = file.previewElement;
            row.querySelector('.vault-upload-filename').title = file.name;
            row.querySelector('.vault-upload-progress').setAttribute('aria-label', trans('Upload progress') + ': ' + file.name);
            row.querySelector('.vault-upload-remove').addEventListener('click', () => this.remove(file));
            row.querySelector('.vault-upload-retry').addEventListener('click', () => this.retry(file));
            this.queue.hidden = false;
        });
        dropzone.on('processing', file => this.progress(file, 0));
        dropzone.on('uploadprogress', (file, progress) => this.progress(file, progress));
        dropzone.on('error', (file, message, xhr) => this.fail(file, message, xhr));
        dropzone.on('success', (file, response) => {
            if (!this.contains(file) || file.status === Dropzone.CANCELED) return;
            if (!response || response.success !== true || !response.attachmentID) {
                this.fail(file, response, file.xhr);
                return;
            }
            this.savedCount++;
            this.announcement.textContent = trans('Saved to Vault') + ': ' + file.name;
            this.remove(file);
            onSaved();
        });
        dropzone.on('removedfile', () => { this.queue.hidden = dropzone.files.length === 0; });
        dropzone.on('queuecomplete', () => {
            if (!this.savedCount || this.isUploading()) return;
            launchToast('success', trans('Vault'), trans(this.savedCount === 1 ? '1 file saved to your Vault.' : ':count files saved to your Vault.').replace(':count', this.savedCount));
            this.savedCount = 0;
        });
        window.addEventListener('beforeunload', event => {
            if (!this.isUploading()) return;
            event.preventDefault();
            event.returnValue = '';
        });
    },

    contains: function (file) {
        return this.dropzone.files.indexOf(file) !== -1;
    },

    isUploading: function () {
        return this.dropzone.files.some(file => [Dropzone.ADDED, Dropzone.QUEUED, Dropzone.UPLOADING].indexOf(file.status) !== -1);
    },

    progress: function (file, value) {
        if (!this.contains(file) || file.status === Dropzone.ERROR || file.status === Dropzone.CANCELED) return;
        const percent = Math.max(0, Math.min(100, Math.round(Number(value) || 0)));
        const row = file.previewElement;
        row.querySelector('.vault-upload-progress').setAttribute('aria-valuenow', percent);
        row.querySelector('.vault-upload-state').textContent = percent < 100
            ? trans('Uploading') + ' ' + percent + '%' : trans('Saving...');
        // Once all bytes are sent, aborting cannot reliably prevent the server from saving.
        const remove = row.querySelector('.vault-upload-remove');
        if (percent === 100 && remove === document.activeElement) {
            this.root.closest('#media-vault-page').querySelector('.vault-upload-button').focus({preventScroll: true});
        }
        remove.hidden = percent === 100;
    },

    fail: function (file, message, xhr) {
        if (!this.contains(file) || file.status === Dropzone.CANCELED) return;
        file.status = Dropzone.ERROR;
        const row = file.previewElement;
        const status = Number(xhr && xhr.status) || 0;
        file.vaultCanRetry = file.accepted !== false && (status === 0 || status === 408 || status === 429 || status >= 500);
        row.classList.add('vault-upload-row--failed');
        row.querySelector('.vault-upload-state').textContent = trans('Upload failed');
        row.querySelector('.vault-upload-progress').hidden = true;
        const error = row.querySelector('.vault-upload-error');
        error.textContent = this.errorMessage(message);
        error.hidden = false;
        row.querySelector('.vault-upload-retry').hidden = !file.vaultCanRetry;
        const remove = row.querySelector('.vault-upload-remove');
        remove.hidden = false;
        remove.textContent = trans('Dismiss');
        this.announcement.textContent = trans('Upload failed') + ': ' + file.name + '. ' + error.textContent;
    },

    errorMessage: function (message) {
        if (message && typeof message === 'object') message = message.message || message.error;
        // Proxies may return an HTML error page; keep that out of the queue.
        return typeof message === 'string' && message.trim() && !/<[a-z!][^>]*>/i.test(message)
            ? message : trans('Unable to upload this file. Please try again.');
    },

    remove: function (file) {
        if (!this.contains(file)) return;
        const restoreFocus = file.previewElement.contains(document.activeElement);
        this.dropzone.removeFile(file);
        // Dismissal/cancellation never calls attachment deletion endpoints.
        if (restoreFocus) this.root.closest('#media-vault-page').querySelector('.vault-upload-button').focus({preventScroll: true});
    },

    retry: function (file) {
        if (!this.contains(file) || file.status !== Dropzone.ERROR || !file.vaultCanRetry) return;
        this.remove(file);
        delete file.xhr;
        // addFile resets the upload UUID/chunks and reruns the same validation.
        this.dropzone.addFile(file);
        file.previewElement.querySelector('.vault-upload-remove').focus({preventScroll: true});
    },
};
