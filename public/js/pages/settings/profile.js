/**
 * Settings profile component
 */
"use strict";
/* global app, mediaSettings, launchToast, EasyMDE, bioConfig, AiSuggestions, trans */

$(function () {
    ProfileSettings.initUploader('avatar');
    ProfileSettings.initUploader('cover');

    $('.avatar-holder').on('tap', function (e) {
        e.preventDefault();
        $('.avatar-holder .actions-holder').toggleClass('d-none');
    });

    $('.avatar-holder').on({
        mouseenter: function () {
            $('.avatar-holder .actions-holder').removeClass('d-none');
        },
        mouseleave: function () {
            $('.avatar-holder .actions-holder').addClass('d-none');
        }
    });

    $('.profile-cover-bg').on('tap', function (e) {
        e.preventDefault();
        $('.profile-cover-bg .actions-holder').toggleClass('d-none');
    });

    $('.profile-cover-bg').on({
        mouseenter: function () {
            $('.profile-cover-bg .actions-holder').removeClass('d-none');
        },
        mouseleave: function () {
            $('.profile-cover-bg .actions-holder').addClass('d-none');
        }
    });

    if (bioConfig.allow_profile_bio_markdown) {
        let toolbar = ["bold", "italic", "|", "code", "quote", "unordered-list", "ordered-list"];
        if (bioConfig.allow_profile_bio_markdown_links) {
            toolbar = ["bold", "italic", "|", "code", "quote", "link", "unordered-list", "ordered-list"];
        }
        // eslint-disable-next-line no-unused-vars
        ProfileSettings.mdeEditor = new EasyMDE({
            element: document.getElementById("bio"),
            toolbar: toolbar,
            spellChecker: false,
            styleSelectedText: false,
            status: [],
        });
    }

    if (app.ai_text_enabled) {
        AiSuggestions.init({ target: '#bio', type: 'profile_bio' });
    }

    if ($('#ai_traits').length && $.fn.selectize) {
        $('#ai_traits').selectize({
            persist: true,
            create: function (input) {
                input = (input || '').trim();
                if (!input) return false;

                // keep tags reasonable (UX guard; backend is source of truth)
                if (input.length > 24) input = input.substring(0, 24);

                return { value: input, text: input };
            },
            maxItems: 5,
            delimiter: ',',
            placeholder: trans('Add up to 5 traits...')
        });
    }

    // Social links
    ProfileSettings.initSocialLinks();
});

/**
 * ProfileSettings Class
 */
var ProfileSettings = {

    dropzones: {},
    mdeEditor: null,

    // --- Social links config ---
    socialLinks: {
        wrapperSelector: '#social-links-wrapper',
        addBtnSelector: '#add-social-link',
        rowSelector: '.social-link-row',
        removeBtnSelector: '.remove-social-link',
        platformSelector: 'select.social-platform',
        valueSelector: 'input.social-value',
        templateSelector: '#social-link-template',
    },

    /**
     * Instantiates the media uploader for avatar / cover
     */
    initUploader: function (type) {
        let selector = '.profile-cover-bg';
        if (type === 'avatar') {
            selector = '.avatar-holder';
        }

        ProfileSettings.dropzones[type] = new window.Dropzone(selector, {
            url: app.baseUrl + '/my/settings/profile/upload/' + type,
            previewTemplate: document.querySelector('.dz-preview').innerHTML.replace('d-none', ''),
            paramName: "file",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            clickable: [`${selector} .upload-button`],
            maxFilesize: mediaSettings.max_file_upload_size,
            addRemoveLinks: true,
            dictRemoveFile: "x",
            acceptedFiles: mediaSettings.allowed_file_extensions,
            autoDiscover: false,

            // SHOW loader when upload starts
            sending: function (file) {
                ProfileSettings.showAssetLoader(type);
                file.previewElement.innerHTML = "";
            },

            // HIDE loader on success
            success: function (file, response) {
                $(selector + ' .card-img-top').attr('src', response.assetSrc);

                if (type === 'avatar') {
                    $('.user-avatar').attr('src', response.assetSrc);
                }

                file.previewElement.innerHTML = "";
                ProfileSettings.hideAssetLoader(type);
            },

            // HIDE loader on error
            error: function (file, errorMessage) {
                if (typeof errorMessage === 'string') {
                    launchToast('danger', 'Error ', errorMessage, 'now');
                } else {
                    launchToast('danger', 'Error ', errorMessage.errors.file, 'now');
                }

                file.previewElement.innerHTML = "";
                ProfileSettings.hideAssetLoader(type);
            },

            dictInvalidFileType: trans("You can't upload files of this type."),
        });
    },

    /**
     * Social links init: add/remove/reindex
     */
    initSocialLinks: function () {
        const cfg = ProfileSettings.socialLinks;
        const $wrapper = $(cfg.wrapperSelector);
        const $addBtn = $(cfg.addBtnSelector);

        if (!$wrapper.length || !$addBtn.length) {
            // Blade block not present on page
            return;
        }

        // Remove row
        $wrapper.on('click', cfg.removeBtnSelector, function (e) {
            e.preventDefault();
            $(this).closest(cfg.rowSelector).remove();

            ProfileSettings.reindexSocialLinks();
            ProfileSettings.refreshDisabledPlatforms();

            // Re-run reindex to update the Add button disabled state after options were re-enabled
            ProfileSettings.reindexSocialLinks();
        });

        // Platform changed: autofill base url if empty + refresh duplicates
        $wrapper.on('change', cfg.platformSelector, function () {
            const $row = $(this).closest(cfg.rowSelector);
            const $input = $row.find(cfg.valueSelector);

            // Clear invalid state on platform
            $(this).removeClass('is-invalid');

            const platform = ($(this).val() || '').trim();
            if (!platform) {
                // If user reset platform, also clear URL invalid (optional)
                // $input.removeClass('is-invalid');
                ProfileSettings.refreshDisabledPlatforms();
                return;
            }

            const baseUrl = $(this).find('option:selected').data('base-url') || '';

            // Autofill if empty
            if (!$input.val() && baseUrl) {
                $input.val(baseUrl);
            }

            // IMPORTANT: clear URL invalid if it has *any* value now
            if (($input.val() || '').trim()) {
                $input.removeClass('is-invalid');
            }

            ProfileSettings.refreshDisabledPlatforms();
        });


        // Add new row
        $addBtn.on('click', function (e) {
            e.preventDefault();

            const check = ProfileSettings.canAddAnotherSocialRow();

            if (!check.ok) {
                if (check.reason === 'max') {
                    launchToast('danger', 'Error ', trans('You have added all available social networks.'), 'now');
                    return;
                }

                launchToast('danger', 'Error ', trans('Please select a platform and enter a valid URL before adding another link.'), 'now');
                ProfileSettings.markLastRowInvalid();
                return;
            }

            ProfileSettings.addSocialLinkRow();
            ProfileSettings.reindexSocialLinks();
            ProfileSettings.refreshDisabledPlatforms();
        });


        // If user edits URL, remove invalid styling
        $wrapper.on('input change', cfg.valueSelector, function () {
            $(this).removeClass('is-invalid');
        });

        // Initial
        ProfileSettings.reindexSocialLinks();
        ProfileSettings.refreshDisabledPlatforms();
    },

    /**
     * Adds a new social link row (HTML expected to exist in a hidden template)
     */
    addSocialLinkRow: function () {
        const cfg = ProfileSettings.socialLinks;
        const $wrapper = $(cfg.wrapperSelector);

        const $tpl = $(cfg.templateSelector);
        if ($tpl.length) {
            $wrapper.append($tpl.html());
            return;
        }

        // Fallback: clone first row
        const $first = $wrapper.find(cfg.rowSelector).first();
        if ($first.length) {
            const $clone = $first.clone();
            $clone.find(cfg.platformSelector).val('');
            $clone.find(cfg.valueSelector).val('');
            $wrapper.append($clone);
        }
    },

    /**
     * Reindex input names social_links[i][platform/value] and handle remove button disabled state
     */
    reindexSocialLinks: function () {
        const cfg = ProfileSettings.socialLinks;
        const $wrapper = $(cfg.wrapperSelector);
        const $rows = $wrapper.find(cfg.rowSelector);

        $rows.each(function (i) {
            const $row = $(this);

            const $platform = $row.find(cfg.platformSelector);
            const $value = $row.find(cfg.valueSelector);

            // Ensure classes exist
            $platform.addClass('social-platform');
            $value.addClass('social-value');

            $platform.attr('name', `social_links[${i}][platform]`);
            $value.attr('name', `social_links[${i}][value]`);
        });

        // Disable remove when only one row exists
        const disableRemove = $rows.length <= 1;
        $rows.find(cfg.removeBtnSelector).prop('disabled', disableRemove);

        // Disable "Add another link" when we reached max allowed platforms
        const allowedCount = ProfileSettings.getAllowedPlatformCount();
        const rowCount = $rows.length;
        $(cfg.addBtnSelector).prop('disabled', allowedCount > 0 && rowCount >= allowedCount);

    },

    /**
     * Optional: Prevent selecting the same platform twice by disabling options already chosen.
     */
    refreshDisabledPlatforms: function () {
        const cfg = ProfileSettings.socialLinks;
        const $rows = $(cfg.wrapperSelector).find(cfg.rowSelector);
        if (!$rows.length) return;

        // collect selected values
        const selected = [];
        $rows.each(function () {
            const val = ($(this).find(cfg.platformSelector).val() || '').trim();
            if (val) selected.push(val);
        });

        // reset all disabled states first
        $rows.find(cfg.platformSelector).each(function () {
            $(this).find('option').prop('disabled', false);
        });

        // disable already-selected in other selects
        $rows.find(cfg.platformSelector).each(function () {
            const $select = $(this);
            const current = ($select.val() || '').trim();

            selected.forEach(function (val) {
                if (!val) return;
                if (val === current) return;
                $select.find(`option[value="${val}"]`).prop('disabled', true);
            });
        });
    },

    /**
     * UX guardrail: allow adding a row only if the last row is either completely empty,
     * or has a selected platform AND a valid URL.
     */
    canAddAnotherSocialRow: function () {
        const cfg = ProfileSettings.socialLinks;
        const $wrapper = $(cfg.wrapperSelector);
        const $rows = $wrapper.find(cfg.rowSelector);

        const allowedCount = ProfileSettings.getAllowedPlatformCount();

        // Hard cap: can't exceed allowed platforms (if we can detect them)
        if (allowedCount > 0 && $rows.length >= allowedCount) {
            return { ok: false, reason: 'max' };
        }

        const $lastRow = $rows.last();
        if (!$lastRow.length) return { ok: true };

        // Require last row to be complete + valid before adding another
        if (!ProfileSettings.isRowCompleteAndValid($lastRow)) {
            return { ok: false, reason: 'incomplete' };
        }

        return { ok: true };
    },


    /**
     * Mark last row fields invalid to guide the user.
     */
    markLastRowInvalid: function () {
        const cfg = ProfileSettings.socialLinks;
        const $lastRow = $(cfg.wrapperSelector).find(cfg.rowSelector).last();
        if (!$lastRow.length) return;

        const $platform = $lastRow.find(cfg.platformSelector);
        const $value = $lastRow.find(cfg.valueSelector);

        const platform = ($platform.val() || '').trim();
        const url = ($value.val() || '').trim();

        if (!platform) $platform.addClass('is-invalid');
        if (!url) $value.addClass('is-invalid');

        // If url exists but invalid, still mark
        if (url) {
            try { new URL(url); }
            catch (e) { $value.addClass('is-invalid'); }
        }
    },

    getAllowedPlatformCount: function () {
        const cfg = ProfileSettings.socialLinks;
        const $selects = $(cfg.wrapperSelector).find(cfg.platformSelector);
        if (!$selects.length) return 0;

        const $first = $selects.first();

        // Count all real options (exclude placeholder), ignore disabled state
        return $first.find('option').filter(function () {
            const val = ($(this).attr('value') || '').trim();
            return val !== '';
        }).length;
    },

    isRowCompleteAndValid: function ($row) {
        const cfg = ProfileSettings.socialLinks;

        const platform = ($row.find(cfg.platformSelector).val() || '').trim();
        const $input = $row.find(cfg.valueSelector);
        const url = ($input.val() || '').trim();

        if (!platform || !url) return false;

        // Use browser validation for type="url"
        // (works well and matches what the form will accept)
        if (typeof $input[0].checkValidity === 'function') {
            return $input[0].checkValidity();
        }

        // Fallback
        try {
            const parsed = new URL(url);
            return !!parsed.hostname;
        } catch (e) {
            return false;
        }
    },


    /**
     * Removes the user asset ( avatar / cover )
     * @param type
     */
    removeUserAsset(type) {
        $.ajax({
            type: 'POST',
            url: app.baseUrl + '/my/settings/profile/remove/' + type,
            success: function (result) {
                launchToast('success', 'Success ', result.message, 'now');
                $('.profile-cover-bg img').attr('src', result.data.cover);
                $('.avatar-holder img').attr('src', result.data.avatar);
            },
            error: function (result) {
                // eslint-disable-next-line no-console
                console.warn(result);
            }
        });
    },

    generateUserAsset: function (type) {
        ProfileSettings.showAssetLoader(type);

        $.ajax({
            type: 'POST',
            url: app.baseUrl + '/my/settings/assets/ai/' + type,
            success: function (res) {
                if (res.success && res.assetSrc) {
                    if (type === 'cover') {
                        $('.profile-cover-bg img').attr('src', res.assetSrc);
                    } else {
                        $('.avatar-holder img').attr('src', res.assetSrc);
                        $('.user-avatar').attr('src', res.assetSrc);
                    }
                    launchToast('success', trans('Success'), trans('Generated successfully'));
                } else {
                    launchToast('danger', trans('Error'), res.message || 'Generation failed');
                }
            },
            error: function (xhr) {
                launchToast(
                    'danger',
                    trans('Error'),
                    xhr.responseJSON?.message || 'Generation failed'
                );
            },
            complete: function () {
                ProfileSettings.hideAssetLoader(type);
            }
        });
    },

    showAssetLoader: function (type) {
        var container = type === 'cover'
            ? $('.profile-cover-bg')
            : $('.avatar-holder');

        container.addClass('loading');
        container.find('.profile-asset-loading').removeClass('d-none');
        container.find('.actions-holder').addClass('d-none');
    },

    hideAssetLoader: function (type) {
        var container = type === 'cover'
            ? $('.profile-cover-bg')
            : $('.avatar-holder');

        container.removeClass('loading');
        container.find('.profile-asset-loading').addClass('d-none');
        container.find('.actions-holder').removeClass('d-none');
    },

};
