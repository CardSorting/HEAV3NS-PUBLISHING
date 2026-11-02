/**
 *
 * Main App Component
 *
 */
"use strict";
/* global app, bootstrap, CookieConsent, PWABanner */

// Init
$(function () {

    log('🚀 © JustFans Loaded © 🚀');
    initHomepageNavbarState();
    initMobileUserMenu();
    initPasswordRevealFields();
    initUserMoreMenuFocus();

    // Instantiating default actions if installed
    if(typeof app !== 'undefined'){

        if(app.showCookiesBox === true){
            initCookieBox();
        }

        // Check if age verification dialog should be enabled
        if (isConsentDialogEnabled())
        {
            initConsentDialog();
        }

        // Auto-including the CSRF token in all AJAX Requests
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
        });

        if(app.debug){
            // Globally handling AJAX requests, especially for handling expired tokens and sesisions
            $(document).ajaxError(function (event, jqXHR) {
                if (jqXHR.status === 0) {
                    log('Not connect.n Verify Network.', 'error');
                } else if (jqXHR.status === 404) {
                    log('Requested page not found. [404]', 'error');
                } else if (jqXHR.status === 500) {
                    log('Internal Server Error [500].', 'error');
                } else if (jqXHR.status === 401) {
                    log('Session expired. Redirecting you to refresh the session.', 'error');
                    // redirect(app.baseUrl);
                } else if (jqXHR.status === 408) {
                    reload();
                } else {
                    log('Uncaught Error.n' + jqXHR.responseText, 'error');
                }
            });
        }


        // Displaying error messages for expired sessions
        if (app.sessionStatus === 'expired') {
            launchToast('info', 'Session expired ', 'Page refreshed', 'now');
        }

        // Dark mode switcher event
        initDarkModeSwitcher();

        // Initialize tooltips
        initTooltips();

        // Make sure TOS is agreed when registering with social
        initSocialLoginAgreementChecker();

        if(app.pwa_enabled && app.pwa_install_prompt_enabled){
            PWABanner.init();
        }

    }

});

$(window).scroll(function () {
    if(typeof skipDefaultScrollInits === 'undefined'){
        if($('.side-menu').length){
            initStickyComponent('.side-menu','sticky');
        }
    }
});

/**
 * Watching for ctrl+s events within the app
 */
document.addEventListener('keydown', function (e) {
    // Check for Ctrl+S (or Cmd+S on Mac)
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        // Prevent browser "Save Page" action
        e.preventDefault();

        // Check if modal is currently visible
        if ($('#language-selector-dialog').hasClass('show')) {
            setUserLanguage();
        }
    }
});

function initSocialLoginAgreementChecker(){
    if(window.location.href.indexOf('register') >= 0){
        // Forcing TOS checkbox for social auth
        $('.social-login-links a').on('click', function (event) {
            if($('#tosAgree').is(':checked') === false){
                event.preventDefault();
                $('#tosAgree').addClass('is-invalid');
            }
        });
    }
}

function initPasswordRevealFields(){
    $(document).off('show.bs.tooltip.passwordReveal').on('show.bs.tooltip.passwordReveal', '.password-reveal-toggle', function (event) {
        if ($(this).data('passwordRevealSuppressTooltip')) {
            event.preventDefault();
        }
    });

    $(document).off('mouseleave.passwordReveal').on('mouseleave.passwordReveal', '.password-reveal-toggle', function () {
        $(this).data('passwordRevealSuppressTooltip', false);
    });

    $(document).off('click.passwordReveal').on('click.passwordReveal', '.password-reveal-toggle', function () {
        var toggle = $(this);
        var wrapper = toggle.closest('.password-reveal-field');
        var field = wrapper.find('input').first();
        var isHidden = field.attr('type') === 'password';
        var title = isHidden ? trans('Hide password') : trans('Show password');

        if ($.fn.tooltip) {
            toggle.tooltip('hide').tooltip('dispose');
        }

        field.attr('type', isHidden ? 'text' : 'password');
        toggle.find('.password-reveal-hide').toggleClass('d-none', !isHidden);
        toggle.find('.password-reveal-show').toggleClass('d-none', isHidden);
        toggle.attr('aria-label', title).attr('title', title).removeAttr('aria-describedby');
        toggle.data('passwordRevealSuppressTooltip', true);

        if ($.fn.tooltip) {
            toggle.tooltip({trigger: 'hover'});
        }

        toggle.blur();
    });
}

function initDarkModeSwitcher(){
    $('.dark-mode-switcher').on('click', function (event) {
        event.preventDefault();
        if (window.AppTheme && typeof window.AppTheme.toggle === 'function') {
            window.AppTheme.toggle();
        }
    });
}

function initUserMoreMenuFocus(){
    let returnTarget = null;
    let languageDialog = $('#language-selector-dialog');

    if(!languageDialog.length){
        return;
    }

    $('.user-more-language-switcher').on('click', function () {
        returnTarget = $(this).closest('.dropup').find('[data-bs-toggle="dropdown"]').get(0) || null;
    });

    languageDialog.on('hidden.bs.modal', function () {
        if(returnTarget){
            returnTarget.focus();
            returnTarget = null;
        }
    });
}

function isConsentDialogEnabled(){
    return (app.age_gate_driver === 'built_in' || app.enable_age_verification_dialog) &&
        !isSlugInUrl(app.tosPageSlug) &&
        !isSlugInUrl(app.privacyPageSlug) &&
        window.location.href.indexOf('invoices') === -1;
}

function initConsentDialog(){
    const classes = 'body .flex-fill, footer, .global-announcement-banner, .navbar';
    if (!getCookie('site_entry_approval')) {
        // Show modal and add blur class to multiple elements
        $('#site-entry-approval-dialog').modal('show');
        const elementsToBlur = $(classes);
        elementsToBlur.addClass('blurred');
    }

    // Remove blur class when modal is hidden
    $('#site-entry-approval-dialog').on('hidden.bs.modal', function () {
        const elementsToBlur = $(classes);
        elementsToBlur.removeClass('blurred');
    });
}

function initCookieBox(){
    const marketingEnabled = !!(app.conversionTracking && app.conversionTracking.enabled);
    const cookieConfig = {
        categories: {
            necessary: { enabled: true, readOnly: true },
            analytics: {}
        },

        guiOptions: {
            consentModal: {
                layout: 'box inline',
                position: 'bottom right',
                equalWeightButtons: true,
                flipButtons: false
            },
            preferencesModal: {
                layout: 'box',
                equalWeightButtons: true,
                flipButtons: false
            }
        },

        language: {
            default: 'en',
            translations: {
                en: {
                    consentModal: {
                        title: trans('We use cookies'),
                        description: trans('We use cookies to improve your experience. You can accept, reject, or manage preferences.'),
                        acceptAllBtn: trans('Accept all'),
                        acceptNecessaryBtn: trans('Reject non-essential'),
                        showPreferencesBtn: trans('Manage preferences')
                    },

                    preferencesModal: {
                        title: trans('Cookie preferences'),
                        acceptAllBtn: trans('Accept all'),
                        acceptNecessaryBtn: trans('Reject non-essential'),
                        savePreferencesBtn: trans('Save preferences'),
                        closeIconLabel: trans('Close'),

                        sections: [
                            {
                                description: `
                                  <p class="cc-intro">
                                    ${trans(marketingEnabled
        ? 'Essential cookies are always on. Choose whether to allow analytics and marketing cookies.'
        : 'Essential cookies are always on. Choose if you want analytics cookies too.')}
                                  </p>
                                `
                            },
                            {
                                title: trans('Essential cookies'),
                                description: trans('Required for the site to work properly.'),
                                linkedCategory: 'necessary'
                            },
                            {
                                title: trans('Analytics cookies'),
                                description: trans('Help us understand usage and improve the site.'),
                                linkedCategory: 'analytics'
                            },
                            {
                                title: trans('More information'),
                                description: `
                                  <p class="cc-moreinfo">
                                    ${trans('Learn more about cookies or get in touch if you have questions.')}
                                  </p>
                                  <ul class="cc-links">
                                    <li><a href="${app.routes.privacy}">${trans('Privacy & cookies')}</a></li>
                                    <li><a href="${app.routes.contact}">${trans('Contact us')}</a></li>
                                  </ul>
                                `
                            }
                        ]


                    }
                }
            }
        }
    };

    if (marketingEnabled) {
        // Ask again when expanding the notice from subscriptions to all selected purchases.
        cookieConfig.revision = 1;
        cookieConfig.categories.marketing = {};
        cookieConfig.language.translations.en.preferencesModal.sections.splice(3, 0, {
            title: trans('Marketing cookies'),
            description: trans('Link visits and purchases to marketing campaigns. Optional and disabled until you consent.'),
            linkedCategory: 'marketing'
        });
    }

    CookieConsent.run(cookieConfig);

}

/**
 * Log function sugar syntax
 * @param v
 */
function log(v,type = 'log') {
    if(typeof app !== 'undefined' && app.debug){
        switch (type) {
        case 'info':
            // eslint-disable-next-line no-console
            console.info(v);
            break;
        case 'log':
            // eslint-disable-next-line no-console
            console.log(v);
            break;
        case 'warn':
            // eslint-disable-next-line no-console
            console.warn(v);
            break;
        case 'error':
            // eslint-disable-next-line no-console
            console.error(v);
            break;
        }
    }
    return true;
}

/**
 * Instantiates tooltips
 */
function initTooltips(){
    var tooltipSelector = '[data-bs-toggle="tooltip"], .to-tooltip';
    var supportsPointerQuery = window.matchMedia && window.matchMedia('(hover: hover)').matches !== window.matchMedia('(hover: none)').matches;
    var isCoarsePointer = supportsPointerQuery && window.matchMedia('(hover: none), (pointer: coarse)').matches;
    var hasTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

    if (isCoarsePointer || (!supportsPointerQuery && hasTouch)) {
        $(tooltipSelector).tooltip('dispose');
        $('.tooltip').remove();
        $(tooltipSelector).filter('[data-tooltip-touch="true"]').tooltip();
        return;
    }

    $(tooltipSelector).tooltip();
}

/**
 * Updates either an active Bootstrap 5 tooltip or the title used at initialization.
 */
// eslint-disable-next-line no-unused-vars
function updateTooltipTitle(element, title){
    var node = $(element).get(0);
    if (!node) return;

    var tooltip = typeof bootstrap !== 'undefined' ? bootstrap.Tooltip.getInstance(node) : null;
    if (tooltip) {
        tooltip.dispose();
    }

    $(node)
        .attr('aria-label', title)
        .attr('title', title)
        .removeAttr('aria-describedby');

    if (tooltip) {
        bootstrap.Tooltip.getOrCreateInstance(node);
    }
}

/**
 * Redirect function
 * @param url
 */
function redirect(url) {
    window.location.href = url;
}

/**
 * Submits the search form
 */
// eslint-disable-next-line no-unused-vars
function submitSearch() {
    $('.search-box-wrapper').submit();
}

/**
 * Page reload function
 */
function reload() {
    return window.location.reload();
}

/**
 * Copy to clipboard function
 * @param textToCopy
 */
function copyToClipboard(textToCopy, container = 'body') {
    let $temp = $("<textarea>");
    $(container).append($temp);
    $temp.val(textToCopy).select();
    document.execCommand("copy");
    $temp.remove();
}

/**
 * Attaches scroll handlers & sticky behaviour to desired components
 * @param component
 * @param stickyClass
 */
function initStickyComponent(component,stickyClass) {
    let sticky = false;
    let top = $(window).scrollTop();
    if ($(".main-wrapper").offset().top < top) {
        $(component).addClass(stickyClass);
        // eslint-disable-next-line no-unused-vars
        sticky = true;
    } else {
        $(".side-menu, .suggestions-box").removeClass(stickyClass);
    }
}

/**
 * Go to login via UI redirect
 */
// eslint-disable-next-line no-unused-vars
function goToLogin() {
    redirect(app.baseUrl + '/login');
}

/**
 * Accepts adult content confirm dialog
 */
// eslint-disable-next-line no-unused-vars
function acceptSiteEntry() {
    setCookie('site_entry_approval',true,90);
    $('#site-entry-approval-dialog').modal('hide');
}

/**
 * Set cookie
 * @param key
 * @param value
 * @param expiry
 */
function setCookie(key, value, expiry) {
    var expires = new Date();
    expires.setTime(expires.getTime() + (expiry * 24 * 60 * 60 * 1000));
    document.cookie = key + '=' + value + ';expires=' + expires.toUTCString() + ';path=/';
}

/**
 * Get cookie value
 * @param key
 * @returns {any}
 */
function getCookie(key) {
    var keyValue = document.cookie.match('(^|;) ?' + key + '=([^;]*)(;|$)');
    return keyValue ? keyValue[2] : null;
}

/**
 * Delete cookie
 * @param key
 */
// eslint-disable-next-line no-unused-vars
function eraseCookie(key) {
    var keyValue = getCookie(key);
    setCookie(key, keyValue, '-1');
}

/**
 * Refresh the active theme without navigation.
 */
// eslint-disable-next-line no-unused-vars
function reloadTheme() {
    if (window.AppTheme && typeof window.AppTheme.refresh === 'function') {
        return window.AppTheme.refresh();
    }

    return null;
}

/**
 * Launches custom, stackable and dismisable toasts
 * @param type
 * @param title
 * @param message
 * @param subtitle
 */
function launchToast(type, title, message, subtitle = '') {
    $.toast({
        type: '',
        title: title,
        subtitle: subtitle,
        content: message,
        dismissible: true,
        indicator: {
            type: type
        },
        delay: 5000,
    });
}

/**
 * Opens up device share API or fallbacks to URL copy
 * @param url
 */
// eslint-disable-next-line no-unused-vars
function shareOrCopyLink(url = false) {
    if (url === false) {
        url = window.location.href;
    }
    if (navigator.share) {
        navigator.share({
            title: document.title,
            url: url
        })
            // eslint-disable-next-line no-console
            .then(() => console.log('Successful share'))
            // eslint-disable-next-line no-console
            .catch(error => console.log('Error sharing:', error));
    } else {
        copyToClipboard(url);
        launchToast('success', trans('Success'), trans('Link copied to clipboard')+'.', 'now');
    }
}

/**
 * Auto Adjusts textareas on resize
 * @param el
 */
// eslint-disable-next-line no-unused-vars
function textAreaAdjust(el) {
    el.style.height = (el.scrollHeight > el.clientHeight) ? (el.scrollHeight) + "px" : "45px";
}

/**
 * Filters up user received notifications ( via sockets )
 * @returns {string}
 */
// eslint-disable-next-line no-unused-vars
function getNotificationsActiveFilter() {
    let activeType = '';
    // get active filter if exists
    if (window.location.href.indexOf('/likes') >= 0) {
        activeType = '/likes';
    } else if (window.location.href.indexOf('/messages') >= 0) {
        activeType = '/messages';
    } else if (window.location.href.indexOf('/subscriptions') >= 0) {
        activeType = '/subscriptions';
    } else if (window.location.href.indexOf('/tips') >= 0) {
        activeType = '/tips';
    } else if (window.location.href.indexOf('/promos') >= 0) {
        activeType = '/promos';
    }

    return activeType;
}

/**
 * Method used for translating locale strings
 * @param key
 * @param replace
 * @returns {T|*}
 */
// eslint-disable-next-line no-unused-vars
function trans(key, replace = {}) {
    var dict = window.translations || {};
    var translation = dict[key];

    if (translation === null || typeof translation === "undefined") {
        translation = key;
    }

    Object.keys(replace || {}).forEach(function (placeholder) {
        var value = String(replace[placeholder]);

        // Replace Laravel-style ":user"
        translation = translation.split(":" + placeholder).join(value);

        // (Optional) Replace "{user}" too
        translation = translation.split("{" + placeholder + "}").join(value);
    });

    return translation;
}

/**
 * Method used for translating locale strings
 * Supports multiple choices translations
 * @param key
 * @param replace
 * @returns {T|*}
 */
// eslint-disable-next-line no-unused-vars
function trans_choice(key, count = 1, replace = {})
{
    let keyValue = window.translations[key];
    if(typeof keyValue === 'undefined'){
        return key;
    }
    const translations = keyValue.split('|');
    let translation = count > 1 || count === 0 ? translations[1] : translations[0];
    translation = translation.replace('[2,*]','');

    for (var placeholder in replace) {
        translation = translation.replace(`:${placeholder}`, replace[placeholder]);
    }
    return translation;
}

/**
 * Updates button state, adding loading icon to it and disabling it
 * @param state
 * @param buttonElement
 */
// eslint-disable-next-line no-unused-vars
function updateButtonState(state, buttonElement, buttonContent = false, loadingColor = 'primary'){
    if(state === 'loaded'){
        if(buttonContent){
            buttonElement.html(buttonContent);
        }
        else{
            buttonElement.html('<div class="d-flex justify-content-center align-items-center"><ion-icon name="paper-plane"></ion-icon></div>');
        }
        buttonElement.removeClass('disabled');
    }
    else{
        buttonElement.html( `<div class="d-flex justify-content-center align-items-center">
            <div class="spinner-border text-${loadingColor} spinner-border-sm" role="status">
            <span class="visually-hidden">${trans('Loading...')}</span>
            </div>
            ${(buttonContent !== false ? '<div class="ms-2">'+buttonContent+'</div>' : '')}
            </div>`);
        buttonElement.addClass('disabled');
    }
}

// eslint-disable-next-line no-unused-vars
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

/**
 * Re-sends the user email verification
 * @param callback
 */
// eslint-disable-next-line no-unused-vars
function sendEmailConfirmation(callback = function(){}){
    $('.unverified-email-box').attr('onClick','');
    $.ajax({
        url:app.baseUrl +'/resendVerification',
        type:'POST',
        success : function(){
            $('.unverified-email-box').fadeOut();
            launchToast('success', trans('Success'), trans('Confirmation email sent. Please check your inbox and spam folder.'), 'now');
            callback();
        },
        error: function () {

        }
    });
}

/**
 * Returns current bootstrap breakpoint to the JS side
 * @returns {{name: (string|string), index: number}|null}
 */
// eslint-disable-next-line no-unused-vars
function bootstrapDetectBreakpoint() {
    // cache some values on first call
    let breakpointNames = ["xl", "lg", "md", "sm", "xs"];
    let breakpointValues = [];
    for (const breakpointName of breakpointNames) {
        breakpointValues[breakpointName] = window.getComputedStyle(document.documentElement).getPropertyValue('--breakpoint-' + breakpointName);
    }
    let i = breakpointNames.length;
    for (const breakpointName of breakpointNames) {
        i--;
        if (window.matchMedia("(min-width: " + breakpointValues[breakpointName] + ")").matches) {
            return {name: breakpointName, index: i};
        }
    }
    return null;
}

/**
 * Increments the notifications badge by 1 or adds it if it doesnt exist
 */
// eslint-disable-next-line no-unused-vars
function incrementNotificationsCount(selector, value = 1) {
    if(parseInt($(selector).html()) + (value) > 0){
        $(selector).removeClass('d-none');
        $(selector).html(parseInt($(selector).html()) + (value));
    }
    else{
        $(selector).html('0');
        $(selector).addClass('d-none');
    }
}

/**
 * Checks if creator can post a PPV post within the limits
 */
// eslint-disable-next-line no-unused-vars
function passesMinMaxPPPostLimits(price) {
    let hasError = false;
    if(parseInt(price) < parseInt(app.min_ppv_post_price)){
        hasError = true;
    }
    if(parseInt(price) > parseInt(app.max_ppv_post_price)){
        hasError = true;
    }
    if(price.length <= 0){
        hasError = true;
    }
    return !hasError;
}

/**
 * Checks if creator can post a PPV message within the limits
 * @param price
 * @returns {boolean}
 */
// eslint-disable-next-line no-unused-vars
function passesMinMaxPPVMessageLimits(price) {
    let hasError = false;
    if(parseInt(price) < parseInt(app.min_ppv_message_price)){
        hasError = true;
    }
    if(parseInt(price) > parseInt(app.max_ppv_message_price)){
        hasError = true;
    }
    if(price.length <= 0){
        hasError = true;
    }
    return !hasError;
}


// eslint-disable-next-line no-unused-vars
function showDialog(dialogID){
    $('#' + dialogID).modal('show');
}

// eslint-disable-next-line no-unused-vars
function hideDialog(dialogID){
    $('#' + dialogID).modal('hide');
}

// eslint-disable-next-line no-unused-vars
function openLanguageSelectorDialog() {
    $('#language-selector-dialog').modal('show');
}

// eslint-disable-next-line no-unused-vars
function setUserLanguage() {
    let languageLink = app.baseUrl + '/language/' + $('#language_code').val();
    window.location.href = languageLink;
}

// eslint-disable-next-line no-unused-vars
function getWebsiteFormattedAmount(amount){
    let currencyPosition = app.currencyPosition;
    let currency = app.currencySymbol;

    return currencyPosition === 'left' ? currency + amount : amount + currency;
}

// eslint-disable-next-line no-unused-vars
function getTaxDescription(taxName, taxPercentage, taxType){
    if(taxType !== 'fixed') {
        let type = taxType === 'inclusive' ? ' incl.' : '';
        return taxName + " (" + taxPercentage + "%" + type + ")";
    }
    return taxName;
}

/**
 * Detects if CSS's line-clamp property is actively cutting off content
 * @param selector
 * @returns {boolean}
 */
// eslint-disable-next-line no-unused-vars
function multiLineOverflows(target) {
    const el = typeof target === 'string'
        ? document.querySelector(target)
        : (target && target.jquery ? target[0] : target);
    if (el) {
        return el.scrollHeight > el.clientHeight;
    }
    return false;
}

// eslint-disable-next-line no-unused-vars
function dimissGlobalAnnouncement(id) {
    $.ajax({
        url:app.baseUrl +'/markBannerAsSeen',
        type:'POST',
        data : {id: id},
        success : function(){
            // Placeholders
        },
        error: function () {
            // Placeholders
        }
    });
}
// eslint-disable-next-line no-unused-vars
function bindNoLongPressEvents() {
    // Capture also covers new media and runs before gallery handlers can stop propagation.
    document.addEventListener('contextmenu', preventMediaContextMenu, true);
}

function preventMediaContextMenu(event) {
    if (typeof app !== 'undefined' && app.feedDisableRightClickOnMedia === true
        && event.target instanceof Element && event.target.closest('.no-long-press')) {
        event.preventDefault();
    }
}

// Function to check if URL contains specific slug
function isSlugInUrl(slug) {
    return slug !== null && window.location.href.indexOf(slug) >= 0;
}

// eslint-disable-next-line no-unused-vars
function onEnter(containerSelector, callback) {
    $(document).on('keydown', `${containerSelector} input, ${containerSelector} select, ${containerSelector} textarea`, function (e) {
        if (e.ctrlKey || e.metaKey || e.altKey) return;
        if ((e.key === 'Enter' || e.which === 13) && this.tagName !== 'TEXTAREA') {
            e.preventDefault();
            callback.call(this, e);
        }
    });
}

/**
 * Used for fetching backend renderd SVG icons (wrapped into our own backend icons handler)
 * Also eliminates any flickering; as it's pre-rendered
 * Only needed for custom SVG; non ion-icon
 * @param name
 * @returns {string|string}
 */
// eslint-disable-next-line no-unused-vars
function getStoredSvg(name) {
    var svg = document.querySelector('#svg-store [data-icon="' + name + '"] .ion-icon-wrapper');
    return svg ? svg.outerHTML : '';
}

/** Render only server-confirmed administrators, never display names or role labels. */
// eslint-disable-next-line no-unused-vars
function renderUserStaffBadge(badge, overlay, iconHtml) {
    if (badge !== 'admin') {
        return '';
    }

    var label = trans('Admin');
    var safeLabel = String(label).replace(/[&<>"']/g, function (character) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character];
    });
    var icon = iconHtml || (typeof app !== 'undefined' && app.staffBadgeHtml) || getStoredSvg('staff') || '<ion-icon name="shield-checkmark-outline" aria-hidden="true"></ion-icon>';
    var color = overlay ? 'text-white' : '';

    return '<span class="user-staff-badge d-inline-flex align-items-center flex-shrink-0 ms-1 ' + color + '" role="img" aria-label="' + safeLabel + '" title="' + safeLabel + '">' + icon + '</span>';
}

function initHomepageNavbarState() {
    var navbar = document.querySelector('.app-navbar-home');
    var root = document.documentElement;
    var scrollOffset = 24;
    var ticking = false;

    if (!navbar) {
        return;
    }

    var toggleNavbarState = function () {
        var isScrolled = window.scrollY > scrollOffset;

        navbar.classList.toggle('app-navbar-scrolled', isScrolled);
        navbar.classList.toggle('app-navbar-top', !isScrolled);
        root.classList.toggle('home-navbar-scrolled', isScrolled);
    };

    var onScroll = function () {
        if (ticking) {
            return;
        }

        ticking = true;
        window.requestAnimationFrame(function () {
            toggleNavbarState();
            ticking = false;
        });
    };

    toggleNavbarState();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('pageshow', toggleNavbarState);
}

function initMobileUserMenu() {
    var mobileUserMenuTrigger = document.querySelector('.mobile-user-menu-trigger');
    var mobileUserMenu = document.querySelector('.mobile-user-menu');
    var navbar;

    if (!mobileUserMenuTrigger || !mobileUserMenu) {
        return;
    }

    navbar = mobileUserMenuTrigger.closest('.sticky-nav');

    var getBreakpoint = function () {
        if (!navbar) {
            return 768;
        }

        if (navbar.classList.contains('navbar-expand-lg')) {
            return 992;
        }

        return 768;
    };

    var closeMobileUserMenu = function () {
        if (!navbar) {
            return;
        }

        mobileUserMenu.hidden = true;
        mobileUserMenuTrigger.setAttribute('aria-expanded', 'false');
        navbar.classList.remove('mobile-user-menu-open');
    };

    var toggleMobileUserMenu = function () {
        var shouldOpen;

        if (!navbar || window.innerWidth >= getBreakpoint()) {
            return;
        }

        shouldOpen = mobileUserMenu.hidden;
        mobileUserMenu.hidden = !shouldOpen;
        mobileUserMenuTrigger.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        navbar.classList.toggle('mobile-user-menu-open', shouldOpen);
    };

    mobileUserMenuTrigger.addEventListener('click', function (event) {
        if (window.innerWidth >= getBreakpoint()) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        toggleMobileUserMenu();
    });

    document.addEventListener('click', function (event) {
        if (mobileUserMenu.hidden) {
            return;
        }

        if (mobileUserMenu.contains(event.target) || mobileUserMenuTrigger.contains(event.target)) {
            return;
        }

        closeMobileUserMenu();
    });

    window.addEventListener('resize', closeMobileUserMenu);
    window.addEventListener('scroll', closeMobileUserMenu, { passive: true });
}
