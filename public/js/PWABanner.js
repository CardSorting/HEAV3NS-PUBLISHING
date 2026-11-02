"use strict";

window.PWABanner = {
    deferredPrompt: null,
    dismissedStorageKey: 'pwa_install_prompt_dismissed',
    dismissedDays: 7,

    init: function () {
        if (this.isStandalone()) {
            return;
        }

        if (this.wasDismissed()) {
            return;
        }

        this.bindBrowserPrompt();
        this.bindUi();
    },

    bindBrowserPrompt: function () {
        var self = this;

        window.addEventListener('beforeinstallprompt', function (e) {
            e.preventDefault();
            self.deferredPrompt = e;
            self.show();
        });

        window.addEventListener('appinstalled', function () {
            self.hide();
            self.deferredPrompt = null;
            self.clearDismissed();
        });
    },

    bindUi: function () {
        var self = this;

        $(document).on('click', '#pwa-install-button', function () {
            self.install();
        });

        $(document).on('click', '#pwa-install-dismiss', function () {
            self.dismiss();
        });
    },

    install: async function () {
        if (!this.deferredPrompt) {
            return;
        }

        this.deferredPrompt.prompt();

        var result = await this.deferredPrompt.userChoice;

        if (result && result.outcome === 'accepted') {
            this.hide();
        }

        this.deferredPrompt = null;
    },

    dismiss: function () {
        this.setDismissed();
        this.hide();
    },

    show: function () {
        $('#pwa-install-prompt').removeClass('d-none');
    },

    hide: function () {
        $('#pwa-install-prompt').addClass('d-none');
    },

    isStandalone: function () {
        return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    },

    wasDismissed: function () {
        var raw = localStorage.getItem(this.dismissedStorageKey);

        if (!raw) {
            return false;
        }

        var timestamp = parseInt(raw, 10);

        if (!timestamp) {
            return false;
        }

        var maxAge = this.dismissedDays * 24 * 60 * 60 * 1000;

        return (Date.now() - timestamp) < maxAge;
    },

    setDismissed: function () {
        localStorage.setItem(this.dismissedStorageKey, String(Date.now()));
    },

    clearDismissed: function () {
        localStorage.removeItem(this.dismissedStorageKey);
    }
};
