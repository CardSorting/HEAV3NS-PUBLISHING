/**
 * Profile stories component
 */
/* global app, Swiper, trans */
"use strict";

var StoriesProfile = {
    activeStories: [],
    highlightStories: [],
    _bound: false,

    init: function (opts) {
        opts = opts || {};
        if (!window.StoriesSwiper) return;

        // ensure keyboard arrows work on profile pages too
        if (!window.StoriesSwiper._profileShortcutsBound) {
            window.StoriesSwiper._profileShortcutsBound = true;
            window.StoriesSwiper.bindGlobalShortcuts();
        }

        var username = opts.username ? String(opts.username) : "";
        if (!username) return;

        var triggerSelector = opts.triggerSelector || ".profile-avatar-wrap";
        var trigger = document.querySelector(triggerSelector);
        if (!trigger) return;

        // prevent double binding
        if (trigger.dataset.storiesBound === "1") return;
        trigger.dataset.storiesBound = "1";

        var activeUrl = app.baseUrl + "/stories/profile/" + encodeURIComponent(username);
        var highlightsUrl = app.baseUrl + "/stories/highlights/" + encodeURIComponent(username);

        var storyIdToOpen = opts.storyId ? parseInt(opts.storyId, 10) : 0;

        // 👇 detect own profile
        var isOwnProfile =
            window.profileVars &&
            window.profileVars.user_id &&
            window.app &&
            window.app.userId &&
            String(window.profileVars.user_id) === String(window.app.userId);

        /* ----------------------------------------------------
           1) Fetch ACTIVE stories (avatar click)
        ---------------------------------------------------- */
        var p1 = window.StoriesSwiper.fetchStories(activeUrl)
            .then((data) => {
                this.activeStories = window.StoriesSwiper.normalizeStories(data) || [];
                var hasActive = Array.isArray(this.activeStories) && this.activeStories.length > 0;

                // toggle clickability only
                trigger.classList.toggle("profile-has-stories", hasActive);
                trigger.classList.toggle("pointer-cursor", hasActive);

                // if no stories, clear any ring classes and bail
                if (!hasActive) {
                    trigger.classList.remove("profile-stories-seen", "profile-stories-unseen");
                } else {
                    // 🚫 IMPORTANT: do NOT manage seen/unseen on own profile
                    if (isOwnProfile) {
                        trigger.classList.remove("profile-stories-seen", "profile-stories-unseen");
                    } else {
                        // Seen/unseen ring state (guests => items[].seen should be false)
                        var anyUnseen = this.activeStories.some((bubble) => {
                            var items = (bubble && bubble.items) ? bubble.items : [];
                            return items.some((it) => it && it.seen === false);
                        });

                        trigger.classList.toggle("profile-stories-unseen", anyUnseen);
                        trigger.classList.toggle("profile-stories-seen", !anyUnseen);
                    }
                }

                // bind click ONCE
                if (!this._bound) {
                    this._bound = true;
                    trigger.addEventListener("click", () => {
                        if (!hasActive) return;
                        window.StoriesSwiper.storiesData = this.activeStories;
                        window.StoriesSwiper.openViewer(0, 0);
                    });
                }
            })
            .catch(() => {
                this.activeStories = [];
                trigger.classList.remove(
                    "profile-has-stories",
                    "pointer-cursor",
                    "profile-stories-seen",
                    "profile-stories-unseen"
                );
            });

        /* ----------------------------------------------------
           2) Fetch HIGHLIGHTS
        ---------------------------------------------------- */

        // show section so skeleton is visible while loading
        this.showHighlightsSkeleton();

        var p2 = window.StoriesSwiper.fetchStories(highlightsUrl)
            .then((data) => {
                this.highlightStories = window.StoriesSwiper.normalizeStories(data) || [];

                this.renderHighlightsRow(this.highlightStories);

                var wrap = document.getElementById("profile-highlights-wrapper");
                var hasHighlights = !!(wrap && wrap.children.length > 0);

                this.hideHighlightsSkeleton();

                var row = document.getElementById("profile-highlights");
                if (row) row.classList.toggle("d-none", !hasHighlights);

                if (hasHighlights) {
                    new Swiper(".profile-highlights-swiper", {
                        slidesPerView: "auto",
                        spaceBetween: 12,
                        freeMode: true
                    });
                }
            })
            .catch(() => {
                this.highlightStories = [];
                this.hideHighlightsSkeleton();
                var row = document.getElementById("profile-highlights");
                if (row) row.classList.add("d-none");
            });

        /* ----------------------------------------------------
           3) Deep link open (after both loaded)
        ---------------------------------------------------- */
        Promise.allSettled([p1, p2]).then(() => {
            if (storyIdToOpen) {
                this.openStoryById(storyIdToOpen);
            }
        });
    },

    /* ----------------------------------------------------
       Highlights rendering
    ---------------------------------------------------- */
    renderHighlightsRow: function (stories) {
        var wrap = document.getElementById("profile-highlights-wrapper");
        if (!wrap) return;

        wrap.innerHTML = "";

        (stories || []).forEach((bubble, bubbleIndex) => {
            var items = bubble && bubble.items ? bubble.items : [];
            if (!items.length) return;

            var first = items[0];
            var avatar = bubble.photo || "";
            var cover = avatar;

            if (first) {
                if (first.type === "text") {
                    cover = avatar;
                } else if (first.type === "video") {
                    cover = first.preview ? String(first.preview) : avatar;
                } else if (first.type === "image") {
                    cover = first.preview
                        ? String(first.preview)
                        : (first.src ? String(first.src) : avatar);
                } else {
                    cover = avatar;
                }
            }

            var slide = document.createElement("div");
            slide.className = "swiper-slide";

            slide.innerHTML =
                '<div class="profile-highlight-item pointer-cursor" data-highlight-index="' + bubbleIndex + '">' +
                    '<div class="profile-highlight-circle">' +
                        '<img src="' + cover + '" alt="' + trans("Highlight") + '">' +
                    '</div>' +
                    '<div class="profile-highlight-label">' + trans("Pinned") + '</div>' +
                '</div>';


            wrap.appendChild(slide);
        });

        wrap.onclick = (e) => {
            var el = e.target.closest("[data-highlight-index]");
            if (!el) return;

            var idx = parseInt(el.getAttribute("data-highlight-index") || "0", 10) || 0;
            window.StoriesSwiper.storiesData = this.highlightStories;
            window.StoriesSwiper.openViewer(idx, 0);
        };
    },

    /* ----------------------------------------------------
       Deep-link helpers
    ---------------------------------------------------- */
    openStoryById: function (storyId) {
        var found = this.findStoryInDataset(this.activeStories, storyId);
        if (found) {
            window.StoriesSwiper.storiesData = this.activeStories;
            window.StoriesSwiper.openViewer(found.storyIndex, found.itemIndex);
            return true;
        }

        found = this.findStoryInDataset(this.highlightStories, storyId);
        if (found) {
            window.StoriesSwiper.storiesData = this.highlightStories;
            window.StoriesSwiper.openViewer(found.storyIndex, found.itemIndex);
            return true;
        }

        return false;
    },

    findStoryInDataset: function (dataset, storyId) {
        if (!Array.isArray(dataset) || !storyId) return null;

        for (var s = 0; s < dataset.length; s++) {
            var bubble = dataset[s];
            var items = bubble && bubble.items ? bubble.items : [];
            for (var i = 0; i < items.length; i++) {
                if (String(items[i].id) === String(storyId)) {
                    return { storyIndex: s, itemIndex: i };
                }
            }
        }
        return null;
    },

    showHighlightsSkeleton: function () {
        var row = document.getElementById("profile-highlights");
        if (!row) return;

        // row is SSR-rendered only when highlights exist, so it should NOT be d-none anymore
        row.classList.remove("is-loaded");
        row.classList.add("is-loading");

        // optional: explicitly show skeleton block if you keep it in blade
        var sk = row.querySelector(".profile-highlights-skeleton");
        if (sk) sk.classList.remove("d-none");
    },

    hideHighlightsSkeleton: function () {
        var row = document.getElementById("profile-highlights");
        if (!row) return;

        row.classList.remove("is-loading");
        row.classList.add("is-loaded");

        // optional: hide skeleton block
        var sk = row.querySelector(".profile-highlights-skeleton");
        if (sk) sk.classList.add("d-none");
    },


};

window.StoriesProfile = StoriesProfile;
