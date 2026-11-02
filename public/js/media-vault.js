"use strict";
/* global app, appSettings, bootstrap, FileUpload, VaultUpload, trans, launchToast, PhotoSwipe, PhotoSwipeUI_Default */

var MediaVault = {
    browsers: [],
    init: function () {
        $('[data-vault-browser]').each(function () {
            var browser = {element: $(this), page: 1, lastPage: 1, assets: {}, request: null};
            MediaVault.browsers.push(browser);
            MediaVault.initFilters(browser);
            browser.element.on('change', '.vault-type-filter, .vault-archive-filter, .vault-sort-filter', function () {
                browser.page = 1;
                MediaVault.load(browser, {navigate: true});
            });
            browser.element.on('click', '.vault-filters-reset', function () {
                if ($(this).prop('disabled')) return;
                // Reset can live in the filter menu or the empty state, which disappears on load.
                if (document.activeElement === this) {
                    var typeControl = browser.element.find('.vault-type-filter')[0];
                    var fromEmptyState = $(this).closest('.vault-empty-state').length > 0;
                    var focusTarget = fromEmptyState && browser.element.attr('data-vault-mode') === 'library'
                        ? browser.element.find('.vault-filter-toggle')[0] : typeControl;
                    if (focusTarget) focusTarget.focus({preventScroll: true});
                }
                browser.element.find('.vault-type-filter').val('all');
                browser.element.find('.vault-archive-filter').val('0');
                browser.element.find('.vault-sort-filter').val('newest');
                browser.page = 1;
                MediaVault.load(browser, {navigate: true});
            });
            browser.element.on('click', '.vault-previous, .vault-next, .vault-page-button', function () {
                if (browser.pending || $(this).prop('disabled')) return;
                var page = $(this).hasClass('vault-page-button') ? Number($(this).attr('data-page'))
                    : browser.page + ($(this).hasClass('vault-next') ? 1 : -1);
                page = Math.max(1, Math.min(browser.lastPage, page));
                if (page === browser.page) return;
                browser.page = page;
                MediaVault.load(browser, {navigate: true, scroll: true});
            });
            browser.element.on('click', '.vault-reload', function () {
                MediaVault.load(browser);
            });
            browser.element.on('click', '.vault-card-media a', function (event) {
                if (!$(this).find('img').length) return;
                event.preventDefault();
                MediaVault.openGallery(browser, this);
            });
            browser.element.on('click', '.vault-select', function () {
                MediaVault.select(browser, $(this));
            });
            browser.element.on('click', '.vault-usage', function () {
                MediaVault.showUsage($(this).closest('[data-attachment-id]').attr('data-attachment-id'));
            });
            browser.element.on('click', '.vault-action', function () {
                MediaVault.action(browser, $(this));
            });
            browser.element.on('click', '.vault-retry', function () {
                var id = $(this).closest('[data-attachment-id]').attr('data-attachment-id');
                $(this).prop('disabled', true);
                $.post(app.baseUrl + '/attachment/transcoding/retry', {attachmentId: id})
                    .done(function (data) { FileUpload.applyTranscodingUpdate(data); MediaVault.load(browser); })
                    .fail(function (xhr) { MediaVault.error(xhr); MediaVault.load(browser); });
            });
            if (browser.element.attr('data-vault-mode') === 'library') MediaVault.load(browser);
        });

        $('.vault-picker-open').on('click', function () {
            if (!FileUpload.myDropzone || FileUpload.isRestoring || FileUpload.restoreFailed) return;
            MediaVault.pickerTrigger = this;
            $('#vault-picker-dialog').modal('show');
        });
        $('#vault-picker-dialog').on('show.bs.modal', function () {
            if (MediaVault.restoringPickerFromGallery) return;
            MediaVault.browsers.filter(function (b) { return b.element.attr('data-vault-mode') === 'picker'; })
                .forEach(function (b) {
                    const mediaOnly = FileUpload.getVaultSelectionMode() !== 'multiple';
                    b.element.find('.vault-type-filter option[value="file"]').prop('hidden', mediaOnly).prop('disabled', mediaOnly);
                    if(mediaOnly && b.element.find('.vault-type-filter').val() === 'file') b.element.find('.vault-type-filter').val('all');
                    b.page = 1; b.fingerprint = null; MediaVault.markSelections(b); MediaVault.load(b);
                });
        }).on('hide.bs.modal', function () {
            var toggle = $(this).find('.vault-filter-toggle');
            if (toggle.attr('aria-expanded') === 'true') toggle.dropdown('toggle');
        }).on('hidden.bs.modal', function () {
            $(this).find('video, audio').each(function () { this.pause(); });
            if (MediaVault.galleryOpening) return;
            if (MediaVault.pickerTrigger && document.documentElement.contains(MediaVault.pickerTrigger) && $(MediaVault.pickerTrigger).is(':visible')) {
                MediaVault.pickerTrigger.focus({preventScroll: true});
            }
        });
        if ($('#media-vault-page').length) {
            VaultUpload.init(function () { MediaVault.refreshVisible(); });
        }
        $('#vault-archive-dialog, #vault-delete-dialog').on('hidden.bs.modal', function () {
            MediaVault.dismissActionDialog(this.id);
        });
        $(document).on('attachment:transcoding-update', function () { MediaVault.refreshVisible(); });
        window.setInterval(function () {
            if (document.hidden) return;
            MediaVault.browsers.forEach(function (browser) {
                if (browser.element.is(':visible') && browser.element.find('[data-status="queued"], [data-status="processing"]').length) {
                    if (!browser.pending && !browser.failed) MediaVault.load(browser, {background: true});
                }
            });
        }, 10000);
    },

    initFilters: function (browser) {
        var toggle = browser.element.find('.vault-filter-toggle');
        if (toggle.length) {
            bootstrap.Dropdown.getOrCreateInstance(toggle[0], {
                popperConfig: function (config) {
                    var opensRight = config.placement === 'right-start';
                    // Try the other side, then above/below when neither side fits on mobile.
                    return Object.assign({}, config, {modifiers: config.modifiers.concat([{
                        name: 'flip',
                        options: {fallbackPlacements: opensRight ? ['left-start', 'bottom-start', 'top-start'] : ['right-start', 'bottom-end', 'top-end']}
                    }])});
                }
            });
        }
        browser.element.on('show.bs.dropdown', '.vault-filter-menu', function () {
            var bounds = browser.element.find('.vault-filter-toggle')[0].getBoundingClientRect();
            // Leave room for the anchor on short screens; scroll the menu instead of covering it.
            var availableHeight = Math.max(bounds.top, window.innerHeight - bounds.bottom) - 16;
            browser.element.find('.vault-filter-panel')
                .toggleClass('dropdown-menu-end', window.getComputedStyle(this).direction !== 'rtl')
                .css('--filter-dropdown-available-height', Math.max(0, availableHeight) + 'px');
        });
        browser.element.on('click', '.vault-filter-panel', function (event) {
            // Apply/reset in place; Bootstrap should only dismiss on an outside click.
            event.stopPropagation();
        });
        browser.element.on('keydown', '.vault-filter-menu', function (event) {
            var toggle = browser.element.find('.vault-filter-toggle');
            if (event.key === 'Escape' && toggle.attr('aria-expanded') === 'true') {
                event.preventDefault();
                event.stopPropagation();
                toggle.dropdown('toggle');
                toggle[0].focus({preventScroll: true});
            } else if (event.target.tagName === 'SELECT') {
                // Keep native select keyboard controls; don't let Bootstrap treat them as menu items.
                event.stopPropagation();
            }
        });
    },

    updateFilterState: function (browser) {
        var count = Number((browser.element.find('.vault-type-filter').val() || 'all') !== 'all')
            + Number(browser.element.find('.vault-archive-filter').val() === '1')
            + Number((browser.element.find('.vault-sort-filter').val() || 'newest') !== 'newest');
        browser.element.find('.vault-filter-toggle').toggleClass('active', count > 0)
            .attr('aria-label', trans('Filters') + (count ? ' (' + count + ')' : ''));
        browser.element.find('.vault-filter-count').text(count).toggleClass('d-none', count === 0);
        browser.element.find('.vault-filters-reset').prop('disabled', count === 0);
    },

    pageNumbers: function (page, lastPage) {
        lastPage = Math.max(1, Math.floor(Number(lastPage)) || 1);
        page = Math.max(1, Math.min(lastPage, Math.floor(Number(page)) || 1));
        var visible = lastPage > 1 ? [1, lastPage] : [1];
        var start = lastPage <= 5 ? 1 : Math.max(1, Math.min(page - 1, lastPage - 2));
        var end = lastPage <= 5 ? lastPage : Math.min(lastPage, Math.max(page + 1, 3));
        for (var number = start; number <= Math.min(end, lastPage); number++) {
            if (visible.indexOf(number) === -1) visible.push(number);
        }
        visible.sort(function (left, right) { return left - right; });
        var numbers = [];
        visible.forEach(function (number) {
            if (numbers.length && number - numbers[numbers.length - 1] > 1) numbers.push(null);
            numbers.push(number);
        });
        return numbers;
    },

    renderPagination: function (browser) {
        var container = browser.element.find('.vault-page-numbers');
        var key = browser.page + '/' + browser.lastPage;
        if (browser.paginationKey !== key) {
            container.empty();
            MediaVault.pageNumbers(browser.page, browser.lastPage).forEach(function (page) {
                if (page === null) {
                    $('<span>').addClass('app-pagination-ellipsis').attr('aria-hidden', 'true').text('…').appendTo(container);
                    return;
                }
                var current = page === browser.page;
                var button = $('<button>').attr({type: 'button', 'data-page': page, 'aria-label': trans('Page') + ' ' + page})
                    .addClass('app-pagination-control vault-page-button')
                    .text(page).appendTo(container);
                if (current) button.attr('aria-current', 'page');
            });
            browser.paginationKey = key;
        }
        container.find('.vault-page-button').each(function () {
            $(this).prop('disabled', browser.pending || Number($(this).attr('data-page')) === browser.page);
        });
        browser.element.find('.vault-pagination').toggleClass('d-none', browser.lastPage <= 1);
        var pageLabel = trans('Page :current of :total').replace(':current', browser.page).replace(':total', browser.lastPage);
        browser.element.find('.vault-page-label-full').text(pageLabel);
        browser.element.find('.vault-page-label-short').text(browser.page + ' / ' + browser.lastPage).attr('aria-label', pageLabel);
        browser.element.find('.vault-previous').prop('disabled', browser.pending || browser.page <= 1);
        browser.element.find('.vault-next').prop('disabled', browser.pending || browser.page >= browser.lastPage);
    },

    restorePaginationFocus: function (browser) {
        var previous = browser.paginationFocus;
        browser.paginationFocus = null;
        if (!previous || MediaVault.gallery) return;
        // Never steal focus if the user moved to a filter, picker action, or another control while waiting.
        if (document.activeElement && document.activeElement !== previous
            && document.activeElement !== document.body && document.activeElement !== document.documentElement) return;
        var target = document.documentElement.contains(previous) && !$(previous).prop('disabled') ? previous : null;
        if (!target) {
            target = browser.element.find('.vault-next:not(:disabled), .vault-previous:not(:disabled)')[0]
                || browser.element.find('.vault-page-label').attr('tabindex', '-1')[0];
        }
        if (!target || !$(target).is(':visible')) target = browser.element.find('.vault-toolbar').attr('tabindex', '-1')[0];
        if (target && $(target).is(':visible')) target.focus({preventScroll: true});
    },

    load: function (browser, options) {
        options = options || {};
        MediaVault.updateFilterState(browser);
        browser.page = Math.max(1, Math.floor(Number(browser.page)) || 1);
        var generation = browser.generation = (browser.generation || 0) + 1;
        if (browser.request) browser.request.abort();
        var query = {
            type: browser.element.find('.vault-type-filter').val(),
            archived: browser.element.find('.vault-archive-filter').val() || 0,
            sort: browser.element.find('.vault-sort-filter').val() || 'newest',
            picker: browser.element.attr('data-vault-mode') === 'picker' ? 1 : 0,
            page: browser.page
        };
        if(query.picker) query.context = FileUpload.uploadType || 'post';
        var key = JSON.stringify(query);
        var changing = key !== browser.loadedKey;
        var pagination = browser.element.find('.vault-pagination')[0];
        if (pagination && pagination.contains(document.activeElement)) browser.paginationFocus = document.activeElement;
        browser.pending = true;
        browser.failed = false;
        browser.element.attr('aria-busy', 'true');
        browser.element.find('.vault-reload').addClass('d-none').prop('disabled', true);
        browser.element.find('.vault-previous, .vault-next, .vault-page-button').prop('disabled', true);
        if (changing) {
            browser.assets = {};
            browser.element.find('video, audio').each(function () { this.pause(); });
            // Keep the grid's footprint while preventing interaction with the previous query's cards.
            browser.element.find('.vault-grid').css('visibility', 'hidden').attr('aria-hidden', 'true');
            browser.element.find('.vault-results-count').text('');
        }
        if (changing || !options.background) {
            browser.element.find('.vault-empty-state').addClass('d-none');
            browser.element.find('.vault-message').text(trans('Loading...')).removeClass('d-none');
        }
        browser.request = $.get(app.baseUrl + '/my/vault/items', query).done(function (response) {
            if (generation !== browser.generation) return;
            browser.lastPage = Math.max(1, Math.floor(Number(response.last_page)) || 1);
            if (browser.page > browser.lastPage) {
                browser.page = browser.lastPage;
                MediaVault.load(browser, options);
                return;
            }
            browser.assets = {};
            (response.attachments || []).forEach(function (attachment) { browser.assets[attachment.attachmentID] = attachment; });
            const fingerprint = JSON.stringify([key, (response.attachments || []).map(function (a) {
                return [a.attachmentID, a.transcoding_status, a.transcoding_progress, a.vault_archived_at, a.placements_count];
            })]);
            if(fingerprint !== browser.fingerprint){
                browser.element.find('video, audio').each(function () { this.pause(); });
                browser.element.find('.vault-grid').html(response.html);
                // The shared feed wrapper links audio; native controls must not navigate away.
                browser.element.find('.vault-card-media a').has('audio, video').each(function () {
                    $(this).replaceWith($(this).contents());
                });
                browser.element.find('.vault-card-media a').has('img').attr('aria-label', trans('Preview image'));
                browser.element.find('.vault-card-media img, .vault-card-media video, .vault-card-media audio')
                    .one('error.vault', function () { MediaVault.refreshPreview(browser, this); });
                browser.fingerprint = fingerprint;
            }
            browser.loadedKey = key;
            browser.element.find('.vault-grid').css('visibility', '').removeAttr('aria-hidden');
            browser.element.find('.vault-message').text('').addClass('d-none');
            var filtered = (query.type && query.type !== 'all') || Number(query.archived) === 1;
            browser.element.find('.vault-empty-state .app-empty-state-title').text(filtered ? trans('No matching media') : trans('No media yet'));
            browser.element.find('.vault-empty-state .app-empty-state-copy').text(filtered
                ? trans('Try changing or resetting your filters.')
                : trans('Upload media to get started.'));
            browser.element.find('.vault-empty-state .app-empty-state-actions').toggleClass('d-none', !filtered);
            browser.element.find('.vault-empty-state').toggleClass('d-none', Number(response.total) > 0);
            var count = browser.element.attr('data-vault-mode') === 'library'
                ? Number(response.total).toLocaleString(document.documentElement.lang || 'en') : response.total;
            browser.element.find('.vault-results-count').text(count + ' ' + trans(Number(response.total) === 1 ? 'item' : 'items'));
            MediaVault.markSelections(browser);
            if (options.scroll) {
                var toolbar = browser.element.find('.vault-toolbar')[0] || browser.element[0];
                var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                if (toolbar && toolbar.scrollIntoView) toolbar.scrollIntoView({behavior: reduceMotion ? 'auto' : 'smooth', block: 'start'});
            }
        }).fail(function (xhr, status) {
            if (generation !== browser.generation || status === 'abort') return;
            browser.failed = true;
            browser.element.find('.vault-empty-state').addClass('d-none');
            browser.element.find('.vault-message').text((xhr.responseJSON || {}).message || trans('Unable to load media. Please try again.')).removeClass('d-none');
            browser.element.find('.vault-reload').removeClass('d-none');
            if (changing) {
                browser.element.find('.vault-grid').empty().css('visibility', '').removeAttr('aria-hidden');
                browser.fingerprint = null;
            }
        }).always(function () {
            if (generation !== browser.generation) return;
            browser.pending = false;
            browser.request = null;
            browser.element.attr('aria-busy', 'false');
            browser.element.find('.vault-reload').prop('disabled', false);
            MediaVault.renderPagination(browser);
            MediaVault.restorePaginationFocus(browser);
            if (browser.refreshAfterLoad) {
                browser.refreshAfterLoad = false;
                MediaVault.load(browser, {background: true});
            }
        });
    },

    markSelections: function (browser) {
        if (browser.element.attr('data-vault-mode') !== 'picker') return;
        var singleComposer = FileUpload.getVaultSelectionMode() !== 'multiple';
        var count = (FileUpload.attachaments || []).length;
        var selectionLabel = trans(count === 1 ? '1 selected' : ':count selected').replace(':count', count);
        browser.element.find('.vault-selection-count').toggleClass('d-none', singleComposer)
            .text(singleComposer ? '' : selectionLabel);
        browser.element.find('.vault-done-count').text(!singleComposer && count ? ' (' + count + ')' : '');
        browser.element.find('.vault-picker-done').attr('aria-label', trans('Done') + (!singleComposer && count ? ', ' + selectionLabel : ''));
        var blocked = FileUpload.isRestoring || FileUpload.restoreFailed || (singleComposer && FileUpload.pendingVaultSelections > 0);
        browser.element.find('[data-attachment-id]').each(function () {
            var id = $(this).attr('data-attachment-id');
            var selected = !!FileUpload.findAttachment(id);
            var attachment = browser.assets[id];
            var allowed = attachment && FileUpload.canSelectVaultAttachment(attachment, true, true);
            var replacing = !selected && attachment && FileUpload.getVaultReplacementIds(attachment).length > 0;
            $(this).toggleClass('is-selected', selected).find('.vault-select')
                .prop('disabled', blocked || !!(browser.pendingSelectionIds || {})[id] || (!selected && !allowed))
                .attr('aria-pressed', selected ? 'true' : 'false')
                .attr('title', !selected && attachment && attachment.can_select === false ? trans('This file format is currently disabled.') : '')
                .text(trans(selected ? 'Deselect' : (replacing ? 'Replace' : 'Select')));
        });
    },

    select: function (browser, button) {
        const id = button.closest('[data-attachment-id]').attr('data-attachment-id');
        const generation = FileUpload.restoreGeneration;
        const singleComposer = FileUpload.getVaultSelectionMode() !== 'multiple';
        if (!FileUpload.myDropzone || FileUpload.isRestoring || FileUpload.restoreFailed
            || (singleComposer && FileUpload.pendingVaultSelections > 0) || (browser.pendingSelectionIds || {})[id]) return;
        if (FileUpload.findAttachment(id)) {
            FileUpload.deselectVaultAttachment(id);
            MediaVault.markSelections(browser);
            return;
        }
        browser.pendingSelectionIds = browser.pendingSelectionIds || {};
        browser.pendingSelectionIds[id] = true;
        button.prop('disabled', true);
        FileUpload.pendingVaultSelections++;
        MediaVault.markSelections(browser);
        $.post(app.baseUrl + '/my/vault/resolve', {ids: [id]}).done(function (response) {
            if(generation !== FileUpload.restoreGeneration) return;
            const attachment = (response.attachments || [])[0];
            if(!attachment || attachment.vault_archived_at || attachment.transcoding_status !== 'completed') {
                launchToast('warning', trans('Vault'), trans('This media is no longer available for selection.'));
                MediaVault.load(browser);
                return;
            }
            if (FileUpload.applyVaultSelection(attachment) && singleComposer) $('#vault-picker-dialog').modal('hide');
        }).fail(MediaVault.error).always(function () {
            delete browser.pendingSelectionIds[id];
            if(generation === FileUpload.restoreGeneration) FileUpload.pendingVaultSelections--;
            MediaVault.markSelections(browser);
        });
    },

    refreshPreview: function (browser, element) {
        const id = $(element).closest('[data-attachment-id]').attr('data-attachment-id');
        // A long-lived page can outlast a configured signed URL. Resolve once on a media load error.
        $.post(app.baseUrl + '/my/vault/resolve', {ids: [id]}).done(function (response) {
            const attachment = (response.attachments || [])[0];
            if(!attachment || !attachment.path || !document.documentElement.contains(element)) return;
            browser.assets[id] = attachment;
            $(element).attr('src', attachment.path + (element.tagName === 'IMG' ? '' : '#t=0.001'));
            $(element).closest('a').attr('href', attachment.path);
            MediaVault.updateGallerySource(browser, id, attachment.path);
        });
    },

    openGallery: function (browser, anchor) {
        if (browser.pending && !browser.loadedKey) return;
        var template = document.querySelector('.pswp');
        if (!template || typeof PhotoSwipe === 'undefined' || typeof PhotoSwipeUI_Default === 'undefined') {
            launchToast('warning', trans('Vault'), trans('Unable to open media preview.'));
            return;
        }
        if (MediaVault.gallery || MediaVault.galleryOpening) return;
        var items = [];
        var index = 0;
        browser.element.find('.vault-card-media a').has('img').each(function () {
            var image = $(this).find('img')[0];
            if (this === anchor) index = items.length;
            items.push({src: this.href, w: image.naturalWidth || 0, h: image.naturalHeight || 0,
                vaultId: $(this).closest('[data-attachment-id]').attr('data-attachment-id'), vaultAnchor: this});
        });
        if (!items.length) return;
        browser.element.find('video, audio').each(function () { this.pause(); });
        var zoom = typeof appSettings !== 'undefined' && appSettings.feed && appSettings.feed.allow_gallery_zoom === true;
        var options = {index: index, history: false, bgOpacity: 0.9, showHideOpacity: true,
            shareEl: false, fullscreenEl: false, captionEl: false, counterEl: false,
            closeOnScroll: false, zoomEl: zoom, maxSpreadZoom: zoom ? 2 : 1};
        if (!zoom) options.getDoubleTapZoom = function (isMouseClick, item) { return item.initialZoomLevel; };
        var picker = $(anchor).closest('.modal');
        var restorePicker = function () {
            if (!picker.length || !document.documentElement.contains(picker[0])) return;
            if (!picker.hasClass('show')) {
                MediaVault.restoringPickerFromGallery = true;
                picker.one('shown.bs.modal', function () {
                    MediaVault.restoringPickerFromGallery = false;
                    if (document.documentElement.contains(anchor) && $(anchor).is(':visible')) anchor.focus({preventScroll: true});
                });
                bootstrap.Modal.getOrCreateInstance(picker[0]).show();
            } else if (document.documentElement.contains(anchor) && $(anchor).is(':visible')) {
                anchor.focus({preventScroll: true});
            }
        };
        var launchGallery = function () {
            MediaVault.galleryOpening = false;
            var gallery = new PhotoSwipe(template, PhotoSwipeUI_Default, items, options);
            var state = MediaVault.gallery = {instance: gallery, browser: browser, items: items, closed: false};
            gallery.listen('gettingData', function (itemIndex, item) {
                // This PhotoSwipe version doesn't emit imageLoadComplete on every failed load.
                // Checking the source also catches URLs that expired after the thumbnail was displayed.
                MediaVault.loadGalleryDimensions(state, item);
            });
            gallery.listen('imageLoadComplete', function (itemIndex, item) {
                if (item.loadError) MediaVault.refreshGalleryItem(state, item);
            });
            gallery.listen('destroy', function () {
                state.closed = true;
                if (MediaVault.gallery === state) MediaVault.gallery = null;
                restorePicker();
            });
            try {
                gallery.init();
            } catch (error) {
                state.closed = true;
                MediaVault.gallery = null;
                restorePicker();
                launchToast('warning', trans('Vault'), trans('Unable to open media preview.'));
            }
        };

        if (picker.length && picker.hasClass('show')) {
            MediaVault.galleryOpening = true;
            picker.one('hidden.bs.modal', launchGallery);
            bootstrap.Modal.getOrCreateInstance(picker[0]).hide();
        } else {
            launchGallery();
        }
    },

    loadGalleryDimensions: function (state, item) {
        if (state.closed || item.vaultLoadingDimensions === item.src) return;
        var source = item.src;
        item.vaultLoadingDimensions = source;
        var image = new Image();
        image.onload = function () {
            if (state.closed || item.src !== source) return;
            if (item.w === image.naturalWidth && item.h === image.naturalHeight) return;
            item.w = image.naturalWidth;
            item.h = image.naturalHeight;
            state.instance.invalidateCurrItems();
            state.instance.updateSize(true);
        };
        image.onerror = function () {
            if (!state.closed && item.src === source) MediaVault.refreshGalleryItem(state, item);
        };
        image.src = source;
    },

    refreshGalleryItem: function (state, item) {
        if (state.closed || item.vaultResolveAttempted) return;
        item.vaultResolveAttempted = true;
        $.post(app.baseUrl + '/my/vault/resolve', {ids: [item.vaultId]}).done(function (response) {
            var attachment = (response.attachments || [])[0];
            if (state.closed || !attachment || !attachment.path) return;
            state.browser.assets[item.vaultId] = attachment;
            MediaVault.updateGallerySource(state.browser, item.vaultId, attachment.path);
        });
    },

    updateGallerySource: function (browser, id, source) {
        var state = MediaVault.gallery;
        if (!state || state.closed || state.browser !== browser) return;
        state.items.filter(function (item) { return item.vaultId === id; }).forEach(function (item) {
            item.src = source;
            item.loadError = false;
            item.loaded = false;
            item.loading = false;
            item.imageAppended = false;
            item.vaultLoadingDimensions = null;
            if (document.documentElement.contains(item.vaultAnchor)) {
                $(item.vaultAnchor).attr('href', source).find('img').attr('src', source);
            }
            MediaVault.loadGalleryDimensions(state, item);
        });
        state.instance.invalidateCurrItems();
        state.instance.updateSize(true);
    },

    action: function (browser, button) {
        if (button.prop('disabled') || button.attr('aria-disabled') === 'true') return;
        var action = button.attr('data-action');
        var id = button.closest('[data-attachment-id]').attr('data-attachment-id');
        if (['archive', 'restore', 'delete'].indexOf(action) === -1) return;
        if (action !== 'restore') {
            if (MediaVault.actionDialog) return;
            MediaVault.pendingAction = {browser: browser, button: button, action: action, id: id};
            MediaVault.actionDialog = 'vault-' + action + '-dialog';
            MediaVault.actionTrigger = button.closest('[data-attachment-id]').find('.vault-options-button')[0];
            $('#' + MediaVault.actionDialog).modal('show');
            return;
        }
        MediaVault.performAction(browser, button, action, id);
    },

    confirmAction: function (action) {
        var pending = MediaVault.pendingAction;
        if (!pending || pending.action !== action) return;
        // Consume the confirmation before sending so double-clicks cannot submit twice.
        MediaVault.pendingAction = null;
        $('#' + MediaVault.actionDialog).modal('hide');
        MediaVault.performAction(pending.browser, pending.button, pending.action, pending.id);
    },

    dismissActionDialog: function (id) {
        if (MediaVault.actionDialog !== id) return;
        MediaVault.pendingAction = null;
        MediaVault.actionDialog = null;
        var trigger = MediaVault.actionTrigger;
        MediaVault.actionTrigger = null;
        if (!trigger || !document.documentElement.contains(trigger) || !$(trigger).is(':visible')) {
            trigger = $('.vault-upload-button')[0];
        }
        if (trigger) trigger.focus({preventScroll: true});
    },

    performAction: function (browser, button, action, id) {
        if (button.prop('disabled') || button.attr('aria-disabled') === 'true') return;
        button.prop('disabled', true);
        $.ajax({
            url: app.baseUrl + '/my/vault/' + encodeURIComponent(id) + (action === 'delete' ? '' : '/' + action),
            type: action === 'delete' ? 'DELETE' : 'POST'
        }).done(function () {
            MediaVault.load(browser);
        }).fail(function (xhr) {
            button.prop('disabled', false);
            MediaVault.error(xhr);
        });
    },

    showUsage: function (id) {
        MediaVault.usageId = id;
        var dialog = $('#vault-usage-dialog');
        dialog.find('.vault-usage-list').empty();
        dialog.find('.vault-usage-more').addClass('d-none');
        dialog.modal('show');
        MediaVault.loadUsage(id, 1);
    },

    loadUsage: function (id, page) {
        var dialog = $('#vault-usage-dialog');
        $.get(app.baseUrl + '/my/vault/' + encodeURIComponent(id) + '/usage', {page: page}).done(function (response) {
            if(MediaVault.usageId !== id) return;
            if (page === 1) dialog.find('.vault-usage-list').empty();
            if (!response.total) $('<li>').addClass('vault-usage-item text-muted').text(trans('This media is not used anywhere yet.')).appendTo(dialog.find('.vault-usage-list'));
            (response.uses || []).forEach(function (use) {
                const item = $('<li>').addClass('vault-usage-item');
                const label = use.label + (use.enabled ? ' · ' + trans('Enabled') : '');
                if(use.url) $('<a>').attr('href', use.url).text(label).appendTo(item);
                else item.text(label);
                item.appendTo(dialog.find('.vault-usage-list'));
            });
            dialog.find('.vault-usage-more').toggleClass('d-none', response.page >= response.last_page)
                .off('click').on('click', function () { MediaVault.loadUsage(id, page + 1); });
        }).fail(MediaVault.error);
    },

    refreshVisible: function () {
        MediaVault.browsers.forEach(function (browser) {
            if (!browser.element.is(':visible')) return;
            // Upload/transcoding notifications received during a request still need a fresh result afterwards.
            if (browser.pending) browser.refreshAfterLoad = true;
            else MediaVault.load(browser, {background: true});
        });
    },

    error: function (xhr) {
        launchToast('danger', trans('Error'), (xhr.responseJSON || {}).message || trans('The media could not be updated.'));
    }
};

$(function () { MediaVault.init(); });
