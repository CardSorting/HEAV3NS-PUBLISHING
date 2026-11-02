"use strict";

window.AdminNavigation = window.AdminNavigation || {
    initialized: false,

    init: function (script) {
        if (this.initialized || !script || !script.dataset.appUrl) return;

        this.appUrl = new URL(script.dataset.appUrl, document.baseURI);
        this.basePath = this.appUrl.pathname.replace(/\/+$/, "");
        this.initialized = true;
        document.addEventListener("click", this.handleClick.bind(this), true);
    },

    handleClick: function (event) {
        if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;

        var link = event.target.closest ? event.target.closest("a[href]") : null;
        if (!link || (link.getAttribute("target") || "").toLowerCase() !== "_blank" || link.hasAttribute("download")) return;

        var destination;
        try {
            destination = new URL(link.getAttribute("href"), document.baseURI);
        } catch (error) {
            return;
        }
        if (!/^https?:$/.test(destination.protocol) || destination.origin !== this.appUrl.origin) return;
        if (destination.pathname !== this.basePath && !destination.pathname.startsWith(this.basePath + "/")) return;

        // Match impersonation: avoid a new browsing context that an installed PWA can capture.
        link.setAttribute("target", "_self");
    }
};

window.AdminNavigation.init(document.currentScript);
