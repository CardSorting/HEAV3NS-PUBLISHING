/*
* RTMP streams JS component
*/
"use strict";
/* global streamVars, videojs */


// eslint-disable-next-line no-unused-vars
var RTMPStream = {
    player: null,
    retryTimer: null,
    retryCount: 0,
    retryInterval: 5000,
    maxRetries: 120,
    source: null,
    startedPlaying: false,

    init: function () {
        this.initVideo();
    },

    initVideo: function () {
        if (!streamVars.canWatchStream) return;

        this.source = $('#my_video_1 source').attr('src');
        this.player = videojs('my_video_1', {
            autoplay: true,
            preload: "auto",
            controls: true,
            poster: streamVars.streamPoster,
            controlBar: {
                pictureInPictureToggle: false
            }
        });

        if (typeof this.player.qualityLevels === 'function') {
            this.player.qualityLevels();
        }

        if (typeof this.player.qualityMenu === 'function') {
            this.player.qualityMenu({
                defaultResolution: 'none'
            });
        }

        this.player.on('playing', () => {
            this.startedPlaying = true;
            this.clearRetryTimer();
            this.retryCount = 0;
        });

        this.player.on('error', () => {
            this.scheduleRetry();
        });

        this.tryPlay();
        this.scheduleRetry();
    },

    tryPlay: function () {
        if (!this.player) return;

        var playPromise = this.player.play();
        if (playPromise && typeof playPromise.catch === 'function') {
            playPromise.catch(() => {
                this.scheduleRetry();
            });
        }
    },

    scheduleRetry: function () {
        if (!streamVars.isLiveStream || this.startedPlaying || this.retryTimer || this.retryCount >= this.maxRetries || !this.source) return;

        this.retryTimer = window.setTimeout(() => {
            this.retryTimer = null;
            this.retryCount += 1;
            this.retryPlayback();
        }, this.retryInterval);
    },

    retryPlayback: function () {
        if (!this.player || !this.source) return;

        if (this.shouldReloadSource()) {
            this.player.error(null);
            this.player.src({
                src: this.getRetrySource(),
                type: 'application/vnd.apple.mpegurl'
            });
            this.player.load();
        }

        this.tryPlay();
        this.scheduleRetry();
    },

    shouldReloadSource: function () {
        var hasError = typeof this.player.error === 'function' && !!this.player.error();
        var hasNoMediaData = typeof this.player.readyState === 'function' && this.player.readyState() === 0;

        return hasError || hasNoMediaData;
    },

    getRetrySource: function () {
        var separator = this.source.indexOf('?') === -1 ? '?' : '&';

        return this.source + separator + 'live_retry=' + Date.now();
    },

    clearRetryTimer: function () {
        if (!this.retryTimer) return;

        window.clearTimeout(this.retryTimer);
        this.retryTimer = null;
    }
};
