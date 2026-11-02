"use strict";
/* global PostCreate, FileUpload, trans, getWebsiteFormattedAmount, YouTubeEmbed, Sortable */

// Presentation only: pricing, polls and scheduling remain owned by PostCreate.
var PostComposer = {
    root: null,
    startedAt: 0,
    youtubePreview: null,
    youtubeAllowedHosts: null,

    init: function () {
        PostComposer.root = document.querySelector('.post-composer');
        if(!PostComposer.root) return;
        PostComposer.startedAt = Date.now();
        PostCreate.postReleaseDate = $('#post_release_date').val() || null;
        PostCreate.postExpireDate = $('#post_expire_date').val() || null;
        PostComposer.initYouTubePreview();
        PostComposer.refresh();
    },

    initYouTubePreview: function () {
        PostComposer.youtubePreview = null;
        PostComposer.youtubeAllowedHosts = null;
        if(PostComposer.root.dataset.enableYoutubeEmbeds !== 'true' || typeof YouTubeEmbed === 'undefined') return;
        const preview = PostComposer.root.querySelector('.post-composer-youtube-preview');
        if(!preview) return;
        try {
            PostComposer.youtubeAllowedHosts = JSON.parse(PostComposer.root.dataset.youtubeAllowedHosts);
            if(!Array.isArray(PostComposer.youtubeAllowedHosts)) return;
        } catch (error) {
            // Saving still validates links on the server; hide a preview with an invalid policy.
            return;
        }
        PostComposer.youtubePreview = preview;
        $('#dropzone-uploader').off('.postComposerYouTube')
            .on('input.postComposerYouTube change.postComposerYouTube', PostComposer.refreshYouTubePreview)
            .on('paste.postComposerYouTube', function () {
                // The paste event precedes the textarea's new value.
                window.requestAnimationFrame(PostComposer.refreshYouTubePreview);
            });
    },

    refreshYouTubePreview: function () {
        const preview = PostComposer.youtubePreview;
        if(!preview) return;
        const videoIds = YouTubeEmbed.videoIds($('#dropzone-uploader').val(), PostComposer.youtubeAllowedHosts);
        preview.hidden = videoIds.length === 0;
        preview.querySelector('.post-composer-youtube-title').textContent = videoIds.length === 1
            ? trans('YouTube video')
            : trans(':count YouTube videos').replace(':count', videoIds.length);
    },

    // Keep the shared uploader's previews; only track the post's count and order.
    bindUploads: function (dropzone) {
        PostComposer.initMediaSorting(dropzone);
        ['addedfile', 'removedfile'].forEach(function (event) {
            dropzone.on(event, function () {
                PostComposer.updateMediaCount(dropzone);
                PostComposer.updateMediaOrderControls(dropzone);
                PostCreate.scheduleDraftSave();
            });
        });
        dropzone.on('success', function (file, response) {
            if(!response.success || dropzone.files.indexOf(file) < 0) return;
            // A faster upload must not jump ahead of an earlier selection.
            PostComposer.syncAttachmentOrder(dropzone);
            PostCreate.scheduleDraftSave();
        });
        PostComposer.updateMediaCount(dropzone);
        PostComposer.updateMediaOrderControls(dropzone);
    },

    initMediaSorting: function (dropzone) {
        const grid = dropzone.previewsContainer;
        if(!grid) return;
        if(typeof Sortable !== 'undefined') new Sortable(grid, {
            draggable: '.dz-preview',
            handle: '.post-media-drag',
            // Row-based detection also handles RTL grids and full-width audio previews.
            direction: 'vertical',
            animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 150,
            ghostClass: 'post-media-sort-ghost',
            filter: function () { return PostCreate.isSaving || FileUpload.isRestoring || FileUpload.restoreFailed; },
            preventOnFilter: false,
            onEnd: function (event) {
                if(event.oldDraggableIndex !== event.newDraggableIndex) PostComposer.syncMediaOrder(dropzone, event.item);
            }
        });
        grid.addEventListener('keydown', function (event) {
            const button = event.target.closest('.post-media-drag');
            let offset = {ArrowUp: -1, ArrowDown: 1, ArrowLeft: -1, ArrowRight: 1}[event.key];
            if(!button || !grid.contains(button) || offset === undefined || PostCreate.isSaving || FileUpload.isRestoring || FileUpload.restoreFailed) return;
            event.preventDefault();
            if((event.key === 'ArrowLeft' || event.key === 'ArrowRight') && window.getComputedStyle(grid).direction === 'rtl') offset *= -1;
            const preview = button.closest('.dz-preview');
            const previews = Array.from(grid.children).filter(function (item) { return item.matches('.dz-preview'); });
            const adjacent = previews[previews.indexOf(preview) + offset];
            if(!adjacent) return;
            grid.insertBefore(preview, offset < 0 ? adjacent : adjacent.nextSibling);
            PostComposer.syncMediaOrder(dropzone, preview);
            // Moving a node can drop focus; keep keyboard users with that attachment.
            button.focus({preventScroll: true});
        });
    },

    syncAttachmentOrder: function (dropzone) {
        const ids = dropzone.files.map(function (file) { return String(file.upload && file.upload.attachmentID); });
        FileUpload.attachaments.sort(function (a, b) { return ids.indexOf(String(a.attachmentID)) - ids.indexOf(String(b.attachmentID)); });
    },

    syncMediaOrder: function (dropzone, movedPreview) {
        const ordered = Array.from(dropzone.previewsContainer.children).map(function (preview) {
            return dropzone.files.find(function (file) { return file.previewElement === preview; });
        }).filter(Boolean);
        // Pending files have no server ID yet. Keep their file objects in visual order too.
        dropzone.files = ordered.concat(dropzone.files.filter(function (file) { return !ordered.includes(file); }));
        PostComposer.syncAttachmentOrder(dropzone);
        PostComposer.updateMediaOrderControls(dropzone);
        PostCreate.scheduleDraftSave();
        const position = dropzone.files.findIndex(function (file) { return file.previewElement === movedPreview; }) + 1;
        $(PostComposer.root).find('.post-media-order-status').text(trans('Attachment moved to position :position of :count.')
            .replace(':position', position).replace(':count', dropzone.files.length));
    },

    updateMediaOrderControls: function (dropzone) {
        dropzone.files.forEach(function (file, index) {
            const controls = file.previewElement && file.previewElement.querySelector('.post-media-drag');
            if(!controls) return;
            controls.hidden = dropzone.files.length < 2;
            controls.setAttribute('aria-label', trans('Reorder attachment :position of :count').replace(':position', index + 1).replace(':count', dropzone.files.length));
        });
    },

    updateMediaCount: function (dropzone) {
        const count = dropzone.files.length;
        let label = count === 1 ? trans('1 attachment') : trans(':count attachments').replace(':count', count);
        if(count > 1) label += ' · ' + trans('Drag to reorder');
        $(PostComposer.root).find('.post-composer-media-count').prop('hidden', count === 0)
            .text(label);
        $(PostComposer.root).find('.post-composer-file-order').prop('hidden', !dropzone.files.some(function (file) {
            return FileUpload.isFileAttachment && FileUpload.isFileAttachment(file);
        }));
    },

    submitLabel: function () {
        const root = PostComposer.root;
        if(!root) return trans('Save');
        if(root.dataset.mode === 'edit') return trans('Save changes');
        // datetime-local fields use server wall time, not the viewer's timezone.
        const serverNow = Date.parse(root.dataset.serverTime + 'Z') + Date.now() - PostComposer.startedAt;
        const release = PostCreate.postReleaseDate && Date.parse(PostCreate.postReleaseDate.replace(' ', 'T') + 'Z');
        return release > serverNow ? trans('Schedule post') : trans('Post');
    },

    refresh: function () {
        if(!PostComposer.root) return;
        PostComposer.refreshYouTubePreview();
        const root = $(PostComposer.root);
        const paid = Number(PostCreate.postPrice) > 0;
        const price = paid ? getWebsiteFormattedAmount(PostCreate.postPrice) : trans('Not set');
        root.find('.post-price-label').text(paid ? price : '');
        root.find('.post-price-summary').prop('hidden', !paid);
        root.find('.post-price-button').attr('aria-label', trans('Set post price') + ': ' + price);
        const dates = [];
        if(PostCreate.postReleaseDate) dates.push(trans('Release') + ': ' + PostCreate.postReleaseDate.replace('T', ' '));
        if(PostCreate.postExpireDate) dates.push(trans('Expires') + ': ' + PostCreate.postExpireDate.replace('T', ' '));
        root.find('.post-schedule-summary').prop('hidden', !dates.length).find('span').last()
            .text(dates.join(' · ') + (dates.length ? ' (' + PostComposer.root.dataset.timezone + ')' : ''));
        root.find('.post-poll-summary').prop('hidden', !PostCreate.pollAnswers.length);
        root.find('.post-poll-check').prop('hidden', !PostCreate.pollAnswers.length);
        root.find('#post-poll-state').text(trans(PostCreate.pollAnswers.length ? 'Configured' : 'Not set'));
        root.find('.post-schedule-check').prop('hidden', !dates.length);
        root.find('#post-schedule-state').text(trans(dates.length ? 'Configured' : 'Not set'));
        root.find('.post-notification-summary, .post-notification-check').prop('hidden', !PostCreate.postNotifications);
        root.find('.post-notification-button').attr('aria-pressed', String(PostCreate.postNotifications));
        const hasSettings = (paid && root.find('.post-price-summary').length)
            || (dates.length && root.find('.post-schedule-summary').length)
            || (PostCreate.pollAnswers.length && root.find('.post-poll-summary').length)
            || (PostCreate.postNotifications && root.find('.post-notification-summary').length);
        root.find('.post-composer-settings').prop('hidden', !hasSettings);
        if(!PostCreate.isSaving) root.find('.post-create-button').text(PostComposer.submitLabel());
    }
};
