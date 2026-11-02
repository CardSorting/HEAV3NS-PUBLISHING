/**
 * Thin AJAX wrapper for reels interactions.
 */
"use strict";
/* global app, launchToast, trans */

var ReelsApi = {
    csrfToken: function () {
        return $('meta[name="csrf-token"]').attr("content");
    },

    fetchFeed: function (url, params) {
        return $.get(url, params || {});
    },

    fetchComments: function (reelId) {
        return $.get(app.baseUrl + "/reels/comments", {reel_id: reelId});
    },

    fetchReplies: function (reelId, rootId, offset) {
        return $.get(app.baseUrl + "/reels/comments/replies", {reel_id: reelId, root_id: rootId, offset: offset});
    },

    markView: function (reelId) {
        return $.ajax({
            type: "POST",
            url: app.baseUrl + "/reels/view",
            data: {reel_id: reelId},
            headers: {"X-CSRF-TOKEN": this.csrfToken()}
        });
    },

    action: function (method, url, data, onSuccess, onError, onComplete) {
        if (typeof onComplete === "undefined" && typeof onError === "function") {
            onComplete = onError;
            onError = null;
        }

        return $.ajax({
            type: method,
            url: app.baseUrl + url,
            data: data,
            headers: {"X-CSRF-TOKEN": this.csrfToken()},
            success: function (response) {
                if (response && response.success && typeof onSuccess === "function") {
                    onSuccess(response);
                }
            },
            error: function (xhr) {
                var response = xhr.responseJSON || {};
                if (typeof onError === "function") {
                    onError(response);
                }
                launchToast("danger", trans("Error"), response.message || trans("Something went wrong."));
            },
            complete: function () {
                if (typeof onComplete === "function") {
                    onComplete();
                }
            }
        });
    }
};

window.ReelsApi = ReelsApi;
