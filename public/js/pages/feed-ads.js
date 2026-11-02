/* Home feed ads. Provider code is trusted administrator HTML. */
"use strict";

$(function () { FeedAds.init(); });

var FeedAds = {
    container: null,
    template: null,
    code: '',
    frequency: 6,
    offset: 0,
    perPage: 0,
    mobileOnly: true,
    viewport: null,
    pending: false,
    slots: new Map(),
    scripts: new Map(),

    init: function () {
        if (this.container) return;
        const template = document.getElementById('feed-ad-template');
        const code = document.getElementById('feed-ad-code');
        const container = document.querySelector('.posts-wrapper');
        if (!template || !code || !container) return;
        this.code = JSON.parse(code.textContent);
        if (!this.code.trim()) return;
        this.container = container;
        this.template = template;
        this.frequency = Math.max(1, Number(template.dataset.frequency) || 6);
        this.offset = Math.max(0, Number(template.dataset.postOffset) || 0);
        this.perPage = Math.max(0, Number(template.dataset.perPage) || 0);
        this.mobileOnly = template.dataset.visibility !== 'all';
        this.viewport = window.matchMedia('(max-width: 767.98px)');

        // Page-level SDKs own their readiness/queues. Never reload them per slot.
        document.querySelectorAll('script[src]').forEach(script => {
            if (this.isExecutable(script)) this.scripts.set(script.src, Promise.resolve());
        });
        $(document).on('posts:appended.feedAds', (event, posts, direction, page) => {
            if (direction === 'prev') {
                this.offset = Number.isInteger(page) && page > 0 && this.perPage
                    ? (page - 1) * this.perPage : Math.max(0, this.offset - posts.length);
            }
            this.schedule();
        });
        $(document).on('posts:removed.feedAds', () => this.schedule());
        this.viewport.addEventListener('change', () => this.schedule());
        // repo-standards-ignore-next-line RS-JS-002: CookieConsent event name, not displayed text.
        window.addEventListener('cc:onConsent', () => this.schedule());
        // repo-standards-ignore-next-line RS-JS-002: CookieConsent event name, not displayed text.
        window.addEventListener('cc:onChange', () => this.schedule());
        this.schedule();
    },

    schedule: function () {
        if (this.pending) return;
        this.pending = true;
        window.requestAnimationFrame(() => {
            this.pending = false;
            this.place();
        });
    },

    place: function () {
        const visible = !this.mobileOnly || this.viewport.matches;
        if (!visible) {
            this.slots.forEach(slot => { slot.hidden = true; });
            return;
        }
        const posts = Array.from(this.container.children).filter(node => node.matches('.post-box[data-postID]'));
        const due = new Map();
        posts.forEach((post, index) => {
            const position = this.offset + index + 1;
            if (position % this.frequency === 0) due.set(position, post);
        });
        this.slots.forEach((slot, position) => {
            if (!due.has(position) || slot.parentElement !== this.container) {
                slot.remove();
                this.slots.delete(position);
            }
        });
        due.forEach((post, position) => {
            let slot = this.slots.get(position);
            if (!slot) {
                slot = this.template.content.firstElementChild.cloneNode(true);
                slot.id = 'feed-ad-' + position;
                slot.dataset.feedAdSlot = String(position);
                this.slots.set(position, slot);
            }
            const separator = post.nextElementSibling;
            const anchor = separator && separator.tagName === 'HR' ? separator : post;
            if (anchor.nextElementSibling !== slot) anchor.after(slot);
            slot.hidden = !this.hasConsent(slot.feedAdConsentScripts || []);
            if (!slot.hidden) this.initialize(slot);
        });
    },

    isExecutable: function (script) {
        return ['', 'module', 'text/javascript', 'application/javascript', 'text/ecmascript', 'application/ecmascript']
            .includes((script.getAttribute('type') || '').toLowerCase());
    },

    hasConsent: function (scripts) {
        return scripts.every(script => {
            const consent = window.CookieConsent;
            if (!consent || typeof consent.acceptedCategory !== 'function' || !consent.acceptedCategory(script.dataset.category)) return false;
            return !script.dataset.service || (typeof consent.acceptedService === 'function' && consent.acceptedService(script.dataset.service, script.dataset.category));
        });
    },

    initialize: async function (slot) {
        if (['initializing', 'ready', 'error'].includes(slot.dataset.adState)) return;
        if (!slot.feedAdSteps) {
            const fragment = document.createElement('template');
            fragment.innerHTML = this.code;
            // Match the existing consent-gated custom-code convention, including
            // newly inserted slots after consent has already been granted.
            slot.feedAdConsentScripts = Array.from(fragment.content.querySelectorAll('script[type="text/plain"][data-category]'));
            if (!this.hasConsent(slot.feedAdConsentScripts)) {
                slot.hidden = true;
                return;
            }
            slot.feedAdSteps = Array.from(fragment.content.querySelectorAll('script')).map(original => {
                const marker = document.createComment('feed ad script');
                original.replaceWith(marker);
                return {original, marker};
            });
            slot.feedAdIndex = 0;
            // Native insertion with script placeholders avoids jQuery evaluating
            // scripts automatically and bypassing the provider-loader registry.
            slot.querySelector('.feed-ad-content').appendChild(fragment.content);
        }
        slot.dataset.adState = 'initializing';
        try {
            while (slot.feedAdIndex < slot.feedAdSteps.length) {
                if (!slot.isConnected || (this.mobileOnly && !this.viewport.matches) || !this.hasConsent(slot.feedAdConsentScripts)) {
                    slot.dataset.adState = 'pending';
                    return;
                }
                await this.runScript(slot.feedAdSteps[slot.feedAdIndex]);
                slot.feedAdIndex++;
            }
            slot.dataset.adState = 'ready';
            slot.dispatchEvent(new CustomEvent('feed-ad:ready', {bubbles: true}));
        } catch {
            // A blocked/broken ad provider must not interrupt feed pagination.
            slot.dataset.adState = 'error';
        }
    },

    runScript: function ({original, marker}) {
        const script = document.createElement('script');
        Array.from(original.attributes).forEach(attribute => script.setAttribute(attribute.name, attribute.value));
        script.textContent = original.textContent;
        if (script.type === 'text/plain' && script.dataset.category) {
            script.type = script.dataset.type || 'text/javascript';
            script.removeAttribute('data-category');
            script.removeAttribute('data-service');
        }
        if (!this.isExecutable(script)) {
            marker.replaceWith(script);
            return Promise.resolve();
        }
        const shared = script.hasAttribute('src') && !script.hasAttribute('data-feed-ad-per-slot');
        const key = script.src;
        if (shared && this.scripts.has(key)) {
            marker.remove();
            return this.scripts.get(key);
        }
        const loaded = new Promise((resolve, reject) => {
            if (script.hasAttribute('src') || script.type === 'module') {
                script.onload = resolve;
                script.onerror = reject;
            } else {
                resolve();
            }
        });
        if (shared) this.scripts.set(key, loaded);
        marker.replaceWith(script);
        return loaded;
    },
};
