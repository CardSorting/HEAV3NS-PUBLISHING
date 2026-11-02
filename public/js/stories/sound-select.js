/**
 * SoundSelect (object-based module)
 * Requires: jQuery + selectize
 */
/* global app, trans */
"use strict";

var SoundSelect = {

    // -------- state --------
    debug: false,

    opts: {},
    selectize: null,

    // preview state
    previewAudio: null,
    previewTimeout: null,
    playingSoundId: null,
    playingBtn: null,

    el: {
        input: null,
        soundId: null,
        soundTitle: null,
        soundArtist: null,
        soundUrl: null,
        soundStartMs: null
    },

    _ignoreNextChange: false,

    // store bound handlers so we can unbind on destroy
    _dropdownHandlersBound: false,

    /* ------------------------------
     * INIT
     * ------------------------------ */

    init: function (opts) {
        this.opts = Object.assign({}, this.opts || {}, opts || {});
        this.cacheEls();

        if (!this.el.input) return;

        if (!window.jQuery || !jQuery.fn || !jQuery.fn.selectize) {
            // eslint-disable-next-line no-console
            console.warn("[SoundSelect] selectize not found. Make sure selectize JS is loaded before SoundSelect.js");
            return;
        }

        this.mountSelectize();
    },

    cacheEls: function () {
        var cfg = this.opts || {};

        this.el.input = document.getElementById(cfg.inputId || "storySoundSelect");
        this.el.soundId = document.getElementById(cfg.soundIdId || "storySoundId");
        this.el.soundTitle = document.getElementById(cfg.soundTitleId || "storySoundTitle");
        this.el.soundArtist = document.getElementById(cfg.soundArtistId || "storySoundArtist");
        this.el.soundUrl = document.getElementById(cfg.soundUrlId || "storySoundUrl");
        this.el.soundStartMs = document.getElementById(cfg.soundStartMsId || "storySoundStartMs");
    },

    mountSelectize: function () {
        var self = this;

        var baseUrl = (this.opts && this.opts.baseUrl)
            ? this.opts.baseUrl
            : (window.app && app.baseUrl ? app.baseUrl : "");

        var trendingUrl = (this.opts && this.opts.trendingUrl)
            ? this.opts.trendingUrl
            : (baseUrl + "/sounds/trending");

        var searchUrl = (this.opts && this.opts.searchUrl)
            ? this.opts.searchUrl
            : (baseUrl + "/sounds/search");

        var enablePreviewHover = !!(this.opts && this.opts.enablePreview); // keep false if you use play button
        var previewSeconds = (this.opts && this.opts.previewSeconds) ? this.opts.previewSeconds : 8;

        var $input = $(this.el.input);

        // if already mounted, destroy first (safe re-init)
        if (this.selectize) {
            // eslint-disable-next-line no-empty
            try { this.selectize.destroy(); } catch (e) {}
            this.selectize = null;
        }

        $input.selectize({
            persist: false,
            maxItems: 1,
            valueField: "id",
            labelField: "title",
            searchField: ["title", "artist"],
            create: false,
            preload: true,

            // IMPORTANT: keep dropdown open; we’ll close it ourselves on real selection
            closeAfterSelect: false,

            placeholder: this.opts.placeholder || trans("Search for a sound…"),

            load: function (query, callback) {
                var url = query
                    ? (searchUrl + "?q=" + encodeURIComponent(query))
                    : trendingUrl;

                jQuery.ajax({
                    url: url,
                    method: "GET",
                    dataType: "json",
                    success: function (resp) { callback(self.normalize(resp)); },
                    error: function () { callback([]); }
                });
            },

            render: {
                option: function (item, escape) {
                    var title = escape(item.title || trans("Untitled"));
                    var artist = escape(item.artist || "");
                    var cover = item.cover ? escape(item.cover) : "";
                    var hasAudio = !!item.url;

                    return (
                        '<div class="sound-opt" data-sound-id="' + escape(item.id) + '">' +
                        '<div class="sound-opt-left">' +
                        (cover
                            ? '<img class="sound-opt-cover" src="' + cover + '" alt=""/>'
                            : '<div class="sound-opt-cover sound-opt-cover--ph"></div>') +
                        "</div>" +

                        '<div class="sound-opt-mid">' +
                        '<div class="sound-opt-title">' + title + "</div>" +
                        (artist ? '<div class="sound-opt-artist">' + artist + "</div>" : "") +
                        "</div>" +

                        '<div class="sound-opt-right">' +
                        (hasAudio
                            ? '<button type="button" class="sound-opt-play" ' +
                            'data-sound-id="' + escape(item.id) + '" ' +
                            'data-sound-url="' + escape(item.url) + '" ' +
                            'tabindex="-1" aria-label="'+trans('Play preview')+'">▶</button>'
                            : "") +
                        "</div>" +
                        "</div>"
                    );
                },

                item: function (item, escape) {
                    var title = escape(item.title || "Untitled");
                    var artist = escape(item.artist || "");
                    return "<div>" + title + (artist ? ' <span class="text-muted">— ' + artist + "</span>" : "") + "</div>";
                }
            },

            onItemAdd: function (value) {
                if (self._ignoreNextChange) {
                    self._ignoreNextChange = false;
                    return;
                }

                self.stopPreview();

                // close only on real selection
                if (value) this.close();
            },

            onChange: function (value) {
                if (self._ignoreNextChange) {
                    self._ignoreNextChange = false;
                    return;
                }

                self.stopPreview();

                if (!value) {
                    self.clearSelection();
                    return;
                }

                var it = this.options[value];
                if (!it) return;

                self.applySelection(it);
            },

            onDropdownClose: function () {
                // stop audio, but do NOT reset the play/pause button UI
                self.stopPreview(false);
            },

            onDropdownOpen: function () {
                // Selectize can rebuild dropdown/options; always rebind to the current dropdown
                self.bindDropdownPlayButtons(previewSeconds);
            },

            onOptionHover: enablePreviewHover
                ? function (value) {
                    var it = this.options[value];
                    if (it && it.url) self.playPreview(it.url, previewSeconds, it.id, null);
                }
                : null
        });

        this.selectize = $input[0].selectize;
        (function (s) {
            if (!s || s.__soundselectPatched) return;
            s.__soundselectPatched = true;

            var orig = s.onOptionSelect;
            s.onOptionSelect = function (e) {
                // If the click originated from the play button, do NOT select/close
                try {
                    var t = e && e.target;
                    if (t && t.closest && t.closest(".sound-opt-play")) {
                        if (e.cancelable) e.preventDefault();
                        e.stopPropagation();
                        return;
                    }
                    // eslint-disable-next-line no-empty
                } catch (err) {}
                return orig.call(this, e);
            };
        })(this.selectize);

        // bind play buttons inside dropdown (instance-safe)
        this.bindDropdownPlayButtons(previewSeconds);
    },


    bindDropdownPlayButtons: function (previewSeconds) {
        if (!this.selectize || !this.selectize.$dropdown) return;

        var self = this;
        var $dropdown = this.selectize.$dropdown;

        $dropdown.off(".soundselect");

        // Important: do NOT preventDefault on touchstart here (it can kill click on mobile)
        $dropdown.on("click.soundselect", ".sound-opt-play", function (e) {
            e.preventDefault();
            e.stopPropagation();

            var btn = this;
            var url = btn.getAttribute("data-sound-url");
            var sid = btn.getAttribute("data-sound-id");
            if (!url) return false;

            if (self.previewAudio && self.playingSoundId && String(self.playingSoundId) === String(sid)) {
                self.stopPreview(true);
                return false;
            }

            self._ignoreNextChange = true;
            self.toggleButtonState(btn, sid);
            self.playPreview(url, previewSeconds, sid, btn);

            // keep dropdown open
            // eslint-disable-next-line no-empty
            try { self.selectize.open(); } catch (err) {}
            return false;
        });
    },



    /* ------------------------------
     * DATA
     * ------------------------------ */

    normalize: function (resp) {
        var list = Array.isArray(resp) ? resp : (resp && Array.isArray(resp.sounds) ? resp.sounds : []);
        if (!Array.isArray(list)) list = [];

        return list.map(function (s) {
            return {
                id: String(s.id || s.sound_id || s.soundId || ""),
                title: s.title || s.name || "",
                artist: s.artist || s.author || "",
                url: s.url || s.preview_url || s.previewUrl || "",
                cover: s.cover || s.cover_url || s.coverUrl || ""
            };
        }).filter(function (x) {
            return x.id && x.title;
        });
    },

    /* ------------------------------
     * SELECTION
     * ------------------------------ */

    applySelection: function (it) {
        this.stopPreview();

        if (this.el.soundId) this.el.soundId.value = it.id || "";
        if (this.el.soundTitle) this.el.soundTitle.value = it.title || "";
        if (this.el.soundArtist) this.el.soundArtist.value = it.artist || "";
        if (this.el.soundUrl) this.el.soundUrl.value = it.url || "";
        if (this.el.soundStartMs) this.el.soundStartMs.value = "0";
    },

    clearSelection: function () {
        this.stopPreview();

        if (this.el.soundId) this.el.soundId.value = "";
        if (this.el.soundTitle) this.el.soundTitle.value = "";
        if (this.el.soundArtist) this.el.soundArtist.value = "";
        if (this.el.soundUrl) this.el.soundUrl.value = "";
        if (this.el.soundStartMs) this.el.soundStartMs.value = "0";
    },

    /* ------------------------------
     * PREVIEW UI
     * ------------------------------ */

    toggleButtonState: function (btnEl, soundId) {
        // reset old button UI
        if (this.playingBtn) {
            this.playingBtn.classList.remove("is-playing");
            this.playingBtn.textContent = "▶";
            this.playingBtn.setAttribute("aria-label", "Play preview");
        }

        this.playingBtn = btnEl || null;
        this.playingSoundId = soundId || null;

        if (this.playingBtn) {
            this.playingBtn.classList.add("is-playing");
            this.playingBtn.textContent = "❚❚";
            this.playingBtn.setAttribute("aria-label", trans("Pause preview"));
        }
    },

    playPreview: function (url, seconds, soundId, btnEl) {
        if (!url) return;

        // Global: only one preview at a time (across instances)
        window.__SoundSelectPreview = window.__SoundSelectPreview || { owner: null, audio: null };

        try {
            var g = window.__SoundSelectPreview;

            // stop previous instance (and reset its UI)
            if (g.owner && g.owner !== this && typeof g.owner.stopPreview === "function") {
                g.owner.stopPreview(true);
            }

            // if same instance is already the global owner, stop before starting again
            if (g.owner === this) {
                // stop audio/timer but do NOT reset UI,
                // because the click handler already set the NEW button to ❚❚
                this.stopPreview(false);
            }
            // eslint-disable-next-line no-empty
        } catch (e) {}

        // If same sound is playing, toggleButtonState already called stopPreview()
        if (this.previewAudio && this.playingSoundId && soundId && String(this.playingSoundId) === String(soundId)) {
            return;
        }

        // don't wipe button here; toggleButtonState manages it
        this.stopPreview(false);

        var a = new Audio();
        a.preload = "auto";
        a.src = url;
        a.currentTime = 0;

        // mark this as the global active preview
        window.__SoundSelectPreview.owner = this;
        window.__SoundSelectPreview.audio = a;

        var ms = Math.max(2000, (seconds || 8) * 1000);

        if (this.previewTimeout) {
            window.clearTimeout(this.previewTimeout);
            this.previewTimeout = null;
        }

        this.previewTimeout = window.setTimeout(() => {
            this.stopPreview(true);
        }, ms);

        a.addEventListener("ended", () => { this.stopPreview(true); });

        a.play().catch(() => {
            this.stopPreview(true);
        });

        this.previewAudio = a;
        this.playingSoundId = soundId || this.playingSoundId;
        this.playingBtn = btnEl || this.playingBtn;
    },

    // stopPreview(resetBtn = true)
    stopPreview: function (resetBtn) {
        if (resetBtn === undefined) resetBtn = true;

        if (this.previewTimeout) {
            window.clearTimeout(this.previewTimeout);
            this.previewTimeout = null;
        }

        if (this.previewAudio) {
            try {
                this.previewAudio.pause();
                this.previewAudio.src = "";
                // eslint-disable-next-line no-empty
            } catch (e) {}
            this.previewAudio = null;
        }

        // clear global preview pointer if THIS instance owned it
        try {
            var g = window.__SoundSelectPreview;
            if (g && g.owner === this) {
                g.owner = null;
                g.audio = null;
            }
            // eslint-disable-next-line no-empty
        } catch (e) {}

        if (resetBtn && this.playingBtn) {
            this.playingBtn.classList.remove("is-playing");
            this.playingBtn.textContent = "▶";
            this.playingBtn.setAttribute("aria-label", "Play preview");
            this.playingBtn = null;
            this.playingSoundId = null;
        } else if (resetBtn) {
            this.playingBtn = null;
            this.playingSoundId = null;
        }
    },

    /* ------------------------------
     * DESTROY
     * ------------------------------ */

    destroy: function () {
        this.stopPreview();

        if (this.selectize) {
            try {
                // unbind dropdown handlers first (safe)
                if (this.selectize.$dropdown) {
                    this.selectize.$dropdown.off(".soundselect");
                }
                this.selectize.destroy();
                // eslint-disable-next-line no-empty
            } catch (e) {}

            this.selectize = null;
        }

        this._dropdownHandlersBound = false;
    },

    /* ------------------------------
     * OPTIONAL: factory for multiple instances
     * ------------------------------ */

    create: function (opts) {
        // simple shallow clone of the module (so multiple selects can exist)
        var inst = Object.assign({}, this);

        // clone nested objects so instances don't share references
        inst.opts = Object.assign({}, this.opts || {}, opts || {});
        inst.el = Object.assign({}, this.el);
        inst.selectize = null;
        inst.previewAudio = null;
        inst.previewTimeout = null;
        inst.playingSoundId = null;
        inst.playingBtn = null;
        inst._ignoreNextChange = false;
        inst._dropdownHandlersBound = false;

        return inst;
    }
};

// Export
window.SoundSelect = SoundSelect;
