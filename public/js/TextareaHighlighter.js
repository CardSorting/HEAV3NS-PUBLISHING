"use strict";

var TextareaHighlighter = {
    // config defaults (can override per init call)
    defaults: {
        wrapSelector: ".hl-textarea-wrap",
        highlightsSelector: ".hl-highlights",
        backdropSelector: ".hl-backdrop",

        enableTags: false,
        enableMentions: false,

        tagChars: "a-zA-Z0-9_",
        mentionChars: "a-zA-Z0-9_-", // alpha_dash
        tagMaxLen: 64,
        mentionMaxLen: 255,
        startBoundary: "(^|[\\s(])"
    },

    // runtime
    instances: [],
    reTag: null,
    reMention: null,
    options: null,

    escapeHtml: function (str) {
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    },

    buildRegexes: function () {
        var o = TextareaHighlighter.options;

        TextareaHighlighter.reTag = null;
        TextareaHighlighter.reMention = null;

        if (o.enableTags) {
            TextareaHighlighter.reTag = new RegExp(
                o.startBoundary + "(#([" + o.tagChars + "]{1," + o.tagMaxLen + "}))(?![" + o.tagChars + "])",
                "g"
            );
        }

        if (o.enableMentions) {
            TextareaHighlighter.reMention = new RegExp(
                o.startBoundary + "(@([" + o.mentionChars + "]{1," + o.mentionMaxLen + "}))(?![" + o.mentionChars + "])",
                "g"
            );
        }
    },

    applyHighlights: function (raw) {
        var safe = TextareaHighlighter.escapeHtml(raw);

        // keep height stable on last line
        safe = safe.replace(/\n$/g, "\n\n");

        if (TextareaHighlighter.reTag) {
            safe = safe.replace(TextareaHighlighter.reTag, function (_, lead, full) {
                return lead + '<span class="hl-tag">' + full + "</span>";
            });
        }

        if (TextareaHighlighter.reMention) {
            safe = safe.replace(TextareaHighlighter.reMention, function (_, lead, full) {
                return lead + '<span class="hl-mention">' + full + "</span>";
            });
        }

        return safe;
    },

    syncStyles: function (taEl, hlEl) {
        var props = [
            "fontFamily", "fontSize", "fontWeight", "fontStyle",
            "letterSpacing", "lineHeight",
            "textTransform", "textIndent", "textDecoration",
            "paddingTop", "paddingRight", "paddingBottom", "paddingLeft",
            "borderTopWidth", "borderRightWidth", "borderBottomWidth", "borderLeftWidth",
            "boxSizing", "tabSize", "width"
        ];

        var cs = window.getComputedStyle(taEl);
        props.forEach(function (p) {
            hlEl.style[p] = cs[p];
        });
    },

    bindOne: function (taEl) {
        var o = TextareaHighlighter.options;

        var $ta = $(taEl);
        var $wrap = $ta.closest(o.wrapSelector);
        if (!$wrap.length) return;

        var hlEl = $wrap.find(o.highlightsSelector)[0];
        var backdropEl = $wrap.find(o.backdropSelector)[0];
        if (!hlEl || !backdropEl) return;

        function update() {
            hlEl.innerHTML = TextareaHighlighter.applyHighlights($ta.val());
        }

        function syncScroll() {
            backdropEl.scrollTop = taEl.scrollTop;
            backdropEl.scrollLeft = taEl.scrollLeft;
        }

        // initial
        TextareaHighlighter.syncStyles(taEl, hlEl);
        update();
        syncScroll();

        // events (namespaced so we can destroy cleanly)
        $ta.on("input.hl keyup.hl change.hl", update);
        $ta.on("scroll.hl", syncScroll);

        // resize observer
        var ro = null;
        if (window.ResizeObserver) {
            ro = new ResizeObserver(function () {
                TextareaHighlighter.syncStyles(taEl, hlEl);
                update();
                syncScroll();
            });
            ro.observe(taEl);
        } else {
            $(window).on("resize.hl", function () {
                TextareaHighlighter.syncStyles(taEl, hlEl);
                update();
                syncScroll();
            });
        }

        TextareaHighlighter.instances.push({
            taEl: taEl,
            hlEl: hlEl,
            backdropEl: backdropEl,
            ro: ro
        });
    },

    init: function (opts) {
        if (typeof opts === "string") {
            opts = { selector: opts };
        }
        opts = opts || {};
        if (!opts.selector) return;

        // base options
        TextareaHighlighter.options = $.extend({}, TextareaHighlighter.defaults, opts);

        // read global app settings if present (and opts didn’t explicitly override)
        if (typeof window.app !== "undefined") {
            if (typeof opts.enableTags === "undefined" && typeof window.app.enable_hashtags !== "undefined") {
                TextareaHighlighter.options.enableTags = !!window.app.enable_hashtags;
            }
            if (typeof opts.enableMentions === "undefined" && typeof window.app.enable_mentions !== "undefined") {
                TextareaHighlighter.options.enableMentions = !!window.app.enable_mentions;
            }
        }

        // now build regexes using final flags
        TextareaHighlighter.buildRegexes();

        // bind + render
        $(opts.selector).each(function () {
            TextareaHighlighter.bindOne(this);
        });
    },


    destroy: function () {
        // unbind all events + disconnect observers
        TextareaHighlighter.instances.forEach(function (inst) {
            $(inst.taEl).off(".hl");
            if (inst.ro) inst.ro.disconnect();
        });
        TextareaHighlighter.instances = [];
        $(window).off(".hl");
    },

    clear: function (target) {
        var o = TextareaHighlighter.options || TextareaHighlighter.defaults;

        var $targets;
        if (!target) return;

        if (target instanceof $) {
            $targets = target;
        } else if (target.nodeType === 1) {
            $targets = $(target);
        } else {
            $targets = $(target);
        }

        $targets.each(function () {
            var $ta = $(this);
            var $wrap = $ta.closest(o.wrapSelector);
            if (!$wrap.length) return;

            var $hl = $wrap.find(o.highlightsSelector);
            if ($hl.length) $hl.html("");
        });
    },

};
