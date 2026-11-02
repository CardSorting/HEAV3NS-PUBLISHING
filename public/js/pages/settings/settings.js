/**
 * General settings component
 */
"use strict";
/* global app, trans, launchToast, initStickyComponent */

$(function () {
    document.querySelectorAll('.settings-data-page img[data-fallback-src]').forEach(function (avatar) {
        const useFallback = function () {
            avatar.removeEventListener('error', useFallback);
            avatar.src = avatar.dataset.fallbackSrc;
        };

        avatar.addEventListener('error', useFallback);
        if (avatar.complete && avatar.naturalWidth === 0) {
            useFallback();
        }
    });
});

$(window).scroll(function () {
    if (document.querySelector('.main-wrapper')) {
        initStickyComponent('.settings-menu-wrapper','sticky-sm');
    }
});

// eslint-disable-next-line no-unused-vars
var GeneralSettings = {

    /**
     * Updates general (whitelisted) settings flags
     * @param key
     * @param value
     */
    updateFlagSetting: function (key,value) {
        $.ajax({
            type: 'POST',
            data: {
                'key': key,
                'value': value
            },
            dataType: 'json',
            url: app.baseUrl+'/my/settings/flags/save',
            success: function (result) {
                if(result.success){
                    launchToast('success',trans('Success'),trans('Setting saved'));
                }
                else{
                    launchToast('danger',trans('Error'),trans('Setting save failed'));
                }
            },
            error: function () {
                launchToast('danger',trans('Error'),trans('Setting save failed'));
            }
        });
    }
};
