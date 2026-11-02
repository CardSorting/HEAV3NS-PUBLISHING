/**
 * Stories playback/progress engine (object-literal style for consistency).
 * Keeps your behavior: autoplay, progress bars, hold-to-pause, play/pause, sound toggle, video activation.
 *
 * Requires: jQuery (for show/hide), window, document
 */
"use strict";

var StoriesPlayer = {

    /* ------------------------------
     * UI helpers
     * ------------------------------ */

    updateSoundButtonVisibility: function (ctx) {
        var item = ctx.getCurrentItem ? ctx.getCurrentItem() : null;
        var $btn = $(".stories-viewer-toggle-sound");
        if (!$btn.length) return;

        var hasTrack = !!(item && item.sound && item.sound.audio_src);
        var isVideo = !!(item && item.type === "video");

        // show button if either video has audio OR story has soundtrack
        if (isVideo || hasTrack) $btn.show();
        else $btn.hide();
    },

    syncPlayButtonUI: function (ctx) {
        if (ctx.getIsPaused()) {
            $(".stories-viewer-toggle-play .icon-pause").hide();
            $(".stories-viewer-toggle-play .icon-play").show();
        } else {
            $(".stories-viewer-toggle-play .icon-play").hide();
            $(".stories-viewer-toggle-play .icon-pause").show();
        }
    },

    /* ------------------------------
     * Media helpers
     * ------------------------------ */

    pauseAllVideos: function () {
        document.querySelectorAll(".story-video").forEach(function (v) {
            // eslint-disable-next-line no-empty
            try { v.pause(); } catch (e) {}
        });
    },

    setVideoSoundState: function (ctx, video) {
        if (!video) return;

        if (ctx.getIsSoundOn()) {
            video.muted = false;
            video.volume = 1.0;
        } else {
            video.muted = true;
            video.volume = 0.0;
        }
    },

    applySoundToCurrentVideo: function (ctx) {
        var currentItemIndex = ctx.getCurrentItemIndex();
        var slides = document.querySelectorAll(".stories-viewer-swiper .swiper-slide");

        slides.forEach(function (slide, index) {
            var video = slide.querySelector("video.story-video");
            if (!video) return;

            if (index === currentItemIndex) {
                StoriesPlayer.setVideoSoundState(ctx, video);
            }
        });
    },

    activateCurrentMedia: function (ctx) {
        var currentItemIndex = ctx.getCurrentItemIndex();
        var slides = document.querySelectorAll(".stories-viewer-swiper .swiper-slide");

        slides.forEach(function (slide, index) {
            var video = slide.querySelector("video.story-video");
            if (!video) return;

            if (index === currentItemIndex) {
                StoriesPlayer.setVideoSoundState(ctx, video);

                var p = video.play();
                if (p && typeof p.catch === "function") {
                    p.catch(function () {
                        // ignore autoplay errors
                    });
                }
            } else {
                video.pause();
                // eslint-disable-next-line no-empty
                try { video.currentTime = 0; } catch (e) {}
            }
        });
    },

    getCurrentSlide: function (ctx) {
        var slides = document.querySelectorAll(".stories-viewer-swiper .swiper-slide");
        return slides[ctx.getCurrentItemIndex()] || null;
    },

    getCurrentMediaElement: function (ctx) {
        var slide = StoriesPlayer.getCurrentSlide(ctx);
        if (!slide) {
            return null;
        }

        return slide.querySelector("img.story-image, video.story-video");
    },

    isCurrentMediaReady: function (ctx) {
        var item = ctx.getCurrentItem();
        var media = StoriesPlayer.getCurrentMediaElement(ctx);

        if (!item || item.type === "text" || !media) {
            return true;
        }

        if (media.tagName === "IMG") {
            return !!(media.complete && media.naturalWidth > 0);
        }

        if (media.tagName === "VIDEO") {
            return media.readyState >= 3;
        }

        return true;
    },

    clearMediaLoadingTimer: function (ctx) {
        var timer = ctx.getMediaLoadingTimer && ctx.getMediaLoadingTimer();
        if (timer) {
            clearTimeout(timer);
            ctx.setMediaLoadingTimer(null);
        }
    },

    setMediaLoading: function (ctx, loading) {
        if (!loading) {
            StoriesPlayer.clearMediaLoadingTimer(ctx);
        }

        $(".stories-media-loading").toggleClass("d-none", !loading);
        $(".stories-viewer-swiper").toggleClass("is-media-loading", !!loading);
    },

    queueMediaLoading: function (ctx, loading) {
        if (!loading) {
            StoriesPlayer.setMediaLoading(ctx, false);
            return;
        }

        StoriesPlayer.clearMediaLoadingTimer(ctx);
        ctx.setMediaLoadingTimer(setTimeout(function () {
            if (ctx.isViewerOpen() && !StoriesPlayer.isCurrentMediaReady(ctx)) {
                StoriesPlayer.setMediaLoading(ctx, true);
            }
        }, 350));
    },

    clearMediaError: function () {
        $(".stories-media-error").addClass("d-none");
        $(".stories-viewer-swiper").removeClass("has-media-error");
    },

    showMediaError: function (ctx) {
        StoriesPlayer.clearMediaLoadingTimer(ctx);
        StoriesPlayer.setMediaLoading(ctx, false);
        StoriesPlayer.clearAutoplayTimer(ctx);
        StoriesPlayer.pauseProgressAnimation(ctx);
        StoriesPlayer.pauseSound();
        $(".stories-media-error").removeClass("d-none");
        $(".stories-viewer-swiper").addClass("has-media-error");
    },

    pauseProgressForMedia: function (ctx) {
        var item = ctx.getCurrentItem();
        var durationMs = (item && item.length ? item.length : 5) * 1000;
        var fraction;

        if (ctx.getMediaProgressPaused && ctx.getMediaProgressPaused()) {
            return;
        }

        StoriesPlayer.clearAutoplayTimer(ctx);
        StoriesPlayer.pauseProgressAnimation(ctx);
        StoriesPlayer.pauseSound();

        fraction = StoriesPlayer.getProgressFraction(ctx);
        ctx.setMediaPausedProgress({
            fraction: fraction,
            remainingMs: durationMs * (1 - fraction)
        });
        ctx.setMediaProgressPaused(true);
    },

    resumeProgressAfterMedia: function (ctx) {
        var item = ctx.getCurrentItem();
        var mediaProgress = ctx.getMediaPausedProgress ? ctx.getMediaPausedProgress() : null;
        var fraction = mediaProgress ? mediaProgress.fraction : StoriesPlayer.getProgressFraction(ctx);
        var remainingMs = mediaProgress && mediaProgress.remainingMs ? mediaProgress.remainingMs : ((item && item.length ? item.length : 5) * 1000 * (1 - fraction));
        var media = StoriesPlayer.getCurrentMediaElement(ctx);
        var playPromise;

        if (!ctx.isViewerOpen() || ctx.getDevFreeze() || ctx.getIsPaused() || ctx.getIsHolding() || !item) {
            return;
        }

        ctx.setMediaProgressPaused(false);
        ctx.setMediaPausedProgress(null);

        if (remainingMs <= 0) {
            ctx.nextItem();
            return;
        }

        StoriesPlayer.startProgressAnimation(ctx, remainingMs, fraction);

        if (item.type !== "video") {
            StoriesPlayer.clearAutoplayTimer(ctx);
            ctx.setAutoplayTimer(setTimeout(function () {
                ctx.nextItem();
            }, remainingMs));
            StoriesPlayer.applySoundForItem(ctx, false);
            return;
        }

        if (media && media.tagName === "VIDEO") {
            StoriesPlayer.setVideoSoundState(ctx, media);
            playPromise = media.play();
            if (playPromise && typeof playPromise.catch === "function") {
                playPromise.catch(function () {});
            }
        }

        StoriesPlayer.applySoundForItem(ctx, false);
    },

    handleCurrentMediaWaiting: function (ctx) {
        if (!ctx.isViewerOpen()) {
            return;
        }

        StoriesPlayer.clearMediaError();
        StoriesPlayer.queueMediaLoading(ctx, true);
        StoriesPlayer.pauseProgressForMedia(ctx);
    },

    handleCurrentMediaReady: function (ctx) {
        if (!ctx.isViewerOpen()) {
            return;
        }

        if (!StoriesPlayer.isCurrentMediaReady(ctx)) {
            StoriesPlayer.queueMediaLoading(ctx, true);
            return;
        }

        StoriesPlayer.clearMediaError();
        StoriesPlayer.setMediaLoading(ctx, false);

        if (ctx.getMediaProgressPaused && ctx.getMediaProgressPaused()) {
            StoriesPlayer.resumeProgressAfterMedia(ctx);
        }
    },

    handleCurrentMediaError: function (ctx) {
        if (!ctx.isViewerOpen()) {
            return;
        }

        StoriesPlayer.showMediaError(ctx);
    },

    /* ------------------------------
     * Progress UI
     * ------------------------------ */

    buildProgressBars: function (count) {
        var $wrap = $(".stories-viewer-progress-wrap");
        $wrap.empty();

        for (var i = 0; i < count; i++) {
            var bar = $('<div class="story-progress-bar"><div class="story-progress-fill"></div></div>');
            $wrap.append(bar);
        }
    },

    updateProgressActive: function (ctx) {
        var currentItemIndex = ctx.getCurrentItemIndex();
        var bars = document.querySelectorAll(".story-progress-bar");

        bars.forEach(function (bar, index) {
            bar.classList.remove("active", "passed");

            if (index < currentItemIndex) {
                bar.classList.add("passed");
            } else if (index === currentItemIndex) {
                bar.classList.add("active");
            }
        });
    },

    resetProgressBarsState: function (ctx) {
        var currentItemIndex = ctx.getCurrentItemIndex();
        var bars = document.querySelectorAll(".story-progress-bar");

        bars.forEach(function (bar, index) {
            var fill = bar.querySelector(".story-progress-fill");
            if (!fill) return;

            fill.style.transition = "none";

            if (index < currentItemIndex) {
                fill.style.width = "100%";
            } else if (index === currentItemIndex) {
                fill.style.width = "0%";
            } else {
                fill.style.width = "0%";
            }
        });

        StoriesPlayer.updateProgressActive(ctx);
    },

    startProgressAnimation: function (ctx, durationMs, startFraction) {
        startFraction = startFraction || 0;

        var currentItemIndex = ctx.getCurrentItemIndex();
        var bars = document.querySelectorAll(".story-progress-bar");
        var activeBar = bars[currentItemIndex];
        if (!activeBar) return;

        var fill = activeBar.querySelector(".story-progress-fill");
        if (!fill) return;

        fill.style.transition = "none";
        fill.style.width = (startFraction * 100) + "%";

        // force reflow
        void fill.offsetWidth;

        fill.style.transition = "width " + durationMs + "ms linear";
        fill.style.width = "100%";
    },

    pauseProgressAnimation: function (ctx) {
        var currentItemIndex = ctx.getCurrentItemIndex();
        var bars = document.querySelectorAll(".story-progress-bar");
        var activeBar = bars[currentItemIndex];
        if (!activeBar) return;

        var fill = activeBar.querySelector(".story-progress-fill");
        if (!fill) return;

        var computed = window.getComputedStyle(fill);
        var width = computed.width;

        fill.style.transition = "none";
        fill.style.width = width;
    },

    getProgressFraction: function (ctx) {
        var currentItemIndex = ctx.getCurrentItemIndex();
        var bars = document.querySelectorAll(".story-progress-bar");
        var activeBar = bars[currentItemIndex];
        if (!activeBar) return 0;

        var fill = activeBar.querySelector(".story-progress-fill");
        if (!fill) return 0;

        var barRect = activeBar.getBoundingClientRect();
        var fillRect = fill.getBoundingClientRect();

        if (barRect.width <= 0) return 0;

        var frac = fillRect.width / barRect.width;
        if (frac < 0) frac = 0;
        if (frac > 1) frac = 1;
        return frac;
    },

    /* ------------------------------
     * Timers + autoplay orchestration
     * ------------------------------ */

    clearAutoplayTimer: function (ctx) {
        var t = ctx.getAutoplayTimer();
        if (t) {
            clearTimeout(t);
            ctx.setAutoplayTimer(null);
        }
    },

    resetAutoplayForCurrentItem: function (ctx, fromUserGesture) {
        StoriesPlayer.clearAutoplayTimer(ctx);
        ctx.setPausedProgress(null);

        var item = ctx.getCurrentItem();
        if (!item) return;

        StoriesPlayer.clearMediaLoadingTimer(ctx);
        StoriesPlayer.setMediaLoading(ctx, false);
        StoriesPlayer.clearMediaError();
        ctx.setMediaProgressPaused(false);
        ctx.setMediaPausedProgress(null);

        StoriesPlayer.updateSoundButtonVisibility(ctx);
        StoriesPlayer.syncSoundButtonUI(ctx);

        // pass the flag through
        StoriesPlayer.applySoundForItem(ctx, !!fromUserGesture);
        StoriesPlayer.syncSoundPill(ctx);

        var isVideo = item.type === "video";
        var durationMs = (item.length || 5) * 1000;

        if (ctx.getDevFreeze() || ctx.getIsPaused()) {
            StoriesPlayer.pauseProgressAnimation(ctx);
            StoriesPlayer.pauseAllVideos();
            return;
        }

        StoriesPlayer.resetProgressBarsState(ctx);
        StoriesPlayer.activateCurrentMedia(ctx);

        if (!StoriesPlayer.isCurrentMediaReady(ctx)) {
            StoriesPlayer.queueMediaLoading(ctx, true);
            StoriesPlayer.pauseProgressForMedia(ctx);
            return;
        }

        StoriesPlayer.startProgressAnimation(ctx, durationMs, 0);

        if (!isVideo) {
            ctx.setAutoplayTimer(setTimeout(function () {
                ctx.nextItem();
            }, durationMs));
        }
    },

    /* ------------------------------
     * Manual pause/resume (button)
     * ------------------------------ */

    pauseStory: function (ctx) {
        ctx.setIsPaused(true);

        StoriesPlayer.clearAutoplayTimer(ctx);
        StoriesPlayer.pauseProgressAnimation(ctx);

        var item = ctx.getCurrentItem();
        var durationMs = (item && item.length ? item.length : 5) * 1000;

        var fraction = StoriesPlayer.getProgressFraction(ctx);

        ctx.setPausedProgress({
            fraction: fraction,
            remainingMs: durationMs * (1 - fraction)
        });

        var currentItemIndex = ctx.getCurrentItemIndex();
        document.querySelectorAll(".stories-viewer-swiper .swiper-slide").forEach(function (slide, index) {
            var video = slide.querySelector("video.story-video");
            if (!video) return;
            if (index === currentItemIndex) {
                // eslint-disable-next-line no-empty
                try { video.pause(); } catch (e) {}
            }
        });
    },

    resumeStory: function (ctx) {
        if (!ctx.isViewerOpen()) return;

        ctx.setIsPaused(false);

        if (!StoriesPlayer.isCurrentMediaReady(ctx)) {
            StoriesPlayer.handleCurrentMediaWaiting(ctx);
            return;
        }

        ctx.setMediaProgressPaused(false);
        ctx.setMediaPausedProgress(null);

        var item = ctx.getCurrentItem();
        var durationMs = (item && item.length ? item.length : 5) * 1000;

        var pausedProgress = ctx.getPausedProgress();
        var fraction = pausedProgress ? pausedProgress.fraction : StoriesPlayer.getProgressFraction(ctx);
        var remainingMs = (pausedProgress && pausedProgress.remainingMs)
            ? pausedProgress.remainingMs
            : durationMs * (1 - fraction);

        if (remainingMs <= 0) {
            ctx.nextItem();
            return;
        }

        StoriesPlayer.startProgressAnimation(ctx, remainingMs, fraction);

        if (item.type !== "video") {
            StoriesPlayer.clearAutoplayTimer(ctx);
            ctx.setAutoplayTimer(setTimeout(function () {
                ctx.nextItem();
            }, remainingMs));
        }

        var currentItemIndex = ctx.getCurrentItemIndex();
        document.querySelectorAll(".stories-viewer-swiper .swiper-slide").forEach(function (slide, index) {
            var video = slide.querySelector("video.story-video");
            if (!video) return;
            if (index === currentItemIndex) {
                // eslint-disable-next-line no-empty
                try { video.play(); } catch (e) {}
            }
        });

        ctx.setPausedProgress(null);
    },

    /* ------------------------------
     * Hold-to-pause (pointerdown/up)
     * ------------------------------ */

    holdPause: function (ctx) {
        if (ctx.getDevFreeze()) return;
        if (ctx.getIsHolding()) return;

        ctx.setIsHolding(true);

        StoriesPlayer.clearAutoplayTimer(ctx);
        StoriesPlayer.pauseProgressAnimation(ctx);

        var currentItemIndex = ctx.getCurrentItemIndex();
        var slides = document.querySelectorAll(".stories-viewer-swiper .swiper-slide");
        slides.forEach(function (slide, index) {
            var video = slide.querySelector("video.story-video");
            if (!video) return;
            if (index === currentItemIndex) {
                // eslint-disable-next-line no-empty
                try { video.pause(); } catch (e) {}
            }
        });
    },

    holdResume: function (ctx) {
        if (ctx.getDevFreeze()) return;
        if (!ctx.getIsHolding()) return;

        ctx.setIsHolding(false);

        // Respect manual pause button
        if (ctx.getIsPaused()) return;

        var item = ctx.getCurrentItem();
        if (!item) return;

        var durationMs = (item.length || 5) * 1000;

        var fraction = StoriesPlayer.getProgressFraction(ctx);
        var remainingMs = durationMs * (1 - fraction);

        if (remainingMs <= 0) {
            ctx.nextItem();
            return;
        }

        StoriesPlayer.startProgressAnimation(ctx, remainingMs, fraction);

        StoriesPlayer.clearAutoplayTimer(ctx);
        if (item.type !== "video") {
            ctx.setAutoplayTimer(setTimeout(function () {
                ctx.nextItem();
            }, remainingMs));
        }

        var currentItemIndex = ctx.getCurrentItemIndex();
        var slides = document.querySelectorAll(".stories-viewer-swiper .swiper-slide");
        slides.forEach(function (slide, index) {
            var video = slide.querySelector("video.story-video");
            if (!video) return;
            if (index === currentItemIndex) {
                // eslint-disable-next-line no-empty
                try { video.play(); } catch (e) {}
            }
        });
    },

    // ---- SOUNDTRACK (global audio element) ----

    getSoundEl: function () {
        return document.getElementById("stories-sound");
    },

    stopSound: function () {
        var a = this.getSoundEl();
        if (!a) return;
        a.pause();
        a.currentTime = 0;
        a.removeAttribute("src");
        a.load();
    },

    pauseSound: function () {
        var a = this.getSoundEl();
        if (!a) return;
        a.pause();
    },

    applySoundForItem: function (ctx, fromUserGesture) {
        var a = this.getSoundEl();
        if (!a || !ctx) return;

        // CRITICAL: if viewer closed, kill audio and exit
        if (ctx.isViewerOpen && !ctx.isViewerOpen()) {
            this.stopSound();
            return;
        }

        var item = ctx.getCurrentItem ? ctx.getCurrentItem() : null;
        var src = item && item.sound && item.sound.audio_src ? item.sound.audio_src : null;

        if (!src) {
            this.stopSound();
            return;
        }

        if (a.getAttribute("src") !== src) {
            a.pause();
            a.setAttribute("src", src);
            a.currentTime = 0;
            a.load();
        }

        a.muted = !ctx.getIsSoundOn();

        if (ctx.getIsPaused && ctx.getIsPaused()) return;
        if (ctx.getMediaProgressPaused && ctx.getMediaProgressPaused()) return;

        var unlocked = ctx.getAudioUnlocked && ctx.getAudioUnlocked();
        if (!a.muted && !fromUserGesture && !unlocked) return;

        a.play().catch(function(){});
    },

    syncSoundPill: function (ctx) {
        var pill = document.getElementById("stories-sound-pill");
        if (!pill || !ctx) return;

        var item = ctx.getCurrentItem ? ctx.getCurrentItem() : null;
        var name = item && item.sound && item.sound.title ? String(item.sound.title) : "";

        if (!name.trim()) {
            pill.classList.add("d-none");
            pill.textContent = "";
            return;
        }

        pill.classList.remove("d-none");
        pill.textContent = "♪ " + name;
    },

    syncSoundButtonUI: function (ctx) {
        var on = ctx.getIsSoundOn && ctx.getIsSoundOn();
        if (on) {
            $(".stories-viewer-toggle-sound .icon-sound-off").hide();
            $(".stories-viewer-toggle-sound .icon-sound-on").show();
        } else {
            $(".stories-viewer-toggle-sound .icon-sound-on").hide();
            $(".stories-viewer-toggle-sound .icon-sound-off").show();
        }
    },

};

// Optional: make it explicit on window as well (harmless + consistent in multi-file land)
window.StoriesPlayer = StoriesPlayer;
