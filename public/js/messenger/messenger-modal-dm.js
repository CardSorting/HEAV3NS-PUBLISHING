/**
 * Quick-DM component
 */
/* global app, trans, launchToast, updateButtonState, GifPicker */
"use strict";

window.StoryDM = window.StoryDM || {

    _bound: false,
    _sending: false,
    _composerRevision: 0,

    // UI context (feed can set this dynamically)
    ctx: {
        receiverId: null,
        username: "",
        isStoryReply: false
    },

    init: function () {
        if (this._bound) return;
        this._bound = true;
        this.initGifComposer();

        /**
         * Modal opening
         * - Decide story vs normal
         * - Ensure context line always correct
         */
        $("#messageModal").on("show.bs.modal", () => {

            var fromStory = !!window.__messageModalFromStory;
            var storyIdStr = ($("#storyID").val() || "").trim();

            // If NOT opened from a story, wipe any leftover story context
            if (!fromStory) {
                $("#storyID").val("");

                if (window.StoriesSwiper) {
                    window.StoriesSwiper._returnToStory = null;
                    window.StoriesSwiper._reopenAfterModal = false;
                }

                this.ctx.isStoryReply = false;
            } else {
                // Story open: only treat as story reply if story_id exists
                this.ctx.isStoryReply = !!storyIdStr;
            }

            // Consume one-shot flag
            window.__messageModalFromStory = false;

            // Render context line every time
            this.renderContextLine();
        });

        /**
         * Modal fully closed
         * - Reopen story if requested
         * - Cleanup
         */
        $("#messageModal").on("hidden.bs.modal", () => {
            this.clearGifSelection();

            if (window.StoriesSwiper && window.StoriesSwiper._reopenAfterModal) {
                window.StoriesSwiper._reopenAfterModal = false;

                var ctx = window.StoriesSwiper._returnToStory;
                if (ctx && typeof ctx.storyIndex !== "undefined") {
                    this.clearStoryContextUI();
                    window.StoriesSwiper.openViewer(ctx.storyIndex, ctx.itemIndex || 0);
                    return;
                }
            }

            // Normal close cleanup
            this.clearStoryContextUI();
            this.ctx.isStoryReply = false;
            this.renderContextLine();
        });

        /**
         * "Back" link (only visible for story replies)
         */
        $("#dmBackLink").on("click", (e) => {
            e.preventDefault();

            if (window.StoriesSwiper) {
                window.StoriesSwiper._reopenAfterModal = true;
            }

            this.hideNewMessageDialog();
        });

        /**
         * Send button
         */
        $(document).on("click", ".new-conversation-label", (e) => {
            if (!$("#messageModal").hasClass("show")) return;

            e.preventDefault();
            this.sendFromModal();
        });

        /**
         * When modal is fully visible, focus textarea if opened from a story
         * (use shown.bs.modal, not show.bs.modal)
         */
        $("#messageModal").on("shown.bs.modal", () => {
            // Always focus when modal finishes opening
            setTimeout(() => {
                var el = document.getElementById("messageText");
                if (el) el.focus();
            }, 50);
        });

        $("#messageText").on("keydown", (e) => {
            // only when modal is actually visible
            if (!$("#messageModal").hasClass("show")) return;

            // IME composition safeguard (Japanese/Chinese input, etc.)
            if (e.isComposing || e.keyCode === 229) return;

            if (e.key === "Enter" || e.which === 13) {
                // Shift+Enter => newline (default)
                if (e.shiftKey) return;

                // Enter => send
                e.preventDefault();
                this.sendFromModal();
            }
        });

    },

    /**
     * Context line renderer
     * Always show:
     * - Normal: "Sending @username a new message"
     * - Story:  "Replying to @username's story. Back"
     */
    renderContextLine: function () {
        var username = (this.getUsername() || "").trim();
        var at = username ? ("@" + username) : "";

        if (this.ctx.isStoryReply) {
            $("#dmContextText").text(
                at
                    ? trans("Replying to user's story.", { user: at })
                    : trans("Replying to story.")
            );
            $("#dmBackLink").removeClass("d-none");
            return;
        }

        // Normal message
        $("#dmContextText").text(
            at
                ? trans("Sending user a new message", { user: at })
                : trans("Sending a new message")
        );
        $("#dmBackLink").addClass("d-none");
    },

    clearStoryContextUI: function () {
        $("#storyID").val("");
        $("#dmBackLink").addClass("d-none");
    },

    initGifComposer: function () {
        var container = document.getElementById('message-modal-gif-composer');
        if (container && typeof GifPicker !== 'undefined') {
            GifPicker.mount(container, {surface: 'messenger'});
        }
    },

    getGifSelection: function () {
        var container = document.getElementById('message-modal-gif-composer');
        return container && typeof GifPicker !== 'undefined' ? GifPicker.getSelection(container) : null;
    },

    clearGifSelection: function () {
        this._composerRevision++;
        var container = document.getElementById('message-modal-gif-composer');
        if (container && typeof GifPicker !== 'undefined') {
            GifPicker.destroy(container);
            this.initGifComposer();
        }
    },

    getUsername: function () {
        // Prefer ctx.username if set; fallback to hidden input
        return this.ctx.username || ($("#receiverUsername").val() || "");
    },

    sendFromModal: function () {
        if (this._sending) return;
        this._sending = true;

        var $btn = $(".new-conversation-label");
        var receiverId = $("#receiverID").length ? ($("#receiverID").val() || "").trim() : "";
        var msg = $("#messageText").val() || "";
        var gif = this.getGifSelection();
        var composerRevision = this._composerRevision;

        var storyIdStr = ($("#storyID").val() || "").trim();
        var storyId = storyIdStr ? storyIdStr : null;
        var wasStoryReply = !!storyIdStr;

        if (!receiverId) {
            launchToast("danger", trans("Error"), trans("Missing receiver."));
            this._sending = false;
            return;
        }

        if (!msg.trim() && !gif) {
            launchToast("danger", trans("Error"), trans("Please write a message."));
            this._sending = false;
            return;
        }

        if (typeof updateButtonState === "function") {
            updateButtonState("loading", $btn, trans("Send"), "white");
        } else {
            $btn.prop("disabled", true);
        }

        $.ajax({
            type: "POST",
            url: app.baseUrl + "/my/messenger/sendMessage",
            data: {
                receiverIDs: [receiverId],
                message: msg,
                story_id: storyId,
                gif: gif
            },
            success: () => {
                if (composerRevision !== this._composerRevision) return;
                $("#messageText").val("");
                this.clearGifSelection();

                launchToast("success", trans("Success"), trans("Message sent"));

                if (wasStoryReply && window.StoriesSwiper) {
                    window.StoriesSwiper._reopenAfterModal = true;
                }

                this.hideNewMessageDialog();
            },
            error: (result) => {
                launchToast(
                    "danger",
                    trans("Error"),
                    result?.responseJSON?.message || trans("Something went wrong.")
                );
            },
            complete: () => {
                this._sending = false;

                if (typeof updateButtonState === "function") {
                    updateButtonState("loaded", $btn, trans("Send"));
                } else {
                    $btn.prop("disabled", false);
                }
            }
        });
    },

    /**
     * Feed/profile can call this before showing modal
     */
    setReceiver: function (userId, username) {
        if (String(this.ctx.receiverId || '') !== String(userId || '')) {
            this.clearGifSelection();
        }
        this.ctx.receiverId = userId || null;
        this.ctx.username = username || "";

        $("#receiverID").val(String(userId || ""));
        $("#receiverUsername").val(String(username || ""));

        // Not automatically a story reply; that is decided by story flow
        this.ctx.isStoryReply = false;

        this.renderContextLine();
    },

    /**
     * Optional helper for story flow (you can call this from StoriesSwiper)
     */
    setStoryReplyContext: function (storyId) {
        if (String($("#storyID").val() || '') !== String(storyId || '')) {
            this.clearGifSelection();
        }
        $("#storyID").val(String(storyId || ""));
        this.ctx.isStoryReply = !!String(storyId || "").trim();
        this.renderContextLine();
    },

    showNewMessageDialog: function () {
        $("#messageModal").modal("show");
    },

    hideNewMessageDialog: function () {
        $("#messageModal").modal("hide");
    }
};

$(function () {
    window.StoryDM.init();
});
