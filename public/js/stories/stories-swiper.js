/**
 * Stories wrapper based on Swiper
 * Requires: Swiper, jQuery, StoriesPlayer
 */
/* global Swiper, app, StoriesPlayer, trans, StoryDM, launchToast, getStoredSvg, showDialog, hideDialog, renderUserStaffBadge */
"use strict";

var StoriesSwiper = {

    // -------- state --------
    debug: false,

    devFreeze: false,
    isPaused: false,
    pausedProgress: null,
    isHolding: false,
    isSoundOn: false, // default muted (IG behavior)
    pendingDeleteStoryId: null,
    _deleteDialogOpen: false,
    _deleteDialogWasPaused: false,
    _deleteDialogCompleted: false,

    containerId: "stories-swiper",
    storiesData: [],

    thumbsSwiper: null,
    viewerSwiper: null,
    autoplayTimer: null,
    mediaLoadingTimer: null,
    mediaProgressPaused: false,
    mediaPausedProgress: null,

    currentStoryIndex: 0,
    currentItemIndex: 0,

    audioUnlocked: false,
    viewerSession: 0,

    // internal cached handler refs (so removeEventListener works)
    _thumbClickHandler: null,

    _originalUrl: null,
    _originalTitle: null,

    _navFromOwn: false, // if viewer started from own bubble

    getShareUrlForItem: function (item) {
        return app.baseUrl + "/stories/s/" + item.id; // item.id == story_id
    },

    setPreviewUrlForItem: function (item) {
        if (!item) return;

        // store original once per open session
        if (!this._originalUrl) {
            this._originalUrl = window.location.href;
            this._originalTitle = document.title;
        }

        var newUrl = this.getShareUrlForItem(item);

        // update address bar without navigating
        window.history.replaceState({ story_id: item.id }, "", newUrl);
    },

    restoreOriginalUrl: function () {
        if (!this._originalUrl) return;

        window.history.replaceState({}, "", this._originalUrl);

        // optionally restore title later if you change it
        if (this._originalTitle) {
            document.title = this._originalTitle;
        }

        this._originalUrl = null;
        this._originalTitle = null;
    },

    // -------- helpers --------

    renderOverlayText: function () {
        var item = this.getCurrentStoryItem();
        var text = (item && item.text) ? String(item.text).trim() : "";

        var el = document.getElementById("stories-overlay-text");
        if (!el) return;

        el.textContent = text;

        if (!text) {
            el.style.display = "none";
            return;
        }

        el.style.display = "block";
    },


    resetThumbsSwiper: function () {
        if (this.thumbsSwiper) {
            this.thumbsSwiper.destroy(true, true);
            this.thumbsSwiper = null;
        }
        this.initThumbsSwiper();
    },

    setDevFreeze: function (value) {
        this.devFreeze = !!value;
    },

    log: function () {
        if (this.debug) {
            // eslint-disable-next-line no-console
            console.log.apply(console, arguments);
        }
    },

    warn: function () {
        if (this.debug) {
            // eslint-disable-next-line no-console
            console.warn.apply(console, arguments);
        }
    },

    isViewerOpen: function () {
        var overlay = document.getElementById("stories-viewer-overlay");
        return !!(overlay && overlay.classList.contains("visible"));
    },

    getCurrentItem: function () {
        var story = this.storiesData[this.currentStoryIndex];
        if (!story) return null;
        return (story.items || [])[this.currentItemIndex] || null;
    },

    getCtx: function () {
        var self = this;

        return {
            // state getters
            getDevFreeze: function () { return self.devFreeze; },
            getIsPaused: function () { return self.isPaused; },
            getIsHolding: function () { return self.isHolding; },
            getIsSoundOn: function () { return self.isSoundOn; },
            getPausedProgress: function () { return self.pausedProgress; },
            getAutoplayTimer: function () { return self.autoplayTimer; },
            getMediaLoadingTimer: function () { return self.mediaLoadingTimer; },
            getMediaProgressPaused: function () { return self.mediaProgressPaused; },
            getMediaPausedProgress: function () { return self.mediaPausedProgress; },
            getCurrentStoryIndex: function () { return self.currentStoryIndex; },
            getCurrentItemIndex: function () { return self.currentItemIndex; },

            // audio unlock flag
            getAudioUnlocked: function () { return self.audioUnlocked; },
            // audio unlock setter
            setAudioUnlocked: function (v) { self.audioUnlocked = !!v; },

            getViewerSession: function () { return self.viewerSession; },

            // state setters
            setIsPaused: function (v) { self.isPaused = !!v; },
            setIsHolding: function (v) { self.isHolding = !!v; },
            setIsSoundOn: function (v) { self.isSoundOn = !!v; },
            setPausedProgress: function (v) { self.pausedProgress = v; },
            setAutoplayTimer: function (v) { self.autoplayTimer = v; },
            setMediaLoadingTimer: function (v) { self.mediaLoadingTimer = v; },
            setMediaProgressPaused: function (v) { self.mediaProgressPaused = !!v; },
            setMediaPausedProgress: function (v) { self.mediaPausedProgress = v; },

            // callbacks & helpers
            isViewerOpen: function () { return self.isViewerOpen(); },
            getCurrentItem: function () { return self.getCurrentItem(); },
            nextItem: function () { return self.nextItem(); },
            prevItem: function () { return self.prevItem(); }
        };
    },

    /* ------------------------------
     * INIT
     * ------------------------------ */

    init: function (options) {
        options = options || {};

        var container = document.getElementById(this.containerId);
        if (!container) {
            this.warn(`[StoriesSwiper] #${this.containerId} not found.`);
            return;
        }

        var feedUrl = options.feedUrl || `${app.baseUrl}/stories/feed`;

        this.fetchStories(feedUrl)
            .then((data) => {
                this.storiesData = this.normalizeStories(data);
                this.renderThumbsRow(this.storiesData);
                this.resetThumbsSwiper();
                this.disableStoriesRightClick();
                this.bindGlobalShortcuts();
                this.tryOpenDeepLink();
            })
            .catch((err) => {
                this.warn("[StoriesSwiper] feed fetch failed", err);
                this.storiesData = [];
                this.renderThumbsRow(this.storiesData); // shows Add Story only
                this.resetThumbsSwiper();
                this.disableStoriesRightClick();
                this.bindGlobalShortcuts();
                this.tryOpenDeepLink();
            });
    },

    fetchStories: function (url) {
        return new Promise(function (resolve, reject) {
            $.ajax({
                method: "GET",
                url: url,
                dataType: "json",
                success: function (response) { resolve(response); },
                error: function (xhr) { reject(xhr); }
            });
        });
    },

    /**
     * Accepts backend payload and returns the EXACT shape your viewer expects.
     * Backend can send either {stories:[...]} or just [...].
     */
    normalizeStories: function (payload) {
        var list = Array.isArray(payload) ? payload : (payload && payload.stories ? payload.stories : []);
        if (!Array.isArray(list)) return [];

        return list.map(function (s, i) {
            return {
                id: s.id || s.user_id || `story_${i}`,
                // KEEP THIS so findOwnStoryIndex works
                user_id: s.user_id || s.userId || null,

                name: s.name || "",
                username: s.username || "",
                photo: s.photo || "",
                verified: !!(s.verified),
                staff_badge: s.staff_badge === 'admin' ? s.staff_badge : null,
                lastUpdated: s.lastUpdated || s.last_updated || s.last_updated_ts || Math.floor(Date.now() / 1000),

                items: Array.isArray(s.items) ? s.items.map(function (it, k) {

                    // Accept overlay from backend in multiple forms
                    var overlay = it.overlay || it.text_overlay || it.text_pos || null;

                    // If stored as longText/json string, parse it
                    if (overlay && typeof overlay === "string") {
                        try { overlay = JSON.parse(overlay); } catch (e) { overlay = null; }
                    }

                    var defaultLen = (window.app && app.stories && app.stories.defaultLengthSeconds)
                        ? Number(app.stories.defaultLengthSeconds)
                        : 5;

                    return {
                        id: it.id || `${s.id || i}_${k}`,
                        attachment_id: it.attachment_id || null,
                        pinned: !!it.pinned,
                        type: it.type || "image",
                        length: Number(it.length || defaultLen || 5),
                        src: it.src || "",
                        preview: (it.preview !== null && it.preview !== "") ? String(it.preview) : null,
                        has_thumbnail: !!it.has_thumbnail,
                        time: it.time || s.lastUpdated || Math.floor(Date.now() / 1000),
                        seen: !!it.seen,
                        views: Number(it.views || 0),
                        link: it.link || null,
                        linkText: it.linkText || null,
                        text: it.text || "",
                        bg_preset: it.bg_preset || null,
                        overlay: overlay,
                        sound: it.sound || null,
                        sound_id: it.sound_id || null,
                        publication_review: it.publication_review || null,
                    };
                }) : []
            };
        });
    },

    getCurrentStoryItem: function () {
        var story = this.storiesData[this.currentStoryIndex];
        if (!story) return null;

        return (story.items || [])[this.currentItemIndex] || null;
    },

    isStorySeen: function (story) {
        if (!story || !story.items || !story.items.length) return true;
        return story.items.every(function (it) { return !!it.seen; });
    },

    /* ------------------------------
     * RENDER THUMBS ROW
     * ------------------------------ */

    renderThumbsRow: function (stories) {
        // 🔥 REMOVE skeleton once JS starts rendering
        var skeleton = document.querySelector("#stories-swiper .stories-swiper-skeleton");
        if (skeleton) {
            skeleton.remove();
        }

        var container = document.querySelector(`#${this.containerId} .swiper-wrapper`);
        if (!container) return;

        container.innerHTML = "";

        // 1) ADD STORY CARD
        var currentUser = window.user || {};
        var myId = currentUser.user_id ? Number(currentUser.user_id) : null;
        var creationAccess = document.querySelector('[data-public-creation-access]');
        var canViewCreationPage = !creationAccess || creationAccess.dataset.canViewCreationPage !== 'false';
        var showCreation = !creationAccess || creationAccess.dataset.storyCreationVisible !== 'false';
        var hasOwnStory = this.findOwnStoryIndex() !== -1;

        if (showCreation || hasOwnStory) {
            var addSlide = document.createElement("div");
            addSlide.className = "swiper-slide story-thumb story-thumb-add";
            addSlide.dataset.storyAdd = "1";

            var avatarHtml = currentUser.avatar
                ? `<img class="story-thumb-avatar" src="${currentUser.avatar}" alt="${trans('Add story avatar')}">`
                : `<div class="story-thumb-avatar story-thumb-avatar-placeholder"></div>`;

            addSlide.innerHTML = `
              <div class="story-thumb-inner">
                <div class="story-thumb-avatar-wrap story-thumb-avatar-add-wrap" data-action="view-own">
                  ${avatarHtml}
                  ${showCreation && canViewCreationPage ? '<button type="button" class="story-thumb-add-badge border-0" data-action="create" aria-label="' + trans('Add story') + '">+</button>' : ''}
                </div>
                <div class="story-thumb-name text-truncate">
                  ${currentUser.name || currentUser.username || trans("Add story")}
                </div>
              </div>
            `;
            if (!canViewCreationPage && !hasOwnStory) {
                addSlide.querySelector('.story-thumb-name').textContent = creationAccess.dataset.onboardingLabel;
            }
            container.appendChild(addSlide);
        }

        // 2) NORMAL STORIES
        if (!Array.isArray(stories) || !stories.length) {
            this.warn("[StoriesSwiper] No stories to render (aside from Add Story).");
        } else {
            stories.forEach((story, index) => {
                // Skip own story bubble (already represented by Add Story card)
                if (myId && Number(story.user_id) === myId) {
                    return;
                }

                var slide = document.createElement("div");
                slide.className = "swiper-slide story-thumb";
                slide.dataset.storyIndex = String(index);

                slide.innerHTML = `
                    <div class="story-thumb-inner">
                        <div class="story-thumb-avatar-wrap">
                            <img class="story-thumb-avatar" src="${story.photo || ""}">
                        </div>
                        <div class="story-thumb-name text-truncate">${story.name || ""}</div>
                `;


                var seenClass = this.isStorySeen(story) ? "story-thumb-seen" : "story-thumb-unseen";
                slide.classList.add(seenClass);

                container.appendChild(slide);
            });
        }

        // 3) CLICK HANDLERS (event delegation)
        var root = document.getElementById(this.containerId);
        if (!root) return;

        if (!this._thumbClickHandler) {
            this._thumbClickHandler = (e) => {
                var slide = e.target.closest(".story-thumb");
                if (!slide || !root.contains(slide)) return;

                if (slide.classList.contains("story-thumb-add")) {
                    e.preventDefault();

                    var actionEl = e.target.closest("[data-action]");
                    var action = actionEl ? actionEl.getAttribute("data-action") : null;

                    // clicking + always creates
                    if (action === "create") {
                        if (showCreation && canViewCreationPage) window.location.href = `${app.baseUrl}/stories/create`;
                        return;
                    }

                    // clicking avatar/name opens viewer IF user has stories, otherwise create
                    var ownIndex = this.findOwnStoryIndex();
                    if (ownIndex !== -1) {
                        this._navFromOwn = true; // started from own bubble
                        this.openViewer(ownIndex, 0);
                    } else if (showCreation) {
                        window.location.href = canViewCreationPage ? `${app.baseUrl}/stories/create` : creationAccess.dataset.onboardingUrl;
                    }
                    return;
                }

                var idx = parseInt(slide.dataset.storyIndex || "0", 10) || 0;
                this._navFromOwn = false;
                this.openViewer(idx, 0);
            };
        }

        root.removeEventListener("click", this._thumbClickHandler);
        root.addEventListener("click", this._thumbClickHandler);
    },

    findOwnStoryIndex: function () {
        var myId = window.user && window.user.user_id ? window.user.user_id : null;
        if (!myId) return -1;

        for (var i = 0; i < this.storiesData.length; i++) {
            if (Number(this.storiesData[i].user_id) === Number(myId)) {
                var items = this.storiesData[i].items || [];
                if (items.length) return i;
            }
        }
        return -1;
    },

    initThumbsSwiper: function () {
        this.thumbsSwiper = new Swiper("#" + this.containerId, {
            slidesPerView: "auto",
            spaceBetween: 12,
            freeMode: true
        });
    },

    /* ------------------------------
     * VIEWER OVERLAY
     * ------------------------------ */

    ensureViewerDOM: function () {
        if (document.getElementById("stories-viewer-overlay")) {
            return;
        }
        var allowHighlights = this.getAllowHighlights();
        var overlayHtml = `
<div id="stories-viewer-overlay" class="stories-viewer-overlay no-long-press">
  <audio id="stories-sound" preload="none"></audio>
  <div class="stories-viewer-backdrop"></div>

  <div class="stories-viewer">

    <!-- Tap zones OUTSIDE the card -->
    <div class="stories-viewer-tap-zones-outside" aria-hidden="true">
      <div class="stories-viewer-tap-left"></div>
      <div class="stories-viewer-tap-middle"></div>
      <div class="stories-viewer-tap-right"></div>
    </div>

    <div class="stories-viewer-inner">
      <div class="swiper stories-viewer-swiper">
        <div class="swiper-wrapper"></div>
        <div class="stories-media-loading d-none" aria-hidden="true">
          <div class="spinner-border spinner-border-sm" role="status">
            <span class="visually-hidden">${trans('Loading...')}</span>
          </div>
        </div>
        <div class="stories-media-error d-none">
          <ion-icon name="alert-circle-outline"></ion-icon>
          <span>${trans('Could not load this story.')}</span>
        </div>

  <!-- Tap zones INSIDE the card (mobile) -->
  <div class="stories-viewer-tap-zones-inside" aria-hidden="true">
    <div class="stories-viewer-tap-left-inside"></div>
    <div class="stories-viewer-tap-right-inside"></div>
  </div>

        <!-- Legibility gradients -->
        <div class="stories-legibility stories-legibility--top" aria-hidden="true"></div>
        <div class="stories-legibility stories-legibility--bottom" aria-hidden="true"></div>

        <!-- TEXT OVERLAY (rendered on top of media) -->
        <div class="stories-text-layer" id="stories-overlay-text"></div>

        <!-- TOP chrome -->
        <div class="stories-chrome stories-chrome--top">
          <div class="stories-viewer-progress-wrap"></div>

          <div class="stories-viewer-header">
            <div class="stories-viewer-user">
              <img class="stories-viewer-avatar" src="" alt="">
              <div class="stories-viewer-user-info">
                <a class="stories-viewer-username text-white mb-0 pb-0 d-inline-flex align-items-center overflow-hidden mw-100" href="#" target="_blank" rel="noopener"></a>

                <div class="stories-viewer-meta flex-wrap mw-100">
                  <span class="stories-viewer-time"></span>

                  <span class="stories-viewer-views d-none" title="${trans('Views')}">
                    <ion-icon name="eye-outline"></ion-icon>
                    <span class="stories-viewer-views-count">0</span>
                  </span>

                  <button type="button" class="rounded-pill bg-dark bg-opacity-50 text-white px-2 py-1 fw-semibold text-nowrap border-0 focus-ring pe-auto d-none" id="stories-publication-review"
                          data-bs-toggle="tooltip" data-bs-trigger="hover focus" data-bs-placement="bottom"
                          data-bs-container="#stories-viewer-overlay" data-bs-custom-class="publication-review-tooltip"
                          data-bs-html="false" data-tooltip-touch="true" disabled></button>

                  <!-- put pill INLINE with meta -->
                  <span class="stories-sound-pill d-none" id="stories-sound-pill"></span>
                </div>

              </div>
            </div>

            <div class="stories-viewer-controls">
              <button class="stories-viewer-toggle-sound" type="button">
                <ion-icon class="icon-sound-on" name="volume-high-outline" style="display:none;"></ion-icon>
                <ion-icon class="icon-sound-off" name="volume-mute-outline"></ion-icon>
              </button>

              <button class="stories-viewer-toggle-play" type="button">
                <ion-icon class="icon-pause" name="pause-outline"></ion-icon>
                <ion-icon class="icon-play" name="play-outline" style="display:none;"></ion-icon>
              </button>

              <div class="dropdown stories-owner-menu d-none dropstart">
                <a class="stories-owner-menu-btn dropdown-toggle ellipsis-dropdown-toggle"
                   data-bs-toggle="dropdown"
                   href="#"
                   role="button"
                   aria-label="${trans('More')}"
                   aria-haspopup="true"
                   aria-expanded="false">
                  <ion-icon name="ellipsis-horizontal-outline"></ion-icon>
                </a>

                <div class="dropdown-menu dropdown-menu-end stories-owner-menu-dropdown">
                  <a class="dropdown-item stories-action-copy-link d-none" href="javascript:void(0)">${trans('Copy link')}</a>
                  <div class="dropdown-divider stories-owner-divider d-none"></div>
                  <a class="dropdown-item stories-action-report d-none text-danger" href="javascript:void(0)">${trans('Report')}</a>

                  ${allowHighlights ? `
                  <a class="dropdown-item stories-owner-action-pin d-none" href="javascript:void(0)">${trans('Pin story')}</a>
                ` : ``}
                  <div class="dropdown-divider stories-owner-divider-2 d-none"></div>
                  <a class="dropdown-item text-danger stories-owner-action-delete d-none" href="javascript:void(0)">${trans('Delete story')}</a>
                </div>
              </div>

              <button class="stories-viewer-close" type="button" aria-label="${trans('Close story')}">
                <ion-icon name="close-outline"></ion-icon>
              </button>
            </div>
          </div>
        </div>

        <!-- BOTTOM chrome -->
        <div class="stories-chrome stories-chrome--bottom" aria-hidden="false">
          <!-- LINK CTA (above reply) -->
          <a class="stories-link-btn d-none" id="stories-link-btn"
             href="#" target="_blank" rel="noopener nofollow">
            <span class="stories-link-text">${trans('Learn more')}</span>
            <ion-icon name="open-outline"></ion-icon>
          </a>

          <div class="stories-reply" aria-hidden="false">
            <div class="stories-reply-pill">${trans('Send message…')}</div>
            <div class="stories-reply-btn">
              <ion-icon name="paper-plane-outline"></ion-icon>
            </div>
          </div>

        </div>

      </div>
    </div>

  </div>
</div>
`;

        $("body").append(overlayHtml);

        var $overlay = $("#stories-viewer-overlay");

        // Prevent hold-to-pause when interacting with reply/link areas (even if disabled)
        $overlay.on("pointerdown pointerup mousedown mouseup touchstart touchend", ".stories-reply, #stories-link-btn", function (e) {
            e.stopPropagation();
        });

        // If user navigates away / tab hides, kill any playing sound
        if (!this._soundSafetyBound) {
            this._soundSafetyBound = true;

            // Stop sound when tab loses visibility
            document.addEventListener("visibilitychange", () => {
                if (document.hidden) {
                    StoriesPlayer.stopSound();
                }
            });

            // Stop sound on page navigation / reload / back-forward cache
            window.addEventListener("pagehide", () => {
                StoriesPlayer.stopSound();
            });
        }

        // --- Mobile viewport height fix (URL bar / keyboard) ---
        if (!this._vhBound) {
            this._vhBound = true;

            var setVh = function () {
                var h = (window.visualViewport && window.visualViewport.height)
                    ? window.visualViewport.height
                    : window.innerHeight;

                document.documentElement.style.setProperty("--vh", (h * 0.01) + "px");
            };

            setVh();
            window.addEventListener("resize", setVh, { passive: true });
            window.addEventListener("orientationchange", function () {
                setTimeout(setVh, 50);
                setTimeout(setVh, 250);
            }, { passive: true });

            if (window.visualViewport) {
                window.visualViewport.addEventListener("resize", setVh, { passive: true });
                window.visualViewport.addEventListener("scroll", setVh, { passive: true });
            }

            // also refresh when opening overlay (sometimes first paint is off)
            this._setVhNow = setVh;
        }


        // Mobile: swipe left/right to change BUBBLE (user), not item
        if (!this._mobileBubbleSwipeBound) {
            this._mobileBubbleSwipeBound = true;

            let startX = 0, startY = 0, tracking = false;

            const SWIPE_X = 45; // px threshold
            const SWIPE_Y = 60; // cancel if vertical scroll-like

            const ignoreSelector =
                "a, button, input, textarea, select, " +
                ".dropdown-menu, .dropdown-item, " +
                ".stories-viewer-controls, .stories-viewer-user, " +
                ".stories-reply, #stories-link-btn, #stories-overlay-text";

            $overlay.on("touchstart", ".stories-viewer-swiper", (e) => {
                if (!this.isMobileViewport()) return;
                const t = e.originalEvent.touches && e.originalEvent.touches[0];
                if (!t) return;

                // ignore gestures started on interactive UI
                if (e.target.closest(ignoreSelector)) return;

                tracking = true;
                startX = t.clientX;
                startY = t.clientY;

                // pause while the gesture starts (feels more “IG”)
                StoriesPlayer.pauseStory(this.getCtx());
                StoriesPlayer.pauseSound();
                StoriesPlayer.syncPlayButtonUI(this.getCtx());
            });

            $overlay.on("touchmove", ".stories-viewer-swiper", (e) => {
                if (!tracking || !this.isMobileViewport()) return;

                const t = e.originalEvent.touches && e.originalEvent.touches[0];
                if (!t) return;

                const dx = t.clientX - startX;
                const dy = t.clientY - startY;

                // If mostly horizontal, prevent page scroll “nudge”
                if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 10) {
                    e.preventDefault();
                }

                // If user is clearly scrolling vertically, cancel tracking
                if (Math.abs(dy) > SWIPE_Y && Math.abs(dy) > Math.abs(dx)) {
                    tracking = false;
                    StoriesPlayer.resumeStory(this.getCtx());
                    StoriesPlayer.applySoundForItem(this.getCtx(), false);
                }
            });

            $overlay.on("touchend", ".stories-viewer-swiper", (e) => {
                if (!tracking || !this.isMobileViewport()) return;
                tracking = false;

                const changed = e.originalEvent.changedTouches && e.originalEvent.changedTouches[0];
                if (!changed) {
                    StoriesPlayer.resumeStory(this.getCtx());
                    StoriesPlayer.applySoundForItem(this.getCtx(), false);
                    return;
                }

                const dx = changed.clientX - startX;
                const dy = changed.clientY - startY;

                // Only act on mostly-horizontal swipes
                if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) >= SWIPE_X) {
                    if (dx < 0) {
                        this.goToNextStory();
                    } else {
                        this.goToPrevStory();
                    }
                    return;
                }

                // No bubble swipe -> resume
                StoriesPlayer.resumeStory(this.getCtx());
                StoriesPlayer.applySoundForItem(this.getCtx(), false);
            });
        }

        // Clicking username/avatar should not trigger hold-to-pause
        $overlay.on("pointerdown mousedown touchstart", ".stories-viewer-username, .stories-viewer-avatar", function (e) {
            e.stopPropagation();
        });

        // COPY LINK (dummy but functional)
        $overlay.on("click", ".stories-action-copy-link", (e) => {
            e.preventDefault();

            var item = this.getCurrentStoryItem();
            if (!item) return;

            var url = this.getShareUrlForItem(item); // /stories/s/{id}

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(() => {
                    launchToast("success", trans("Success"), trans("Link copied"));
                }).catch(() => {
                    launchToast("danger", trans("Error"), trans("Could not copy link"));
                });
            } else {
                var tmp = document.createElement("input");
                tmp.value = url;
                document.body.appendChild(tmp);
                tmp.select();
                try {
                    document.execCommand("copy");
                    launchToast("success", trans("Success"), trans("Link copied"));
                } catch (err) {
                    launchToast("danger", trans("Error"), trans("Could not copy link"));
                }
                document.body.removeChild(tmp);
            }
        });

        // REPORT
        $overlay.on("click", ".stories-action-report", (e) => {
            e.preventDefault();

            var story = this.storiesData[this.currentStoryIndex];
            var item = this.getCurrentStoryItem();
            if (!story || !item) return;

            var storyId = item.id;
            var ownerUserId = story.user_id;

            // 1️⃣ Close the stories viewer first
            this.closeViewer();

            // 2️⃣ Small delay so animation finishes
            setTimeout(() => {
                if (window.Lists && typeof window.Lists.showReportBox === "function") {
                    window.Lists.showReportBox(ownerUserId, null, null, null, storyId);
                }
            }, 300);
        });

        // Pause story while dropdown is open (Bootstrap dropdown events)
        $overlay.on("shown.bs.dropdown", ".stories-owner-menu", () => {
            this._menuWasPaused = !!this.isPaused;

            if (!this.isPaused) {
                StoriesPlayer.pauseStory(this.getCtx());
                StoriesPlayer.pauseSound();
                StoriesPlayer.syncPlayButtonUI(this.getCtx());
            }
        });

        $overlay.on("hidden.bs.dropdown", ".stories-owner-menu", () => {
            // Only resume if the user wasn't already paused before opening the menu
            if (!this._menuWasPaused && !this._deleteDialogOpen) {
                StoriesPlayer.resumeStory(this.getCtx());
                StoriesPlayer.syncPlayButtonUI(this.getCtx());
                StoriesPlayer.applySoundForItem(this.getCtx(), false);
            }
            this._menuWasPaused = null;
        });

        // Close on backdrop or "X"
        $overlay.on("click", ".stories-viewer-backdrop, .stories-viewer-close", () => {
            this.closeViewer();
        });

        // OWNER MENU ACTIONS
        $overlay.on("click", ".stories-owner-action-delete", () => {
            this.deleteCurrentStory();
        });

        $(document)
            .off("hidden.bs.modal.storiesDelete", "#story-delete-dialog")
            .on("hidden.bs.modal.storiesDelete", "#story-delete-dialog", () => {
                this.handleDeleteDialogHidden();
            });

        $overlay.on("click", ".stories-owner-action-pin", () => {
            this.togglePinCurrentStory();
        });

        // Tap zones click (prev/next)
        $overlay
            .on("click", ".stories-viewer-tap-left", () => { this.prevItem(); })
            .on("click", ".stories-viewer-tap-right", () => { this.nextItem(); });

        $overlay
            .on("click", ".stories-viewer-tap-left-inside", () => { this.prevItem(); })
            .on("click", ".stories-viewer-tap-right-inside", () => { this.nextItem(); });

        // PLAY/PAUSE BUTTON
        $overlay.on("click", ".stories-viewer-toggle-play", () => {
            var ctx = this.getCtx();

            if (this.isPaused) {
                StoriesPlayer.resumeStory(ctx);
                StoriesPlayer.applySoundForItem(ctx, true); // user gesture => can unmute/play
            } else {
                StoriesPlayer.pauseStory(ctx);
                StoriesPlayer.pauseSound(); // stop soundtrack when paused
            }

            StoriesPlayer.syncPlayButtonUI(ctx);
        });
        // MUTE / UNMUTE BUTTON
        $overlay.on("click", ".stories-viewer-toggle-sound", () => {
            this.isSoundOn = !this.isSoundOn;
            this.audioUnlocked = true; // remember gesture happened
            if (this.isSoundOn) {
                $(".stories-viewer-toggle-sound .icon-sound-off").hide();
                $(".stories-viewer-toggle-sound .icon-sound-on").show();
            } else {
                $(".stories-viewer-toggle-sound .icon-sound-on").hide();
                $(".stories-viewer-toggle-sound .icon-sound-off").show();
            }

            StoriesPlayer.applySoundToCurrentVideo(this.getCtx());
            StoriesPlayer.applySoundForItem(this.getCtx(), true, false);
            StoriesPlayer.syncSoundPill(this.getCtx());

        });

        document.querySelectorAll(".stories-viewer-swiper").forEach((zone) => {
            // things that should NOT trigger hold-to-pause
            var ignoreHoldSelector =
                "a, button, " +
                ".dropdown-menu, .dropdown-item, " +
                ".stories-viewer-controls, .stories-viewer-user, " +
                ".stories-reply, #stories-link-btn, #stories-overlay-text";

            zone.addEventListener("pointerdown", (e) => {
                if (e.target.closest(ignoreHoldSelector)) {
                    return;
                }
                StoriesPlayer.holdPause(this.getCtx());
                StoriesPlayer.pauseSound();
            });

            zone.addEventListener("pointerup", (e) => {
                if (e.target.closest(ignoreHoldSelector)) {
                    return;
                }
                StoriesPlayer.holdResume(this.getCtx());
                StoriesPlayer.applySoundForItem(this.getCtx(), false);
            });

            zone.addEventListener("pointerleave", () => {
                StoriesPlayer.holdResume(this.getCtx());
                StoriesPlayer.applySoundForItem(this.getCtx(), false);
            });
        });

        // Tap video to turn sound ON (IG style)
        $overlay.on("click", "video.story-video", (e) => {
            this.audioUnlocked = true; // gesture happened

            this.isSoundOn = true;

            $(".stories-viewer-toggle-sound .icon-sound-off").hide();
            $(".stories-viewer-toggle-sound .icon-sound-on").show();

            StoriesPlayer.setVideoSoundState(this.getCtx(), e.currentTarget);
            StoriesPlayer.applySoundForItem(this.getCtx(), true, false); // optional but nice
        });

        // Reply from story -> open DM modal, and allow "Back to story"
        if (!this._replyBound) {
            this._replyBound = true;

            $overlay.on("click", ".stories-reply-pill, .stories-reply-btn", (e) => {
                e.preventDefault();

                // must be logged in
                if (!window.user || !window.user.user_id) {
                    launchToast("danger", trans("Error"), trans("Please log in to send messages."));
                    return;
                }

                var story = this.storiesData[this.currentStoryIndex];
                var item = this.getCurrentStoryItem();
                if (!story || !item) return;

                // don't DM yourself
                var myId = Number(window.user.user_id);
                if (Number(story.user_id) === myId) return;

                // receiver field differs depending on modal variant
                if ($("#receiverID").length) {
                    $("#receiverID").val(String(story.user_id));
                } else if ($("#select-repo").length) {
                    $("#select-repo").val(String(story.user_id)).trigger("change");
                }

                // story context (DB)
                $("#storyID").val(String(item.id));

                // REQUIRED PIECE: remember where to return, and show the button
                this._returnToStory = {
                    storyIndex: this.currentStoryIndex,
                    itemIndex: this.currentItemIndex
                };
                $("#backToStoryBtn").removeClass("d-none");

                // close viewer first, then open modal
                window.__messageModalFromStory = true;

                StoryDM.setReceiver(story.user_id, story.username); // for "@username"
                StoryDM.setStoryReplyContext(item.id); // for "Replying to ... story"

                StoriesSwiper._returnToStory = {
                    storyIndex: StoriesSwiper.currentStoryIndex,
                    itemIndex: StoriesSwiper.currentItemIndex
                };

                StoriesSwiper.closeViewer();
                setTimeout(() => {
                    StoryDM.showNewMessageDialog();
                    $("#messageText").focus();
                }, 200);
            });
        }
    },

    formatTimeAgo: function (timestamp) {
        var now = Math.floor(Date.now() / 1000);
        var diff = now - timestamp;

        if (diff < 60) return trans("Just now");
        if (diff < 3600) return Math.floor(diff / 60) + trans("m");
        if (diff < 86400) return Math.floor(diff / 3600) + trans("h");
        return Math.floor(diff / 86400) + trans("d");
    },

    openViewer: function (storyIndex, itemIndex) {
        if (!this.storiesData[storyIndex]) return;

        this.ensureViewerDOM();
        this.viewerSession++; // invalidate any pending play() calls

        this.currentStoryIndex = storyIndex;
        this.currentItemIndex = itemIndex || 0;

        // 🔗 Update fake browser URL for currently opened story item
        var currentItem = this.getCurrentStoryItem();
        if (currentItem) {
            this.setPreviewUrlForItem(currentItem);
        }

        var story = this.storiesData[storyIndex];

        // Owner controls visibility
        var myId = window.user && window.user.user_id ? Number(window.user.user_id) : null;
        var isOwn = myId && Number(story.user_id) === myId;

        // show/hide dropdown
        var $ownerMenu = $(".stories-owner-menu");

        // show menu for both owner and non-owner
        $ownerMenu.removeClass("d-none");

        // owner actions
        $(".stories-owner-action-pin, .stories-owner-action-delete, .stories-owner-divider-2")
            .toggleClass("d-none", !isOwn);

        // copy link should be visible for everyone
        $(".stories-action-copy-link").removeClass("d-none");

        // report only for non-owner
        $(".stories-action-report, .stories-owner-divider")
            .toggleClass("d-none", isOwn);

        // compute once, reuse everywhere (fixes var redeclare)
        var item = this.getCurrentStoryItem();

        // update pin label only for owner
        if (isOwn) {
            var pinned = item && item.pinned ? true : false;
            $(".stories-owner-action-pin").text(pinned ? trans("Unpin story") : trans("Pin story"));
        }

        var $reply = $("#stories-viewer-overlay .stories-reply");
        if (isOwn) {
            $reply.addClass("stories-reply--disabled").attr("aria-disabled", "true");
        } else {
            $reply.removeClass("stories-reply--disabled").removeAttr("aria-disabled");
        }

        // Update header
        var username = story.username || "";
        var label = story.name || username || "";
        var nameElement = document.createElement('span');
        nameElement.className = 'text-truncate';
        nameElement.textContent = label;

        var verifiedBadge = story.verified
            ? '<span class="story-verified ms-1 d-inline-flex align-items-center flex-shrink-0" title="' + trans('Verified') + '">' +
            getStoredSvg('verified') +
            '</span>'
            : '';

        var staffBadge = typeof renderUserStaffBadge === 'function' ? renderUserStaffBadge(story.staff_badge, true) : '';

        $(".stories-viewer-username")
            .empty()
            .append(nameElement)
            .append(verifiedBadge + staffBadge)
            .attr("href", app.baseUrl + "/" + username);

        $(".stories-viewer-avatar").attr("src", story.photo || "");

        var ts = item && item.time ? item.time : story.lastUpdated;
        $(".stories-viewer-time").text(this.formatTimeAgo(ts));

        this.syncViewsUI();
        this.trackView();
        this.renderOverlayText();

        // Build slides
        var $wrapper = $(".stories-viewer-swiper .swiper-wrapper");
        $wrapper.empty();

        var items = story.items || [];
        items.forEach(function (it) {
            var slide = $('<div>', { "class": "swiper-slide story-slide" });

            if (it.type === "text") {
                // text slide: no media, gradient background
                slide.addClass("story-slide--text");
                slide.attr("data-bg", it.bg_preset || "grad_default");
                slide.html(""); // empty; background + overlays handle it
            } else if (it.type === "video") {
                slide.html('<video class="story-video no-long-press" src="' + it.src + '" playsinline webkit-playsinline></video>');
            } else {
                slide.html('<img class="story-image no-long-press" src="' + it.src + '" alt="">');
            }

            $wrapper.append(slide);
        });

        this.disableStoriesRightClick();

        // After slides are appended
        $wrapper.find("video.story-video").each((index, el) => {
            el.onended = () => {
                if (index === this.currentItemIndex && this.isViewerOpen()) {
                    this.nextItem();
                }
            };
        });
        this.bindViewerMediaEvents($wrapper);

        // Build progress bars
        StoriesPlayer.buildProgressBars(items.length);

        // Show overlay
        var overlay = document.getElementById("stories-viewer-overlay");
        overlay.classList.remove("closing");
        overlay.classList.add("visible");

        // Lock background scroll while story viewer is open
        document.documentElement.classList.add("stories-no-scroll");
        document.body.classList.add("stories-no-scroll");

        // Init swiper starting from currentItemIndex
        this.initViewerSwiper(items.length);

        // Apply non-player UI for the current item
        this.applyOverlayForCurrentItem();
        this.applyBgPresetForCurrentItem();
        this.applyLinkForCurrentItem();

        // Reset play state on open: autoplay ON
        this.isPaused = false;
        this.pausedProgress = null;

        var ctx = this.getCtx();
        StoriesPlayer.syncPlayButtonUI(ctx);

        // One entry point for media + progress + soundtrack + sound button + pill
        StoriesPlayer.resetAutoplayForCurrentItem(ctx, true);
        StoriesPlayer.updateProgressActive(ctx);
        // Ensure soundtrack is applied for the current item, and restart on enter
        StoriesPlayer.applySoundForItem(ctx, false, true);
        StoriesPlayer.syncSoundPill(ctx);
        StoriesPlayer.updateSoundButtonVisibility(ctx);
        StoriesPlayer.syncSoundButtonUI(ctx);

        // Reset play state on open: autoplay ON (keep your existing double-reset)
        this.isPaused = false;
        this.pausedProgress = null;
        StoriesPlayer.syncPlayButtonUI(this.getCtx());

        this.bindEscKey();

        // keep your explicit icon reset
        this.isPaused = false;
        $(".stories-viewer-toggle-play .icon-play").hide();
        $(".stories-viewer-toggle-play .icon-pause").show();

        if (isOwn) {
            this.syncPinButtonUI();
        }
    },

    bindViewerMediaEvents: function ($wrapper) {
        var self = this;
        var readyEvents = "loadedmetadata.storiesMedia durationchange.storiesMedia loadeddata.storiesMedia canplay.storiesMedia canplaythrough.storiesMedia playing.storiesMedia seeked.storiesMedia";

        $wrapper.find("img.story-image")
            .off(".storiesMedia")
            .on("load.storiesMedia", function () {
                if (self.isCurrentMediaElement(this)) {
                    StoriesPlayer.handleCurrentMediaReady(self.getCtx());
                }
            })
            .on("error.storiesMedia", function () {
                if (self.isCurrentMediaElement(this)) {
                    StoriesPlayer.handleCurrentMediaError(self.getCtx());
                }
            });

        $wrapper.find("video.story-video")
            .off(".storiesMedia")
            .on("waiting.storiesMedia stalled.storiesMedia", function () {
                if (self.isCurrentMediaElement(this)) {
                    StoriesPlayer.handleCurrentMediaWaiting(self.getCtx());
                }
            })
            .on(readyEvents, function () {
                if (self.isCurrentMediaElement(this)) {
                    StoriesPlayer.handleCurrentMediaReady(self.getCtx());
                }
            })
            .on("error.storiesMedia", function () {
                if (self.isCurrentMediaElement(this)) {
                    StoriesPlayer.handleCurrentMediaError(self.getCtx());
                }
            });
    },

    isCurrentMediaElement: function (element) {
        var slide = element ? element.closest(".swiper-slide") : null;
        if (!slide) {
            return false;
        }

        return $(slide).index() === this.currentItemIndex;
    },

    initViewerSwiper: function () {
        if (this.viewerSwiper) {
            this.viewerSwiper.destroy(true, true);
            this.viewerSwiper = null;
        }

        this.viewerSwiper = new Swiper(".stories-viewer-swiper", {
            initialSlide: this.currentItemIndex,
            effect: "fade",
            fadeEffect: { crossFade: true },
            speed: 250,
            allowTouchMove: false,
            // helps allow a "pull" at edges so touchEnd can feel intentional
            resistance: true,
            resistanceRatio: 0.85
        });

        // When swiping between items normally
        this.viewerSwiper.on("slideChange", () => {
            this.currentItemIndex = this.viewerSwiper.activeIndex;

            this.isPaused = false;
            this.pausedProgress = null;
            StoriesPlayer.syncPlayButtonUI(this.getCtx());

            StoriesPlayer.resetAutoplayForCurrentItem(this.getCtx(), false);
            StoriesPlayer.updateProgressActive(this.getCtx());

            var currentItem = this.getCurrentStoryItem();
            if (currentItem) {
                this.setPreviewUrlForItem(currentItem);

                // update header time per item
                var ts = currentItem.time || null;
                if (ts) {
                    $(".stories-viewer-time").text(this.formatTimeAgo(ts));
                }
            }

            this.renderOverlayText();
            this.syncViewsUI();
            this.trackView();
            this.syncPinButtonUI();
            this.syncProfileAvatarRing(); // add this

            this.applyOverlayForCurrentItem();

            var ctx = this.getCtx();
            StoriesPlayer.applySoundForItem(ctx, false, true);
            StoriesPlayer.syncSoundPill(ctx);
            StoriesPlayer.updateSoundButtonVisibility(ctx);
            StoriesPlayer.syncSoundButtonUI(ctx);

            this.applyBgPresetForCurrentItem();
            this.applyLinkForCurrentItem();
        });

        // 🔥 Key part: swipe "past" ends => change bubble
        this.viewerSwiper.on("touchEnd", (swiper) => {
            // swiper.swipeDirection is "next" (swipe left) or "prev" (swipe right)
            var dir = swiper.swipeDirection;

            // If user tried to go beyond last item -> next bubble
            if (swiper.isEnd && dir === "next") {
                this.goToNextStory();
                return;
            }

            // If user tried to go beyond first item -> prev bubble
            if (swiper.isBeginning && dir === "prev") {
                this.goToPrevStory();
                return;
            }
        });

        StoriesPlayer.resetAutoplayForCurrentItem(this.getCtx());
        StoriesPlayer.updateProgressActive(this.getCtx());
    },

    closeViewer: function () {
        var overlay = document.getElementById("stories-viewer-overlay");
        if (!overlay || !overlay.classList.contains("visible")) return;
        $("#stories-publication-review").tooltip('dispose').trigger('blur').removeAttr('title data-bs-original-title aria-describedby');

        // 🔗 restore original browser URL
        this.restoreOriginalUrl();

        // Stop timers & media
        StoriesPlayer.clearAutoplayTimer(this.getCtx());
        StoriesPlayer.clearMediaLoadingTimer(this.getCtx());
        StoriesPlayer.setMediaLoading(this.getCtx(), false);
        StoriesPlayer.clearMediaError();
        StoriesPlayer.pauseAllVideos();
        StoriesPlayer.stopSound();

        this.isPaused = true;
        this.pausedProgress = null;
        this.mediaProgressPaused = false;
        this.mediaPausedProgress = null;

        overlay.classList.add("closing");
        overlay.classList.remove("visible");

        // Unlock background scroll
        document.documentElement.classList.remove("stories-no-scroll");
        document.body.classList.remove("stories-no-scroll");

        this.syncProfileAvatarRing(); // add this
        setTimeout(function () {
            overlay.classList.remove("closing");
        }, 250);
    },

    /* ------------------------------
     * NAVIGATION
     * ------------------------------ */

    getNonOwnStoryIndices: function () {
        var myId = window.user && window.user.user_id ? Number(window.user.user_id) : null;
        var out = [];

        for (var i = 0; i < this.storiesData.length; i++) {
            var s = this.storiesData[i];
            if (!s) continue;

            if (myId && Number(s.user_id) === myId) {
                continue;
            }

            // only bubbles that have items
            if (s.items && s.items.length) {
                out.push(i);
            }
        }

        return out;
    },

    getNavOrder: function () {
        var nonOwn = this.getNonOwnStoryIndices();

        if (!this._navFromOwn) {
            return nonOwn;
        }

        var ownIndex = this.findOwnStoryIndex();
        if (ownIndex === -1) {
            return nonOwn;
        }

        // ensure no duplicates
        return [ownIndex].concat(nonOwn.filter(function (i) { return i !== ownIndex; }));
    },

    getNextIndexInNavOrder: function () {
        var order = this.getNavOrder();
        var pos = order.indexOf(this.currentStoryIndex);

        // if current isn't in order (edge case), start from first
        if (pos === -1) return order.length ? order[0] : -1;

        return (pos + 1 < order.length) ? order[pos + 1] : -1;
    },

    getPrevIndexInNavOrder: function () {
        var order = this.getNavOrder();
        var pos = order.indexOf(this.currentStoryIndex);

        if (pos === -1) return order.length ? order[0] : -1;

        return (pos - 1 >= 0) ? order[pos - 1] : -1;
    },

    goToNextStory: function () {
        var nextIdx = this.getNextIndexInNavOrder();
        if (nextIdx !== -1) {
            this.openViewer(nextIdx, 0);
        } else {
            this.closeViewer();
        }
    },

    goToPrevStory: function () {
        var prevIdx = this.getPrevIndexInNavOrder();
        if (prevIdx !== -1) {
            // go to last item of previous bubble (more natural)
            var prevBubble = this.storiesData[prevIdx];
            var lastItem = (prevBubble && prevBubble.items) ? (prevBubble.items.length - 1) : 0;
            this.openViewer(prevIdx, Math.max(0, lastItem));
        }
    },

    nextItem: function () {
        var story = this.storiesData[this.currentStoryIndex];
        if (!story) return;

        var items = story.items || [];

        if (this.currentItemIndex < items.length - 1) {
            // user explicitly navigated -> start new item playing
            this.isPaused = false;
            this.pausedProgress = null;
            StoriesPlayer.syncPlayButtonUI(this.getCtx());

            this.viewerSwiper.slideNext();
        } else {
            this.goToNextStory();
        }
    },

    prevItem: function () {
        var story = this.storiesData[this.currentStoryIndex];
        if (!story) return;

        if (this.currentItemIndex > 0) {
            this.isPaused = false;
            this.pausedProgress = null;
            StoriesPlayer.syncPlayButtonUI(this.getCtx());

            this.viewerSwiper.slidePrev();
        } else {
            this.goToPrevStory();
        }
    },

    /* ------------------------------
     * GLOBAL SHORTCUTS
     * ------------------------------ */

    bindEscKey: function () {
        $(window).off("keydown.storiesViewer").on("keydown.storiesViewer", (e) => {
            var key = e.key || e.which;
            if (key === "Escape" || key === "Esc" || key === 27) {
                e.preventDefault();
                this.closeViewer();
            }
        });
    },

    bindGlobalShortcuts: function () {
        $(window).off("keydown.storiesGlobal").on("keydown.storiesGlobal", (e) => {
            var key = e.key || e.which;

            if (!this.isViewerOpen()) {
                return;
            }

            // Esc
            if (key === "Escape" || key === "Esc" || key === 27) {
                e.preventDefault();
                this.closeViewer();
                return;
            }

            // Right arrow -> next item
            if (key === "ArrowRight" || key === 39) {
                e.preventDefault();
                this.nextItem();
                return;
            }

            // Left arrow -> previous item
            if (key === "ArrowLeft" || key === 37) {
                e.preventDefault();
                this.prevItem();
                return;
            }
        });
    },

    deleteCurrentStory: function () {
        var item = this.getCurrentStoryItem();
        if (!item) return;

        this.pendingDeleteStoryId = item.id;
        this._deleteDialogOpen = true;
        this._deleteDialogCompleted = false;
        this._deleteDialogWasPaused = !!this.isPaused;

        if (!this.isPaused) {
            StoriesPlayer.pauseStory(this.getCtx());
            StoriesPlayer.pauseSound();
            StoriesPlayer.syncPlayButtonUI(this.getCtx());
        }

        showDialog("story-delete-dialog");
    },

    handleDeleteDialogHidden: function () {
        var shouldResume = this._deleteDialogOpen && !this._deleteDialogCompleted && !this._deleteDialogWasPaused;

        this._deleteDialogOpen = false;
        this._deleteDialogWasPaused = false;
        this._deleteDialogCompleted = false;
        this.pendingDeleteStoryId = null;

        if (shouldResume && this.isViewerOpen()) {
            StoriesPlayer.resumeStory(this.getCtx());
            StoriesPlayer.syncPlayButtonUI(this.getCtx());
            StoriesPlayer.applySoundForItem(this.getCtx(), false);
        }
    },

    confirmDeleteCurrentStory: function () {
        var item = this.getCurrentStoryItem();
        var storyId = this.pendingDeleteStoryId;

        if (!item || String(item.id) !== String(storyId)) {
            hideDialog("story-delete-dialog");
            return;
        }

        $.ajax({
            type: "DELETE",
            url: app.baseUrl + "/stories/delete",
            data: { story_id: storyId }, // item.id === story_id
            success: () => {
                this._deleteDialogCompleted = true;
                hideDialog("story-delete-dialog");

                // remove item from local state
                var bubble = this.storiesData[this.currentStoryIndex];
                bubble.items = bubble.items.filter(i => String(i.id) !== String(storyId));

                // if bubble is empty → remove bubble
                if (!bubble.items.length) {
                    this.storiesData.splice(this.currentStoryIndex, 1);
                    this.closeViewer();
                    this.renderThumbsRow(this.storiesData);
                    this.initThumbsSwiper();
                    return;
                }

                // otherwise move to next item safely
                this.currentItemIndex = Math.max(0, this.currentItemIndex - 1);
                this.openViewer(this.currentStoryIndex, this.currentItemIndex);

                launchToast("success", trans("Success"), trans("Story deleted"));
            },
            error: (result) => {
                launchToast(
                    "danger",
                    trans("Error"),
                    result?.responseJSON?.message || trans("Something went wrong.")
                );
            }
        });
    },

    togglePinCurrentStory: function () {
        var item = this.getCurrentStoryItem();
        if (!item) return;

        $.ajax({
            type: "POST",
            url: app.baseUrl + "/stories/pin-toggle",
            data: { story_id: item.id }, // item.id
            success: (resp) => {
                item.pinned = !!resp.pinned;
                this.syncPinButtonUI();

                launchToast(
                    "success",
                    trans("Success"),
                    item.pinned ? trans("Story pinned") : trans("Story unpinned")
                );
            },
            error: (result) => {
                launchToast(
                    "danger",
                    trans("Error"),
                    result?.responseJSON?.message || trans("Something went wrong.")
                );
            }
        });
    },

    tryOpenDeepLink: function () {
        if (!window.storiesDeepLink || !window.storiesDeepLink.story_id) return;

        var targetId = String(window.storiesDeepLink.story_id);

        // search in bubbles+items
        for (var s = 0; s < this.storiesData.length; s++) {
            var bubble = this.storiesData[s];
            var items = bubble.items || [];

            for (var i = 0; i < items.length; i++) {
                if (String(items[i].id) === targetId) {
                    // prevent reopening if init is called again
                    window.storiesDeepLink = null;

                    this.openViewer(s, i);
                    return;
                }
            }
        }

        // if we got here => not found (expired / not in feed / access denied)
        window.storiesDeepLink = null;
        launchToast("danger", trans("Error"), trans("Story not available."));
    },

    syncPinButtonUI: function () {
        var item = this.getCurrentStoryItem();
        if (!item) return;

        $(".stories-owner-action-pin").text(
            item.pinned ? trans("Unpin story") : trans("Pin story")
        );
    },

    syncViewsUI: function () {
        var story = this.storiesData[this.currentStoryIndex];
        if (!story) return;

        var myId = window.user && window.user.user_id ? Number(window.user.user_id) : null;
        var isOwn = myId && Number(story.user_id) === myId;

        var item = this.getCurrentStoryItem();

        if (isOwn && item) {
            $(".stories-viewer-views").removeClass("d-none");
            $(".stories-viewer-views-count").text(item.views || 0);
        } else {
            $(".stories-viewer-views").addClass("d-none");
        }
    },

    trackView: function () {
        var story = this.storiesData[this.currentStoryIndex];
        if (!story) return;

        var viewer = window.user && window.user.user_id ? Number(window.user.user_id) : null;
        var isOwn = viewer && Number(story.user_id) === viewer;
        if (isOwn) return;

        var item = this.getCurrentStoryItem();
        if (!item || !item.id) return;

        var storyId = String(item.id);

        // OPTIMISTIC UI: mark as seen immediately (don’t wait for AJAX)
        item.seen = true;

        var bubble = this.storiesData[this.currentStoryIndex];
        if (bubble && Array.isArray(bubble.items)) {
            bubble.items.forEach(function (it) {
                if (String(it.id) === storyId) {
                    it.seen = true;
                }
            });
        }

        // Update thumb ring immediately
        this.updateThumbSeenState(this.currentStoryIndex);
        this.syncProfileAvatarRing(); // add this

        // Fire and forget (backend will persist view)
        $.ajax({
            type: "POST",
            url: app.baseUrl + "/stories/view",
            data: { story_id: item.id },
            success: (resp) => {
                // keep views updated too (optional)
                if (resp && typeof resp.views !== "undefined") {
                    item.views = Number(resp.views || 0);
                    // if you want, sync header count right away:
                    this.syncViewsUI();
                }
            },
            error: () => {
                // optional: you can ignore errors; UI stays optimistic
                // (if you want to be strict, you could revert, but it’s usually not worth it)
            }
        });
    },

    updateThumbSeenState: function (storyIndex) {
        var root = document.getElementById(this.containerId);
        if (!root) return;

        var el = root.querySelector('.story-thumb[data-story-index="' + storyIndex + '"]');
        if (!el) return;

        var story = this.storiesData[storyIndex];
        var isSeen = this.isStorySeen(story);

        el.classList.toggle("story-thumb-unseen", !isSeen);
        el.classList.toggle("story-thumb-seen", isSeen);
    },

    applyOverlayForCurrentItem: function () {
        var item = this.getCurrentStoryItem();
        if (!item) return;

        var review = item.publication_review;
        var reason = review ? review.rejection_reason || "" : "";
        var $review = $("#stories-publication-review");
        $review.tooltip('dispose').trigger('blur').removeAttr('title data-bs-original-title aria-describedby')
            .text(review ? review.label : "")
            .prop('disabled', !reason)
            .toggleClass("d-none", !review);
        if (reason) {
            $review.attr('title', reason).tooltip();
        }

        var $el = $("#stories-overlay-text");
        if (!$el.length) return;

        // 1) set text
        var text = item.text || "";
        $el.text(text);

        // 2) show/hide when empty
        if (!String(text).trim()) {
            $el.hide();
            return;
        }
        $el.css("display", "block");

        // 3) position
        var x = 0.5, y = 0.5;

        if (item.overlay) {
            var ox = parseFloat(item.overlay.x);
            var oy = parseFloat(item.overlay.y);
            if (!isNaN(ox) && !isNaN(oy)) { x = ox; y = oy; }
        }

        $el.css({
            left: (x * 100) + "%",
            top:  (y * 100) + "%",
            transform: "translate(-50%, -50%)"
        });
    },

    applyLinkForCurrentItem: function () {
        var item = this.getCurrentStoryItem();
        var $btn = $("#stories-link-btn");
        if (!$btn.length) return;

        var url = item && item.link ? String(item.link).trim() : "";
        var txt = item && item.linkText ? String(item.linkText).trim() : "";

        if (!url) {
            $btn.addClass("d-none").attr("href", "#");
            $btn.find(".stories-link-text").text("");
            return;
        }

        if (!/^https?:\/\//i.test(url)) {
            // safety (should never happen if backend validated)
            $btn.addClass("d-none");
            return;
        }

        if (!txt) txt = trans("Learn more");

        $btn.removeClass("d-none");
        $btn.attr("href", url);
        $btn.find(".stories-link-text").text(txt);
    },

    applyBgPresetForCurrentItem: function () {
        var item = this.getCurrentStoryItem();
        var stage = document.querySelector(".stories-viewer-swiper");
        if (!stage || !item) return;

        // remove any previous preset classes
        stage.className = stage.className.replace(/\bstory-bg--\S+/g, "").trim();

        // media stories should just be black behind the image/video
        if (item.type !== "text") {
            stage.style.background = ""; // optional: let css default apply
            return;
        }

        var preset = (item.bg_preset || "solid_black").toString().trim();
        stage.classList.add("story-bg--" + preset);
    },

    getAllowHighlights: function () {
        return !!(window.app && app.stories && app.stories.allowHighlights);
    },

    syncProfileAvatarRing: function () {
        var wrap = document.querySelector(".profile-avatar-wrap");
        if (!wrap) return; // not on profile page

        // If we don't have story data yet, do nothing
        var story = this.storiesData[this.currentStoryIndex];
        if (!story) return;

        // If owner has no active stories, remove both states
        // (and remove pointer cursor if you use it)
        if (!story.items || !story.items.length) {
            wrap.classList.remove("profile-has-stories", "profile-stories-unseen", "profile-stories-seen", "cursor-pointer");
            return;
        }

        // Ensure "has stories"
        wrap.classList.add("profile-has-stories");

        // Compute seen/unseen based on LOCAL data
        var seen = this.isStorySeen(story);

        wrap.classList.toggle("profile-stories-seen", !!seen);
        wrap.classList.toggle("profile-stories-unseen", !seen);

        // Optional: only show cursor-pointer when stories actually exist
        wrap.classList.toggle("cursor-pointer", true);
    },

    openFromPayload: function (payload, opts) {
        opts = opts || {};

        // Backend can send either {stories:[...]} or [...]
        var normalized = this.normalizeStories(payload);

        if (!normalized || !normalized.length) {
            launchToast("danger", trans("Error"), trans("Story not available."));
            return;
        }

        // IMPORTANT: the entire swiper uses storiesData, not data
        this.storiesData = normalized;

        // external payload mode: don't try to do nav order that depends on feed thumbs
        this._navFromOwn = false;

        // Make sure global shortcuts exist (safe to call multiple times)
        this.bindGlobalShortcuts();

        // Optional: if you ever want to refresh thumbs on the page
        if (opts.refreshThumbs) {
            this.renderThumbsRow(this.storiesData);
            this.resetThumbsSwiper();
        }

        // Open first bubble
        this.openViewer(0, opts.itemIndex || 0);
    },

    openByStoryId: function (storyId) {
        var targetId = String(storyId || "");
        if (!targetId) return false;

        for (var s = 0; s < this.storiesData.length; s++) {
            var bubble = this.storiesData[s];
            var items = (bubble && bubble.items) ? bubble.items : [];

            for (var i = 0; i < items.length; i++) {
                if (String(items[i].id) === targetId) {
                    this._navFromOwn = false;
                    this.openViewer(s, i);
                    return true;
                }
            }
        }

        return false;
    },

    openByStoryIdOrFetch: function (storyId) {
        // 1) Try local dataset first
        if (this.openByStoryId(storyId)) return;

        // 2) Fallback: fetch payload endpoint (you said you'll add it)
        $.ajax({
            type: "GET",
            url: app.baseUrl + "/stories/payload/" + storyId,
            dataType: "json",
            success: (res) => {
                var payload = (res && res.data) ? res.data : res;
                this.openFromPayload(payload, { refreshThumbs: false });
            },
            error: () => {
                launchToast("danger", trans("Error"), trans("Story not available."));
            }
        });
    },

    isMobileViewport: function () {
        return window.matchMedia && window.matchMedia("(max-width: 768px)").matches;
    },

    disableStoriesRightClick: function () {
        // Only run if feature is enabled
        if (!window.app || app.feedDisableRightClickOnMedia !== true) return;

        var $overlay = $("#stories-viewer-overlay");

        // Thumbs row (avatars)
        $("#" + this.containerId)
            .off("contextmenu.stories")
            .on("contextmenu.stories", ".story-thumb img, .story-thumb", function () {
                return false;
            });

        // Viewer overlay media (image + video)
        $overlay
            .off("contextmenu.stories")
            .on("contextmenu.stories", "img.story-image, video.story-video, .stories-viewer, .stories-viewer-swiper", function () {
                return false;
            });

        // Optional: prevent right click on overlay text too
        $overlay
            .off("contextmenu.storiesText")
            .on("contextmenu.storiesText", "#stories-overlay-text", function () {
                return false;
            });

        // Your existing long-press preventer (mobile)
        if (typeof window.bindNoLongPressEvents === "function") {
            // If your helper works by scanning `.no-long-press`, add class below (see #2)
            window.bindNoLongPressEvents();
        }
    },

};

// keep it explicit for multi-file usage
window.StoriesSwiper = StoriesSwiper;
