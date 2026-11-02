/**
 * Feed reels strip.
 */
"use strict";
/* global app, ReelsPlayer, trans, Swiper, renderUserStaffBadge */

$(function () {
    FeedReelsWidget.init();
});

$(document).on("posts:appended", function () {
    FeedReelsWidget.insertAfterAppend();
    FeedReelsWidget.init();
});

var FeedReelsWidget = {
    maxExcludedIds: 100,
    renderedWidgets: 0,
    randomSeed: Math.floor(Math.random() * 1000000) + 1,
    displayedReelIds: {},

    init: function () {
        var self = this;

        this.insertDueWidgets();

        $(".feed-reels-widget:not(.is-initialized)").each(function () {
            self.initWidget($(this));
        });
    },

    insertDueWidgets: function () {
        var config = this.getConfig();
        var desiredPositions = this.getDesiredWidgetPositions(config);
        var widgetCount = $(".feed-reels-widget").length;

        if (!config.enabled || !desiredPositions.length) {
            return;
        }

        while (widgetCount < desiredPositions.length && widgetCount < config.maxWidgets) {
            this.insertWidgetAtPostPosition(config, widgetCount, desiredPositions[widgetCount]);
            widgetCount += 1;
        }
    },

    getDesiredWidgetPositions: function (config) {
        var totalPosts = $(".posts-wrapper .post-box").length;
        var positions = [];
        var nextPosition = config.firstAfterPosts;

        if (totalPosts === 0) {
            return positions;
        }

        if (config.placementMode !== "repeat") {
            return [nextPosition];
        }

        positions.push(nextPosition);

        if (nextPosition === 0) {
            nextPosition = config.repeatEveryPosts;
        } else {
            nextPosition += config.repeatEveryPosts;
        }

        while (nextPosition <= totalPosts && positions.length < config.maxWidgets) {
            positions.push(nextPosition);
            nextPosition += config.repeatEveryPosts;
        }

        return positions;
    },

    initWidget: function ($widget) {
        var self = this;
        var config = this.getConfig();
        var maxWidgets = parseInt($widget.data("max-widgets") || config.maxWidgets || 1, 10);
        var widgetIndex = parseInt($widget.data("widget-index") || 0, 10);

        if (config.avoidRepeats && this.hasPendingPreviousWidget(widgetIndex)) {
            window.setTimeout(function () {
                self.initWidget($widget);
            }, 120);
            return;
        }

        $widget.addClass("is-initialized");

        if (this.renderedWidgets >= maxWidgets) {
            this.removeWidget($widget);
            return;
        }

        this.renderedWidgets += 1;
        this.bindWidgetEvents($widget);
        this.fetchWidget($widget);
    },

    hasPendingPreviousWidget: function (widgetIndex) {
        var pending = false;

        $(".feed-reels-widget").each(function () {
            var $widget = $(this);
            var currentIndex = parseInt($widget.data("widget-index") || 0, 10);

            if (currentIndex < widgetIndex && $widget.data("loaded") !== true) {
                pending = true;
                return false;
            }

            return true;
        });

        return pending;
    },

    bindWidgetEvents: function ($widget) {
        var self = this;

        $widget.on("click", ".feed-reel-card", function () {
            var reels = $widget.data("reels") || [];
            var index = parseInt($(this).data("reel-index"), 10);

            if (!window.ReelsPlayer || !reels[index]) {
                return;
            }

            self.stopCardPreview($(this));
            ReelsPlayer.openExternalList(reels, index, {
                baseUrl: $widget.data("base-url") || window.location.href,
                permalinkTemplate: $widget.data("permalink-template") || (app.baseUrl + "/reels/__REEL_ID__")
            });
        });

        $widget.on("click", ".feed-reels-scroll", function () {
            var direction = $(this).hasClass("feed-reels-scroll-prev") ? -1 : 1;
            self.stopWidgetPreviews($widget);
            self.scrollTrack($widget, direction);
        });

        $widget.on("mouseenter focusin", ".feed-reel-card", function () {
            self.startCardPreview($(this));
        });

        $widget.on("mouseleave focusout", ".feed-reel-card", function () {
            self.stopCardPreview($(this));
        });

        $widget.on("error", ".feed-reel-poster", function () {
            var $image = $(this);
            $image.addClass("d-none");
            $image.closest(".feed-reel-media").find(".feed-reel-placeholder").removeClass("d-none");
        });

        $widget.on("error", ".feed-reel-preview", function () {
            self.stopCardPreview($(this).closest(".feed-reel-card"));
        });
    },

    startCardPreview: function ($card) {
        if (!this.supportsCardPreviews()) {
            return;
        }

        var video = $card.find(".feed-reel-preview").get(0);
        if (!video) {
            return;
        }

        if (!video.getAttribute("src")) {
            video.setAttribute("src", video.getAttribute("data-preview-src") || "");
        }

        if (!video.getAttribute("src")) {
            return;
        }

        this.stopOtherPreviews($card);
        $card.addClass("is-previewing");
        var playAttempt = video.play();

        if (playAttempt && typeof playAttempt.catch === "function") {
            playAttempt.catch(function () {
                $card.removeClass("is-previewing");
            });
        }
    },

    stopCardPreview: function ($card) {
        var video = $card.find(".feed-reel-preview").get(0);
        $card.removeClass("is-previewing");

        if (!video) {
            return;
        }

        video.pause();
        video.currentTime = 0;
    },

    supportsCardPreviews: function () {
        return window.matchMedia
            && window.matchMedia("(hover: hover) and (pointer: fine)").matches;
    },

    stopOtherPreviews: function ($activeCard) {
        var self = this;

        $(".feed-reel-card.is-previewing").not($activeCard).each(function () {
            self.stopCardPreview($(this));
        });
    },

    stopWidgetPreviews: function ($widget) {
        var self = this;

        $widget.find(".feed-reel-card.is-previewing").each(function () {
            self.stopCardPreview($(this));
        });
    },

    fetchWidget: function ($widget, retainedReels) {
        var self = this;
        var feedUrl = this.buildFeedUrl($widget);
        var limit = parseInt($widget.data("cards-per-widget") || this.getConfig().cardsPerWidget || 12, 10);
        retainedReels = retainedReels || [];

        $.get(feedUrl, function (response) {
            var reelIds = new Set();
            var reels = retainedReels.concat(response.reels || []).filter(function (reel) {
                var id = String(reel.id);
                if (reelIds.has(id)) {
                    return false;
                }
                reelIds.add(id);
                return true;
            }).slice(0, limit);

            if (reels.length < limit && $widget.data("ignore-exclude") !== true && self.getExcludedIds($widget).length) {
                // Keep fresh reels first, then fill the remaining slots from earlier selections.
                $widget.data("ignore-exclude", true);
                $widget.data("reel-offset", 0);
                $widget.attr("data-reel-offset", 0);
                self.fetchWidget($widget, reels);
                return;
            }

            if (!reels.length && parseInt($widget.data("reel-offset") || 0, 10) > 0) {
                $widget.data("reel-offset", 0);
                $widget.attr("data-reel-offset", 0);
                self.fetchWidget($widget);
                return;
            }

            self.renderWidget($widget, reels);
        }).fail(function () {
            self.renderWidget($widget, retainedReels);
        });
    },

    renderWidget: function ($widget, reels) {
        var self = this;

        if (!reels.length) {
            this.removeWidget($widget);
            return;
        }

        $widget.data("reels", reels);
        this.recordDisplayedReels(reels);
        $widget.data("loaded", true);
        $widget.find(".feed-reels-track .swiper-wrapper").html(reels.map(function (reel, index) {
            return self.renderCard(reel, index);
        }).join("") + this.renderCreateCard());
        this.initCarousel($widget);
        this.disableRightClick($widget);
        this.updateControls($widget);
    },

    buildFeedUrl: function ($widget) {
        var config = this.getConfig();
        var feedUrl = $widget.data("feed-url") || config.feedUrl || (app.baseUrl + "/reels/feed");
        var separator = feedUrl.indexOf("?") >= 0 ? "&" : "?";
        var limit = parseInt($widget.data("cards-per-widget") || config.cardsPerWidget || 12, 10);
        var excludedIds = config.avoidRepeats && $widget.data("ignore-exclude") !== true ? this.getExcludedIds($widget) : [];
        var offset = excludedIds.length ? 0 : parseInt($widget.data("reel-offset") || 0, 10);
        var params = [
            "limit=" + encodeURIComponent(limit),
            "offset=" + encodeURIComponent(offset)
        ];

        if (config.randomizeCards) {
            params.push("randomize=1");
            params.push("seed=" + encodeURIComponent(this.randomSeed + parseInt($widget.data("widget-index") || 0, 10)));
        }

        if (config.prioritizeUnseen) {
            params.push("prioritize_unseen=1");
        }

        if (excludedIds.length) {
            params.push("exclude_ids=" + encodeURIComponent(excludedIds.join(",")));
        }

        return feedUrl + separator + params.join("&");
    },

    getExcludedIds: function ($widget) {
        if (parseInt($widget.data("widget-index") || 0, 10) <= 0) {
            return [];
        }

        return Object.keys(this.displayedReelIds).slice(0, this.maxExcludedIds);
    },

    recordDisplayedReels: function (reels) {
        var self = this;

        reels.forEach(function (reel) {
            if (reel && reel.id) {
                self.displayedReelIds[parseInt(reel.id, 10)] = true;
            }
        });
    },

    removeWidget: function ($widget) {
        var swiper = $widget.data("swiper");
        var $separator = $widget.next("hr");

        if (swiper && swiper.destroy) {
            swiper.destroy(true, true);
        }

        if ($separator.length) {
            $separator.remove();
        }
        $widget.remove();
    },

    insertAfterAppend: function () {
        this.insertDueWidgets();
    },

    insertWidgetAtPostPosition: function (config, widgetIndex, postPosition) {
        var $wrapper = $(".posts-wrapper").first();
        var $posts = $wrapper.find(".post-box");
        var widgetHtml = this.renderWidgetShell(config, widgetIndex);

        if (!$wrapper.length) {
            return;
        }

        if (postPosition <= 0 || !$posts.length) {
            $wrapper.prepend(widgetHtml);
            return;
        }

        var $targetPost = $posts.eq(Math.min(postPosition, $posts.length) - 1);
        var $separator = $targetPost.next("hr");

        if ($separator.length) {
            $separator.after(widgetHtml);
            return;
        }

        $targetPost.after(widgetHtml);
    },

    renderWidgetShell: function (config, widgetIndex) {
        var reelOffset = widgetIndex * config.cardsPerWidget;
        var isDarkTheme = app && app.theme === "dark";
        var themeClasses = isDarkTheme ? " text-white is-theme-dark" : " bg-white text-dark-r";
        var skeletons = "";

        for (var i = 0; i < Math.min(config.cardsPerWidget, 4); i += 1) {
            skeletons += '<div class="swiper-slide feed-reel-slide feed-reel-skeleton"></div>';
        }

        return '' +
            '<section class="feed-reels-widget' + themeClasses + '" ' +
                'data-feed-url="' + this.escapeAttr(config.feedUrl) + '" ' +
                'data-reels-url="' + this.escapeAttr(config.reelsUrl) + '" ' +
                'data-base-url="' + this.escapeAttr(config.baseUrl) + '" ' +
                'data-permalink-template="' + this.escapeAttr(config.permalinkTemplate) + '" ' +
                'data-cards-per-widget="' + this.escapeAttr(config.cardsPerWidget) + '" ' +
                'data-widget-index="' + this.escapeAttr(widgetIndex) + '" ' +
                'data-reel-offset="' + this.escapeAttr(reelOffset) + '" ' +
                'data-max-widgets="' + this.escapeAttr(config.maxWidgets) + '">' +
                '<div class="feed-reels-widget-header">' +
                    '<h6>' + this.escape(config.labels.reels) + '</h6>' +
                    '<div class="feed-reels-widget-actions">' +
                        '<a href="' + this.escapeAttr(config.reelsUrl) + '">' + this.escape(config.labels.seeAll) + '</a>' +
                    '</div>' +
                '</div>' +
                '<div class="feed-reels-track swiper-container" aria-label="' + this.escapeAttr(config.labels.reels) + '"><div class="swiper-wrapper">' + skeletons + '</div></div>' +
            '</section><hr>';
    },

    renderCard: function (reel, index) {
        var user = reel.user || {};
        var image = reel.cover || "";
        var video = reel.src || "";
        var username = user.username ? ("@" + user.username) : "";
        var verified = window.ReelsPlayer ? ReelsPlayer.renderVerifiedBadge(user.verified, "feed-reel-verified") : "";
        var staff = typeof renderUserStaffBadge === 'function' ? renderUserStaffBadge(user.staff_badge) : '';
        var protectedMediaClass = this.getProtectedMediaClass();

        return '' +
            '<div class="swiper-slide feed-reel-slide">' +
                '<button type="button" class="feed-reel-card" data-reel-index="' + this.escapeAttr(index) + '">' +
                    '<span class="feed-reel-media' + protectedMediaClass + '">' +
                        (image ? '<img class="feed-reel-poster' + protectedMediaClass + '" src="' + this.escapeAttr(image) + '" alt="">' : '') +
                        (video ? '<video class="feed-reel-preview' + protectedMediaClass + '" data-preview-src="' + this.escapeAttr(video) + '" muted playsinline loop preload="none"></video>' : '') +
                        '<span class="feed-reel-placeholder' + (image ? " d-none" : "") + '"><ion-icon name="play-outline"></ion-icon><span>' + this.escape(trans("No preview")) + '</span></span>' +
                        '<span class="feed-reel-gradient"></span>' +
                        '<span class="feed-reel-stats">' +
                            '<span><ion-icon name="play-outline"></ion-icon>' + this.escape(this.formatCount(reel.views || 0)) + '</span>' +
                            '<span><ion-icon name="heart-outline"></ion-icon>' + this.escape(this.formatCount(reel.reactions || 0)) + '</span>' +
                        '</span>' +
                    '</span>' +
                    '<span class="feed-reel-meta">' +
                        '<span class="feed-reel-avatar"><img src="' + this.escapeAttr(user.photo || "") + '" alt=""></span>' +
                        '<span class="feed-reel-title"><span>' + this.escape(username) + '</span>' + verified + staff + '</span>' +
                    '</span>' +
                '</button>' +
            '</div>';
    },

    renderCreateCard: function () {
        var config = this.getConfig();
        if (!config.creationVisible) return "";
        var access = document.querySelector('[data-public-creation-access]');
        var canViewCreationPage = !access || access.dataset.canViewCreationPage !== 'false';
        var actionUrl = canViewCreationPage ? config.createUrl : access.dataset.onboardingUrl;
        var actionLabel = canViewCreationPage ? config.labels.createYourReel : access.dataset.onboardingLabel;

        if (!actionUrl) {
            return "";
        }

        return '' +
            '<div class="swiper-slide feed-reel-slide">' +
                '<a class="feed-reel-create-card" href="' + this.escapeAttr(actionUrl) + '" aria-label="' + this.escapeAttr(actionLabel) + '">' +
                    '<span class="feed-reel-create-media text-primary">' +
                        '<span class="feed-reel-create-icon"><ion-icon name="add-outline"></ion-icon></span>' +
                    '</span>' +
                    '<span class="feed-reel-create-title">' + this.escape(actionLabel) + '</span>' +
                '</a>' +
            '</div>';
    },

    initCarousel: function ($widget) {
        var track = $widget.find(".feed-reels-track").get(0);
        var previousSwiper = $widget.data("swiper");

        if (previousSwiper && previousSwiper.destroy) {
            previousSwiper.destroy(true, true);
            $widget.removeData("swiper");
        }

        if (!track || typeof Swiper === "undefined") {
            return;
        }

        $widget.data("swiper", new Swiper(track, {
            slidesPerView: "auto",
            spaceBetween: 14,
            freeMode: true,
            watchOverflow: true
        }));
    },

    scrollTrack: function ($widget, direction) {
        var swiper = $widget.data("swiper");
        var track = $widget.find(".feed-reels-track").get(0);

        if (swiper) {
            if (direction < 0) {
                swiper.slidePrev();
            } else {
                swiper.slideNext();
            }
            return;
        }

        if (!track) {
            return;
        }

        track.scrollBy({
            left: direction * Math.max(track.clientWidth * 0.75, 180),
            behavior: "smooth"
        });
    },

    updateControls: function ($widget) {
        var swiper = $widget.data("swiper");
        var track = $widget.find(".feed-reels-track").get(0);
        var canScroll = swiper
            ? !swiper.isLocked
            : track && track.scrollWidth > track.clientWidth + 4;

        $widget.find(".feed-reels-scroll").toggleClass("d-none", !canScroll);
    },

    formatCount: function (value) {
        return window.ReelsPlayer ? ReelsPlayer.formatCount(value) : String(parseInt(value || 0, 10));
    },

    disableRightClick: function ($widget) {
        if (!window.app || app.feedDisableRightClickOnMedia !== true || !$widget || !$widget.length) {
            return;
        }

        $widget
            .off("contextmenu.feedReelsMedia")
            .on("contextmenu.feedReelsMedia", ".feed-reel-media, .feed-reel-poster, .feed-reel-preview", function () {
                return false;
            });

        if (typeof window.bindNoLongPressEvents === "function") {
            window.bindNoLongPressEvents();
        }
    },

    shouldProtectMedia: function () {
        return typeof window.app !== "undefined" && window.app.feedDisableRightClickOnMedia === true;
    },

    getProtectedMediaClass: function () {
        return this.shouldProtectMedia() ? " no-long-press" : "";
    },

    escape: function (value) {
        return $("<div>").text(value === null ? "" : value).html();
    },

    escapeAttr: function (value) {
        return this.escape(value).replace(/"/g, "&quot;");
    },

    getConfig: function () {
        var config = window.feedReelsWidgetConfig || {};
        var labels = config.labels || {};

        return {
            enabled: config.enabled !== false,
            placementMode: config.placementMode === "repeat" ? "repeat" : "once",
            firstAfterPosts: Math.max(0, parseInt(config.firstAfterPosts === null ? 3 : config.firstAfterPosts, 10)),
            repeatEveryPosts: Math.max(1, parseInt(config.repeatEveryPosts || 10, 10)),
            cardsPerWidget: Math.max(1, Math.min(parseInt(config.cardsPerWidget || 12, 10), 30)),
            maxWidgets: Math.max(1, parseInt(config.maxWidgets || 1, 10)),
            randomizeCards: config.randomizeCards !== false,
            prioritizeUnseen: config.prioritizeUnseen !== false,
            avoidRepeats: config.avoidRepeats !== false,
            feedUrl: config.feedUrl || (app.baseUrl + "/reels/feed"),
            reelsUrl: config.reelsUrl || (app.baseUrl + "/reels"),
            createUrl: config.createUrl || (app.baseUrl + "/reels/create"),
            creationVisible: config.creationVisible !== false,
            baseUrl: config.baseUrl || window.location.href,
            permalinkTemplate: config.permalinkTemplate || (app.baseUrl + "/reels/__REEL_ID__"),
            labels: {
                reels: labels.reels || trans("Reels"),
                seeAll: labels.seeAll || trans("See all"),
                createYourReel: labels.createYourReel || trans("Create your reel"),
                previous: labels.previous || trans("Previous"),
                next: labels.next || trans("Next")
            }
        };
    }
};
