/**
 * Reel creator component
 */
"use strict";
/* global app, FileUpload, SoundSelect, AiSuggestions, launchToast, trans, redirect, mediaSettings, isAllowedToPost, user */

$(function () {
    ReelCreator.init();
    if (app.ai_text_enabled && window.AiSuggestions) {
        AiSuggestions.init({
            type: "reel",
            target: "#reel-caption",
            editorGetter: null
        });
    }
});

window.addEventListener("beforeunload", function (event) {
    if (FileUpload.isLoading === true) {
        event.preventDefault();
        event.returnValue = '';
    }

    ReelCreator.saveDraftData(true);
});

var ReelCreator = {
    soundSelect: null,
    isSubmitting: false,
    isPublishingRedirect: false,
    isRestoringDraft: false,
    draftSaveTimer: null,
    videoDurationByAttachmentId: {},

    init: function () {
        this.cacheDom();
        this.initSoundSelect();
        this.restoreDraftData();
        this.bindEvents();

        if (this.canUpload()) {
            FileUpload.initDropZone(".reel-upload-zone", "/attachment/upload/reel", false, {
                maxFiles: 2
            });

            this.hookUploader();
        } else {
            this.disableUploader();
        }

        this.updatePreview();
        this.updateSubmitState();
    },

    canUpload: function () {
        if (typeof isAllowedToPost !== "undefined" && !isAllowedToPost) {
            return false;
        }

        return !(typeof mediaSettings !== "undefined" && mediaSettings.initUploader === false);
    },

    disableUploader: function () {
        var $zone = $(".reel-upload-zone");

        $zone
            .addClass("is-disabled disabled")
            .attr("aria-disabled", "true");

        if (!$zone.find(".upload-zone-disabled-message").length) {
            $zone.append('<div class="upload-zone-disabled-message">' + trans("Drop files here to upload") + "</div>");
        }
    },

    cacheDom: function () {
        this.el = {
            caption: $("#reel-caption"),
            isPublic: $("#reel-is-public"),
            submit: $("#btn-reel-share"),
            clearDraft: $("#btn-reel-clear-draft"),
            preview: $("#reel-preview"),
            previewVideo: $("#reel-preview-video"),
            previewCover: $("#reel-preview-cover"),
            previewEmpty: $(".reel-preview-empty"),
            previewCaption: $(".reel-preview-caption"),
            previewSound: $(".reel-preview-sound"),
            orientationWarning: $("#reel-orientation-warning")
        };
    },

    initSoundSelect: function () {
        if (!window.SoundSelect || typeof SoundSelect.create !== "function" || !$("#reel-storySoundSelect").length) {
            return;
        }

        this.soundSelect = SoundSelect.create({
            baseUrl: app.baseUrl,
            inputId: "reel-storySoundSelect",
            soundIdId: "reel-storySoundId",
            soundTitleId: "reel-storySoundTitle",
            soundArtistId: "reel-storySoundArtist",
            soundUrlId: "reel-storySoundUrl",
            soundStartMsId: "reel-storySoundStartMs",
            enablePreview: false,
            previewSeconds: 8
        });
        this.hookSoundSelectPreview();
        this.soundSelect.init();
    },

    hookSoundSelectPreview: function () {
        var self = this;

        if (!this.soundSelect) {
            return;
        }

        ["applySelection", "clearSelection"].forEach(function (method) {
            var original = self.soundSelect[method];

            if (typeof original !== "function") {
                return;
            }

            self.soundSelect[method] = function () {
                var result = original.apply(this, arguments);
                self.updatePreviewText();
                self.scheduleDraftSave();
                return result;
            };
        });
    },

    bindEvents: function () {
        var self = this;

        this.el.caption.on("input", function () {
            self.updatePreviewText();
            self.updateSubmitState();
            self.scheduleDraftSave();
        });

        $("#reel-storySoundTitle, #reel-storySoundArtist").on("change input", function () {
            self.updatePreviewText();
            self.scheduleDraftSave();
        });

        this.el.isPublic.on("change", function () {
            self.scheduleDraftSave();
        });

        this.el.clearDraft.on("click", function () {
            self.clearDraft();
        });

        this.el.submit.on("click", function () {
            self.submit();
        });

        this.el.previewVideo.on("loadedmetadata", function () {
            self.updateOrientationWarning(this);
            self.rememberPreviewVideoDuration(this);
            self.updateSubmitState();
        });
    },

    hookUploader: function () {
        var self = this;

        if (!FileUpload || !FileUpload.myDropzone) {
            return;
        }

        FileUpload.myDropzone.on("success", function (file, response) {
            self.rememberUploadedVideoDuration(file, response);
            self.refreshUploadLabels();
            self.updatePreview();
            self.updateSubmitState();
            self.scheduleDraftSave();
        });

        FileUpload.myDropzone.on("addedfile", function (file) {
            self.bindLocalVideoDuration(file);
            self.refreshUploadLabels();
            self.updateSubmitState();
            self.scheduleDraftSave();
        });

        FileUpload.myDropzone.on("complete", function () {
            self.refreshUploadLabels();
            self.updateSubmitState();
            self.scheduleDraftSave();
        });

        FileUpload.myDropzone.on("removedfile", function () {
            self.refreshUploadLabels();
            self.updatePreview();
            self.updateSubmitState();
            self.scheduleDraftSave();
        });

        FileUpload.myDropzone.on("error", function () {
            window.setTimeout(function () {
                self.refreshUploadLabels();
                self.updatePreview();
                self.updateSubmitState();
                self.scheduleDraftSave();
            }, 0);
        });
    },

    getDraftStorageKey: function () {
        var userId = window.user && user.user_id ? user.user_id : "guest";
        return "reelDraft:" + userId;
    },

    getSoundDraftData: function () {
        return {
            id: $("#reel-storySoundId").val() || "",
            title: $("#reel-storySoundTitle").val() || "",
            artist: $("#reel-storySoundArtist").val() || "",
            url: $("#reel-storySoundUrl").val() || "",
            start_ms: $("#reel-storySoundStartMs").val() || "0"
        };
    },

    getCurrentDraftData: function () {
        return {
            caption: this.el.caption.val() || "",
            is_public: this.el.isPublic.length && this.el.isPublic.is(":checked") ? 1 : 0,
            sound: this.getSoundDraftData(),
            attachments: this.serializeAttachments(),
            updated_at: Date.now()
        };
    },

    serializeAttachments: function () {
        return FileUpload.getDraftAttachments();
    },

    isDraftEmpty: function (draft) {
        if (!draft) {
            return true;
        }

        var sound = draft.sound || {};

        return !(draft.caption || "").trim().length &&
            !(draft.attachments || []).length &&
            !sound.id &&
            !draft.is_public;
    },

    scheduleDraftSave: function () {
        var self = this;

        if (this.isRestoringDraft || this.isPublishingRedirect) {
            return;
        }

        window.clearTimeout(this.draftSaveTimer);
        this.draftSaveTimer = window.setTimeout(function () {
            self.saveDraftData(false);
        }, 150);
    },

    saveDraftData: function (force) {
        if (this.isPublishingRedirect || (this.isRestoringDraft && force !== true)) {
            return;
        }

        var draft = this.getCurrentDraftData();

        try {
            if (this.isDraftEmpty(draft)) {
                localStorage.removeItem(this.getDraftStorageKey());
                return;
            }

            localStorage.setItem(this.getDraftStorageKey(), JSON.stringify(draft));
        } catch (error) {
            return;
        }
    },

    clearDraftData: function () {
        try {
            localStorage.removeItem(this.getDraftStorageKey());
        } catch (error) {
            return;
        }
    },

    restoreDraftData: function () {
        var draft = null;

        try {
            draft = JSON.parse(localStorage.getItem(this.getDraftStorageKey()) || "null");
        } catch (error) {
            draft = null;
        }

        if (!draft || this.isDraftEmpty(draft)) {
            return;
        }

        this.isRestoringDraft = true;
        FileUpload.attachaments = (draft.attachments || []).map(function (attachment) {
            return {attachmentID: attachment.attachmentID || attachment.id};
        });
        this.el.caption.val(draft.caption || "");
        this.el.isPublic.prop("checked", !!draft.is_public);
        this.restoreSoundSelection(draft.sound || {});
        this.isRestoringDraft = false;
    },

    restoreSoundSelection: function (sound) {
        $("#reel-storySoundId").val(sound.id || "");
        $("#reel-storySoundTitle").val(sound.title || "");
        $("#reel-storySoundArtist").val(sound.artist || "");
        $("#reel-storySoundUrl").val(sound.url || "");
        $("#reel-storySoundStartMs").val(sound.start_ms || "0");

        if (!this.soundSelect || !this.soundSelect.selectize || !sound.id) {
            return;
        }

        this.soundSelect.selectize.addOption({
            id: String(sound.id),
            title: sound.title || trans("Untitled"),
            artist: sound.artist || "",
            url: sound.url || ""
        });
        this.soundSelect.selectize.addItem(String(sound.id), true);
    },

    clearDraft: function () {
        FileUpload.clearSelection();

        FileUpload.attachaments = [];
        this.videoDurationByAttachmentId = {};
        this.el.caption.val("");
        this.el.isPublic.prop("checked", false);
        this.restoreSoundSelection({});

        if (this.soundSelect && this.soundSelect.selectize) {
            this.soundSelect.selectize.clear(true);
        }

        this.clearDraftData();
        this.updatePreview();
        this.updateSubmitState();
    },

    getAttachments: function () {
        return FileUpload.attachaments || [];
    },

    getVideoAttachment: function () {
        return this.getVideoAttachments()[0] || null;
    },

    getVideoAttachments: function () {
        return this.getAttachments().filter(function (attachment) {
            return ReelCreator.isAttachmentType(attachment, "video");
        });
    },

    getCoverAttachment: function () {
        return this.getCoverAttachments()[0] || null;
    },

    getCoverAttachments: function () {
        return this.getAttachments().filter(function (attachment) {
            return ReelCreator.isAttachmentType(attachment, "image");
        });
    },

    getUnsupportedAttachments: function () {
        return this.getAttachments().filter(function (attachment) {
            return !ReelCreator.isAttachmentType(attachment, "video") && !ReelCreator.isAttachmentType(attachment, "image");
        });
    },

    isAttachmentType: function (attachment, type) {
        var attachmentType = String((attachment && attachment.type) || "").toLowerCase();
        return attachmentType === type || attachmentType.indexOf(type) === 0;
    },

    isUploaderBusy: function () {
        return !!(FileUpload && FileUpload.isBusy());
    },

    updateSubmitState: function () {
        var disabled = this.isSubmitting || !this.canUpload();
        this.el.submit.prop("disabled", disabled);

        if (!this.canUpload()) {
            this.el.submit.attr("title", trans("User not verified. Can not post content."));
            return;
        }

        this.el.submit.removeAttr("title");
    },

    validateUploads: function () {
        if (this.isUploaderBusy()) {
            FileUpload.showUnfinishedUploadsDialog();
            return null;
        }

        var unsupported = this.getUnsupportedAttachments();
        if (unsupported.length) {
            launchToast("danger", trans("Error"), trans("Reels can only include one video and one optional image cover."));
            return null;
        }

        var covers = this.getCoverAttachments();
        if (covers.length > 1) {
            launchToast("danger", trans("Error"), trans("Please keep only one cover image."));
            return null;
        }

        var videos = this.getVideoAttachments();
        if (videos.length > 1) {
            launchToast("danger", trans("Error"), trans("Please keep only one video for this reel."));
            return null;
        }

        if (!videos.length) {
            launchToast("danger", trans("Error"), trans("Please upload one video first."));
            return null;
        }

        if (!this.validateVideoDuration(videos[0])) {
            return null;
        }

        return {
            video: videos[0],
            cover: covers[0] || null
        };
    },

    updatePreview: function () {
        this.refreshUploadLabels();
        this.updatePreviewText();
        this.updateSubmitState();

        var video = this.getVideoAttachment();
        var cover = this.getCoverAttachment();
        if (video) {
            this.el.preview.removeClass("is-empty is-no-media");
            this.el.previewEmpty.addClass("d-none");
            this.el.previewCover.addClass("d-none");
            this.setPreviewMediaSource(this.el.previewCover, "");
            this.el.previewVideo.removeClass("d-none");
            this.setPreviewMediaSource(
                this.el.previewVideo,
                video.path || video.thumbnail || FileUpload.getLocalPreviewUrl(video.attachmentID) || ""
            );
            this.playPreviewVideo();
            this.checkVideoOrientation(video);
            return;
        }

        if (cover) {
            this.el.preview.removeClass("is-empty is-no-media");
            this.el.previewEmpty.addClass("d-none");
            this.el.previewVideo.addClass("d-none");
            this.pausePreviewVideo();
            this.setPreviewMediaSource(this.el.previewVideo, "");
            this.el.previewCover.removeClass("d-none");
            this.setPreviewMediaSource(this.el.previewCover, cover.path || cover.thumbnail || "");
            this.hideOrientationWarning();
            return;
        }

        this.el.previewVideo.addClass("d-none");
        this.pausePreviewVideo();
        this.el.previewCover.addClass("d-none");
        this.setPreviewMediaSource(this.el.previewVideo, "");
        this.setPreviewMediaSource(this.el.previewCover, "");
        this.el.preview.addClass("is-empty is-no-media");
        this.el.previewEmpty.removeClass("d-none");
        this.hideOrientationWarning();
    },

    updatePreviewText: function () {
        var caption = (this.el.caption.val() || "").trim();
        this.el.previewCaption
            .toggleClass("d-none", !caption.length)
            .text(caption);

        var soundTitle = ($("#reel-storySoundTitle").val() || "").trim();
        var soundArtist = ($("#reel-storySoundArtist").val() || "").trim();
        var soundText = soundTitle;

        if (soundTitle && soundArtist) {
            soundText += " - " + soundArtist;
        }

        this.el.previewSound
            .toggleClass("d-none", !soundText.length)
            .find("span")
            .text(soundText);
    },

    playPreviewVideo: function () {
        var video = this.el.previewVideo.get(0);

        if (!video) {
            return;
        }

        video.muted = true;

        var playPromise;

        try {
            playPromise = video.play();
        } catch (e) {
            return;
        }

        if (playPromise && typeof playPromise.catch === "function") {
            playPromise.catch(function () {});
        }
    },

    pausePreviewVideo: function () {
        var video = this.el.previewVideo.get(0);

        if (video && typeof video.pause === "function") {
            video.pause();
        }
    },

    setPreviewMediaSource: function ($element, src) {
        var nextSrc = src || "";
        var currentSrc = $element.attr("src") || "";

        if (currentSrc === nextSrc) {
            return;
        }

        $element.attr("src", nextSrc);
    },

    refreshUploadLabels: function () {
        if (!FileUpload || !FileUpload.myDropzone || !FileUpload.myDropzone.files) {
            return;
        }

        FileUpload.myDropzone.files.forEach(function (file) {
            if (!file || !file.previewElement) {
                return;
            }

            var label = ReelCreator.getUploadLabel(file);
            var preview = $(file.previewElement);
            var badge = preview.find(".reel-upload-badge");

            if (!label) {
                badge.remove();
                return;
            }

            if (!badge.length) {
                badge = $('<span class="reel-upload-badge"></span>');
                preview.append(badge);
            }

            badge.text(label);
        });
    },

    getUploadLabel: function (file) {
        var type = String(file.type || "").toLowerCase();

        if (type.indexOf("video") === 0 || type === "video") {
            return trans("Video");
        }

        if (type.indexOf("image") === 0 || $(file.previewElement).find(".dz-image").length) {
            return trans("Cover");
        }

        return "";
    },

    getMaxVideoLength: function () {
        return window.app && app.reels && app.reels.maxVideoLengthSeconds ? Number(app.reels.maxVideoLengthSeconds) : 0;
    },

    isFiniteDuration: function (duration) {
        var value = Number(duration);
        return isFinite(value) && value > 0;
    },

    getAttachmentById: function (attachmentId) {
        return this.getAttachments().filter(function (attachment) {
            return String(attachment.attachmentID) === String(attachmentId);
        })[0] || null;
    },

    rememberPreviewVideoDuration: function (videoElement) {
        var video = this.getVideoAttachment();

        if (!video || !video.attachmentID || !this.isFiniteDuration(videoElement.duration)) {
            return;
        }

        video.length = Math.ceil(Number(videoElement.duration));
        this.videoDurationByAttachmentId[video.attachmentID] = Number(videoElement.duration);
    },

    bindLocalVideoDuration: function (file) {
        var self = this;

        if (!FileUpload.isVideoFile(file)) {
            return;
        }

        window.setTimeout(function () {
            var videoElement = file && file.previewElement ? $(file.previewElement).find("video").get(0) : null;
            if (!videoElement) {
                return;
            }

            var rememberDuration = function () {
                if (!self.isFiniteDuration(videoElement.duration)) {
                    return;
                }

                file.upload = file.upload || {};
                file.upload.reelDuration = Number(videoElement.duration);

                if (file.upload.attachmentID) {
                    self.videoDurationByAttachmentId[file.upload.attachmentID] = Number(videoElement.duration);
                }
            };

            if (videoElement.readyState >= 1) {
                rememberDuration();
                return;
            }

            $(videoElement).one("loadedmetadata", rememberDuration);
        }, 0);
    },

    rememberUploadedVideoDuration: function (file, response) {
        if (!response || !response.attachmentID) {
            return;
        }

        var attachment = this.getAttachmentById(response.attachmentID);
        var duration = this.isFiniteDuration(response.length) ? Number(response.length) : null;

        if (!duration && file && file.upload && this.isFiniteDuration(file.upload.reelDuration)) {
            duration = Number(file.upload.reelDuration);
        }

        if (!duration) {
            return;
        }

        this.videoDurationByAttachmentId[response.attachmentID] = duration;

        if (attachment) {
            attachment.length = Math.ceil(duration);
        }
    },

    getVideoDuration: function (video) {
        if (!video) {
            return null;
        }

        if (this.isFiniteDuration(video.length)) {
            return Math.ceil(Number(video.length));
        }

        if (this.isFiniteDuration(video.duration)) {
            return Math.ceil(Number(video.duration));
        }

        if (video.attachmentID && this.isFiniteDuration(this.videoDurationByAttachmentId[video.attachmentID])) {
            return Math.ceil(Number(this.videoDurationByAttachmentId[video.attachmentID]));
        }

        return null;
    },

    validateVideoDuration: function (video) {
        var maxLength = this.getMaxVideoLength();
        var duration = this.getVideoDuration(video);

        if (!maxLength || !duration) {
            return true;
        }

        if (duration > maxLength) {
            launchToast("danger", trans("Error"), trans("Video is too long."));
            return false;
        }

        return true;
    },

    checkVideoOrientation: function (video) {
        var videoElement = this.el.previewVideo.get(0);

        if (!videoElement) {
            this.hideOrientationWarning();
            return;
        }

        if (videoElement.readyState >= 1 && videoElement.videoWidth && videoElement.videoHeight) {
            this.updateOrientationWarning(videoElement);
            return;
        }

        if (!video || !video.path) {
            this.hideOrientationWarning();
        }
    },

    updateOrientationWarning: function (videoElement) {
        var width = videoElement.videoWidth || 0;
        var height = videoElement.videoHeight || 0;

        if (!width || !height) {
            this.hideOrientationWarning();
            return;
        }

        var isVerticalEnough = height > width && (width / height) <= 0.75;
        this.el.orientationWarning.toggleClass("d-none", isVerticalEnough);
    },

    hideOrientationWarning: function () {
        this.el.orientationWarning.addClass("d-none");
    },

    submit: function () {
        if (this.isSubmitting || !this.canUpload()) {
            return;
        }

        var uploads = this.validateUploads();
        if (!uploads) {
            return;
        }

        var payload = {
            caption: this.el.caption.val(),
            is_public: this.el.isPublic.length && this.el.isPublic.is(":checked") ? 1 : 0,
            video_attachment_id: uploads.video.attachmentID,
            cover_attachment_id: uploads.cover ? uploads.cover.attachmentID : null,
            sound_id: $("#reel-storySoundId").length ? $("#reel-storySoundId").val() : null
        };

        this.isSubmitting = true;
        this.updateSubmitState();

        $.ajax({
            type: "POST",
            url: app.baseUrl + "/reels/create",
            data: payload,
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
            },
            success: function (response) {
                if (response && response.success) {
                    if (response.publication_review) {
                        launchToast("success", trans("Success"), response.message || response.publication_review.label);
                    }
                    ReelCreator.isPublishingRedirect = true;
                    ReelCreator.clearDraftData();
                    redirect(response.redirect_url || app.baseUrl + "/reels");
                    return;
                }

                launchToast("danger", trans("Error"), (response && response.message) || trans("Something went wrong."));
            },
            error: function (xhr) {
                var response = xhr.responseJSON || {};
                launchToast("danger", trans("Error"), response.message || trans("Something went wrong."));
            },
            complete: function () {
                ReelCreator.isSubmitting = false;
                ReelCreator.updateSubmitState();
            }
        });
    }
};
