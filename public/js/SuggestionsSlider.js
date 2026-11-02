/**
 * Class for handling feed members suggestions slider
 */
"use strict";
/* global  app, launchToast, trans, Swiper, sliderConfig*/

$(function () {

});

var SuggestionsSlider = {

    /**
     * Instantiates the suggested members slider
     * @returns {*}
     */
    init: function (container = '#suggestions-box') {
        let localSwiperConfig = {};
        if(container === '#suggestions-box'){
            localSwiperConfig = sliderConfig.suggestions;
        }
        if(container === '#suggestions-box-expired'){
            localSwiperConfig = sliderConfig.expiredSubs;
        }
        let swiperConfig = {
            pagination: {
                el: container + " .swiper-pagination",
                clickable: true,
                dynamicBullets: true,
            },
        };
        if (localSwiperConfig.autoslide === true) {
            swiperConfig.autoplay = { delay: 10000 };
        }
        return new Swiper(container + " .mySwiper", swiperConfig);

    },

    /**
     * Loads suggestions, based on filters
     * @param filters
     */
    loadSuggestions: function (filters = {}) {
        $.ajax({
            type: 'POST',
            data: {filters},
            dataType: 'json',
            url: app.baseUrl+'/suggestions/members',
            success: function (result) {
                if(result.success){
                    // launchToast('success',trans('Success'),'Setting saved');
                    SuggestionsSlider.appendSuggestionsResults(result.data);
                    launchToast('success',trans('Success'),trans('Suggestions list refreshed'));
                }
                else{
                    launchToast('danger',trans('Error'),trans('Error fetching suggestions'));
                }
            },
            error: function () {
                launchToast('danger',trans('Error'),trans('Error fetching suggestions'));
            }
        });
    },

    /**
     * Appends new suggestions to the widget
     * @param posts
     */
    appendSuggestionsResults: function(posts){
        $('#suggestions-box .suggestions-content').html('');
        $('#suggestions-box .suggestions-content').append(posts.html).fadeIn('slow');
        SuggestionsSlider.init();
    },

};

