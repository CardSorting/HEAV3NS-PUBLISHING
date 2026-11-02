/**
 * Rates settings component
 */
"use strict";
/* global GeneralSettings */
$(function () {
    $("#profile_access_offer_date").on('change',function () {
        $("#is_offer").prop('checked',true);
    });

    $("#is_offer").on('change',function () {
        $("#profile_access_offer_date").val('');
    });

    $('#paid-profile').on('change',function () {
        const key = $(this).attr('id');
        const val = $(this).prop("checked");
        GeneralSettings.updateFlagSetting(key,val);

        $('.paid-profile-rates').toggleClass('d-none', !val);
        $('[data-free-profile-info]').toggleClass('d-none', val);
    });
});

// eslint-disable-next-line no-unused-vars
var RatesSettings = {

};
