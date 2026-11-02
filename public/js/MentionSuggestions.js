/**
 * MentionSuggestions (global helper)
 *
 * Requires a global list:
 *   window.mentionContacts = [{id, username, name, avatar}, ...]
 * or an object keyed by id:
 *   window.mentionContacts = { "12": {id:12, username:"...", ...}, ... }
 *
 * Usage:
 *   MentionSuggestions.init({
 *     target: '#dropzone-uploader',
 *     source: window.mentionContacts
 *   });
 */
"use strict";

// eslint-disable-next-line no-unused-vars
var MentionSuggestions = {
    // config
    targetSelector: null,
    source: null, // array or object
    minChars: 1,
    limit: 8,
    debounceMs: 120,
    mentionChars: "a-zA-Z0-9_-", // alpha_dash
    offsetY: -15, // px gap between caret and menu (tight!)
    offsetX: 0, // px horizontal tweak
    minLeft: 8, // viewport clamp
    zIndex: 2000,

    // runtime
    _bound: false,
    _timer: null,
    _all: [],

    // ui state
    $ta: null,
    $menu: null,
    open: false,
    items: [],
    activeIndex: 0,

    // token state
    tokenStart: null,
    tokenEnd: null,
    tokenQuery: "",

    init: function (opts) {
        opts = opts || {};
        this.targetSelector = opts.target || this.targetSelector;
        this.source = opts.source || this.source || (window.mentionContacts || null);

        if (typeof opts.minChars === "number") this.minChars = opts.minChars;
        if (typeof opts.limit === "number") this.limit = opts.limit;
        if (typeof opts.debounceMs === "number") this.debounceMs = opts.debounceMs;
        if (typeof opts.offsetY === "number") this.offsetY = opts.offsetY;
        if (typeof opts.offsetX === "number") this.offsetX = opts.offsetX;

        if (!this.targetSelector) return;

        this._all = this.normalize(this.source);
        this.bind();
    },

    bind: function () {
        if (this._bound) return;
        this._bound = true;

        var self = this;

        // one menu globally
        self.$menu = $('<div class="mention-suggest-menu dropdown-menu p-0"></div>')
            .attr("id", "mention-suggest-menu")
            .appendTo("body")
            .hide();

        // text updates
        $(document).on("input.ms keyup.ms click.ms", self.targetSelector, function (e) {
            self.$ta = $(this);
            self.onTextEvent(e);
        });

        // keyboard navigation
        $(document).on("keydown.ms", self.targetSelector, function (e) {
            return self.onKeydown(e);
        });

        // click pick
        $(document).on("mousedown.ms", ".mention-suggest-item", function (e) {
            e.preventDefault();
            var idx = parseInt($(this).attr("data-index"), 10);
            if (!isNaN(idx)) self.pick(idx);
        });

        // hover highlight (twitter-like)
        $(document).on("mouseenter.ms", ".mention-suggest-item", function () {
            var idx = parseInt($(this).attr("data-index"), 10);
            if (!isNaN(idx)) self.setActive(idx);
        });

        // outside click closes
        $(document).on("mousedown.ms", function (e) {
            if (!self.open) return;
            var $t = $(e.target);
            if ($t.closest(".mention-suggest-menu").length) return;
            if ($t.closest(self.targetSelector).length) return;
            self.close();
        });

        // reposition on scroll/resize
        $(window).on("resize.ms scroll.ms", function () {
            if (self.open) self.positionMenu();
        });
    },

    onTextEvent: function (e) {
        if (!this.$ta || !this.$ta.length) return;

        // don't refilter on keyboard navigation keys (prevents resetting activeIndex)
        if (e && e.type === "keyup") {
            var k = e.key;
            var navKeys = [
                "ArrowDown", "ArrowUp", "Enter", "Escape", "Tab",
                "Home", "End", "PageUp", "PageDown"
            ];
            if (navKeys.indexOf(k) !== -1) return;
        }

        var info = this.getActiveMentionToken(this.$ta[0]);
        if (!info) {
            this.close();
            return;
        }

        this.tokenStart = info.start;
        this.tokenEnd = info.end;
        this.tokenQuery = info.query;

        if (this.tokenQuery.length < this.minChars) {
            this.close();
            return;
        }

        this.scheduleFilter(this.tokenQuery);
    },

    onKeydown: function (e) {
        if (!this.open) return true;

        var key = e.key;
        var self = this;

        function jump(delta) {
            e.preventDefault();
            self.setActive(self.activeIndex + delta);
            return false;
        }

        if (key === "ArrowDown") return jump(1);
        if (key === "ArrowUp") return jump(-1);
        if (key === "PageDown") return jump(5);
        if (key === "PageUp") return jump(-5);

        if (key === "Home") {
            e.preventDefault();
            self.setActive(0);
            return false;
        }

        if (key === "End") {
            e.preventDefault();
            self.setActive(self.items.length - 1);
            return false;
        }

        if (key === "Enter") {
            if (self.items.length) {
                e.preventDefault();
                self.pick(self.activeIndex);
                return false;
            }
        }

        // Tab picks active (tight twitter-like)
        if (key === "Tab") {
            if (self.items.length) {
                e.preventDefault();
                self.pick(self.activeIndex);
                return false;
            }
        }

        if (key === "Escape") {
            e.preventDefault();
            self.close();
            return false;
        }

        return true;
    },

    scheduleFilter: function (q) {
        var self = this;
        if (self._timer) clearTimeout(self._timer);
        self._timer = setTimeout(function () {
            self.filter(q);
        }, self.debounceMs);
    },

    filter: function (q) {
        var self = this;
        var needle = (q || "").toLowerCase();

        // rank: startsWith username > startsWith name > contains username > contains name
        var startsU = [];
        var startsN = [];
        var contU = [];
        var contN = [];

        for (var i = 0; i < self._all.length; i++) {
            var u = self._all[i];
            var un = u.usernameLower;
            var nm = u.nameLower;

            if (un && un.indexOf(needle) === 0) startsU.push(u);
            else if (nm && nm.indexOf(needle) === 0) startsN.push(u);
            else if (un && un.indexOf(needle) !== -1) contU.push(u);
            else if (nm && nm.indexOf(needle) !== -1) contN.push(u);
        }

        self.items = startsU.concat(startsN, contU, contN).slice(0, self.limit);

        if (!self.items.length) {
            self.close();
            return;
        }

        self.activeIndex = 0;
        self.render();
        self.openMenu();
    },

    normalize: function (src) {
        var arr = [];
        if (!src) return arr;

        // object keyed by id -> array
        if (!Array.isArray(src) && typeof src === "object") {
            Object.keys(src).forEach(function (k) {
                arr.push(src[k]);
            });
        } else {
            arr = src.slice(0);
        }

        return arr.map(function (u) {
            var username = (u.username || "").toString().trim();
            var name = (u.name || "").toString().trim();

            return {
                id: u.id,
                username: username,
                name: name,
                avatar: u.avatar || "",
                usernameLower: username.toLowerCase(),
                nameLower: name.toLowerCase()
            };
        }).filter(function (u) {
            return u.username && u.username.length;
        });
    },

    render: function () {
        var self = this;
        var html = "";

        self.$menu.attr({
            role: "listbox",
            "aria-label": "Mention suggestions"
        });

        for (var i = 0; i < self.items.length; i++) {
            var u = self.items[i];
            var active = (i === self.activeIndex);
            var activeClass = active ? " active" : "";
            var avatar = u.avatar ? ('<img class="mention-avatar" src="' + self.escapeAttr(u.avatar) + '" alt="">') : "";
            var optId = "mention-opt-" + i;

            html +=
                '<button type="button" ' +
                'id="' + optId + '" ' +
                'role="option" ' +
                'aria-selected="' + (active ? "true" : "false") + '" ' +
                'class="mention-suggest-item dropdown-item d-flex align-items-center' + activeClass + '" ' +
                'data-index="' + i + '">' +
                avatar +
                '<div class="d-flex flex-column ms-2">' +
                '<div class="mention-username">@' + self.escapeHtml(u.username) + '</div>' +
                (u.name ? '<div class="mention-name small text-muted">' + self.escapeHtml(u.name) + '</div>' : "") +
                '</div>' +
                '</button>';
        }

        self.$menu.html(html);

        if (self.$ta && self.$ta.length) {
            self.$ta.attr({
                "aria-autocomplete": "list",
                "aria-controls": "mention-suggest-menu",
                "aria-activedescendant": "mention-opt-" + self.activeIndex
            });
        }

        // align after height changes
        self.positionMenu();
    },

    openMenu: function () {
        this.open = true;
        this.positionMenu();
        this.$menu.show();
    },

    close: function () {
        this.open = false;
        this.items = [];
        this.activeIndex = 0;
        if (this.$menu) this.$menu.hide().empty();
        if (this.$ta && this.$ta.length) {
            this.$ta.removeAttr("aria-activedescendant");
        }
    },

    positionMenu: function () {
        if (!this.$ta || !this.$ta.length || !this.$menu) return;

        var ta = this.$ta[0];

        // Anchor to caret (current cursor position)
        var caretPos = ta.selectionStart;
        if (caretPos === null) caretPos = (this.tokenEnd !== null ? this.tokenEnd : 0);

        var caret = getCaretCoordinates(ta, caretPos);

        // sizing
        var menuWidth = Math.min(360, Math.max(260, this.$ta.outerWidth() * 0.75));
        this.$menu.css({ width: menuWidth });

        var menuH = this.$menu.outerHeight() || 240;
        var viewportH = window.innerHeight;
        var viewportW = window.innerWidth;

        // VERY TIGHT offset near caret
        var gapY = (typeof this.offsetY === "number") ? this.offsetY : 1;
        var gapX = (typeof this.offsetX === "number") ? this.offsetX : 0;

        var top = caret.top + caret.height + gapY;
        var placeAbove = (top + menuH > viewportH - 8);
        if (placeAbove) top = caret.top - menuH - gapY;

        var left = caret.left + gapX;

        // clamp horizontally
        var minLeft = (typeof this.minLeft === "number") ? this.minLeft : 8;
        left = Math.max(minLeft, Math.min(left, viewportW - menuWidth - minLeft));

        this.$menu.css({
            position: "fixed",
            top: Math.round(top),
            left: Math.round(left),
            zIndex: this.zIndex
        });
    },

    setActive: function (idx) {
        if (!this.items.length) return;

        if (idx < 0) idx = this.items.length - 1;
        if (idx >= this.items.length) idx = 0;

        this.activeIndex = idx;

        var $items = this.$menu.find(".mention-suggest-item");
        $items.removeClass("active").attr("aria-selected", "false");

        var $active = $items.eq(idx);
        $active.addClass("active").attr("aria-selected", "true");

        if (this.$ta && this.$ta.length) {
            this.$ta.attr("aria-activedescendant", "mention-opt-" + idx);
        }

        var el = $active.get(0);
        if (el && typeof el.scrollIntoView === "function") {
            el.scrollIntoView({ block: "nearest" });
        }
    },

    pick: function (idx) {
        if (!this.$ta || !this.$ta.length) return;
        if (!this.items.length) return;

        var u = this.items[idx];
        if (!u) return;

        var ta = this.$ta[0];
        var text = this.$ta.val();

        var before = text.slice(0, this.tokenStart);
        var after = text.slice(this.tokenEnd);

        var insert = "@" + u.username + " ";
        var next = before + insert + after;

        this.$ta.val(next);

        var newPos = before.length + insert.length;
        ta.setSelectionRange(newPos, newPos);

        this.$ta.trigger("input");
        this.close();
    },

    getActiveMentionToken: function (taEl) {
        var text = taEl.value;
        var pos = taEl.selectionStart;
        if (pos === null) return null;

        // find '@' backwards, stop on whitespace
        var i = pos - 1;
        while (i >= 0) {
            var ch = text.charAt(i);
            if (ch === "@") break;
            if (/\s/.test(ch)) return null;
            i--;
        }
        if (i < 0) return null;

        // boundary before '@'
        var beforeAt = (i === 0) ? "" : text.charAt(i - 1);
        if (beforeAt && !/[\s(]/.test(beforeAt)) return null;

        var query = text.slice(i + 1, pos);

        if (!query.length) return { start: i, end: pos, query: "" };

        var allowed = new RegExp("^[" + this.mentionChars + "]+$");
        if (!allowed.test(query)) return null;

        return { start: i, end: pos, query: query };
    },

    escapeHtml: function (str) {
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    },

    escapeAttr: function (str) {
        return this.escapeHtml(str);
    }
};

/**
 * Get caret coordinates relative to the viewport for a textarea.
 * Returns { top, left, height } in viewport coordinates.
 */
function getCaretCoordinates(textarea, position) {
    var style = window.getComputedStyle(textarea);
    var div = document.createElement("div");

    var props = [
        "boxSizing", "width", "height",
        "overflowX", "overflowY",
        "borderTopWidth", "borderRightWidth", "borderBottomWidth", "borderLeftWidth",
        "paddingTop", "paddingRight", "paddingBottom", "paddingLeft",
        "fontStyle", "fontVariant", "fontWeight", "fontStretch",
        "fontSize", "fontSizeAdjust", "lineHeight", "fontFamily",
        "letterSpacing", "textTransform", "textAlign", "textIndent",
        "whiteSpace", "wordWrap", "wordBreak",
        "tabSize", "MozTabSize"
    ];

    div.style.position = "absolute";
    div.style.visibility = "hidden";
    div.style.whiteSpace = "pre-wrap";
    div.style.wordWrap = "break-word";
    div.style.top = "0";
    div.style.left = "-9999px";

    for (var i = 0; i < props.length; i++) {
        div.style[props[i]] = style[props[i]];
    }

    var value = textarea.value;
    var text = value.substring(0, position);

    div.scrollTop = textarea.scrollTop;
    div.scrollLeft = textarea.scrollLeft;

    div.textContent = text;

    var span = document.createElement("span");
    span.textContent = value.substring(position) || ".";
    div.appendChild(span);

    document.body.appendChild(div);

    var spanRect = span.getBoundingClientRect();
    var divRect = div.getBoundingClientRect();
    document.body.removeChild(div);

    var taRect = textarea.getBoundingClientRect();

    var left = taRect.left + (spanRect.left - divRect.left) - textarea.scrollLeft;
    var top = taRect.top + (spanRect.top - divRect.top) - textarea.scrollTop;
    var height = spanRect.height || parseFloat(style.lineHeight) || 16;

    return { left: left, top: top, height: height };
}
