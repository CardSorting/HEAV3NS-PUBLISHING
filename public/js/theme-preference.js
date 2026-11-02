/** Resolve the visitor's preference before styles paint. */
(function () {
    'use strict';

    var config = window.appThemeConfig || {};
    var root = document.documentElement;
    var allowedThemes = ['light', 'dark'];
    var allowedPreferences = ['light', 'dark', 'auto'];
    var preference = allowedPreferences.indexOf(config.preference) >= 0 ? config.preference : 'light';
    var systemTheme = typeof window.matchMedia === 'function' ? window.matchMedia('(prefers-color-scheme: dark)') : null;
    var theme;

    function resolveTheme() {
        return preference === 'auto' ? (systemTheme && systemTheme.matches ? 'dark' : 'light') : preference;
    }

    function persistTheme(value) {
        var secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = 'app_theme=' + value + '; Path=/; Max-Age=31536000; SameSite=Lax' + secure;
    }

    function targetPreference() {
        return allowedPreferences[(allowedPreferences.indexOf(preference) + 1) % allowedPreferences.length];
    }

    function updateLegacyThemeClasses() {
        [
            ['.side-menu', 'side-menu-theme-light', 'side-menu-theme-dark'],
            ['.messenger-shell', 'messenger-light', 'messenger-dark'],
            ['.settings-data-page', 'theme-light', 'theme-dark'],
            ['.settings-payments-page', 'theme-light', 'theme-dark'],
            ['.checkout-dialog', 'checkout-theme-light', 'checkout-theme-dark'],
            ['.deposit-nice-shell', 'deposit-theme-light', 'deposit-theme-dark'],
            ['.profile-compact-posts-wrapper', 'profile-compact-posts-theme-light', 'profile-compact-posts-theme-dark'],
            ['.feed-reels-widget', 'bg-white', 'is-theme-dark'],
            ['.feed-reels-widget', 'text-dark-r', 'text-white']
        ].forEach(function (contract) {
            document.querySelectorAll(contract[0]).forEach(function (element) {
                element.classList.toggle(contract[1], theme === 'light');
                element.classList.toggle(contract[2], theme === 'dark');
            });
        });
    }

    function updateElements() {
        document.querySelectorAll('[data-theme-light-src][data-theme-dark-src]').forEach(function (element) {
            var source = element.getAttribute('data-theme-' + theme + '-src');
            var attribute = element.tagName === 'A' ? 'href' : 'src';
            if (source && element.getAttribute(attribute) !== source) {
                element.setAttribute(attribute, source);
            }
        });

        document.querySelectorAll('[data-theme-light-class], [data-theme-dark-class]').forEach(function (element) {
            allowedThemes.forEach(function (mode) {
                (element.getAttribute('data-theme-' + mode + '-class') || '')
                    .split(/\s+/)
                    .filter(Boolean)
                    .forEach(function (className) {
                        element.classList.toggle(className, mode === theme);
                    });
            });
        });

        document.querySelectorAll('.dark-mode-switcher').forEach(function (switcher) {
            var nextTheme = targetPreference();
            var labels = config.labels || {};
            var toggleLabels = config.toggleLabels || labels;
            switcher.setAttribute('data-current-theme', theme);
            switcher.setAttribute('data-current-preference', preference);
            switcher.setAttribute('aria-label', toggleLabels[nextTheme] || labels[nextTheme] || nextTheme);

            switcher.querySelectorAll('[data-theme-toggle-label]').forEach(function (label) {
                label.textContent = label.getAttribute('data-theme-' + nextTheme + '-label')
                    || labels[nextTheme]
                    || nextTheme;
            });
            switcher.querySelectorAll('.side-menu-create-option-label').forEach(function (label) {
                label.textContent = toggleLabels[nextTheme] || labels[nextTheme] || nextTheme;
            });
            switcher.querySelectorAll('ion-icon').forEach(function (icon) {
                icon.setAttribute('name', theme === 'dark' ? 'contrast-outline' : 'contrast');
            });
        });

        // Bridge the few Fans components whose theme class predates data-bs-theme.
        updateLegacyThemeClasses();
    }

    function refresh() {
        theme = resolveTheme();
        root.setAttribute('data-bs-theme', theme);
        // Temporary compatibility mirror for inventoried legacy selectors and integrations.
        root.setAttribute('data-theme', theme);
        root.style.colorScheme = theme;
        root.classList.toggle('cc--darkmode', theme === 'dark');

        var colorScheme = document.querySelector('meta[name="color-scheme"]');
        if (colorScheme) {
            colorScheme.setAttribute('content', theme);
        }

        if (window.app) {
            window.app.theme = theme;
        }

        updateElements();
        document.dispatchEvent(new CustomEvent('app:theme-changed', {detail: {theme: theme}}));

        return theme;
    }

    function setPreference(value) {
        if (!config.allowSwitch || allowedPreferences.indexOf(value) < 0) {
            return false;
        }

        preference = value;
        persistTheme(preference);
        refresh();

        return true;
    }

    window.AppTheme = {
        refresh: refresh,
        setPreference: setPreference,
        toggle: function () {
            return setPreference(targetPreference());
        },
        current: function () {
            return theme;
        },
        preference: function () {
            return preference;
        }
    };

    function updateSystemTheme() {
        if (preference === 'auto') {
            refresh();
        }
    }

    if (systemTheme) {
        if (typeof systemTheme.addEventListener === 'function') {
            systemTheme.addEventListener('change', updateSystemTheme);
        } else if (typeof systemTheme.addListener === 'function') {
            systemTheme.addListener(updateSystemTheme);
        }
    }

    refresh();
    document.addEventListener('DOMContentLoaded', refresh);
})();
