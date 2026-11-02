/* global GifMedia, bootstrap */
'use strict';

var GifPicker = {
    instances: new WeakMap(),
    active: null,
    observer: null,

    element(container) { return container && (container.nodeType ? container : container[0]); },
    getSelection(container) { return this.instances.get(this.element(container))?.selection || null; },

    mount(container, options = {}) {
        container = this.element(container);
        if (!container) return null;
        const present = this.instances.get(container);
        if (present) { if (options.onChange) present.onChange = options.onChange; return present; }
        const surface = options.surface || container.dataset.gifSurface;
        const config = GifMedia.config(surface);
        const state = {container, surface, selection: null, onChange: options.onChange,
            original: container.hasAttribute('data-gif-retain-existing') ? GifMedia.reference(container.dataset.gifExisting) : null,
            generation: 0, controller: null, timer: null, query: '', next: null};
        this.instances.set(container, state);
        const toolbarSlot = container.querySelector('[data-gif-toolbar-slot]');
        const previewSlot = container.querySelector('[data-gif-preview-slot]');
        const toolbar = document.createElement('div');
        toolbar.className = toolbarSlot ? 'd-flex align-items-center' : 'd-flex align-items-center gap-2 mt-1';
        const toggle = document.createElement('button'); toggle.type = 'button';
        toggle.className = 'btn btn-sm btn-link text-muted p-1 mb-0 gif-picker-toggle';
        toggle.textContent = GifMedia.text('GIF'); toggle.setAttribute('aria-label', GifMedia.text('Choose a GIF'));
        toggle.setAttribute('aria-expanded', 'false');
        if (toolbarSlot) {
            toggle.classList.add('shadow-none');
            const label = document.createElement('span'); label.className = 'border rounded px-1 small fw-semibold';
            label.textContent = GifMedia.text('GIF'); toggle.replaceChildren(label);
        }
        const preview = document.createElement('div');
        preview.className = 'gif-picker-selection position-relative ' + (previewSlot ? 'mb-2' : 'mt-2'); preview.hidden = true;
        const remove = document.createElement('button'); remove.type = 'button';
        remove.className = 'btn btn-dark rounded-circle position-absolute top-0 end-0 m-1 p-0 mb-0 shadow-none d-flex align-items-center justify-content-center fs-5 gif-picker-remove';
        remove.textContent = '×';
        remove.setAttribute('aria-label', GifMedia.text('Remove GIF'));
        remove.title = GifMedia.text('Remove GIF');
        const panel = document.createElement('div');
        panel.className = 'gif-picker-panel border rounded p-2 bg-body' + (toolbarSlot ? '' : ' mt-2'); panel.hidden = true;
        panel.setAttribute('role', 'region'); panel.setAttribute('aria-label', GifMedia.text('Choose a GIF'));
        const top = document.createElement('div'); top.className = 'd-flex align-items-center gap-2';
        const input = document.createElement('input'); input.type = 'search'; input.className = 'form-control form-control-sm';
        input.maxLength = 50; input.setAttribute('aria-label', GifMedia.text('Search GIFs'));
        input.placeholder = config.provider === 'klipy' ? 'Search KLIPY' : GifMedia.text('Search GIFs');
        const close = document.createElement('button'); close.type = 'button'; close.className = 'btn-close flex-shrink-0';
        close.setAttribute('aria-label', GifMedia.text('Close')); top.append(input, close);
        const status = document.createElement('div'); status.className = 'small text-muted py-2'; status.setAttribute('role', 'status');
        const results = document.createElement('div'); results.className = 'gif-picker-results';
        const more = document.createElement('button'); more.type = 'button'; more.className = 'btn btn-sm btn-link w-100 mb-0';
        more.textContent = GifMedia.text('Load more'); more.hidden = true;
        panel.append(top, status, results, more, GifMedia.attribution(config.provider));
        toolbar.append(toggle);
        (toolbarSlot || container).append(toolbar);
        (previewSlot || container).append(preview); container.append(panel);
        Object.assign(state, {toolbarSlot, toolbar, toggle, preview, remove, panel, input, status, results, more});
        toggle.addEventListener('click', () => panel.hidden ? this.open(state) : this.close(state));
        close.addEventListener('click', () => this.close(state));
        remove.addEventListener('click', () => {
            this.clear(container);
            if (!toolbar.hidden) toggle.focus({preventScroll: true});
        });
        input.addEventListener('input', () => {
            clearTimeout(state.timer);
            state.generation++; state.controller?.abort();
            state.timer = setTimeout(() => this.search(state, false), 350);
        });
        input.addEventListener('keydown', event => {
            if (event.key === 'Enter') { event.preventDefault(); clearTimeout(state.timer); this.search(state, false); }
        });
        more.addEventListener('click', () => this.search(state, true));
        state.onKeydown = event => {
            if (event.key === 'Escape' && !panel.hidden) { event.preventDefault(); event.stopPropagation(); this.close(state); }
        };
        state.onSetSelection = event => this.setSelection(container, event.detail);
        container.addEventListener('keydown', state.onKeydown);
        container.addEventListener('gif:set-selection', state.onSetSelection);
        if (!toolbarSlot) container.hidden = !config.enabled;
        toolbar.hidden = !config.enabled; toolbar.classList.toggle('d-none', !config.enabled);
        if (state.original) this.setSelection(container, state.original);
        return state;
    },

    open(state) {
        if (!GifMedia.config(state.surface).enabled) return;
        if (this.active && this.active !== state) this.close(this.active, false);
        this.active = state; state.panel.hidden = false; state.toggle.setAttribute('aria-expanded', 'true');
        if (state.toolbarSlot) {
            this.resizePopover(state);
            if (!state.popover) {
                state.popover = new bootstrap.Popover(state.toolbarSlot.parentElement, {
                    trigger: 'manual', animation: false, html: true, content: state.panel,
                    container: state.container, placement: 'bottom', offset: [0, 8],
                    template: '<div class="popover gif-picker-popover border-0 shadow" role="presentation"><div class="popover-body p-0"></div></div>',
                    popperConfig: defaults => {
                        const alignment = getComputedStyle(state.container).direction === 'rtl' ? 'start' : 'end';
                        return {...defaults, strategy: 'fixed', placement: 'bottom-' + alignment,
                            modifiers: [...defaults.modifiers,
                                {name: 'flip', options: {fallbackPlacements: ['top-' + alignment], padding: 8}},
                                {name: 'preventOverflow', options: {padding: 8, altAxis: true}}
                            ]};
                    }
                });
            }
            state.popover.show();
            state.onViewportChange = () => this.resizePopover(state);
            window.addEventListener('resize', state.onViewportChange);
            window.addEventListener('scroll', state.onViewportChange, {capture: true, passive: true});
            window.visualViewport?.addEventListener('resize', state.onViewportChange);
            window.visualViewport?.addEventListener('scroll', state.onViewportChange);
        }
        state.input.focus({preventScroll: true}); this.search(state, false);
    },

    resizePopover(state) {
        if (!state.toolbarSlot || state.panel.hidden) return;
        const anchor = state.toolbarSlot.parentElement.getBoundingClientRect();
        const viewport = window.visualViewport;
        const top = viewport?.offsetTop || 0;
        const height = viewport?.height || window.innerHeight;
        const width = viewport?.width || window.innerWidth;
        state.panel.style.width = Math.max(0, Math.min(420, anchor.width, width - 16)) + 'px';
        state.panel.style.maxHeight = Math.max(0, Math.min(height, Math.max(anchor.top - top, top + height - anchor.bottom)) - 16) + 'px';
        state.popover?.update();
    },

    close(state, focus = true) {
        state.popover?.hide();
        if (state.onViewportChange) {
            window.removeEventListener('resize', state.onViewportChange);
            window.removeEventListener('scroll', state.onViewportChange, true);
            window.visualViewport?.removeEventListener('resize', state.onViewportChange);
            window.visualViewport?.removeEventListener('scroll', state.onViewportChange);
            state.onViewportChange = null;
        }
        state.panel.hidden = true; state.toggle.setAttribute('aria-expanded', 'false');
        clearTimeout(state.timer); state.generation++; state.controller?.abort();
        if (this.active === state) this.active = null;
        if (focus && state.toggle.isConnected) state.toggle.focus();
    },

    async search(state, append) {
        const generation = ++state.generation;
        state.controller?.abort(); state.controller = new AbortController();
        const config = GifMedia.config(state.surface), provider = config.provider;
        const query = state.input.value.trim();
        const cursor = append ? state.next : null;
        if (append && cursor === null) return;
        if (!append) { state.results.replaceChildren(); state.query = query; }
        state.status.textContent = GifMedia.text('Loading GIF…'); state.more.disabled = true;
        state.more.hidden = true;
        try {
            const response = await GifMedia.search(state.surface, query, cursor, state.controller.signal);
            if (state.generation !== generation || !state.container.isConnected || state.panel.hidden
                || provider !== GifMedia.config(state.surface).provider) return;
            for (const item of response.items) {
                const button = document.createElement('button'); button.type = 'button'; button.className = 'gif-picker-result';
                button.setAttribute('aria-label', item.title); button.title = item.title;
                const image = document.createElement('img'); image.alt = item.title; image.loading = 'lazy'; image.referrerPolicy = 'no-referrer';
                const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                if (reduced && !item.still) { image.hidden = true; button.textContent = item.title; }
                else image.src = reduced ? item.still : item.preview;
                image.addEventListener('error', () => { button.disabled = true; image.removeAttribute('src'); }, {once: true});
                button.append(image); state.results.append(button);
                button.addEventListener('click', () => { this.setSelection(state.container, item.reference, item); this.close(state); });
            }
            state.next = response.next;
            state.more.hidden = response.next === null;
            state.status.textContent = state.results.children.length ? '' : GifMedia.text('No GIFs found.');
        } catch (error) {
            if (state.generation !== generation || state.panel.hidden) return;
            state.status.textContent = GifMedia.text(error.message === 'quota'
                ? 'The GIF provider limit was reached. Please try again later.' : 'GIF search is unavailable. Please try again.');
        } finally {
            if (state.generation === generation) {
                state.more.disabled = false;
                this.resizePopover(state);
            }
        }
    },

    setSelection(container, value, item = null) {
        container = this.element(container);
        const state = this.instances.get(container) || this.mount(container);
        if (!state) return;
        const reference = GifMedia.reference(value), config = GifMedia.config(state.surface);
        const retained = reference && state.original && reference.provider === state.original.provider && reference.id === state.original.id;
        if (reference && !retained && (!config.enabled || config.provider !== reference.provider)) { this.clear(container); return; }
        state.selection = reference; state.preview.replaceChildren(); state.preview.hidden = !reference;
        if (!state.toolbarSlot) container.hidden = !config.enabled && !reference;
        state.toolbar.hidden = !config.enabled; state.toolbar.classList.toggle('d-none', !config.enabled);
        state.toggle.hidden = !config.enabled;
        if (reference) {
            const display = GifMedia.createReference(reference, state.surface);
            state.preview.append(display, state.remove);
            if (item) { display.dataset.gifLoaded = '1'; GifMedia.render(display, item); }
            else GifMedia.hydrate(display);
        }
        if (state.onChange) state.onChange(reference);
        container.dispatchEvent(new CustomEvent('gif:change', {bubbles: true, detail: reference}));
    },

    clear(container) {
        container = this.element(container);
        const state = this.instances.get(container);
        if (state) this.setSelection(container, null);
    },

    destroy(container) {
        container = this.element(container);
        const state = this.instances.get(container);
        if (!state) return;
        this.close(state, false);
        state.popover?.dispose();
        container.removeEventListener('keydown', state.onKeydown);
        container.removeEventListener('gif:set-selection', state.onSetSelection);
        state.toolbar.remove(); state.preview.remove(); state.panel.remove();
        this.instances.delete(container);
    },

    scan(root) {
        GifMedia.configure(root);
        if (root.matches && root.matches('[data-gif-composer]')) this.mount(root);
        for (const node of root.querySelectorAll('[data-gif-composer]')) this.mount(node);
    },

    init() {
        if (this.observer) return;
        this.scan(document);
        document.addEventListener('click', event => { if (this.active && !this.active.container.contains(event.target)) this.close(this.active, false); });
        this.observer = new MutationObserver(records => {
            for (const record of records) {
                for (const node of record.addedNodes) if (node.nodeType === 1) this.scan(node);
                for (const node of record.removedNodes) if (node.nodeType === 1) {
                    if (node.matches('[data-gif-composer]') && !node.isConnected) this.destroy(node);
                    for (const composer of node.querySelectorAll('[data-gif-composer]')) if (!composer.isConnected) this.destroy(composer);
                }
            }
        });
        this.observer.observe(document.body, {childList: true, subtree: true});
    }
};
window.GifPicker = GifPicker;
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => GifPicker.init());
else GifPicker.init();
