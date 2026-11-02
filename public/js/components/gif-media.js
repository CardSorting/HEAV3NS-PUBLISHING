/* global trans */
'use strict';

var GifMedia = {
    configs: {},
    pending: new Map(),
    resolved: new WeakMap(),
    observer: null,
    visibility: null,
    scheduled: false,

    text(message) {
        return typeof trans === 'function' ? trans(message) : (Object.values(this.configs)[0]?.labels?.[message] || message);
    },

    reference(value) {
        try {
            const ref = typeof value === 'string' ? JSON.parse(value) : value;
            if (!ref || Object.keys(ref).length !== 2 || !['klipy', 'giphy'].includes(ref.provider)
                || typeof ref.id !== 'string' || !/^[a-zA-Z0-9_-]{1,200}$/.test(ref.id)) return null;
            return {provider: ref.provider, id: ref.id};
        } catch (error) { return null; }
    },

    configure(root) {
        const nodes = [...root.querySelectorAll('[data-gif-config]')];
        if (root.matches && root.matches('[data-gif-config]')) nodes.unshift(root);
        for (const node of nodes) {
            try {
                const config = JSON.parse(node.dataset.gifConfig);
                if (['messenger', 'post_comments', 'reel_comments'].includes(config.surface)) this.configs[config.surface] = config;
            } catch (error) { /* Ignore malformed component configuration. */ }
        }
    },

    config(surface) { return this.configs[surface] || {surface, enabled: false, provider: 'klipy', keys: {}}; },

    safeUrl(value, provider, media = true) {
        try {
            const url = new URL(value);
            const hosts = provider === 'klipy'
                ? (media ? ['static.klipy.com', 'static1.klipy.com', 'static2.klipy.com', 'static.klipy.co'] : ['klipy.com'])
                : (media ? ['media.giphy.com', 'media0.giphy.com', 'media1.giphy.com', 'media2.giphy.com', 'media3.giphy.com', 'media4.giphy.com', 'i.giphy.com'] : ['giphy.com', 'www.giphy.com']);
            if (url.protocol !== 'https:' || url.username || url.password || url.port || !hosts.includes(url.hostname)) return '';
            if (media && !/\.(gif|webp|jpg|jpeg|png|mp4)(?:$)/i.test(url.pathname)) return '';
            return url.href;
        } catch (error) { return ''; }
    },

    normalizeItem(item, provider) {
        if (!item || (item.type && item.type !== 'gif')) return null;
        const id = provider === 'klipy' ? item.slug : item.id;
        const ref = this.reference({provider, id});
        if (!ref) return null;
        const formats = provider === 'klipy' ? item.file || {} : item.images || {};
        const animation = provider === 'klipy'
            ? formats.sm?.mp4 || formats.md?.mp4 || formats.sm?.webp || formats.sm?.gif || formats.md?.gif || formats.hd?.gif
            : formats.fixed_width || formats.downsized || formats.original;
        const still = provider === 'klipy'
            ? formats.sm?.jpg || formats.md?.jpg || formats.xs?.jpg
            : formats.fixed_width_still || formats.downsized_still || formats.original_still;
        const preview = provider === 'klipy'
            ? formats.xs?.gif || formats.xs?.webp || formats.sm?.gif
            : formats.fixed_width_small || formats.fixed_height_small || animation;
        const url = this.safeUrl(animation?.mp4 || animation?.url, provider);
        if (!url) return null;
        return {
            reference: ref, url,
            still: this.safeUrl(still?.url, provider),
            preview: this.safeUrl(preview?.url, provider) || this.safeUrl(still?.url, provider) || url,
            width: Math.min(2000, Math.max(1, Number(animation.width) || 240)),
            height: Math.min(2000, Math.max(1, Number(animation.height) || 180)),
            title: String(item.title || 'GIF').slice(0, 250),
            source: this.safeUrl(item.url || item.itemurl || (provider === 'klipy' ? 'https://klipy.com/gifs/' + encodeURIComponent(id) : ''), provider, false),
            creator: String(item.user?.display_name || item.user?.username || '').slice(0, 100)
        };
    },

    async request(url, signal) {
        const controller = new AbortController();
        const abort = () => controller.abort();
        if (signal) {
            if (signal.aborted) controller.abort();
            else signal.addEventListener('abort', abort, {once: true});
        }
        const timer = setTimeout(abort, 10000);
        try {
            const response = await fetch(url, {signal: controller.signal, credentials: 'omit', referrerPolicy: 'no-referrer'});
            if (!response.ok) throw new Error(response.status === 429 ? 'quota' : 'provider');
            const data = await response.json();
            if (data.result === false || (data.meta && data.meta.status !== 200)) throw new Error('provider');
            return data;
        } finally {
            clearTimeout(timer);
            if (signal) signal.removeEventListener('abort', abort);
        }
    },

    async search(surface, query, cursor, signal) {
        const config = this.config(surface);
        if (!config.enabled || !config.keys?.[config.provider]) throw new Error('disabled');
        const provider = config.provider;
        const key = config.keys[provider];
        const params = new URLSearchParams();
        let url;
        if (provider === 'klipy') {
            params.set('page', cursor || 1); params.set('per_page', 24);
            params.set('content_filter', 'high'); params.set('locale', document.documentElement.lang || 'en');
            if (query) params.set('q', query);
            url = 'https://api.klipy.com/api/v1/' + encodeURIComponent(key) + '/gifs/' + (query ? 'search' : 'trending') + '?' + params;
        } else {
            params.set('api_key', key); params.set('limit', 24); params.set('offset', cursor || 0); params.set('rating', 'pg');
            if (query) params.set('q', query);
            url = 'https://api.giphy.com/v1/gifs/' + (query ? 'search' : 'trending') + '?' + params;
        }
        const data = await this.request(url, signal);
        const raw = provider === 'klipy' ? data.data?.data || [] : data.data || [];
        const items = raw.map(item => this.normalizeItem(item, provider)).filter(Boolean);
        const next = provider === 'klipy'
            ? (data.data?.has_next ? Number(cursor || 1) + 1 : null)
            : (Number(data.pagination?.offset || 0) + Number(data.pagination?.count || 0) < Number(data.pagination?.total_count || 0)
                ? Number(data.pagination.offset) + Number(data.pagination.count) : null);
        return {items, next: provider === 'giphy' && next > (query ? 4999 : 499) ? null : next, provider};
    },

    async lookup(provider, surface, ids) {
        const key = this.config(surface).keys?.[provider];
        if (!key) return [];
        const params = new URLSearchParams(provider === 'klipy' ? {slugs: ids.join(',')} : {api_key: key, ids: ids.join(','), rating: 'pg'});
        const url = provider === 'klipy'
            ? 'https://api.klipy.com/api/v1/' + encodeURIComponent(key) + '/gifs/items?' + params
            : 'https://api.giphy.com/v1/gifs?' + params;
        const data = await this.request(url);
        return (provider === 'klipy' ? data.data?.data || [] : data.data || [])
            .map(item => this.normalizeItem(item, provider)).filter(Boolean);
    },

    createReference(value, surface) {
        const element = document.createElement('div');
        element.className = 'gif-reference';
        const ref = this.reference(value);
        if (ref) { element.dataset.gifReference = JSON.stringify(ref); element.dataset.gifSurface = surface; }
        element.textContent = this.text('Loading GIF…');
        return element;
    },

    renderReference(value, surface) { return value ? this.createReference(value, surface).outerHTML : ''; },

    attribution(provider, item) {
        const link = document.createElement('a');
        link.className = 'gif-attribution';
        link.dataset.gifProvider = provider;
        link.textContent = (provider === 'giphy' ? 'Powered by GIPHY' : 'Powered by KLIPY') + (item?.creator ? ' · ' + item.creator : '');
        if (provider === 'giphy') {
            link.setAttribute('aria-label', link.textContent);
            link.textContent = item?.creator || '';
        }
        link.href = item?.source || (provider === 'giphy' ? 'https://giphy.com' : 'https://klipy.com');
        link.target = '_blank'; link.rel = 'noopener noreferrer'; link.referrerPolicy = 'no-referrer';
        return link;
    },

    render(element, item) {
        if (!element.isConnected) return;
        element.replaceChildren();
        if (!item) {
            const status = document.createElement('span'); status.className = 'gif-status'; status.textContent = this.text('GIF unavailable');
            element.append(status); return;
        }
        this.resolved.set(element, item);
        const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const frame = document.createElement('div'); frame.className = 'gif-frame';
        frame.style.aspectRatio = item.width + ' / ' + item.height;
        const image = document.createElement('img'); image.alt = item.title;
        image.loading = 'lazy'; image.referrerPolicy = 'no-referrer'; image.width = item.width; image.height = item.height;
        const makeVideo = /\.mp4$/i.test(new URL(item.url).pathname);
        const media = makeVideo ? document.createElement('video') : image;
        if (makeVideo) {
            media.muted = true; media.loop = true; media.playsInline = true; media.preload = 'none';
            media.setAttribute('aria-label', item.title);
            if (item.still) media.poster = item.still;
        }
        media.src = reduced && item.still && !makeVideo ? item.still : item.url;
        const failed = () => { if (media.getAttribute('src')) this.render(element, null); };
        media.addEventListener('error', failed, {once: true});
        frame.append(media);
        const toggle = document.createElement('button'); toggle.type = 'button'; toggle.className = 'gif-motion-toggle';
        let playing = !reduced;
        const update = () => {
            toggle.textContent = this.text(playing ? 'Pause GIF' : 'Play GIF');
            toggle.setAttribute('aria-pressed', String(playing));
            if (makeVideo) {
                if (playing) media.play().catch(() => { playing = false; toggle.textContent = this.text('Play GIF'); toggle.setAttribute('aria-pressed', 'false'); });
                else media.pause();
            } else if (playing || item.still) {
                media.hidden = false; media.src = playing ? item.url : item.still;
            } else {
                media.hidden = true; media.removeAttribute('src');
            }
        };
        toggle.addEventListener('click', () => { playing = !playing; update(); });
        frame.append(toggle); element.append(frame, this.attribution(item.reference.provider, item)); update();
    },

    hydrate(root = document) {
        this.configure(root);
        const nodes = [...root.querySelectorAll('[data-gif-reference]')];
        if (root.matches && root.matches('[data-gif-reference]')) nodes.unshift(root);
        for (const node of nodes) {
            if (node.dataset.gifLoaded || node.dataset.gifLoading) continue;
            if (this.visibility) this.visibility.observe(node);
            else node.dataset.gifVisible = '1';
        }
        this.schedule();
    },

    schedule() {
        if (this.scheduled) return;
        this.scheduled = true;
        setTimeout(() => { this.scheduled = false; this.resolveVisible(); }, 0);
    },

    async resolveVisible() {
        const groups = new Map();
        for (const node of document.querySelectorAll('[data-gif-reference][data-gif-visible="1"]:not([data-gif-loaded]):not([data-gif-loading])')) {
            const ref = this.reference(node.dataset.gifReference), surface = node.dataset.gifSurface;
            if (!ref) { node.dataset.gifLoaded = '1'; this.render(node, null); continue; }
            node.dataset.gifLoading = '1';
            const groupKey = ref.provider + ':' + surface;
            if (!groups.has(groupKey)) groups.set(groupKey, {provider: ref.provider, surface, nodes: []});
            groups.get(groupKey).nodes.push({node, ref});
        }
        for (const group of groups.values()) {
            const ids = [...new Set(group.nodes.map(entry => entry.ref.id))];
            for (let offset = 0; offset < ids.length; offset += 50) {
                const batch = ids.slice(offset, offset + 50);
                const matching = group.nodes.filter(entry => batch.includes(entry.ref.id));
                const prefix = group.provider + ':' + group.surface + ':';
                const fresh = batch.filter(id => !this.pending.has(prefix + id));
                if (fresh.length) {
                    const request = this.lookup(group.provider, group.surface, fresh).catch(() => []);
                    for (const id of fresh) this.pending.set(prefix + id, request.then(items => items.find(item => item.reference.id === id)));
                }
                const pending = batch.map(id => this.pending.get(prefix + id));
                const items = (await Promise.all(pending)).filter(Boolean);
                batch.forEach((id, index) => { if (this.pending.get(prefix + id) === pending[index]) this.pending.delete(prefix + id); });
                for (const {node, ref} of matching) {
                    if (node.dataset.gifReference !== JSON.stringify(ref)) continue;
                    node.dataset.gifLoaded = '1'; delete node.dataset.gifLoading;
                    this.render(node, items.find(item => item.reference.id === ref.id));
                }
            }
        }
    },

    init() {
        if (this.observer) return;
        if (typeof IntersectionObserver !== 'undefined') {
            this.visibility = new IntersectionObserver(entries => {
                for (const entry of entries) if (entry.isIntersecting) {
                    entry.target.dataset.gifVisible = '1'; this.visibility.unobserve(entry.target);
                }
                this.schedule();
            }, {rootMargin: '120px'});
        }
        this.hydrate(document);
        this.observer = new MutationObserver(records => {
            for (const record of records) for (const node of record.addedNodes) if (node.nodeType === 1) this.hydrate(node);
        });
        this.observer.observe(document.body, {childList: true, subtree: true});
    }
};
window.GifMedia = GifMedia;
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => GifMedia.init());
else GifMedia.init();
