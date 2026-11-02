/**
 * Reels explore grid and full-screen viewer.
 */
"use strict";
/* global app, launchToast, trans, Lists, shareOrCopyLink, showDialog, hideDialog, ReelsApi, ReelsRenderer */

$(function () {
    ReelsPlayer.init();
});

var ReelsPlayer = {
    reels: [],
    viewed: {},
    activeIndex: null,
    wheelLock: false,
    navigationLock: false,
    navigationLockTimer: null,
    preloadVideos: {},
    touchStartY: null,
    touchStartX: null,
    touchDragY: 0,
    touchIsDragging: false,
    desiredPlayback: true,
    isMuted: true,
    commentsPanelOpen: null,
    commentsGeneration: 0,
    commentsRevision: 0,
    commentReplyTarget: null,
    wasPlayingBeforeComments: false,
    allowProgressScrubbing: true,
    isScrubbingProgress: false,
    viewTimer: null,
    loadingTimer: null,
    progressAnimationFrame: null,
    viewThresholdMs: 2000,
    hasMore: false,
    nextOffset: 0,
    isLoadingMore: false,
    loadMoreCallbacks: [],
    pendingViewerNextLoad: false,
    observer: null,
    actionLocks: {},
    baseUrl: null,
    emptyActionUrl: null,
    emptyActionLabel: null,
    emptyStateHtml: "",
    lockedStateHtml: "",
    permalinkTemplate: null,
    originalUrl: null,
    hasPushedViewerHistory: false,
    restoringFromHistory: false,
    reportReturnIndex: null,
    pendingHistoryClose: false,
    historyCloseTimer: null,
    pendingDeleteReelId: null,
    pendingDeleteCommentId: null,
    isExternalHost: false,
    boundSyncViewerViewport: null,
    commentKeyboardTimer: null,
    keyboardViewportTimer: null,
    isOpeningInitialReel: false,
    gridLoadCheckTimer: null,

    init: function () {
        this.$feed = $("#reels-feed");
        if (!this.$feed.length) {
            this.$feed = $("#feed-reels-player-host");
        }
        if (!this.$feed.length) {
            return;
        }
        this.isExternalHost = String(this.$feed.data("external-host") || "") === "1";
        this.initialReel = this.$feed.data("initial-reel");
        this.initialReelUnavailable = String(this.$feed.data("initial-unavailable") || "") === "1";
        var allowProgressScrubbing = this.$feed.data("allow-progress-scrubbing");
        this.allowProgressScrubbing = allowProgressScrubbing === undefined ? true : String(allowProgressScrubbing) !== "0";
        this.feedUrl = this.$feed.data("feed-url") || (app.baseUrl + "/reels/feed");
        this.baseUrl = this.$feed.data("base-url") || (app.baseUrl + "/reels");
        this.emptyActionUrl = this.$feed.data("empty-action-url") || "";
        this.emptyActionLabel = this.$feed.data("empty-action-label") || trans("Create your reel");
        var emptyStateTemplate = this.$feed.data("empty-state-template");
        this.emptyStateHtml = emptyStateTemplate ? ($(emptyStateTemplate).html() || "") : "";
        var lockedStateTemplate = this.$feed.data("locked-state-template");
        this.lockedStateHtml = lockedStateTemplate ? ($(lockedStateTemplate).html() || "") : "";
        this.permalinkTemplate = this.$feed.data("permalink-template") || (app.baseUrl + "/reels/__REEL_ID__");
        this.originalUrl = window.location.href;
        this.isOpeningInitialReel = !!this.initialReel;
        this.prepareInitialHistoryState();
        this.isMuted = this.getStoredMuted();
        this.bindEvents();
        this.bindGridLoadChecks();
        this.disableReelsRightClick();

        if (this.isExternalHost) {
            this.ensureViewerHost();
            return;
        }

        if (this.initialReelUnavailable) {
            this.renderUnavailableState();
            return;
        }

        this.fetchFeed();
    },

    bindGridLoadChecks: function () {
        var self = this;

        $(window)
            .off("scroll.reelsGridLoad resize.reelsGridLoad orientationchange.reelsGridLoad")
            .on("scroll.reelsGridLoad resize.reelsGridLoad orientationchange.reelsGridLoad", function () {
                self.scheduleGridLoadCheck();
            });
    },

    ensureViewerHost: function () {
        if (!this.$feed.find(".reel-viewer").length) {
            this.$feed.html('<div class="reel-viewer d-none" aria-modal="true" role="dialog"></div>');
        }
    },

    bindEvents: function () {
        var self = this;
        if (!this.boundSyncViewerViewport) {
            this.boundSyncViewerViewport = function () {
                self.syncViewerViewport();
            };
            $(window)
                .off("resize.reelsViewport orientationchange.reelsViewport")
                .on("resize.reelsViewport orientationchange.reelsViewport", this.boundSyncViewerViewport);
            if (window.visualViewport && window.visualViewport.addEventListener) {
                window.visualViewport.removeEventListener("resize", this.boundSyncViewerViewport);
                window.visualViewport.removeEventListener("scroll", this.boundSyncViewerViewport);
                window.visualViewport.addEventListener("resize", this.boundSyncViewerViewport);
                window.visualViewport.addEventListener("scroll", this.boundSyncViewerViewport);
            }
        }

        this.$feed.on("click", ".reel-grid-card", function () {
            self.openViewer(parseInt($(this).data("reel-index"), 10));
        });

        this.$feed.on("mouseenter", ".reel-grid-card", function () {
            self.startGridPreview($(this));
        });

        this.$feed.on("mouseleave blur", ".reel-grid-card", function () {
            self.stopGridPreview($(this));
        });

        this.$feed.on("error", ".reel-grid-poster", function () {
            var $image = $(this);
            $image.addClass("d-none");
            $image.closest(".reel-grid-media").find(".reel-grid-placeholder").removeClass("d-none");
        });

        this.$feed.on("click", ".reel-viewer-close", function () {
            self.requestCloseViewer();
        });

        this.$feed.on("click", ".reel-viewer-next", function () {
            self.showNext();
        });

        this.$feed.on("click", ".reel-viewer-prev", function () {
            self.showPrevious();
        });

        this.$feed.on("click", ".reel-viewer-video", function () {
            self.toggleActivePlayback();
        });

        this.$feed.on("pointerdown", ".reel-viewer-progress", function (event) {
            self.beginProgressScrub(event);
        });

        this.$feed.on("click", ".reel-play-toggle", function () {
            self.toggleActivePlayback();
        });

        this.$feed.on("wheel", ".reel-viewer", function (event) {
            self.handleViewerWheel(event.originalEvent);
        });

        this.$feed.on("touchstart", ".reel-viewer", function (event) {
            self.handleTouchStart(event.originalEvent);
        });

        this.$feed.on("touchmove", ".reel-viewer", function (event) {
            self.handleTouchMove(event.originalEvent);
        });

        this.$feed.on("touchend", ".reel-viewer", function (event) {
            self.handleTouchEnd(event.originalEvent);
        });

        this.$feed.on("click", ".reel-react", function () {
            self.toggleReaction();
        });

        this.$feed.on("click", ".reel-bookmark", function () {
            self.toggleBookmark();
        });

        this.$feed.on("click", ".reel-share", function () {
            var reel = self.getActiveReel();
            if (reel && typeof shareOrCopyLink === "function") {
                shareOrCopyLink(self.getReelPermalink(reel));
            }
        });

        this.$feed.on("click", ".reel-mute", function () {
            self.toggleMuted();
        });

        this.$feed.on("click", ".reel-report", function () {
            self.reportReel();
        });

        this.$feed.on("click", ".reel-delete", function () {
            self.deleteReel();
        });

        this.$feed.on("click", ".reel-comments-toggle", function () {
            self.toggleCommentsPanel();
        });

        this.$feed.on("click", ".reel-comments-close", function () {
            self.closeCommentsPanel();
        });

        this.$feed.on("click", ".reel-viewer.comments-open", function (event) {
            if ($(event.target).is(".reel-viewer")) {
                self.closeCommentsPanel();
            }
        });

        this.$feed.on("submit", ".reel-comment-form", function (e) {
            e.preventDefault();
            self.addComment($(this));
        });

        this.$feed.on("focusin", ".reel-comment-form input", function () {
            self.setCommentKeyboardOpen(true);
        });

        this.$feed.on("focusout", ".reel-comment-form input", function () {
            window.clearTimeout(self.commentKeyboardTimer);
            self.commentKeyboardTimer = window.setTimeout(function () {
                self.setCommentKeyboardOpen(false);
            }, 180);
        });

        this.$feed.on("click", ".reel-comment-react", function () {
            self.toggleCommentReaction($(this));
        });

        this.$feed.on("click", ".reel-comment-report", function () {
            self.reportComment($(this));
        });

        this.$feed.on("click", ".reel-comment-delete", function () {
            self.deleteComment($(this));
        });

        this.$feed.on("click", ".reel-comment-reply", function () {
            self.startCommentReply($(this));
        });

        this.$feed.on("click", ".reel-comment-reply-cancel", function () {
            self.cancelCommentReply();
        });

        this.$feed.on("click", ".reel-comment-replies-toggle", function () {
            self.toggleCommentReplies($(this).closest(".reel-comment-thread"));
        });

        this.$feed.on("click", ".reel-comment-replies-more", function () {
            self.loadCommentReplies($(this).closest(".reel-comment-thread"));
        });

        $(document).on("keydown.reels", function (event) {
            if (self.activeIndex === null) {
                return;
            }

            if (event.key === "Escape") {
                if (self.isCommentsPanelVisible()) {
                    self.closeCommentsPanel();
                    return;
                }

                self.requestCloseViewer();
                return;
            }

            if (self.isTypingTarget(event.target) || $(event.target).closest(".reel-publication-review").length) {
                return;
            }

            if (event.key === " " || event.key === "Spacebar") {
                event.preventDefault();
                self.toggleActivePlayback();
            } else if (event.key === "ArrowDown" || event.key === "ArrowRight") {
                event.preventDefault();
                self.showNext();
            } else if (event.key === "ArrowUp" || event.key === "ArrowLeft") {
                event.preventDefault();
                self.showPrevious();
            } else if (event.key && event.key.toLowerCase() === "m") {
                self.toggleMuted();
            }
        });

        $(window).on("popstate.reels", function (event) {
            self.handlePopState(event.originalEvent);
        });

        $(document).off("visibilitychange.reels").on("visibilitychange.reels", function () {
            if (document.hidden) {
                self.pauseActiveSoundtrack();
            }
        });

        $(window).off("pagehide.reels").on("pagehide.reels", function () {
            self.pauseActiveSoundtrack();
        });

        $(document)
            .off("hidden.bs.modal.reelsReportReturn", "#report-user-post")
            .on("hidden.bs.modal.reelsReportReturn", "#report-user-post", function () {
                self.restoreAfterReportModal();
            });

        $(document)
            .off("content-report:submitted.reelsReportReturn", "#report-user-post")
            .on("content-report:submitted.reelsReportReturn", "#report-user-post", function () {
                self.reportReturnIndex = null;
            });
    },

    fetchFeed: function () {
        var self = this;
        var params = {};

        if (this.initialReel) {
            params.reel_id = this.initialReel;
        }

        ReelsApi.fetchFeed(this.feedUrl, params).done(function (response) {
            self.reels = response.reels || [];
            self.hasMore = !!response.has_more;
            self.nextOffset = response.next_offset || self.reels.length;
            self.render();

            if (self.initialReel && self.reels.length) {
                self.openViewer(0);
            }
        }).fail(function () {
            self.$feed.html('<div class="alert alert-danger w-100">' + self.escape(trans("Could not load reels.")) + "</div>");
        });
    },

    render: function () {
        if (!this.reels.length) {
            this.$feed.html(this.renderEmptyState());
            return;
        }

        this.$feed.html(
            '<div class="reels-grid">' +
                this.reels.map(this.renderGridCard.bind(this)).join("") +
                this.lockedStateHtml +
            '</div>' +
            this.renderGridLoader() +
            '<div class="reel-viewer d-none" aria-modal="true" role="dialog"></div>'
        );
        if (!this.isOpeningInitialReel) {
            this.observeGridLoader();
            this.scheduleGridLoadCheck();
        }
        this.disableReelsRightClick();
    },

    renderEmptyState: function () {
        return this.emptyStateHtml || ReelsRenderer.renderEmptyState(this.emptyActionUrl, this.emptyActionLabel);
    },

    renderUnavailableState: function () {
        this.$feed.html(ReelsRenderer.renderUnavailableState(this.baseUrl || (app.baseUrl + "/reels")));
    },

    renderGridCard: function (reel, index) {
        return ReelsRenderer.renderGridCard(reel, index);
    },

    renderGridLoader: function () {
        return ReelsRenderer.renderGridLoader(this.hasMore);
    },

    observeGridLoader: function () {
        var self = this;
        var loader = this.$feed.find(".reels-grid-loader").get(0);

        if (this.observer) {
            this.observer.disconnect();
            this.observer = null;
        }

        if (!loader || !window.IntersectionObserver) {
            return;
        }

        this.observer = new IntersectionObserver(function (entries) {
            if (entries.some(function (entry) { return entry.isIntersecting; })) {
                self.loadMore();
            }
        }, {rootMargin: "80px 0px"});

        this.observer.observe(loader);
    },

    loadMore: function (callback) {
        var self = this;
        var loadedCount = 0;
        var previousOffset = this.nextOffset;
        var succeeded = false;

        if (typeof callback === "function") {
            this.loadMoreCallbacks.push(callback);
        }

        if (!this.hasMore || this.isLoadingMore) {
            if (!this.hasMore) {
                this.flushLoadMoreCallbacks(0, false);
            }
            return;
        }

        this.isLoadingMore = true;
        ReelsApi.fetchFeed(this.feedUrl, {offset: this.nextOffset}).done(function (response) {
            var items = response.reels || [];
            var startIndex = self.reels.length;

            loadedCount = items.length;
            succeeded = true;
            self.reels = self.reels.concat(items);
            self.hasMore = !!response.has_more && loadedCount > 0 && parseInt(response.next_offset || 0, 10) > previousOffset;
            self.nextOffset = response.next_offset || self.reels.length;

            var cards = items.map(function (reel, offset) {
                return self.renderGridCard(reel, startIndex + offset);
            }).join("");
            var $grid = self.$feed.find(".reels-grid");
            var $lockedCard = $grid.find(".profile-reels-locked");
            if ($lockedCard.length) {
                $lockedCard.before(cards);
            } else {
                $grid.append(cards);
            }

            self.$feed.find(".reels-grid-loader").toggleClass("d-none", !self.hasMore);
            self.renderGridCounts();
            self.disableReelsRightClick();
            self.scheduleGridLoadCheck();
        }).fail(function () {
            self.hasMore = false;
            self.$feed.find(".reels-grid-loader").addClass("d-none");
            launchToast("danger", trans("Error"), trans("Could not load more reels."));
        }).always(function () {
            self.isLoadingMore = false;
            self.flushLoadMoreCallbacks(loadedCount, succeeded);
            self.maybeContinueGridLoad();
        });
    },

    flushLoadMoreCallbacks: function (loadedCount, succeeded) {
        var callbacks = this.loadMoreCallbacks.splice(0);

        callbacks.forEach(function (callback) {
            callback(loadedCount, succeeded);
        });
    },

    prefetchMoreForViewer: function () {
        if (this.activeIndex === null || !this.hasMore || this.isLoadingMore) {
            return;
        }

        if (this.reels.length - this.activeIndex <= 2) {
            this.loadMore();
        }
    },

    scheduleGridLoadCheck: function () {
        var self = this;

        window.clearTimeout(this.gridLoadCheckTimer);
        this.gridLoadCheckTimer = window.setTimeout(function () {
            self.maybeContinueGridLoad();
        }, 80);
    },

    maybeContinueGridLoad: function () {
        var self = this;
        var loader;
        var rect;
        var viewportHeight;

        if (this.activeIndex !== null || !this.hasMore || this.isLoadingMore) {
            return;
        }

        loader = this.$feed.find(".reels-grid-loader").get(0);
        if (!loader || $(loader).hasClass("d-none")) {
            return;
        }

        rect = loader.getBoundingClientRect();
        viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;

        if (viewportHeight > 0 && rect.top <= viewportHeight && rect.bottom >= 0) {
            window.setTimeout(function () {
                self.loadMore();
            }, 0);
        }
    },

    renderVerifiedBadge: function (isVerified, className) {
        return ReelsRenderer.renderVerifiedBadge(isVerified, className);
    },

    renderViewer: function (reel) {
        return ReelsRenderer.renderViewer(reel, {
            isMuted: this.isMuted,
            desiredPlayback: this.desiredPlayback,
            previousReel: this.getAdjacentReel(-1),
            nextReel: this.getAdjacentReel(1),
            canScrub: this.canUseProgressScrubbing()
        });
    },

    renderSwipePreview: function (reel, direction) {
        return ReelsRenderer.renderSwipePreview(reel, direction);
    },

    renderAction: function (className, active, icon, count) {
        return ReelsRenderer.renderAction(className, active, icon, count);
    },

    openViewer: function (index) {
        if (isNaN(index) || !this.reels[index]) {
            return;
        }

        this.ensureViewerHost();
        this.setNavigationLock();
        this.pauseActiveVideo();
        this.clearViewTimer();
        this.stopProgressAnimation();
        $(document).off(".reelsProgressScrub");
        this.isScrubbingProgress = false;
        this.setVideoLoading(false);
        this.activeIndex = index;
        this.commentsGeneration++;
        this.commentsRevision = 0;
        this.commentReplyTarget = null;
        var $viewer = this.$feed.find(".reel-viewer");
        $viewer.find('.reel-publication-review [data-bs-toggle="tooltip"]').tooltip('dispose');
        $viewer
            .removeClass("d-none")
            .html(this.renderViewer(this.reels[index]));
        $viewer.find('.reel-publication-review [data-bs-toggle="tooltip"]').tooltip({container: $viewer.get(0)});
        this.disableReelsRightClick();

        this.syncViewerViewport();
        $("html, body").addClass("reel-viewer-open");
        $("html, body").toggleClass("reel-viewer-touch", this.isTouchDevice());
        if (!this.restoringFromHistory) {
            this.pushReelUrl(this.reels[index]);
        }
        this.attachActiveVideoEvents();
        this.applyMutedState();
        this.updatePlaybackUi();
        this.fetchComments();
        if (this.desiredPlayback) {
            this.playActiveVideo();
        }
        this.applyCommentsPanelPreference();
        this.preloadAdjacentReels(index);
        this.prefetchMoreForViewer();
    },

    openExternalList: function (reels, index, options) {
        options = options || {};

        if (!this.$feed || !this.$feed.length) {
            this.$feed = $("#feed-reels-player-host");
            if (!this.$feed.length) {
                return;
            }

            this.isExternalHost = true;
            this.bindEvents();
        }

        this.reels = reels || [];
        this.hasMore = false;
        this.nextOffset = this.reels.length;
        this.pendingViewerNextLoad = false;
        this.baseUrl = options.baseUrl || this.baseUrl || window.location.href;
        this.permalinkTemplate = options.permalinkTemplate || this.permalinkTemplate || (app.baseUrl + "/reels/__REEL_ID__");
        this.originalUrl = this.baseUrl || window.location.href;
        this.ensureViewerHost();
        this.prepareInitialHistoryState();
        this.openViewer(parseInt(index || 0, 10));
    },

    disableReelsRightClick: function () {
        if (!window.app || app.feedDisableRightClickOnMedia !== true || !this.$feed || !this.$feed.length) {
            return;
        }

        this.$feed
            .off("contextmenu.reelsMedia")
            .on("contextmenu.reelsMedia", ".reel-grid-media, .reel-grid-poster, .reel-grid-preview, .reel-viewer-video, .reel-swipe-preview, .reel-swipe-preview img", function () {
                return false;
            });

        if (typeof window.bindNoLongPressEvents === "function") {
            window.bindNoLongPressEvents();
        }
    },

    closeViewer: function (options) {
        options = options || {};
        this.clearPendingHistoryClose();
        this.pauseActiveVideo();
        this.clearViewTimer();
        this.setVideoLoading(false);
        this.clearViewerRequestState();
        this.desiredPlayback = true;
        this.activeIndex = null;
        this.commentsGeneration++;
        this.commentReplyTarget = null;
        this.pendingViewerNextLoad = false;
        window.clearTimeout(this.gridLoadCheckTimer);
        window.clearTimeout(this.keyboardViewportTimer);
        $("html, body").removeClass("reel-viewer-open reel-viewer-touch reel-keyboard-open");
        var $viewer = this.$feed.find(".reel-viewer");
        $viewer.find('.reel-publication-review [data-bs-toggle="tooltip"]').tooltip('dispose');
        $viewer.addClass("d-none").empty();
        if (options.restoreUrl !== false) {
            this.restoreBaseUrl();
        } else {
            this.hasPushedViewerHistory = false;
        }
        this.$feed.find(".reel-grid-card.is-previewing").each(function () {
            ReelsPlayer.stopGridPreview($(this));
        });
        this.clearNavigationLock();
        if (!this.isExternalHost) {
            this.isOpeningInitialReel = false;
            this.observeGridLoader();
            this.maybeContinueGridLoad();
        }
    },

    syncViewerViewport: function () {
        var $viewer = this.$feed.find(".reel-viewer");
        var height = 0;
        var viewport = window.visualViewport || null;
        var isKeyboardOpen = this.isTouchDevice() && $("html").hasClass("reel-keyboard-open") && viewport;

        if (viewport && viewport.height) {
            height = viewport.height;
        } else if (window.innerHeight) {
            height = window.innerHeight;
        }

        if (height > 0) {
            this.$feed
                .find(".reel-viewer, .reel-viewer-inner, .reel-viewer-phone")
                .css("height", Math.round(height) + "px");
        }

        if (isKeyboardOpen) {
            $viewer.css({
                transform: "translate3d(" + Math.round(viewport.offsetLeft || 0) + "px, " + Math.round(viewport.offsetTop || 0) + "px, 0)",
                width: Math.round(viewport.width || window.innerWidth || $viewer.width()) + "px"
            });
        } else {
            $viewer.css({
                transform: "",
                width: ""
            });
        }
    },

    setCommentKeyboardOpen: function (isOpen) {
        var open = !!isOpen && this.isTouchDevice();
        var self = this;

        window.clearTimeout(this.commentKeyboardTimer);
        window.clearTimeout(this.keyboardViewportTimer);
        $("html, body").toggleClass("reel-keyboard-open", open);
        this.$feed.find(".reel-viewer").toggleClass("keyboard-open", open);
        this.syncViewerViewport();

        if (open) {
            this.keyboardViewportTimer = window.setTimeout(function () {
                self.syncViewerViewport();
            }, 260);
        }
    },

    settleCommentInputAfterSubmit: function ($input) {
        if (this.isTouchDevice()) {
            $input.trigger("blur");
            this.setCommentKeyboardOpen(false);
            return;
        }

        $input.trigger("focus");
    },

    requestCloseViewer: function () {
        var self = this;

        if (this.activeIndex === null) {
            return;
        }

        if (this.shouldCloseWithHistoryBack()) {
            this.pendingHistoryClose = true;
            window.history.back();

            this.historyCloseTimer = window.setTimeout(function () {
                if (self.pendingHistoryClose && self.activeIndex !== null) {
                    self.closeViewer();
                }
            }, 450);
            return;
        }

        this.closeViewer();
    },

    shouldCloseWithHistoryBack: function () {
        var state = window.history ? window.history.state : null;

        return !!(
            window.history &&
            window.history.back &&
            this.hasPushedViewerHistory &&
            state &&
            state.reelsViewer &&
            state.reelId
        );
    },

    clearPendingHistoryClose: function () {
        if (this.historyCloseTimer) {
            window.clearTimeout(this.historyCloseTimer);
            this.historyCloseTimer = null;
        }

        this.pendingHistoryClose = false;
    },

    showNext: function () {
        var self = this;

        if (this.activeIndex === null || !this.reels.length || this.navigationLock) {
            return;
        }

        this.closeCommentsPanel({resumePlayback: false, preservePreference: true});

        if (this.activeIndex >= this.reels.length - 1 && this.hasMore) {
            if (this.pendingViewerNextLoad) {
                return;
            }

            this.pendingViewerNextLoad = true;
            this.loadMore(function (loadedCount) {
                self.pendingViewerNextLoad = false;

                if (self.activeIndex === null) {
                    return;
                }

                if (loadedCount > 0 && self.activeIndex < self.reels.length - 1) {
                    self.openViewer(self.activeIndex + 1);
                    return;
                }

                if (!self.hasMore) {
                    self.openViewer(0);
                }
            });
            return;
        }

        this.openViewer((this.activeIndex + 1) % this.reels.length);
    },

    showPrevious: function () {
        if (this.activeIndex === null || !this.reels.length || this.navigationLock) {
            return;
        }
        this.closeCommentsPanel({resumePlayback: false, preservePreference: true});
        this.openViewer((this.activeIndex - 1 + this.reels.length) % this.reels.length);
    },

    handleViewerWheel: function (event) {
        if (!event || this.activeIndex === null || Math.abs(event.deltaY) < 28) {
            return;
        }

        var $target = $(event.target);
        if ($target.closest(".reel-viewer-comments, .publication-review-tooltip").length) {
            return;
        }

        event.preventDefault();

        if (this.wheelLock) {
            return;
        }

        this.wheelLock = true;
        if (event.deltaY > 0) {
            this.showNext();
        } else {
            this.showPrevious();
        }

        var self = this;
        window.setTimeout(function () {
            self.wheelLock = false;
        }, 520);
    },

    handleTouchStart: function (event) {
        if (!event || !event.changedTouches || !event.changedTouches.length) {
            return;
        }

        if ($(event.target).closest(".reel-viewer-comments, .publication-review-tooltip, .reel-publication-review").length) {
            this.touchStartY = null;
            this.touchStartX = null;
            this.touchDragY = 0;
            this.touchIsDragging = false;
            return;
        }

        if ($(event.target).closest(".reel-viewer-progress").length) {
            this.touchStartY = null;
            this.touchStartX = null;
            this.touchDragY = 0;
            this.touchIsDragging = false;
            return;
        }

        this.touchStartY = event.changedTouches[0].clientY;
        this.touchStartX = event.changedTouches[0].clientX;
        this.touchDragY = 0;
        this.touchIsDragging = false;
        this.resetSwipeDrag(false);
    },

    handleTouchEnd: function (event) {
        if (this.touchStartY === null || !event || !event.changedTouches || !event.changedTouches.length) {
            return;
        }

        var touch = event.changedTouches[0];
        var deltaY = touch.clientY - this.touchStartY;
        var deltaX = touch.clientX - this.touchStartX;
        this.touchStartY = null;
        this.touchStartX = null;
        this.touchDragY = 0;
        this.touchIsDragging = false;

        if (this.reels.length < 2) {
            this.resetSwipeDrag(true);
            return;
        }

        if (Math.abs(deltaY) < 55 || Math.abs(deltaY) < Math.abs(deltaX)) {
            this.resetSwipeDrag(true);
            return;
        }

        if (deltaY < 0) {
            this.commitSwipeDrag(-1);
        } else {
            this.commitSwipeDrag(1);
        }
    },

    handleTouchMove: function (event) {
        if (this.touchStartY === null || !event || !event.changedTouches || !event.changedTouches.length) {
            return;
        }

        if (this.reels.length < 2) {
            return;
        }

        if ($(event.target).closest(".reel-viewer-comments").length) {
            return;
        }

        if ($(event.target).closest(".reel-viewer-progress").length) {
            return;
        }

        var touch = event.changedTouches[0];
        var deltaY = touch.clientY - this.touchStartY;
        var deltaX = touch.clientX - this.touchStartX;

        if (Math.abs(deltaX) > 12 && Math.abs(deltaX) > Math.abs(deltaY)) {
            event.preventDefault();
            this.resetSwipeDrag(true);
            return;
        }

        if (Math.abs(deltaY) > 8 && Math.abs(deltaY) >= Math.abs(deltaX)) {
            event.preventDefault();
            this.touchDragY = deltaY;
            this.touchIsDragging = true;
            this.applySwipeDrag(deltaY);
        }
    },

    applySwipeDrag: function (deltaY) {
        var $phone = this.$feed.find(".reel-viewer-phone").first();
        var $stage = $phone.find(".reel-swipe-stage").first();
        var $next = $phone.find(".reel-swipe-preview-next").first();
        var $prev = $phone.find(".reel-swipe-preview-prev").first();
        var height = $phone.height() || window.innerHeight || 1;
        var clamped = Math.max(Math.min(deltaY, height), -height);

        if (!$stage.length) {
            return;
        }

        $phone.addClass("is-swipe-dragging").removeClass("is-swipe-resetting is-swipe-committing");
        $stage.css("transform", "translate3d(0," + clamped + "px,0)");

        if (clamped < 0) {
            $next.css("transform", "translate3d(0," + (height + clamped) + "px,0)");
            $prev.css("transform", "translate3d(0," + (-height) + "px,0)");
        } else {
            $prev.css("transform", "translate3d(0," + (-height + clamped) + "px,0)");
            $next.css("transform", "translate3d(0," + height + "px,0)");
        }
    },

    resetSwipeDrag: function (animate) {
        var $phone = this.$feed.find(".reel-viewer-phone").first();
        var $stage = $phone.find(".reel-swipe-stage").first();
        var $next = $phone.find(".reel-swipe-preview-next").first();
        var $prev = $phone.find(".reel-swipe-preview-prev").first();
        var height = $phone.height() || window.innerHeight || 1;

        if (!$stage.length) {
            return;
        }

        $phone.toggleClass("is-swipe-resetting", !!animate).removeClass("is-swipe-dragging is-swipe-committing");
        $stage.css("transform", "");
        $next.css("transform", "translate3d(0," + height + "px,0)");
        $prev.css("transform", "translate3d(0," + (-height) + "px,0)");

        if (animate) {
            window.setTimeout(function () {
                $phone.removeClass("is-swipe-resetting");
            }, 180);
        }
    },

    commitSwipeDrag: function (direction) {
        var self = this;
        var $phone = this.$feed.find(".reel-viewer-phone").first();
        var $stage = $phone.find(".reel-swipe-stage").first();
        var $next = $phone.find(".reel-swipe-preview-next").first();
        var $prev = $phone.find(".reel-swipe-preview-prev").first();
        var height = $phone.height() || window.innerHeight || 1;

        if (!$stage.length) {
            direction < 0 ? this.showNext() : this.showPrevious();
            return;
        }

        $phone.addClass("is-swipe-committing").removeClass("is-swipe-dragging is-swipe-resetting");

        if (direction < 0) {
            $stage.css("transform", "translate3d(0," + (-height) + "px,0)");
            $next.css("transform", "translate3d(0,0,0)");
        } else {
            $stage.css("transform", "translate3d(0," + height + "px,0)");
            $prev.css("transform", "translate3d(0,0,0)");
        }

        window.setTimeout(function () {
            $phone.removeClass("is-swipe-committing");
            direction < 0 ? self.showNext() : self.showPrevious();
        }, 170);
    },

    getActiveReel: function () {
        return this.activeIndex === null ? null : this.reels[this.activeIndex];
    },

    getAdjacentReel: function (offset) {
        if (this.activeIndex === null || this.reels.length < 2) {
            return null;
        }

        return this.reels[(this.activeIndex + offset + this.reels.length) % this.reels.length];
    },

    getActiveReelId: function () {
        var reel = this.getActiveReel();
        return reel ? reel.id : null;
    },

    playActiveVideo: function () {
        var video = this.$feed.find(".reel-viewer-video").get(0);
        if (video && typeof video.play === "function") {
            this.applyMutedState();
            this.queueVideoLoading(video.readyState < 3);
            video.play().catch(function () {
                ReelsPlayer.desiredPlayback = false;
                ReelsPlayer.pauseActiveSoundtrack();
                ReelsPlayer.setVideoLoading(false);
                ReelsPlayer.updatePlaybackUi();
            });
        }
    },

    pauseActiveVideo: function () {
        var video = this.$feed.find(".reel-viewer-video").get(0);
        if (video && typeof video.pause === "function") {
            video.pause();
        }
        this.pauseActiveSoundtrack();
    },

    toggleActivePlayback: function () {
        var video = this.$feed.find(".reel-viewer-video").get(0);
        if (!video) {
            return;
        }

        if (video.paused) {
            this.desiredPlayback = true;
            this.playActiveVideo();
        } else {
            this.desiredPlayback = false;
            video.pause();
            this.pauseActiveSoundtrack();
        }

        this.updatePlaybackUi();
    },

    updatePlaybackUi: function () {
        var video = this.$feed.find(".reel-viewer-video").get(0);
        var showPlayOverlay = !video || video.ended || this.desiredPlayback === false;
        this.$feed.find(".reel-viewer-phone").toggleClass("is-paused", showPlayOverlay);
        this.$feed.find(".reel-play-toggle")
            .attr("aria-label", showPlayOverlay ? trans("Play") : trans("Pause"))
            .toggleClass("is-paused", showPlayOverlay)
            .find("ion-icon")
            .attr("name", showPlayOverlay ? "play" : "pause");
    },

    attachActiveVideoEvents: function () {
        var self = this;
        var $video = this.$feed.find(".reel-viewer-video");

        $video.off(".reelsActive");

        $video.on("play.reelsActive", function () {
            self.desiredPlayback = true;
            self.setVideoLoading(false);
            self.clearVideoError();
            self.playActiveSoundtrack();
            self.updatePlaybackUi();
            self.updateProgressBar(this);
            self.startProgressAnimation(this);
            self.scheduleViewMark(self.getActiveReelId());
        });

        $video.on("pause.reelsActive", function () {
            self.clearViewTimer();
            self.setVideoLoading(false);
            self.stopProgressAnimation();
            self.pauseActiveSoundtrack();
            self.updateProgressBar(this);
            self.updatePlaybackUi();
        });

        $video.on("waiting.reelsActive stalled.reelsActive", function () {
            self.clearVideoError();
            self.queueVideoLoading(true);
        });

        $video.on("loadedmetadata.reelsActive durationchange.reelsActive loadeddata.reelsActive canplay.reelsActive canplaythrough.reelsActive playing.reelsActive seeked.reelsActive timeupdate.reelsActive", function () {
            self.setVideoLoading(false);
            self.updateProgressBar(this);
            self.syncActiveSoundtrackToVideo(this);
        });

        $video.on("error.reelsActive", function () {
            self.clearViewTimer();
            self.setVideoLoading(false);
            self.stopProgressAnimation();
            self.pauseActiveSoundtrack();
            self.resetProgressBar();
            self.showVideoError();
        });

        $video.on("volumechange.reelsActive", function () {
            if (self.hasActiveSoundtrack()) {
                return;
            }
            self.isMuted = !!this.muted;
            self.storeMuted(self.isMuted);
            self.updateMuteUi();
        });
    },

    queueVideoLoading: function (loading) {
        var self = this;
        if (!loading) {
            this.setVideoLoading(false);
            return;
        }

        this.clearLoadingTimer();
        this.loadingTimer = window.setTimeout(function () {
            var video = self.$feed.find(".reel-viewer-video").get(0);
            if (video && !video.paused && video.readyState < 3) {
                self.setVideoLoading(true);
            }
        }, 450);
    },

    setVideoLoading: function (loading) {
        if (!loading) {
            this.clearLoadingTimer();
        }

        this.$feed.find(".reel-video-loading").toggleClass("d-none", !loading);
        this.$feed.find(".reel-viewer-phone").toggleClass("is-loading", !!loading);
    },

    clearLoadingTimer: function () {
        if (this.loadingTimer) {
            window.clearTimeout(this.loadingTimer);
            this.loadingTimer = null;
        }
    },

    showVideoError: function () {
        this.desiredPlayback = false;
        this.pauseActiveSoundtrack();
        this.$feed.find(".reel-viewer-phone").addClass("has-video-error");
        this.$feed.find(".reel-video-error").removeClass("d-none");
        this.updatePlaybackUi();
    },

    clearVideoError: function () {
        this.$feed.find(".reel-viewer-phone").removeClass("has-video-error");
        this.$feed.find(".reel-video-error").addClass("d-none");
    },

    scheduleViewMark: function (reelId) {
        var self = this;
        this.clearViewTimer();

        if (!reelId || this.viewed[reelId]) {
            return;
        }

        this.viewTimer = window.setTimeout(function () {
            var video = self.$feed.find(".reel-viewer-video").get(0);
            self.viewTimer = null;
            if (String(self.getActiveReelId()) === String(reelId) && video && !video.paused) {
                self.markView(reelId);
            }
        }, this.viewThresholdMs);
    },

    clearViewTimer: function () {
        if (this.viewTimer) {
            window.clearTimeout(this.viewTimer);
            this.viewTimer = null;
        }
    },

    toggleMuted: function () {
        this.isMuted = !this.isMuted;
        this.storeMuted(this.isMuted);
        this.applyMutedState();
        this.updateMuteUi();
    },

    applyMutedState: function () {
        var video = this.$feed.find(".reel-viewer-video").get(0);
        var soundtrack = this.getActiveSoundtrack();
        var hasSoundtrack = !!soundtrack;

        if (video) {
            video.muted = hasSoundtrack ? true : this.isMuted;
        }

        if (soundtrack) {
            soundtrack.muted = this.isMuted;
            soundtrack.volume = this.isMuted ? 0 : 1;

            if (!this.isMuted && video && !video.paused && this.desiredPlayback) {
                this.playActiveSoundtrack();
            }
        }

        this.updateMuteUi();
    },

    updateMuteUi: function () {
        var icon = this.isMuted ? "volume-mute-outline" : "volume-high-outline";
        this.$feed.find(".reel-mute ion-icon").attr("name", icon);
        this.$feed.find(".reel-mute")
            .attr("aria-label", this.isMuted ? trans("Unmute") : trans("Mute"))
            .toggleClass("is-muted", this.isMuted);
    },

    updateProgressBar: function (video) {
        var progress = 0;

        if (video && isFinite(video.duration) && video.duration > 0) {
            progress = Math.max(0, Math.min(1, video.currentTime / video.duration));
        }

        this.$feed.find(".reel-viewer-progress span").css("transform", "scaleX(" + progress + ")");
    },

    startProgressAnimation: function (video) {
        var self = this;

        this.stopProgressAnimation();

        if (!video) {
            return;
        }

        var tick = function () {
            if (!video.paused && !video.ended && self.$feed.find(".reel-viewer-video").get(0) === video) {
                self.updateProgressBar(video);
                self.progressAnimationFrame = window.requestAnimationFrame(tick);
                return;
            }

            self.progressAnimationFrame = null;
            self.updateProgressBar(video);
        };

        this.progressAnimationFrame = window.requestAnimationFrame(tick);
    },

    beginProgressScrub: function (event) {
        var self = this;
        var originalEvent = event.originalEvent || event;
        var video = this.$feed.find(".reel-viewer-video").get(0);
        var progress = this.$feed.find(".reel-viewer-progress").get(0);

        if (!this.canUseProgressScrubbing() || !this.canScrubVideo(video) || !progress) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        this.isScrubbingProgress = true;
        $(progress).addClass("is-scrubbing");
        this.scrubProgressToEvent(originalEvent, video, progress);

        if (progress.setPointerCapture && originalEvent.pointerId !== undefined) {
            progress.setPointerCapture(originalEvent.pointerId);
        }

        $(document)
            .off(".reelsProgressScrub")
            .on("pointermove.reelsProgressScrub", function (moveEvent) {
                moveEvent.preventDefault();
                self.scrubProgressToEvent(moveEvent.originalEvent || moveEvent, video, progress);
            })
            .on("pointerup.reelsProgressScrub pointercancel.reelsProgressScrub", function (endEvent) {
                endEvent.preventDefault();
                self.endProgressScrub(endEvent.originalEvent || endEvent, video, progress);
            });
    },

    endProgressScrub: function (event, video, progress) {
        $(document).off(".reelsProgressScrub");
        $(progress).removeClass("is-scrubbing");

        if (this.isScrubbingProgress && this.canScrubVideo(video)) {
            this.scrubProgressToEvent(event, video, progress);
        }

        if (progress && progress.releasePointerCapture && event && event.pointerId !== undefined) {
            try {
                progress.releasePointerCapture(event.pointerId);
            } catch (e) {
                // Ignore browsers that reject stale pointer captures.
            }
        }

        this.isScrubbingProgress = false;

        if (video && !video.paused && !video.ended) {
            this.startProgressAnimation(video);
        } else {
            this.updateProgressBar(video);
        }
    },

    scrubProgressToEvent: function (event, video, progress) {
        var rect;
        var ratio;

        if (!this.canScrubVideo(video) || !progress) {
            return;
        }

        rect = progress.getBoundingClientRect();
        if (!rect.width) {
            return;
        }

        ratio = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width));
        video.currentTime = ratio * video.duration;
        this.syncActiveSoundtrackToVideo(video);
        this.updateProgressBar(video);
    },

    canScrubVideo: function (video) {
        return !!(video && isFinite(video.duration) && video.duration > 0);
    },

    canUseProgressScrubbing: function () {
        if (!this.allowProgressScrubbing) {
            return false;
        }

        if (this.isTouchDevice()) {
            return false;
        }

        if (!window.matchMedia) {
            return true;
        }

        return window.matchMedia("(hover: hover) and (pointer: fine)").matches;
    },

    isTouchDevice: function () {
        return !!((navigator.maxTouchPoints && navigator.maxTouchPoints > 0) || ("ontouchstart" in window));
    },

    stopProgressAnimation: function () {
        if (this.progressAnimationFrame) {
            window.cancelAnimationFrame(this.progressAnimationFrame);
            this.progressAnimationFrame = null;
        }
    },

    resetProgressBar: function () {
        this.$feed.find(".reel-viewer-progress span").css("transform", "scaleX(0)");
    },

    getActiveSoundtrack: function () {
        return this.$feed.find(".reel-viewer-soundtrack").get(0) || null;
    },

    hasActiveSoundtrack: function () {
        return !!this.getActiveSoundtrack();
    },

    playActiveSoundtrack: function () {
        var soundtrack = this.getActiveSoundtrack();
        var video = this.$feed.find(".reel-viewer-video").get(0);

        if (!soundtrack || !video || video.paused || this.desiredPlayback === false) {
            return;
        }

        soundtrack.muted = this.isMuted;
        soundtrack.volume = this.isMuted ? 0 : 1;

        if (this.shouldSyncSoundtrack(soundtrack, video)) {
            this.syncSoundtrackToVideo(soundtrack, video);
        }

        if (typeof soundtrack.play === "function") {
            soundtrack.play().catch(function () {});
        }
    },

    pauseActiveSoundtrack: function () {
        var soundtrack = this.getActiveSoundtrack();

        if (soundtrack && typeof soundtrack.pause === "function") {
            soundtrack.pause();
        }
    },

    syncActiveSoundtrackToVideo: function (video) {
        var soundtrack = this.getActiveSoundtrack();

        if (soundtrack && video && this.shouldSyncSoundtrack(soundtrack, video)) {
            this.syncSoundtrackToVideo(soundtrack, video);
        }
    },

    shouldSyncSoundtrack: function (soundtrack, video) {
        return !!(
            soundtrack &&
            video &&
            isFinite(soundtrack.duration) &&
            soundtrack.duration > 0 &&
            isFinite(video.currentTime)
        );
    },

    syncSoundtrackToVideo: function (soundtrack, video) {
        var targetTime = video.currentTime % soundtrack.duration;

        if (Math.abs(soundtrack.currentTime - targetTime) > 0.35) {
            try {
                soundtrack.currentTime = targetTime;
            } catch (e) {
                // Some browsers reject audio seeks while metadata is settling.
            }
        }
    },

    getStoredMuted: function () {
        try {
            var stored = window.localStorage ? window.localStorage.getItem("reels_muted") : null;
            return stored === null ? true : stored !== "0";
        } catch (e) {
            return true;
        }
    },

    storeMuted: function (muted) {
        try {
            if (window.localStorage) {
                window.localStorage.setItem("reels_muted", muted ? "1" : "0");
            }
        } catch (e) {
            // Private browsing/storage policies can block localStorage writes.
        }
    },

    isTypingTarget: function (target) {
        var $target = $(target);
        return $target.is("input, textarea, select") || $target.closest("[contenteditable='true']").length > 0;
    },

    markView: function (reelId) {
        var self = this;
        if (!reelId || this.viewed[reelId]) {
            return;
        }

        this.viewed[reelId] = true;
        ReelsApi.markView(reelId).done(function (response) {
            if (response && typeof response.views !== "undefined") {
                self.updateActiveReel({views: response.views});
                self.$feed.find(".reel-views-count").text(self.formatCount(response.views));
            }
        });
    },

    toggleReaction: function () {
        var self = this;
        var reel = this.getActiveReel();
        var $button = this.$feed.find(".reel-react").first();
        var lockKey;
        var wasReacted;
        var previousCount;
        var nextActive;
        var nextCount;
        var action;
        if (!reel) return;

        lockKey = "reel-reaction-" + reel.id;
        if (!this.beginAction(lockKey, $button)) {
            return;
        }

        wasReacted = !!reel.reacted;
        previousCount = parseInt(reel.reactions || 0, 10);
        nextActive = !wasReacted;
        nextCount = Math.max(0, previousCount + (nextActive ? 1 : -1));
        action = nextActive ? "add" : "remove";

        this.updateReelById(reel.id, {
            reacted: nextActive,
            reactions: nextCount
        });
        this.updateActiveViewerAction(reel.id, "reel-react", nextActive, nextCount);
        this.renderGridCounts();

        this.postAction("/reels/reaction", {type: "reel", id: reel.id, action: action}, function (response) {
            var serverCount = typeof response.reactions !== "undefined" ? response.reactions : nextCount;
            self.updateReelById(reel.id, {
                reacted: nextActive,
                reactions: serverCount
            });
            self.updateActiveViewerAction(reel.id, "reel-react", nextActive, serverCount);
            self.renderGridCounts();
        }, function () {
            self.updateReelById(reel.id, {
                reacted: wasReacted,
                reactions: previousCount
            });
            self.updateActiveViewerAction(reel.id, "reel-react", wasReacted, previousCount);
            self.renderGridCounts();
        }, function () {
            self.endAction(lockKey, $button);
        });
    },

    toggleBookmark: function () {
        var self = this;
        var reel = this.getActiveReel();
        var $button = this.$feed.find(".reel-bookmark").first();
        var lockKey;
        var wasBookmarked;
        var previousCount;
        var nextActive;
        var nextCount;
        var action;
        if (!reel) return;

        lockKey = "reel-bookmark-" + reel.id;
        if (!this.beginAction(lockKey, $button)) {
            return;
        }

        wasBookmarked = !!reel.bookmarked;
        previousCount = parseInt(reel.bookmarks || 0, 10);
        nextActive = !wasBookmarked;
        nextCount = Math.max(0, previousCount + (nextActive ? 1 : -1));
        action = nextActive ? "add" : "remove";

        this.updateReelById(reel.id, {
            bookmarked: nextActive,
            bookmarks: nextCount
        });
        this.updateActiveViewerAction(reel.id, "reel-bookmark", nextActive, nextCount);

        this.postAction("/reels/bookmark", {id: reel.id, action: action}, function (response) {
            var serverCount = typeof response.bookmarks !== "undefined" ? response.bookmarks : nextCount;
            self.updateReelById(reel.id, {
                bookmarked: nextActive,
                bookmarks: serverCount
            });
            self.updateActiveViewerAction(reel.id, "reel-bookmark", nextActive, serverCount);
            launchToast("success", trans("Success"), response.message || trans(action === "add" ? "Reel saved." : "Reel removed from bookmarks."));
        }, function () {
            self.updateReelById(reel.id, {
                bookmarked: wasBookmarked,
                bookmarks: previousCount
            });
            self.updateActiveViewerAction(reel.id, "reel-bookmark", wasBookmarked, previousCount);
        }, function () {
            self.endAction(lockKey, $button);
        });
    },

    updateActiveViewerAction: function (reelId, className, active, count) {
        if (String(this.getActiveReelId()) !== String(reelId)) {
            return;
        }

        this.updateViewerAction(className, active, count);
    },

    updateViewerAction: function (className, active, count) {
        var button = this.$feed.find("." + className).first();
        if (!button.length) {
            return;
        }

        button.toggleClass("is-active", !!active);
        button.find("ion-icon").toggleClass("text-primary", !!active).css("color", "");
        button.find("span").text(this.formatCount(count || 0));
    },

    beginAction: function (key, $buttons) {
        if (this.actionLocks[key]) {
            return false;
        }

        this.actionLocks[key] = true;
        this.setActionDisabled($buttons, true);
        return true;
    },

    endAction: function (key, $buttons) {
        delete this.actionLocks[key];
        this.setActionDisabled($buttons, false);
    },

    setActionDisabled: function ($buttons, disabled) {
        if (!$buttons || !$buttons.length) {
            return;
        }

        $buttons.prop("disabled", disabled).toggleClass("is-busy", disabled);
    },

    showReportBoxAfterClosingViewer: function (userId, postId, messageId, streamId, storyId, reelId, reelCommentId) {
        this.reportReturnIndex = this.activeIndex;

        this.closeViewer();

        setTimeout(function () {
            if (window.Lists && typeof Lists.showReportBox === "function") {
                Lists.showReportBox(userId, postId, messageId, streamId, storyId, reelId, reelCommentId);
            }
        }, 300);
    },

    restoreAfterReportModal: function () {
        var returnIndex = this.reportReturnIndex;

        this.reportReturnIndex = null;

        if (returnIndex !== null && this.activeIndex === null && this.reels[returnIndex]) {
            this.openViewer(returnIndex);
        }
    },

    reportReel: function () {
        var reel = this.getActiveReel();
        if (!reel || !window.Lists || typeof Lists.showReportBox !== "function") {
            return;
        }

        this.showReportBoxAfterClosingViewer((reel.user || {}).id, null, null, null, null, reel.id, null);
    },

    deleteReel: function () {
        var reel = this.getActiveReel();
        if (!reel) return;

        this.pendingDeleteReelId = reel.id;
        showDialog("reel-delete-dialog");
    },

    confirmDeleteReel: function () {
        var self = this;
        var reelId = this.pendingDeleteReelId;
        var reel = this.reels.find(function (item) {
            return String(item.id) === String(reelId);
        });
        var $button = this.$feed.find(".reel-delete").first();
        var $dialogButton = $("#reel-delete-dialog [data-dialog-action]").first();
        var lockKey;

        if (!reel) {
            hideDialog("reel-delete-dialog");
            return;
        }

        lockKey = "reel-delete-" + reel.id;
        if (!this.beginAction(lockKey, $button.add($dialogButton))) {
            return;
        }

        this.ajaxAction("DELETE", "/reels/delete", {reel_id: reel.id}, function () {
            var removedIndex = self.reels.findIndex(function (item) {
                return String(item.id) === String(reel.id);
            });
            var nextIndex = Math.min(removedIndex < 0 ? (self.activeIndex || 0) : removedIndex, self.reels.length - 2);
            hideDialog("reel-delete-dialog");
            self.pendingDeleteReelId = null;
            self.reels = self.reels.filter(function (item) {
                return String(item.id) !== String(reel.id);
            });
            self.render();

            if (self.reels.length) {
                self.openViewer(Math.max(nextIndex, 0));
            } else {
                self.activeIndex = null;
                $("html, body").removeClass("reel-viewer-open reel-viewer-touch");
                self.restoreBaseUrl();
            }
        }, function () {
            self.endAction(lockKey, $button.add($dialogButton));
        });
    },

    clearViewerRequestState: function () {
        this.actionLocks = {};
        this.$feed.find(".is-busy").removeClass("is-busy").prop("disabled", false);
        this.$feed.find(".reel-comment-form").each(function () {
            var $form = $(this);
            $form.data("is-submitting", false);
            ReelsPlayer.setCommentFormLoading($form, false);
        });
    },

    updateCommentCount: function (count) {
        count = parseInt(count || 0, 10);
        this.updateActiveReel({comments: count});
        this.$feed.find(".reel-comments-toggle span, .reel-comments-count").text(this.formatCount(count));
    },

    updateActiveReel: function (attributes) {
        if (this.activeIndex === null || !this.reels[this.activeIndex]) {
            return;
        }

        this.reels[this.activeIndex] = $.extend({}, this.reels[this.activeIndex], attributes);
    },

    updateReelById: function (reelId, attributes) {
        for (var i = 0; i < this.reels.length; i += 1) {
            if (String(this.reels[i].id) === String(reelId)) {
                this.reels[i] = $.extend({}, this.reels[i], attributes);
                return;
            }
        }
    },

    renderGridCounts: function () {
        this.reels.forEach(function (reel, index) {
            ReelsPlayer.$feed
                .find('.reel-grid-card[data-reel-index="' + index + '"] .reel-grid-stats')
                .replaceWith(ReelsRenderer.renderGridStats(reel));
        });
    },

    startGridPreview: function ($card) {
        if (!window.matchMedia || !window.matchMedia("(hover: hover)").matches) {
            return;
        }

        var video = $card.find(".reel-grid-preview").get(0);
        if (!video) {
            return;
        }

        $card.addClass("is-previewing");
        video.play().catch(function () {
            $card.removeClass("is-previewing");
        });
    },

    stopGridPreview: function ($card) {
        var video = $card.find(".reel-grid-preview").get(0);
        $card.removeClass("is-previewing");

        if (!video) {
            return;
        }

        video.pause();
        video.currentTime = 0;
    },

    setNavigationLock: function () {
        var self = this;
        this.clearNavigationLock();
        this.navigationLock = true;
        this.$feed.find(".reel-viewer").addClass("is-navigating");
        this.navigationLockTimer = window.setTimeout(function () {
            self.clearNavigationLock();
        }, 280);
    },

    clearNavigationLock: function () {
        if (this.navigationLockTimer) {
            window.clearTimeout(this.navigationLockTimer);
            this.navigationLockTimer = null;
        }

        this.navigationLock = false;
        this.$feed.find(".reel-viewer").removeClass("is-navigating");
    },

    preloadAdjacentReels: function (index) {
        if (!this.reels.length) {
            return;
        }

        this.preloadReelAt((index + 1) % this.reels.length);
        this.preloadReelAt((index - 1 + this.reels.length) % this.reels.length);
    },

    preloadReelAt: function (index) {
        var reel = this.reels[index];
        if (!reel || !reel.src || this.preloadVideos[reel.src]) {
            return;
        }

        var video = document.createElement("video");
        video.preload = "metadata";
        video.muted = true;
        video.playsInline = true;
        video.src = reel.src;
        this.preloadVideos[reel.src] = video;
    },

    getReelPermalink: function (reel) {
        if (!reel) {
            return window.location.href;
        }

        if (this.permalinkTemplate) {
            return this.permalinkTemplate.replace("__REEL_ID__", encodeURIComponent(reel.id));
        }

        return reel.url || window.location.href;
    },

    prepareInitialHistoryState: function () {
        if (!window.history || !window.history.replaceState) {
            return;
        }

        var currentState = window.history.state || {};
        var baseUrl = this.baseUrl || this.originalUrl;
        var targetUrl = this.initialReel ? baseUrl : window.location.href;

        window.history.replaceState($.extend({}, currentState, {
            reelsFeed: true,
            reelsViewer: false,
            reelsBaseUrl: baseUrl
        }), "", targetUrl);
        this.originalUrl = targetUrl || this.originalUrl;
        this.hasPushedViewerHistory = false;
    },

    pushReelUrl: function (reel) {
        if (!window.history || !window.history.pushState || !reel) {
            return;
        }

        var url = this.getReelPermalink(reel);
        if (url === window.location.href) {
            return;
        }

        var state = {
            reelsFeed: false,
            reelsViewer: true,
            reelsBaseUrl: this.baseUrl || this.originalUrl,
            reelId: reel.id
        };

        if (this.hasPushedViewerHistory) {
            window.history.replaceState(state, "", url);
            return;
        }

        window.history.pushState(state, "", url);
        this.hasPushedViewerHistory = true;
    },

    restoreBaseUrl: function () {
        if (!window.history || !window.history.replaceState) {
            return;
        }

        var url = this.baseUrl || this.originalUrl;
        if (url && url !== window.location.href) {
            window.history.replaceState({
                reelsFeed: true,
                reelsViewer: false,
                reelsBaseUrl: url
            }, "", url);
        }
        this.hasPushedViewerHistory = false;
    },

    handlePopState: function (event) {
        var state = event ? event.state : null;

        this.clearPendingHistoryClose();

        if (this.activeIndex !== null) {
            this.closeViewer({restoreUrl: false});
            return;
        }

        if (state && state.reelsViewer && state.reelId) {
            this.openReelFromHistory(state.reelId);
        }
    },

    openReelFromHistory: function (reelId) {
        var index = this.reels.findIndex(function (reel) {
            return String(reel.id) === String(reelId);
        });

        if (index < 0) {
            return;
        }

        this.restoringFromHistory = true;
        this.openViewer(index);
        this.restoringFromHistory = false;
        this.hasPushedViewerHistory = true;
    },

    postAction: function (url, data, onSuccess, onError, onComplete) {
        return this.ajaxAction("POST", url, data, onSuccess, onError, onComplete);
    },

    ajaxAction: function (method, url, data, onSuccess, onError, onComplete) {
        return ReelsApi.action(method, url, data, onSuccess, onError, onComplete);
    },

    formatCount: function (value) {
        return ReelsRenderer.formatCount(value);
    },

    escape: function (value) {
        return ReelsRenderer.escape(value);
    },

    escapeAttr: function (value) {
        return ReelsRenderer.escapeAttr(value);
    }
};

if (window.ReelsComments) {
    $.extend(ReelsPlayer, window.ReelsComments);
}

window.ReelsPlayer = ReelsPlayer;
