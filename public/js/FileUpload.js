/**
 *
 * Main App Component
 *
 */
"use strict";
/* global app, mediaSettings, Dropzone, trans, launchToast, StoryCreator, ReelCreator */

// Disable dropzone uploader auto loading globally as we will instantiate it manually
Dropzone.autoDiscover = false;

var FileUpload = {

    attachaments: [],
    myDropzone : null,
    isLoading: false,
    isTranscodingVideo: false,
    state: {},
    socialContext: false,
    uploadType: null,
    isRestoring: false,
    restoreFailed: false,
    pendingRestoreIds: [],
    pendingVaultSelections: 0,
    restoreGeneration: 0,

    // Shared transport and validation; callers supply their own presentation and lifecycle.
    createDropzone: function (selector, url, isChunkUpload = false, options = {}) {
        // Prepping chunk uploads, if enabled by admin
        let chunkSize = 1024;
        if(isChunkUpload){
            chunkSize = mediaSettings.upload_chunk_size * 1000000;
            url = url.replace('/upload/','/uploadChunked/');
        }

        return new Dropzone(selector, Object.assign({
            paramName: "file", // The name that will be used to transfer the file
            url: app.baseUrl + url,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            clickable:['.file-upload-button'],
            previewsContainer: ".dropzone-previews",
            maxFilesize: mediaSettings.max_file_upload_size, // MB
            maxFiles: null,
            addRemoveLinks: true,
            dictRemoveFile: "x",
            acceptedFiles: mediaSettings.allowed_file_extensions,
            chunking: isChunkUpload,
            forceChunking: isChunkUpload,
            chunkSize: chunkSize,
            parallelChunkUploads: false,
            retryChunks: false,
            retryChunksLimit: 2,
            dictDefaultMessage: trans("Drop files here to upload"),

            accept: function(file, done) {
                if(FileUpload.isRestoring || FileUpload.restoreFailed){
                    done(trans('Wait for draft media to load before adding files.'));
                    return;
                }
                const hasValidSize = file && typeof file.size === 'number' && file.size > 0;
                const hasValidName = file && typeof file.name === 'string' && file.name.trim() !== '';

                if(!hasValidSize || !hasValidName){
                    done(trans("The selected file is empty or unavailable. Please select it again."));
                    return;
                }

                done();
            },
            dictInvalidFileType: trans("You can't upload files of this type."),
        }, options));
    },

    /** Instantiates the composer uploader and its media selection previews. */
    initDropZone: function (selector, url, isChunkUpload = false, extraOptions = {}) {
        const contextMatch = url.match(/\/upload\/(post|message|message-template|story|reel|vault)$/);
        FileUpload.uploadType = contextMatch ? contextMatch[1] : null;
        FileUpload.socialContext = !!contextMatch;
        const restoredAttachments = FileUpload.socialContext ? FileUpload.attachaments.slice() : [];
        if(FileUpload.socialContext) FileUpload.attachaments = [];

        FileUpload.myDropzone = FileUpload.createDropzone(selector, url, isChunkUpload, {
            previewTemplate: document.querySelector('#tpl').innerHTML,
            maxFiles: extraOptions.maxFiles || null,
            init: function() {
                // FileUpload.attachaments
                FileUpload.attachaments.map((element)=>{
                    var mockFile = {
                        name: element.attachmentID,
                        upload: {attachmentID: element.attachmentID},
                        type: element.type,
                        thumbnail: element.thumbnail,
                        accepted: true,
                        status: Dropzone.SUCCESS
                    };
                    this.files.push(mockFile);
                    this.emit("addedfile", mockFile);
                    this.emit("thumbnail", mockFile, element.thumbnail);
                    this.emit("complete", mockFile);
                    FileUpload.updatePreviewElement(mockFile, false, element);
                });
                FileUpload.updatePreviewsState();
                FileUpload.refreshTranscodingState();
                FileUpload.syncTranscodingStatuses();
                var _this = this;
                $(".draft-clear-button").on("click", function() {
                    FileUpload.restoreGeneration++;
                    FileUpload.isRestoring = false;
                    _this.removeAllFiles(true);
                });
            },
        });

        FileUpload.myDropzone.on("addedfile", file => {
            if(!file.vaultSelection) FileUpload.updatePreviewElement(file, true);
            FileUpload.updatePreviewsState();
            FileUpload.refreshLoadingState();
        });

        FileUpload.myDropzone.on("success", (file, response) => {
            if(FileUpload.socialContext && FileUpload.myDropzone.files.indexOf(file) === -1) return;
            if(response.success){
                file.upload.attachmentID = response.attachmentID;
                FileUpload.attachaments = FileUpload.attachaments.filter(function (attachment) {
                    return String(attachment.attachmentID || attachment.id) !== String(response.attachmentID);
                });
                FileUpload.attachaments.push(FileUpload.normalizeAttachment(response));

                if(FileUpload.isPendingTranscodingStatus(response.transcoding_status)){
                    FileUpload.showVideoEncodingState(file);
                    FileUpload.syncTranscodingStatuses();
                }
                else{
                    switch (FileUpload.getPreviewType(file, response)) {
                    case 'video':
                    case 'audio':
                    case 'file':
                        FileUpload.updatePreviewElement(file, false, response);
                        break;
                    }
                }
            }
            FileUpload.refreshLoadingState();
            FileUpload.refreshTranscodingState();
        });

        FileUpload.myDropzone.on("complete", () => {
            FileUpload.refreshLoadingState();
        });

        FileUpload.myDropzone.on("removedfile", function(file) {
            FileUpload.revokeLocalPreviewUrl(file);
            FileUpload.attachaments = FileUpload.attachaments.filter((attachment)=>{
                if(!file.upload || String(attachment.attachmentID) !== String(file.upload.attachmentID)){
                    return attachment;
                }
                else{
                    if(!FileUpload.socialContext) FileUpload.removeAttachment(attachment);
                }
            });
            FileUpload.updatePreviewsState();
            FileUpload.refreshLoadingState();
            FileUpload.refreshTranscodingState();
        });

        FileUpload.myDropzone.on("error", (file, errorMessage, xhr) => {
            if(FileUpload.isFileAttachment(file)) {
                FileUpload.showFileUploadError(file, errorMessage, xhr);
                FileUpload.refreshLoadingState();
                return;
            }
            if(typeof errorMessage.errors !== 'undefined'){
                launchToast('danger',trans('Error'),errorMessage.message);
                // launchToast('danger',trans('Error'),errorMessage.errors.file)
                // $.each(errorMessage.errors,function (field,error) {
                //     launchToast('danger',trans('Error'),error);
                // });
            }
            else{
                if(typeof errorMessage.message !== 'undefined'){
                    launchToast('danger',trans('Error'),errorMessage.message);
                }
                else{
                    launchToast('danger',trans('Error'),errorMessage);
                }
            }
            FileUpload.myDropzone.removeFile(file);
            FileUpload.refreshLoadingState();
            FileUpload.refreshTranscodingState();
        });

        if(restoredAttachments.length) FileUpload.restoreAttachments(restoredAttachments);
    },

    // Rebuild drafts from owner-authorized IDs; persisted URLs are never rendered.
    restoreAttachments: function (attachments) {
        FileUpload.clearSelection();
        const ids = (attachments || []).map(function (attachment) { return attachment.attachmentID || attachment.id; })
            .filter(Boolean).filter(function (id, index, values) { return values.indexOf(id) === index; });
        if(!ids.length) return;
        const generation = FileUpload.restoreGeneration;
        FileUpload.isRestoring = true;
        FileUpload.pendingRestoreIds = ids;
        const requests = [];
        for(let offset = 0; offset < ids.length; offset += 100){
            requests.push($.ajax({type: 'POST', url: app.baseUrl + '/attachment/resolve', data: {ids: ids.slice(offset, offset + 100)}}));
        }
        Promise.all(requests).then(function (responses) {
            if(generation !== FileUpload.restoreGeneration) return;
            const resolved = {};
            responses.forEach(function (response) {
                (response.attachments || []).forEach(function (attachment) { resolved[attachment.attachmentID] = attachment; });
            });
            ids.forEach(function (id) { if(resolved[id]) FileUpload.addVaultAttachment(resolved[id], true); });
            if(Object.keys(resolved).length < ids.length) launchToast('warning', trans('Vault'), trans('Some draft media is no longer available and was removed from this selection.'));
        }).catch(function () {
            if(generation !== FileUpload.restoreGeneration) return;
            FileUpload.restoreFailed = true;
            const notice = $('<div>').addClass('alert alert-warning vault-restore-error w-100');
            $('<p>').text(trans('Unable to restore draft media. Retry or clear this selection.')).appendTo(notice);
            $('<button type="button">').addClass('btn btn-sm btn-primary me-2').text(trans('Retry'))
                .on('click', function () { FileUpload.restoreAttachments(ids.map(function (id) { return {attachmentID: id}; })); }).appendTo(notice);
            $('<button type="button">').addClass('btn btn-sm btn-outline-secondary').text(trans('Clear selection'))
                .on('click', function () { FileUpload.clearSelection(); }).appendTo(notice);
            notice.appendTo($('.dropzone-previews'));
        }).finally(function () {
            if(generation !== FileUpload.restoreGeneration) return;
            FileUpload.isRestoring = false;
            if(!FileUpload.restoreFailed) FileUpload.pendingRestoreIds = [];
            FileUpload.refreshLoadingState();
            FileUpload.refreshTranscodingState();
            $(document).trigger('attachment:selection-restored');
        });
    },

    clearSelection: function () {
        FileUpload.restoreGeneration++;
        FileUpload.isRestoring = false;
        FileUpload.restoreFailed = false;
        FileUpload.pendingRestoreIds = [];
        FileUpload.pendingVaultSelections = 0;
        $('.vault-restore-error').remove();
        if(FileUpload.myDropzone) FileUpload.myDropzone.removeAllFiles(true);
        FileUpload.attachaments = [];
        $('.dropzone-previews .dz-preview').remove();
        FileUpload.refreshLoadingState();
        FileUpload.refreshTranscodingState();
        FileUpload.updatePreviewsState();
    },

    getVaultSelectionMode: function () {
        if(typeof StoryCreator !== 'undefined') return 'story';
        if(typeof ReelCreator !== 'undefined') return 'reel';
        return 'multiple';
    },

    getVaultReplacementIds: function (attachment) {
        const mode = FileUpload.getVaultSelectionMode();
        const type = attachment.type || attachment.attachmentType;
        return FileUpload.attachaments.filter(function (selected) {
            return mode === 'story' || (mode === 'reel' && (selected.type || selected.attachmentType) === type);
        }).map(function (selected) { return String(selected.attachmentID || selected.id); });
    },

    canSelectVaultAttachment: function (attachment, silent, allowReplacement = false) {
        var error = null;
        const dropzone = FileUpload.myDropzone;
        const type = attachment.type || attachment.attachmentType;
        if(!dropzone || FileUpload.isRestoring || FileUpload.restoreFailed) return false;
        if(attachment.vault_archived_at || attachment.transcoding_status !== 'completed') return false;
        if(FileUpload.isFileAttachment(attachment) && attachment.can_select === false) return false;
        const replacements = allowReplacement ? FileUpload.getVaultReplacementIds(attachment) : [];
        const remainingFiles = dropzone.files.filter(function (file) {
            return !file.upload || replacements.indexOf(String(file.upload.attachmentID)) < 0;
        });
        if(dropzone.options.maxFiles && remainingFiles.length >= dropzone.options.maxFiles) error = trans('Remove a selected file before choosing another.');
        if(typeof StoryCreator !== 'undefined' && ['image', 'video'].indexOf(type) < 0) error = trans('Stories require a photo or video.');
        if(typeof ReelCreator !== 'undefined') {
            if(['image', 'video'].indexOf(type) < 0 || FileUpload.attachaments.some(function (selected) {
                return selected.type === type && replacements.indexOf(String(selected.attachmentID || selected.id)) < 0;
            })) error = trans('A reel supports one video and one cover image.');
            const maxLength = ReelCreator.getMaxVideoLength();
            if(type === 'video' && maxLength && Number(attachment.length) > maxLength) error = trans('This video exceeds the maximum reel length.');
        }
        if(allowReplacement && FileUpload.getVaultSelectionMode() !== 'multiple' && (FileUpload.isLoading || dropzone.files.some(function (file) {
            return ['added', 'queued', 'uploading', 'processing'].indexOf(file.status) >= 0;
        }))) error = trans('Wait for uploads to finish before replacing media.');
        if(error && !silent) launchToast('warning', trans('Vault'), error);
        return !error;
    },

    applyVaultSelection: function (attachment) {
        if(!FileUpload.socialContext || FileUpload.findAttachment(attachment.attachmentID || attachment.id)) return false;
        // Resolve and validate the new media before removing anything from the current draft.
        if(!FileUpload.canSelectVaultAttachment(attachment, false, true)) return false;
        FileUpload.getVaultReplacementIds(attachment).forEach(function (id) { FileUpload.removeAttachment(id); });
        return FileUpload.addVaultAttachment(attachment);
    },

    deselectVaultAttachment: function (id) {
        if(!FileUpload.socialContext || !FileUpload.myDropzone || FileUpload.isRestoring || FileUpload.restoreFailed || !FileUpload.findAttachment(id)) return false;
        FileUpload.removeAttachment(id);
        FileUpload.refreshLoadingState();
        FileUpload.refreshTranscodingState();
        FileUpload.updatePreviewsState();
        return true;
    },

    addVaultAttachment: function (attachment, restoring = false) {
        if(!FileUpload.myDropzone || FileUpload.findAttachment(attachment.attachmentID || attachment.id)) return false;
        if(!restoring && !FileUpload.canSelectVaultAttachment(attachment, false)) return false;
        const normalized = FileUpload.normalizeAttachment(attachment);
        const file = {
            name: normalized.original_name || normalized.attachmentID,
            size: normalized.size,
            upload: {attachmentID: normalized.attachmentID},
            type: normalized.type, accepted: true, status: Dropzone.SUCCESS, vaultSelection: true
        };
        FileUpload.attachaments.push(normalized);
        FileUpload.myDropzone.files.push(file);
        FileUpload.myDropzone.emit('addedfile', file);
        FileUpload.updatePreviewElement(file, false, normalized);
        FileUpload.myDropzone.emit('success', file, Object.assign({success: true}, attachment));
        FileUpload.myDropzone.emit('complete', file);
        FileUpload.refreshLoadingState();
        FileUpload.refreshTranscodingState();
        FileUpload.updatePreviewsState();
        return true;
    },

    getDraftAttachments: function () {
        if(FileUpload.isRestoring || FileUpload.restoreFailed){
            return FileUpload.pendingRestoreIds.map(function (id) { return {attachmentID: id}; });
        }
        return FileUpload.attachaments.map(FileUpload.normalizeAttachment);
    },

    updatePreviewsState:function () {
        const previewContainer = $('.dropzone-previews');
        const previews = previewContainer.find('.dz-preview');
        const hasPreviews = previews.length > 0;
        const hasAudioPreviews = previews.find('.audio-preview-item').length > 0;
        const hasNonAudioPreviews = previews.filter(function () {
            return $(this).find('.audio-preview-item').length === 0;
        }).length > 0;

        previewContainer.toggleClass('has-files', hasPreviews);
        previewContainer.toggleClass('has-audio-only', hasPreviews && hasAudioPreviews && !hasNonAudioPreviews);
    },

    /**
     * Updates the preview template based on uploaded file
     * @param file
     * @param localFile
     * @param attachment
     */
    updatePreviewElement:function (file,localFile, attachment = false) {
        let filePreview = $(file.previewElement);
        const previewType = FileUpload.getPreviewType(file, attachment);
        filePreview.find('.dz-image').remove();
        if(previewType === 'file') {
            FileUpload.updateFilePreview(file, attachment || file);
            return;
        }
        switch (previewType) {
        case 'video':
            filePreview.find('.audio-preview-item').remove();
            filePreview.find('.video-preview-item').remove();
            filePreview.prepend(videoPreview());
            var videoPreviewEl = filePreview.find('video').get(0);
            if(localFile){
                FileUpload.setMediaSourceForPreviewByElementAndFile(videoPreviewEl, file);
            }
            else{
                FileUpload.setPreviewSource(videoPreviewEl, file, attachment);
            }
            break;
        case 'audio':
            filePreview.find('.video-preview-item').remove();
            filePreview.find('.audio-preview-item').remove();
            filePreview.prepend(audioPreview());
            filePreview.addClass("w-100");
            filePreview.find('audio').addClass("w-100");
            filePreview.find(".audio-preview-item").addClass("w-100");
            var audioPreviewEl = filePreview.find('audio').get(0);
            filePreview.addClass("w-100");
            if(localFile){
                FileUpload.setMediaSourceForPreviewByElementAndFile(audioPreviewEl, file);
            }
            else{
                FileUpload.setPreviewSource(audioPreviewEl, file, attachment);
            }
            break;
        case 'application/vnd.ms-excel':
        case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
            filePreview.prepend(excelPreview());
            break;
        case 'application/pdf':
            filePreview.prepend(pdfPreview());
            break;
        default:
            filePreview.prepend(imagePreview());
            if(!localFile){
                let previewElement = filePreview.find('img').get(0);
                FileUpload.setPreviewSource(previewElement, file, attachment);
            }
            break;
        }
    },

    getPreviewType: function (file, attachment = false) {
        if(FileUpload.isFileAttachment(attachment || file)) return 'file';
        if(FileUpload.isAudioFile(file, attachment)){
            return 'audio';
        }

        if(FileUpload.isVideoFile(file)){
            return 'video';
        }

        return file && file.type ? file.type : '';
    },

    isFileAttachment: function (attachment) {
        if(!attachment) return false;
        if(attachment.type === 'file' || attachment.attachmentType === 'file') return true;
        // Local social uploads have not received their authoritative server category yet.
        return FileUpload.socialContext && /\.(pdf|docx|xlsx|pptx|txt|csv|md|zip)$/i.test(attachment.name || '');
    },

    escapeFileText: function (value) {
        return String(value || '').replace(/[&<>"']/g, function (character) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character];
        });
    },

    fileSizeLabel: function (size) {
        size = Number(size) || 0;
        if(size < 1024) return size + ' ' + trans('B');
        if(size < 1024 * 1024) return (size / 1024).toFixed(1) + ' ' + trans('KB');
        return (size / (1024 * 1024)).toFixed(1) + ' ' + trans('MB');
    },

    updateFilePreview: function (file, attachment) {
        const preview = $(file.previewElement);
        preview.find('.file-preview-item, .dz-details, .video-preview-item, .audio-preview-item').remove();
        const name = attachment.original_name || file.name || trans('File');
        const extension = attachment.extension || name.split('.').pop();
        const size = attachment.size_label || FileUpload.fileSizeLabel(attachment.size || file.size);
        const downloadUrl = typeof attachment.download_url === 'string' && /^(?:https?:\/\/|\/(?!\/))/i.test(attachment.download_url)
            ? attachment.download_url : null;
        preview.addClass('is-file-attachment');
        preview.prepend('<div class="file-preview-item border rounded p-3 text-center">'
            + '<ion-icon name="' + (String(extension).toLowerCase() === 'zip' ? 'archive-outline' : 'document-text-outline') + '" class="fs-3 text-primary" aria-hidden="true"></ion-icon>'
            + '<div class="file-preview-name text-break small fw-semibold mt-2" title="' + FileUpload.escapeFileText(name) + '" aria-label="' + FileUpload.escapeFileText(name) + '">' + FileUpload.escapeFileText(name) + '</div>'
            + '<div class="small text-body-secondary">' + FileUpload.escapeFileText(String(extension).toUpperCase()) + ' · ' + FileUpload.escapeFileText(size) + '</div>'
            + (downloadUrl ? '<a class="btn btn-sm btn-outline-primary mt-2 mb-0" href="' + FileUpload.escapeFileText(downloadUrl) + '" aria-label="' + FileUpload.escapeFileText(trans('Download :filename').replace(':filename', name)) + '">' + FileUpload.escapeFileText(trans('Download')) + '</a>' : '')
            + '<div class="file-upload-error small text-danger mt-2" role="alert" hidden></div>'
            + '<button type="button" class="file-upload-retry btn btn-sm btn-outline-primary mt-2 mb-0" hidden>' + FileUpload.escapeFileText(trans('Retry')) + '</button></div>');
    },

    showFileUploadError: function (file, message, xhr) {
        const preview = $(file.previewElement);
        const detail = message && typeof message === 'object' ? message.message : message;
        const error = typeof detail === 'string' && detail.trim() && !/<[a-z!][^>]*>/i.test(detail)
            ? detail : trans('Unable to upload this file. Please try again.');
        preview.find('.dz-progress').hide();
        preview.find('.file-upload-error').prop('hidden', false).text(error);
        const status = Number((xhr || file.xhr || {}).status) || 0;
        const canRetry = file.accepted !== false && (status === 0 || status === 408 || status === 429 || status >= 500);
        preview.find('.file-upload-retry').prop('hidden', !canRetry).off('click').on('click', function () {
            FileUpload.myDropzone.removeFile(file);
            delete file.xhr;
            FileUpload.myDropzone.addFile(file);
            const remove = file.previewElement && file.previewElement.querySelector('.dz-remove');
            if(remove) remove.focus({preventScroll: true});
        });
    },

    /**
     * Sets up the media src for the uploaded file type
     * @param element
     * @param file
     * @returns {boolean}
     */
    setMediaSourceForPreviewByElementAndFile: function (element, file) {
        if(typeof element === 'undefined'){ return false;}
        file.upload = file.upload || {};
        const previewMimeType = FileUpload.getPreviewMimeType(file);
        if (element.canPlayType(previewMimeType).length && element.canPlayType(previewMimeType) !== "no") {
            const fileURL = window.URL.createObjectURL(file);
            file.upload.localPreviewPlayable = true;
            file.upload.localPreviewUrl = fileURL;
            $(element).attr('src', fileURL);
            $(element).attr('type', previewMimeType);
        }
        else{
            file.upload.localPreviewPlayable = false;
            if(FileUpload.isAudioFile(file)){
                FileUpload.showAudioPlaceholder(file, trans('Audio preview unavailable'), trans('Uploading audio...'));
            }
            else{
                FileUpload.showVideoPlaceholder(file, trans('Video preview unavailable'), trans('Uploading video...'));
            }
        }
    },

    getPreviewMimeType: function (file) {
        const fileName = file && typeof file.name === 'string' ? file.name.toLowerCase() : '';

        if(FileUpload.isAudioFile(file) && /\.m4a$/i.test(fileName)){
            return 'audio/mp4';
        }

        if(FileUpload.isAudioFile(file) && /\.webm$/i.test(fileName)){
            return 'audio/webm';
        }

        return file && file.type ? file.type : '';
    },

    /**
     * Sets media source | Thumbnail
     * @param element
     * @param file
     * @param attachment
     */
    setPreviewSource: function (element, file, attachment) {
        if(FileUpload.isPendingTranscodingStatus(attachment.transcoding_status)){
            FileUpload.showVideoEncodingState(file);
        }
        else if(attachment.transcoding_status === 'failed' || attachment.transcoding_status === 'cancelled'){
            FileUpload.showVideoProcessingFailedState(file, attachment);
        }
        else{
            let fileSrc = attachment.path;
            if(attachment.type === 'image'){
                fileSrc = typeof attachment.thumbnail !== 'undefined' ? attachment.thumbnail : attachment.path;
            }
            FileUpload.clearVideoEncodingState(file);
            FileUpload.revokeLocalPreviewUrl(file);
            if(fileSrc){
                $(element).attr('src', fileSrc);
            }

        }
    },

    normalizeAttachment: function (attachment) {
        return {
            attachmentID: attachment.attachmentID || attachment.id,
            id: attachment.id || attachment.attachmentID,
            path: attachment.path || null,
            type: attachment.attachmentType === 'file' ? 'file' : (attachment.type || attachment.attachmentType),
            original_name: attachment.original_name || null,
            extension: attachment.extension || null,
            mime_type: attachment.mime_type || null,
            size: Number(attachment.size) || 0,
            size_label: attachment.size_label || null,
            download_url: attachment.download_url || null,
            can_select: attachment.can_select !== false,
            thumbnail: attachment.thumbnail || null,
            blurred: attachment.blurred || null,
            length: attachment.length || null,
            has_thumbnail: attachment.has_thumbnail || false,
            transcoding_driver: attachment.transcoding_driver || null,
            transcoding_status: attachment.transcoding_status || 'completed',
            transcoding_progress: typeof attachment.transcoding_progress === 'number' ? attachment.transcoding_progress : null,
            transcoding_message: attachment.transcoding_message || null,
            can_retry_transcoding: attachment.can_retry_transcoding === true,
            vault_archived_at: attachment.vault_archived_at || null
        };
    },

    isPendingTranscodingStatus: function (status) {
        return status === 'queued' || status === 'processing';
    },

    hasPendingTranscoding: function () {
        return FileUpload.attachaments.some(function (attachment) {
            return FileUpload.isPendingTranscodingStatus(attachment.transcoding_status);
        });
    },

    hasUnavailableAttachments: function () {
        return FileUpload.attachaments.some(function (attachment) {
            return attachment.transcoding_status && attachment.transcoding_status !== 'completed';
        });
    },

    isBusy: function () {
        return FileUpload.isRestoring || FileUpload.restoreFailed || FileUpload.pendingVaultSelections > 0 || FileUpload.isLoading === true || FileUpload.hasUnavailableAttachments();
    },

    refreshLoadingState: function () {
        if(!FileUpload.myDropzone){
            FileUpload.isLoading = false;
            return;
        }

        FileUpload.isLoading = FileUpload.myDropzone.files.some(function (file) {
            return ['added', 'queued', 'uploading', 'processing'].indexOf(file.status) >= 0;
        });
    },

    refreshTranscodingState: function () {
        FileUpload.isTranscodingVideo = FileUpload.hasPendingTranscoding();
    },

    syncTranscodingStatuses: function () {
        var ids = FileUpload.attachaments.map(function (attachment) {
            return attachment.attachmentID || attachment.id;
        }).filter(Boolean).filter(function (id, index, values) {
            return values.indexOf(id) === index;
        });

        if(!ids.length){
            FileUpload.refreshTranscodingState();
            return;
        }

        for(var offset = 0; offset < ids.length; offset += 50){
            $.ajax({
                type: 'GET',
                url: app.baseUrl + '/attachment/transcoding-statuses',
                data: {ids: ids.slice(offset, offset + 50)},
                success: function (response) {
                    (response.attachments || []).forEach(function (attachment) {
                        FileUpload.applyTranscodingUpdate(attachment);
                    });
                }
            });
        }
    },

    applyTranscodingUpdate: function (data) {
        if(!data || !(data.attachmentID || data.id)) return;

        var id = String(data.attachmentID || data.id);
        var matched = false;
        FileUpload.attachaments = FileUpload.attachaments.map(function (attachment) {
            if(String(attachment.attachmentID || attachment.id) !== id){
                return attachment;
            }

            matched = true;
            return FileUpload.normalizeAttachment(Object.assign({}, attachment, data));
        });

        if(!matched){
            return;
        }

        if(FileUpload.myDropzone){
            FileUpload.myDropzone.files.forEach(function (file) {
                if(!file.upload || String(file.upload.attachmentID) !== id){
                    return;
                }

                var attachment = FileUpload.findAttachment(id);
                if(FileUpload.isPendingTranscodingStatus(attachment.transcoding_status)){
                    FileUpload.showVideoEncodingState(file, attachment.transcoding_progress);
                }
                else if(attachment.transcoding_status === 'completed'){
                    FileUpload.updatePreviewElement(file, false, attachment);
                }
                else{
                    FileUpload.showVideoProcessingFailedState(file, attachment);
                }
            });
        }

        FileUpload.refreshTranscodingState();
        $(document).trigger('attachment:transcoding-update', [FileUpload.findAttachment(id)]);

        if(typeof StoryCreator !== 'undefined' && String(StoryCreator.attachmentID) === id){
            StoryCreator.setActiveAttachment(id);
        }
        if(typeof ReelCreator !== 'undefined' && typeof ReelCreator.updatePreview === 'function'){
            ReelCreator.updatePreview();
            ReelCreator.updateSubmitState();
        }
    },

    findAttachment: function (id) {
        return FileUpload.attachaments.find(function (attachment) {
            return String(attachment.attachmentID || attachment.id) === String(id);
        }) || null;
    },

    getLocalPreviewUrl: function (id) {
        if(!FileUpload.myDropzone) return null;

        var file = FileUpload.myDropzone.files.find(function (candidate) {
            return candidate.upload && String(candidate.upload.attachmentID) === String(id);
        });

        return file && file.upload ? (file.upload.localPreviewUrl || null) : null;
    },

    isVideoFile: function (file) {
        return file && typeof file.type === 'string' && file.type.indexOf('video') === 0;
    },

    isAudioFile: function (file, attachment = false) {
        const attachmentType = attachment && typeof attachment.type === 'string' ? attachment.type.toLowerCase() : '';
        const fileType = file && typeof file.type === 'string' ? file.type.toLowerCase() : '';
        const fileName = file && typeof file.name === 'string' ? file.name.toLowerCase() : '';

        if(attachmentType === 'video' || fileType.indexOf('video/') === 0){
            return false;
        }

        return attachmentType === 'audio' ||
            ['mp3', 'wav', 'ogg', 'm4a', 'webm'].indexOf(attachmentType) >= 0 ||
            fileType.indexOf('audio/') === 0 ||
            /\.(mp3|wav|ogg|m4a|webm)$/i.test(fileName);
    },

    showVideoEncodingState: function (file, progress) {
        if(!file || !file.previewElement) return;

        $(file.previewElement).removeClass('is-video-processing-failed').addClass('is-video-processing');

        var hasLocalPreview = file.upload && file.upload.localPreviewPlayable === true;
        if(hasLocalPreview){
            FileUpload.clearVideoPlaceholder(file);
            if(file.upload.localPreviewUrl){
                $(file.previewElement).find('video').attr('src', file.upload.localPreviewUrl);
            }
            $(file.previewElement).find('.video-preview-item').addClass('is-encoding');
            FileUpload.updateVideoEncodingProgress(file, progress);
        }
        else{
            FileUpload.showVideoPlaceholder(file, trans('Encoding'), trans('Preview will appear when conversion finishes.'));
            FileUpload.updateVideoEncodingProgress(file, progress);
        }
    },

    updateVideoEncodingProgress: function (file, progress) {
        if(!file || !file.previewElement || typeof progress !== 'number') return;

        var preview = $(file.previewElement);
        preview.find('.video-encoding-progress').remove();
        preview.find('.video-encoding-overlay, .video-preview-placeholder').append(
            `<div class="video-encoding-progress">${Math.max(0, Math.min(100, progress))}%</div>`
        );
    },

    showVideoProcessingFailedState: function (file, attachment) {
        if(!file || !file.previewElement) return;

        var message = attachment.can_retry_transcoding ? '' : trans('Upload again');
        FileUpload.showVideoPlaceholder(file, trans('Processing failed'), message);
        $(file.previewElement).removeClass('is-video-processing').addClass('is-video-processing-failed');

        if(attachment.can_retry_transcoding){
            var button = $(`<button type="button" class="btn btn-sm btn-light mt-2 video-processing-retry">${trans('Retry')}</button>`);
            button.on('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                FileUpload.retryTranscoding(attachment.attachmentID || attachment.id);
            });
            $(file.previewElement).find('.video-preview-placeholder').append(button);
        }
    },

    retryTranscoding: function (attachmentId) {
        $.ajax({
            type: 'POST',
            url: app.baseUrl + '/attachment/transcoding/retry',
            data: {attachmentId: attachmentId},
            success: function (response) {
                FileUpload.applyTranscodingUpdate(response);
                launchToast('success', trans('Success'), trans('Video processing was queued again.'));
            },
            error: function (xhr) {
                var response = xhr.responseJSON || {};
                launchToast('danger', trans('Error'), response.message || trans('The video could not be retried.'));
            }
        });
    },

    discardUnavailableAttachments: function () {
        if(!FileUpload.myDropzone) return;

        FileUpload.myDropzone.files.slice().forEach(function (file) {
            var attachment = file.upload ? FileUpload.findAttachment(file.upload.attachmentID) : null;
            if(!attachment || attachment.transcoding_status !== 'completed'){
                if(typeof FileUpload.myDropzone.cancelUpload === 'function'){
                    FileUpload.myDropzone.cancelUpload(file);
                }
                FileUpload.myDropzone.removeFile(file);
            }
        });

        FileUpload.isLoading = false;
        FileUpload.refreshTranscodingState();
    },

    showUnfinishedUploadsDialog: function (onDiscard) {
        if(FileUpload.isRestoring || FileUpload.restoreFailed || FileUpload.pendingVaultSelections > 0) return;
        var hasProcessing = FileUpload.hasUnavailableAttachments();
        var message = hasProcessing
            ? trans('A video is still processing or needs attention. You can keep waiting, or remove unfinished media before continuing.')
            : trans('Some attachments are still being uploaded. You can keep waiting, or continue without unfinished uploads.');
        var modal = $('#confirm-post-save');
        var continueButton = modal.find('.confirm-post-save');

        modal.find('.modal-body p').text(message);
        continueButton.off('click');

        if(typeof onDiscard === 'function'){
            continueButton
                .removeClass('d-none')
                .text(trans('Remove and continue'))
                .on('click', function () {
                    FileUpload.discardUnavailableAttachments();
                    modal.modal('hide');
                    onDiscard();
                });
        }
        else{
            continueButton.addClass('d-none');
        }

        modal.modal('show');
    },

    clearVideoEncodingState: function (file) {
        if(!file || !file.previewElement) return;
        $(file.previewElement).removeClass('is-video-processing');
        $(file.previewElement).removeClass('is-video-processing-failed');
        $(file.previewElement).find('.video-preview-item').removeClass('is-encoding video-preview-item--placeholder');
        $(file.previewElement).find('.video-encoding-overlay, .video-preview-placeholder').remove();
        $(file.previewElement).find('video').removeClass('d-none');
    },

    showVideoPlaceholder: function (file, title, subtitle) {
        if(!file || !file.previewElement) return;

        var filePreview = $(file.previewElement);
        var videoItem = filePreview.find('.video-preview-item').first();
        if(!videoItem.length){
            filePreview.find('.dz-image').remove();
            filePreview.prepend(videoPreview());
            videoItem = filePreview.find('.video-preview-item').first();
        }

        videoItem.removeClass('is-encoding').addClass('video-preview-item--placeholder');
        videoItem.find('video').addClass('d-none').removeAttr('src');
        videoItem.find('.video-encoding-overlay, .video-preview-placeholder').remove();
        videoItem.append(videoPreviewPlaceholder(title, subtitle));
    },

    clearVideoPlaceholder: function (file) {
        if(!file || !file.previewElement) return;

        var videoItem = $(file.previewElement).find('.video-preview-item');
        videoItem.removeClass('video-preview-item--placeholder');
        videoItem.find('.video-preview-placeholder').remove();
        videoItem.find('video').removeClass('d-none');
        if(!videoItem.find('.video-encoding-overlay').length){
            videoItem.append(videoEncodingOverlay());
        }
    },

    showAudioPlaceholder: function (file, title, subtitle) {
        if(!file || !file.previewElement) return;

        var filePreview = $(file.previewElement);
        var audioItem = filePreview.find('.audio-preview-item').first();
        if(!audioItem.length){
            filePreview.find('.dz-image').remove();
            filePreview.prepend(audioPreview());
            audioItem = filePreview.find('.audio-preview-item').first();
        }

        audioItem.find('audio').addClass('d-none').removeAttr('src');
        audioItem.find('.audio-preview-placeholder').remove();
        audioItem.append(audioPreviewPlaceholder(title, subtitle));
    },

    revokeLocalPreviewUrl: function (file) {
        if(file && file.upload && file.upload.localPreviewUrl){
            window.URL.revokeObjectURL(file.upload.localPreviewUrl);
            file.upload.localPreviewUrl = null;
        }
    },

    /**
     * Removes an attached file
     * @param attachmentID
     */
    removeAttachment: function (attachmentID) {
        if(typeof attachmentID === 'object' && attachmentID !== null){
            attachmentID = attachmentID.attachmentID || attachmentID.id;
        }
        if(FileUpload.socialContext){
            const file = FileUpload.myDropzone && FileUpload.myDropzone.files.find(function (candidate) {
                return candidate.upload && String(candidate.upload.attachmentID) === String(attachmentID);
            });
            if(file) FileUpload.myDropzone.removeFile(file);
            FileUpload.attachaments = FileUpload.attachaments.filter(function (attachment) { return String(attachment.attachmentID) !== String(attachmentID); });
            return;
        }
        $.ajax({
            type: 'POST',
            data: {
                'attachmentId': attachmentID,
            },
            url: app.baseUrl+'/attachment/remove',
            success: function () {
                launchToast('success',trans('Success'), trans('Attachment removed.'));
            },
            error: function () {
                launchToast('danger',trans('Error'), trans('Failed to remove the attachment.'));
            }
        });
    },

};

/**
 * Video preview Component
 * @returns {string}
 */
function videoPreview() {
    return `<div class="video-preview-item shadow">
                <span data-dz-name></span>
                <span data-dz-size></span>
            <video class="video-preview" controls autoplay muted loop></video>
        </div>`;
}

function videoEncodingOverlay() {
    return `<div class="video-encoding-overlay">
                <div class="spinner-border spinner-border-sm" role="status">
                    <span class="visually-hidden">${trans('Loading...')}</span>
                </div>
                <span>${trans('Encoding')}</span>
        </div>`;
}

function videoPreviewPlaceholder(title, subtitle) {
    return `<div class="video-preview-placeholder video-preview-placeholder--theme">
                <div class="video-preview-placeholder-icon">
                    <ion-icon name="videocam-outline"></ion-icon>
                </div>
                <div class="video-preview-placeholder-title">${title}</div>
                ${subtitle ? `<div class="video-preview-placeholder-subtitle">${subtitle}</div>` : ''}
        </div>`;
}

/**
 * Image preview Component
 * @returns {string}
 */
function imagePreview() {
    return `<div class="dz-image shadow">
            <img data-dz-thumbnail/>
        </div>
        <div class="dz-details">
            <div class="dz-filename"><span data-dz-name></span></div>
            <div class="dz-size" data-dz-size></div>
        </div>`;
}

/**
 * Audio preview Component
 * @returns {string}
 */
function audioPreview() {
    return `<div class="audio-preview-item">
                    <span data-dz-name></span>
                    <span data-dz-size></span>
                <audio id="audio-preview" controls type="audio/mpeg"></audio>
        </div>`;
}

function audioPreviewPlaceholder(title, subtitle) {
    return `<div class="audio-preview-placeholder small text-muted">
                <div>${title}</div>
                <div>${subtitle}</div>
        </div>`;
}

/**
 * Pdf document preview Component
 * @returns {string}
 */
function pdfPreview() {
    return `<div class="pdf-preview-item">
                    <div class="dz-image shadow p-4">
                        <img data-dz-thumbnail src="${mediaSettings.manual_payments_pdf_icon}"/>
                    </div>
                    <span data-dz-name></span>
                    <span data-dz-size></span>
        </div>`;
}

/**
 * Excel document preview Component
 * @returns {string}
 */
function excelPreview() {
    return `<div class="xls-preview-item">
                    <div class="dz-image shadow p-4">
                        <img data-dz-thumbnail src="${mediaSettings.manual_payments_excel_icon}"/>
                    </div>
                    <span data-dz-name></span>
                    <span data-dz-size></span>
        </div>`;
}
