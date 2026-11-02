/**
 * Comments panel workflow for the reels viewer.
 */
"use strict";
/* global ReelsApi, ReelsRenderer, Lists, hideDialog, showDialog, trans, updateButtonState, launchToast, GifPicker */

var ReelsComments = {
    toggleCommentsPanel: function () {
        if (this.isCommentsPanelVisible()) {
            this.closeCommentsPanel();
            return;
        }

        this.openCommentsPanel();
    },

    openCommentsPanel: function () {
        this.commentsPanelOpen = true;
        this.showCommentsPanel();
    },

    showCommentsPanel: function () {
        var video = this.$feed.find(".reel-viewer-video").get(0);
        var shouldPauseForPanel = this.isMobileViewer() || this.hasActiveSoundtrack();

        this.$feed.find(".reel-viewer-comments").removeClass("is-closed").addClass("is-open");
        this.$feed.find(".reel-viewer").addClass("comments-open");

        if (shouldPauseForPanel) {
            this.wasPlayingBeforeComments = !!(video && !video.paused && this.desiredPlayback);
            if (this.wasPlayingBeforeComments) {
                this.desiredPlayback = false;
                video.pause();
            }
        } else {
            this.pauseActiveSoundtrack();
        }
    },

    closeCommentsPanel: function (options) {
        options = options || {};
        var shouldResume = options.resumePlayback !== false;

        if (!options.preservePreference) {
            this.commentsPanelOpen = false;
        }

        this.$feed.find(".reel-viewer-comments").removeClass("is-open").addClass("is-closed");
        this.$feed.find(".reel-viewer").removeClass("comments-open");
        this.$feed.find(".reel-comment-form .reel-comment-input").trigger("blur");
        this.setCommentKeyboardOpen(false);

        if (shouldResume && this.wasPlayingBeforeComments) {
            this.desiredPlayback = true;
            this.playActiveVideo();
        } else if (!shouldResume && this.wasPlayingBeforeComments) {
            this.desiredPlayback = true;
        }

        this.wasPlayingBeforeComments = false;
    },

    applyCommentsPanelPreference: function () {
        if (this.commentsPanelOpen === null) {
            this.commentsPanelOpen = !this.isMobileViewer();
        }

        if (this.commentsPanelOpen) {
            this.showCommentsPanel();
            return;
        }

        this.closeCommentsPanel({resumePlayback: false, preservePreference: true});
    },

    isMobileViewer: function () {
        return window.matchMedia && window.matchMedia("(max-width: 767.98px)").matches;
    },

    isCommentsPanelVisible: function () {
        var panel = this.$feed.find(".reel-viewer-comments");
        if (!panel.length) {
            return false;
        }

        if (this.isMobileViewer()) {
            return panel.hasClass("is-open");
        }

        return !panel.hasClass("is-closed");
    },

    fetchComments: function (callback) {
        var self = this;
        var reel = this.getActiveReel();
        var generation = this.commentsGeneration;
        var revision = this.commentsRevision;
        var reelId;
        if (!reel) return;

        reelId = reel.id;

        ReelsApi.fetchComments(reelId).done(function (response) {
            if (!self.isCurrentCommentsContext(reelId, generation)) {
                return;
            }
            if (self.commentsRevision !== revision) {
                self.fetchComments(callback);
                return;
            }

            self.renderComments(response.comments || []);
            if (typeof callback === "function") {
                callback();
            }
        }).fail(function () {
            if (!self.isCurrentCommentsContext(reelId, generation)) {
                return;
            }
            if (self.commentsRevision !== revision) {
                self.fetchComments(callback);
                return;
            }

            self.$feed.find(".reel-comments-list").html('<div class="text-danger">' + self.escape(trans("Could not load comments.")) + "</div>");
        });
    },

    renderComments: function (comments) {
        this.$feed.find(".reel-comments-list").html(ReelsRenderer.renderComments(comments));
    },

    renderComment: function (comment) {
        return ReelsRenderer.renderComment(comment);
    },

    isCurrentCommentsContext: function (reelId, generation) {
        return String(this.getActiveReelId()) === String(reelId) && this.commentsGeneration === generation;
    },

    startCommentReply: function ($button) {
        var $actions = $button.closest(".reel-comment-actions");
        var $form = this.$feed.find(".reel-comment-form");
        if ($form.data("is-submitting")) return;
        this.commentReplyTarget = {
            id: $actions.data("comment-id"),
            rootId: $button.closest(".reel-comment-thread").data("root-comment-id"),
            reelId: this.getActiveReelId()
        };
        this.$feed.find(".reel-comment-reply-context").removeClass("d-none")
            .find("span").text(trans("Replying to") + " @" + $actions.data("username"));
        $form.find(".reel-comment-input").trigger("focus");
    },

    cancelCommentReply: function () {
        this.commentReplyTarget = null;
        this.$feed.find(".reel-comment-reply-context").addClass("d-none");
    },

    toggleCommentReplies: function ($thread) {
        var $replies = $thread.find(".reel-comment-replies");
        var isOpening = $replies.hasClass("d-none");
        $replies.toggleClass("d-none", !isOpening);
        this.updateReplyControls($thread);
        if (isOpening && !$thread.data("replies-loaded")) this.loadCommentReplies($thread);
    },

    updateReplyControls: function ($thread) {
        var total = Number($thread.data("replies-total") || 0);
        var offset = Number($thread.data("replies-offset") || 0);
        var unseen = Math.max(0, total - $thread.find(".reel-comment-replies-items .reel-comment").length);
        var isOpen = !$thread.find(".reel-comment-replies").hasClass("d-none");
        $thread.find(".reel-comment-replies-toggle").toggleClass("d-none", total === 0)
            .attr("aria-expanded", isOpen ? "true" : "false")
            .text(isOpen ? trans("Hide replies") : trans("View :count replies", {count: total}));
        $thread.find(".reel-comment-replies-more").toggleClass("d-none", offset >= total || unseen === 0)
            .text(trans("View :count more replies", {count: unseen}));
    },

    appendCommentReplies: function ($thread, replies) {
        var self = this;
        var $items = $thread.find(".reel-comment-replies-items");
        replies.forEach(function (reply) {
            var exists = $items.children(".reel-comment").filter(function () {
                return String($(this).data("comment-id")) === String(reply.id);
            }).length;
            if (!exists) $items.append(self.renderComment(reply));
        });
        // Locally posted replies may already be present before their page is loaded.
        $items.append($items.children(".reel-comment").sort(function (a, b) {
            return Number($(a).data("comment-time")) - Number($(b).data("comment-time"))
                || Number($(a).data("comment-id")) - Number($(b).data("comment-id"));
        }));
    },

    loadCommentReplies: function ($thread) {
        var self = this;
        var reelId = this.getActiveReelId();
        var generation = this.commentsGeneration;
        var revision = this.commentsRevision;
        var rootId = $thread.data("root-comment-id");
        var offset = Number($thread.data("replies-offset") || 0);
        if ($thread.data("replies-loading")) return;
        $thread.data("replies-loading", true);
        $thread.find(".reel-comment-replies-more").prop("disabled", true);
        ReelsApi.fetchReplies(reelId, rootId, offset).done(function (response) {
            if (!self.isCurrentCommentsContext(reelId, generation) || self.commentsRevision !== revision || !$.contains(self.$feed[0], $thread[0])) return;
            self.appendCommentReplies($thread, response.replies || []);
            $thread.data("replies-loaded", true).data("replies-offset", response.next_offset || 0)
                .data("replies-total", response.total || 0);
            self.updateReplyControls($thread);
        }).fail(function () {
            if (!self.isCurrentCommentsContext(reelId, generation) || self.commentsRevision !== revision) return;
            launchToast("danger", trans("Error"), trans("Could not load comments."));
        }).always(function () {
            $thread.data("replies-loading", false);
            $thread.find(".reel-comment-replies-more").prop("disabled", false);
        });
    },

    renderEmptyCommentsState: function () {
        this.$feed.find(".reel-comments-list").html(ReelsRenderer.renderEmptyCommentsState());
    },

    toggleCommentReaction: function ($button) {
        var self = this;
        var $actions = $button.closest(".reel-comment-actions");
        var commentId = $actions.data("comment-id");
        var lockKey = "comment-reaction-" + commentId;
        var action = $button.hasClass("is-active") ? "remove" : "add";

        if (!this.beginAction(lockKey, $button)) {
            return;
        }

        this.postAction("/reels/reaction", {type: "comment", id: commentId, action: action}, function (response) {
            $button.toggleClass("is-active text-primary", action === "add");
            $button.find("span").text(self.formatCount(response.reactions || 0));
        }, function () {
            self.endAction(lockKey, $button);
        });
    },

    reportComment: function ($button) {
        if (!window.Lists || typeof Lists.showReportBox !== "function") {
            return;
        }

        var $actions = $button.closest(".reel-comment-actions");
        this.showReportBoxAfterClosingViewer($actions.data("user-id"), null, null, null, null, null, $actions.data("comment-id"));
    },

    deleteComment: function ($button) {
        var $actions = $button.closest(".reel-comment-actions");
        this.pendingDeleteCommentId = $actions.data("comment-id");
        showDialog("reel-comment-delete-dialog");
    },

    confirmDeleteComment: function () {
        var self = this;
        var reelId = this.getActiveReelId();
        var generation = this.commentsGeneration;
        var commentId = this.pendingDeleteCommentId;
        var $button = this.$feed.find(".reel-comment-actions").filter(function () {
            return String($(this).data("comment-id")) === String(commentId);
        }).find(".reel-comment-delete").first();
        var $dialogButton = $("#reel-comment-delete-dialog [data-dialog-action]").first();
        var lockKey = "comment-delete-" + commentId;

        if (!commentId) {
            hideDialog("reel-comment-delete-dialog");
            return;
        }

        if (!this.beginAction(lockKey, $button.add($dialogButton))) {
            return;
        }

        this.ajaxAction("DELETE", "/reels/comments/delete", {comment_id: commentId}, function (response) {
            hideDialog("reel-comment-delete-dialog");
            if (!self.isCurrentCommentsContext(reelId, generation)) return;
            self.commentsRevision++;
            self.pendingDeleteCommentId = null;
            self.updateCommentCount(response.comments || 0);
            var $comment = self.$feed.find(".reel-comment").filter(function () {
                return String($(this).data("comment-id")) === String(commentId);
            });
            var $thread = $comment.closest(".reel-comment-thread");
            var isRoot = String($thread.data("root-comment-id")) === String(commentId);
            if (self.commentReplyTarget && (String(self.commentReplyTarget.id) === String(commentId)
                || (isRoot && String(self.commentReplyTarget.rootId) === String(commentId)))) self.cancelCommentReply();
            if (!isRoot) {
                $thread.data("replies-total", response.thread_replies || 0)
                    .data("replies-offset", Math.max(0, Number($thread.data("replies-offset") || 0) - 1));
                self.updateReplyControls($thread);
            }
            self.removeComment($comment, function () {
                if (isRoot) $thread.remove();
                if (!self.$feed.find(".reel-comment").length) {
                    self.renderEmptyCommentsState();
                }
            });
        }, function () {
            self.endAction(lockKey, $button.add($dialogButton));
        });
    },

    addComment: function ($form) {
        var self = this;
        var reel = this.getActiveReel();
        var generation = this.commentsGeneration;
        var target = this.commentReplyTarget;
        var $input = $form.find(".reel-comment-input");
        var gifComposer = $form.find("[data-gif-composer]")[0];
        var gif = typeof GifPicker !== "undefined" ? GifPicker.getSelection(gifComposer) : null;
        var message = ($input.val() || "").trim();

        if ($form.data("is-submitting")) {
            return;
        }

        if (!reel || (!message.length && !gif)) {
            return;
        }

        var gifWasInert = !!(gifComposer && gifComposer.inert);
        if (gifComposer) gifComposer.inert = true;
        $form.data("is-submitting", true);
        this.setCommentFormLoading($form, true);

        var data = {reel_id: reel.id, message: message, gif: gif};
        if (target && String(target.reelId) === String(reel.id)) data.reply_to_id = target.id;
        this.postAction("/reels/comments/add", data, function (response) {
            if (!self.isCurrentCommentsContext(reel.id, generation)) return;
            self.commentsRevision++;
            $input.val("");
            if (typeof GifPicker !== "undefined") GifPicker.clear(gifComposer);
            self.cancelCommentReply();
            $form.data("is-submitting", false);
            self.setCommentFormLoading($form, false);
            self.updateCommentCount(response.comments || 0);

            if (response.comment) {
                if (response.parent_id) {
                    var $thread = self.$feed.find(".reel-comment-thread").filter(function () {
                        return String($(this).data("root-comment-id")) === String(response.parent_id);
                    });
                    $thread.data("replies-total", response.thread_replies || 0);
                    $thread.find(".reel-comment-replies").removeClass("d-none");
                    self.appendCommentReplies($thread, [response.comment]);
                    self.updateReplyControls($thread);
                } else {
                    self.prependComment(response.comment);
                }
                self.settleCommentInputAfterSubmit($input);
                return;
            }

            self.fetchComments(function () {
                self.settleCommentInputAfterSubmit($input);
                self.$feed.find(".reel-comments-list").scrollTop(0);
            });
        }, null, function () {
            if (gifComposer) gifComposer.inert = gifWasInert;
            $form.data("is-submitting", false);
            self.setCommentFormLoading($form, false);
        });
    },

    prependComment: function (comment) {
        var $list = this.$feed.find(".reel-comments-list");
        if (!$list.find(".reel-comment-thread").length) $list.empty();
        $list.find(".reel-comments-empty").remove();
        $list.prepend(ReelsRenderer.renderCommentThread(comment));
        $list.scrollTop(0);
    },

    removeComment: function ($comment, callback) {
        if (!$comment.length) {
            if (typeof callback === "function") {
                callback();
            }
            return;
        }

        $comment.addClass("is-removing");
        window.setTimeout(function () {
            $comment.remove();
            if (typeof callback === "function") {
                callback();
            }
        }, 220);
    },

    setCommentFormLoading: function ($form, isLoading) {
        var $input = $form.find(".reel-comment-input");
        var $button = $form.find("button[type='submit']");

        $input.prop("disabled", isLoading);
        $button.prop("disabled", isLoading);

        if (typeof updateButtonState === "function") {
            updateButtonState(isLoading ? "loading" : "loaded", $button, trans("Send"), "white");
            $button.prop("disabled", isLoading);
        } else {
            $button.toggleClass("disabled", isLoading);
        }
    }
};

window.ReelsComments = ReelsComments;
