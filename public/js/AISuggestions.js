/**
 * Ai Suggestions (global helper)
 * - No modal
 * - Frontend sends only "type"
 * - Backend uses translations + locale
 *
 * Usage:
 *   AiSuggestions.init({
 *     target: '#bio',
 *     editorGetter: () => (window.ProfileSettings ? ProfileSettings.mdeEditor : null),
 *   });
 *
 * Blade:
 *   <a href="#" class="ai-suggest-link" data-ai-type="profile_bio">AI Suggestion</a>
 *   <span class="ai-suggest-link" data-ai-type="post" data-ai-target="#post-text">...</span>
 */
"use strict";
/* global app, launchToast, trans */

/**
 * Safe loader helper for pills/links/spans that restores original HTML afterwards.
 * Does NOT touch your existing updateButtonState() helper.
 *
 * NOTE: you said you already have this elsewhere — if so, REMOVE this definition here
 * and keep only one global copy to avoid redeclare collisions.
 */
function updateElementLoadingState(state, $el, loadingText = null, loadingColor = 'primary') {
    if (!$el || !$el.length) return;

    // cache original html once
    if ($el.data('original-html') === undefined) {
        $el.data('original-html', $el.html());
    }

    if (state === 'loaded') {
        $el.html($el.data('original-html'));
        $el.removeClass('disabled').removeAttr('aria-disabled');
        $el.css('pointer-events', '');
        return;
    }

    $el.html(
        `<div class="d-flex justify-content-center align-items-center">
            <div class="spinner-border text-${loadingColor} spinner-border-sm" role="status">
                <span class="visually-hidden">${trans ? trans('Loading...') : 'Loading...'}</span>
            </div>
            ${loadingText ? `<div class="ms-2">${loadingText}</div>` : ''}
         </div>`
    );

    $el.addClass('disabled').attr('aria-disabled', 'true');
    $el.css('pointer-events', 'none');
}

var AiSuggestions = {
    // default target (can be overridden per init or per element)
    targetSelector: null,

    // optional: returns editor instance (EasyMDE) when present
    editorGetter: null,

    // prevent spam (global)
    busy: false,

    // internal
    _bound: false,

    init: function (opts) {
        opts = opts || {};
        this.targetSelector = opts.target || this.targetSelector || null;
        this.editorGetter = opts.editorGetter || this.editorGetter || null;

        this.bindGlobalClickHandler();
    },

    bindGlobalClickHandler: function () {
        if (this._bound) return;
        this._bound = true;

        // ✅ IMPORTANT: also capture clicks on children (icons/spans inside pills)
        $(document).on('click', '.ai-suggest-link, .ai-suggest-link *', function (e) {
            e.preventDefault();

            // always resolve the real clickable root
            var $el = $(e.target).closest('.ai-suggest-link');
            if (!$el.length) return;

            // If already disabled/loading, ignore
            if ($el.hasClass('disabled') || $el.data('ai-loading') || $el.attr('aria-disabled') === 'true') return;

            var type = ($el.data('ai-type') || '').toString().trim();
            if (!type) {
                // eslint-disable-next-line no-console
                console.warn('[AiSuggestions] Missing data-ai-type on element.');
                return;
            }

            AiSuggestions.suggest(type, $el);
        });
    },

    suggest: function (type, $el) {
        if (this.busy) return;
        this.busy = true;

        // mark this element as loading too (so even if busy changes, it won't double-trigger)
        if ($el && $el.length) {
            $el.data('ai-loading', true);

            // spinner loader (keeps your post-create pill behavior)
            var loadingLabel = ($el.data('loading-text') || (trans ? trans('Generating...') : 'Generating...'));
            updateElementLoadingState('loading', $el, loadingLabel, 'light');
        }

        $.ajax({
            type: 'POST',
            url: (app && app.baseUrl ? app.baseUrl : '') + '/suggestions/generate',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
            data: { type: type },

            success: function (res) {
                var text = (res && typeof res.message === 'string') ? res.message.trim() : '';
                if (text) {
                    AiSuggestions.apply(text, $el);
                    launchToast(
                        'success',
                        trans ? trans('Success') : 'Success',
                        trans ? trans('Generated successfully') : 'Generated successfully'
                    );
                } else {
                    launchToast(
                        'danger',
                        trans ? trans('Error') : 'Error',
                        (res && res.message) ? res.message : 'Generation failed'
                    );
                }
            },

            error: function (xhr) {
                var msg =
                    xhr?.responseJSON?.message ||
                    (xhr.status === 419 ? 'CSRF expired (419). Refresh and try again.' : 'Something went wrong.');

                launchToast('danger', trans ? trans('Error') : 'Error', msg);
            },

            complete: function () {
                AiSuggestions.busy = false;

                if ($el && $el.length) {
                    $el.data('ai-loading', false);
                    updateElementLoadingState('loaded', $el);
                }
            }
        });
    },

    /**
     * Applies generated text into:
     * 1) per-element target (data-ai-target)
     * 2) editorGetter (EasyMDE)
     * 3) init() targetSelector
     * 4) fallback #bio
     */
    apply: function (text, $sourceEl) {
        // 1) Per-element override target
        var perTarget = null;
        if ($sourceEl && $sourceEl.length) {
            perTarget = ($sourceEl.data('ai-target') || '').toString().trim();
        }

        // If target is explicitly set, use it first (useful on post create page)
        if (perTarget) {
            $(perTarget).val(text).trigger('input');
            return;
        }

        // 2) If an editor exists, prefer it
        var editor = null;
        if (typeof this.editorGetter === 'function') {
            try { editor = this.editorGetter(); } catch (e) { editor = null; }
        }

        if (editor && typeof editor.value === 'function') {
            editor.value(text);
            return;
        }

        // 3) fallback: init target selector
        if (this.targetSelector) {
            $(this.targetSelector).val(text).trigger('input');
            return;
        }

        // 4) last resort
        $('#bio').val(text).trigger('input');
    }
};
