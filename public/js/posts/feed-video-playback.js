/* Optional playback for uploaded videos in post feeds. */
"use strict";

$(function () { FeedVideoPlayback.init(); });

var FeedVideoPlayback = {
    container: null,
    videos: new Map(),
    visible: new Set(),
    galleries: new Map(),
    active: null,
    presented: null,
    pending: false,
    pageHidden: false,
    muted: true,
    volume: 1,

    init: function () {
        if (this.container || !window.IntersectionObserver) return;
        const container = document.querySelector('.posts-wrapper:not(.profile-compact-posts-wrapper)');
        if (!container) return;
        this.container = container;
        this.observer = new window.IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) this.visible.add(entry.target);
                else this.visible.delete(entry.target);
            });
            this.schedule();
        });
        this.uiObserver = new MutationObserver(() => this.update());

        $(document).on('posts:appended.feedVideoPlayback posts:removed.feedVideoPlayback', () => this.scan());
        ['play', 'pause', 'ended', 'volumechange'].forEach(name => {
            document.addEventListener(name, event => this.onMediaEvent(event), true);
        });
        document.addEventListener('visibilitychange', () => this.update());
        document.addEventListener('fullscreenchange', () => this.update());
        document.addEventListener('webkitfullscreenchange', () => this.update());
        ['enterpictureinpicture', 'leavepictureinpicture', 'webkitbeginfullscreen', 'webkitendfullscreen', 'webkitpresentationmodechanged'].forEach(name => {
            document.addEventListener(name, event => {
                const video = event.target;
                if (!this.videos.has(video)) return;
                this.presented = name === 'enterpictureinpicture' || name === 'webkitbeginfullscreen' ||
                    (name === 'webkitpresentationmodechanged' && video.webkitPresentationMode !== 'inline') ? video : null;
                this.update();
            }, true);
        });
        window.addEventListener('scroll', () => this.schedule(), {passive: true});
        window.addEventListener('resize', () => this.schedule());
        window.addEventListener('pagehide', () => { this.pageHidden = true; this.update(); });
        window.addEventListener('pageshow', () => { this.pageHidden = false; this.scan(); });
        if (navigator.connection) navigator.connection.addEventListener('change', () => this.update());
        this.scan();
    },

    scan: function () {
        this.videos.forEach((state, video) => {
            if (this.container.contains(video)) return;
            this.pause(video);
            this.observer.unobserve(video);
            this.visible.delete(video);
            this.videos.delete(video);
            if (this.active === video) this.active = null;
            if (this.presented === video) this.presented = null;
        });
        this.container.querySelectorAll('.post-box .post-media video.video-preview').forEach(video => {
            if (this.videos.has(video)) return;
            this.videos.set(video, {manualPause: false, blocked: false, pending: false, requested: false, autoPauses: 0, userStarted: false});
            this.applySoundPreference(video);
            video.playsInline = true;
            this.observer.observe(video);
        });

        this.galleries.forEach((callback, swiper) => {
            if (!swiper.destroyed && this.container.contains(swiper.el)) return;
            if (!swiper.destroyed) swiper.off('slideChange', callback);
            this.galleries.delete(swiper);
        });
        this.container.querySelectorAll('.post-media-swiper').forEach(gallery => {
            const swiper = gallery._swiper;
            if (!swiper || this.galleries.has(swiper)) return;
            const callback = () => this.update();
            swiper.on('slideChange', callback);
            this.galleries.set(swiper, callback);
        });

        // These existing viewer classes cover overlays without changing their player code.
        this.uiObserver.disconnect();
        [document.documentElement, document.body, ...document.querySelectorAll('.pswp')].forEach(node => {
            this.uiObserver.observe(node, {attributes: true, attributeFilter: ['class']});
        });
        this.schedule();
    },

    schedule: function () {
        if (this.pending) return;
        this.pending = true;
        window.requestAnimationFrame(() => {
            this.pending = false;
            this.update();
        });
    },

    getPresentedVideo: function () {
        const fullscreen = document.fullscreenElement || document.webkitFullscreenElement;
        return document.pictureInPictureElement || this.presented ||
            (fullscreen && (fullscreen.matches('video') ? fullscreen : fullscreen.querySelector('video')));
    },

    isSuspended: function () {
        return document.hidden || this.pageHidden ||
            document.documentElement.matches('.stories-no-scroll, .reel-viewer-open') ||
            document.body.classList.contains('modal-open') || !!document.querySelector('.pswp--open') ||
            !!(document.fullscreenElement || document.webkitFullscreenElement) ||
            Array.from(this.container.querySelectorAll('.post-media audio')).some(audio => !audio.paused && !audio.ended);
    },

    focusDistance: function (video) {
        if (!video.isConnected) return Infinity;
        const gallery = video.closest('.post-media-swiper');
        const swiper = gallery && gallery._swiper;
        if (swiper && video.closest('.swiper-slide') !== swiper.slides[swiper.activeIndex]) return Infinity;
        const rect = video.getBoundingClientRect();
        const height = window.innerHeight;
        const width = window.innerWidth;
        const visibleHeight = Math.max(0, Math.min(rect.bottom, height) - Math.max(rect.top, 0));
        const visibleWidth = Math.max(0, Math.min(rect.right, width) - Math.max(rect.left, 0));
        // Cap the denominator at the viewport so tall portrait clips can still play.
        if (!rect.height || !rect.width || visibleHeight < Math.min(rect.height, height) / 2 ||
            visibleWidth < Math.min(rect.width, width) / 2) return Infinity;
        return Math.abs((rect.top + rect.bottom) / 2 - height / 2);
    },

    update: function () {
        const presented = this.getPresentedVideo();
        if (presented) {
            const pictureInPicture = presented === document.pictureInPictureElement || presented.webkitPresentationMode === 'picture-in-picture';
            this.pauseOthers(!this.pageHidden && (!document.hidden || pictureInPicture) ? presented : null);
            this.active = this.videos.has(presented) ? presented : null;
            return;
        }
        let candidate = null;
        let distance = Infinity;
        if (!this.isSuspended()) {
            this.visible.forEach(video => {
                if (!this.videos.has(video)) return;
                const nextDistance = this.focusDistance(video);
                if (nextDistance < distance) { candidate = video; distance = nextDistance; }
            });
            if (this.active && this.videos.has(this.active)) {
                const activeDistance = this.focusDistance(this.active);
                const state = this.videos.get(this.active);
                if (activeDistance < Infinity && ((state.userStarted && !this.active.paused) || activeDistance <= distance + 24)) {
                    candidate = this.active;
                }
            }
        }
        this.pauseOthers(candidate);
        this.active = candidate;
        if (!candidate) return;
        const state = this.videos.get(candidate);
        if (navigator.connection && navigator.connection.saveData) {
            if (!state.userStarted) this.pause(candidate);
            return;
        }
        if (state.manualPause || state.blocked || state.pending || candidate.ended || !candidate.paused) return;
        this.applySoundPreference(candidate);
        state.requested = state.pending = true;
        const play = () => {
            try { return candidate.play(); }
            catch (error) { return Promise.reject(error); }
        };
        play().catch(error => {
            if (error.name === 'NotAllowedError' && !candidate.muted && this.active === candidate &&
                this.videos.has(candidate) && !this.isSuspended() && !state.manualPause && this.focusDistance(candidate) < Infinity) {
                // Browser policy can still reject sound on a new video. Keep the user's preference.
                candidate.muted = state.muted = true;
                return play();
            }
            throw error;
        }).catch(error => {
            state.requested = false;
            // A scroll/overlay can interrupt a pending play; that isn't a browser refusal.
            if (error.name !== 'AbortError') state.blocked = true;
        }).finally(() => {
            state.pending = false;
            if (this.videos.has(candidate)) this.schedule();
        });
    },

    applySoundPreference: function (video) {
        video.muted = this.muted;
        video.volume = this.volume;
        const state = this.videos.get(video);
        // Native volumechange events are queued, including changes made by this controller.
        state.muted = video.muted;
        state.volume = video.volume;
    },

    pauseOthers: function (except) {
        this.videos.forEach((state, video) => {
            if (video !== except) this.pause(video);
        });
    },

    pause: function (video) {
        if (video.paused) return;
        this.videos.get(video).autoPauses++;
        video.pause();
    },

    onMediaEvent: function (event) {
        const video = event.target;
        const state = this.videos.get(video);
        if (!state) {
            if (event.type !== 'volumechange' && video.matches('.post-media audio')) this.update();
            return;
        }
        if (event.type === 'volumechange') {
            if (state.muted === video.muted && state.volume === video.volume) return;
            this.muted = video.muted;
            this.volume = video.volume;
            this.videos.forEach((state, other) => this.applySoundPreference(other));
        } else if (event.type === 'play') {
            if (state.requested) {
                state.requested = false;
                if (this.active !== video || this.isSuspended() || this.focusDistance(video) === Infinity ||
                    (navigator.connection && navigator.connection.saveData)) this.pause(video);
            } else {
                state.manualPause = state.blocked = false;
                state.userStarted = true;
                this.pauseOthers(video);
                this.active = video;
            }
        } else if (event.type === 'pause') {
            if (state.autoPauses) state.autoPauses--;
            else if (!video.ended) state.manualPause = true;
        }
    }
};
