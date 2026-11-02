/*
* Feed page & component
*/
"use strict";
/* global app, paginatorConfig, initialPostIDs, PostsPaginator, Post, getCookie */
/* global QRCode, StreamsPaginator, postsFilter, multiLineOverflows, profileVars, Autolinker */
/* global showDisabledPaywallWarning, launchToast, trans, Swiper */

$(function () {
    if(postsFilter === 'reels') {
        Profile.initBioHyperlinks();
        Post.setActivePage('profile');
        Post.initGalleryModule('.recent-media');
        if(multiLineOverflows('.description-content')){
            $('.profile-description-holder .show-more-actions').removeClass('d-none');
        }
        return;
    }

    if(typeof paginatorConfig !== 'undefined' && postsFilter !== 'streams'){
        if((paginatorConfig.total > 0 && paginatorConfig.total > paginatorConfig.per_page) && paginatorConfig.hasMore) {
            PostsPaginator.initScrollLoad();
        }
        PostsPaginator.init(paginatorConfig.next_page_url, '.posts-wrapper');
    }
    else if(postsFilter !== 'streams'){
        // eslint-disable-next-line no-console
        console.error('Pagination failed to initialize.');
    }

    if(postsFilter !== 'streams') {
        PostsPaginator.initPostsGalleries(initialPostIDs);
        PostsPaginator.initPostsHyperLinks();
    }
    Profile.initBioHyperlinks();
    if(postsFilter !== 'streams') {
        // Animate polls
        Post.animatePollResults();
    }

    Post.setActivePage('profile');
    if(postsFilter !== 'streams' && getCookie('app_prev_post') !== null){
        PostsPaginator.scrollToLastPost(getCookie('app_prev_post'));
    }
    if(postsFilter !== 'streams') {
        Post.initPostsMediaModule();
        // Initing read more/less toggler based on clip property
        PostsPaginator.initDescriptionTogglers();
    }
    Post.initGalleryModule('.recent-media');
    if(app.feedDisableRightClickOnMedia === true){
        Post.disablePostsRightClick();
    }

    if(postsFilter === 'streams') {
        if(typeof paginatorConfig !== 'undefined'){
            if((paginatorConfig.total > 0 && paginatorConfig.total > paginatorConfig.per_page) && paginatorConfig.hasMore) {
                StreamsPaginator.initScrollLoad();
            }
            StreamsPaginator.init(paginatorConfig.next_page_url, '.streams-wrapper');
        }
        else{
            // eslint-disable-next-line no-console
            console.error('Pagination failed to initialize.');
        }
    }

    if(multiLineOverflows('.description-content')){
        $('.profile-description-holder .show-more-actions').removeClass('d-none');
    }

    if (window.StoriesProfile && window.profileVars && profileVars.username) {
        var storyId = null;

        try {
            var u = new URL(window.location.href);
            var raw = u.searchParams.get("story");
            var n = raw ? parseInt(raw, 10) : 0;
            storyId = (n && !isNaN(n)) ? n : null;
            // eslint-disable-next-line no-empty
        } catch (e) {}

        window.StoriesProfile.init({
            username: String(profileVars.username),
            triggerSelector: ".profile-avatar-wrap",
            storyId: storyId
        });
    }

    if(showDisabledPaywallWarning){
        launchToast('success',trans('Note'),trans('Previewing as admin, paywall disabled.'));
    }

    // Spotify widget
    if (window.Swiper && $('.profile-spotify-swiper').length) {
        new Swiper('.profile-spotify-swiper', {
            slidesPerView: 'auto',
            spaceBetween: 12,

            // snap behavior
            freeMode: false,
            centeredSlides: false,
            watchOverflow: true,

            // feels nicer for snapping rails
            speed: 250,
            threshold: 6,

            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev'
            },

        });
    }

});

$(window).scroll(function(){
    var top = $(window).scrollTop();
    if ($(".main-wrapper").offset().top < top) {
        $(".profile-widgets-area").addClass("sticky-profile-widgets");
    } else {
        $(".profile-widgets-area").removeClass("sticky-profile-widgets");
    }
});

window.onunload = function(){
    // Reset scrolling to top
    $(".inline-border-tabs").get(0).scrollIntoView();
};

// eslint-disable-next-line no-unused-vars
var Profile = {

    /**
     * Toggles profile's description
     */
    toggleFullDescription:function () {
        $('.profile-description-holder .label-less, .profile-description-holder .label-more').addClass('d-none');
        if($('.description-content').hasClass('line-clamp-3')){
            $('.description-content').removeClass('line-clamp-3');
            $('.profile-description-holder .label-less').removeClass('d-none');
        }
        else{
            $('.description-content').addClass('line-clamp-3');
            $('.profile-description-holder .label-more').removeClass('d-none');
        }
    },

    /**
     * Toggles profile's bundles section, if available
     */
    toggleBundles:function () {
        $('.subscription-holder .label-less, .subscription-holder .label-more').addClass('d-none');
        if($('.subscription-bundles').hasClass('d-none')){
            $('.subscription-bundles').removeClass('d-none');
            $('.subscription-holder .label-less').removeClass('d-none');
            $('.subscription-holder .label-icon').html('<ion-icon name="chevron-up-outline"></ion-icon>');
        }
        else{
            $('.subscription-bundles').addClass('d-none');
            $('.subscription-holder .label-more').removeClass('d-none');
            $('.subscription-holder .label-icon').html('<ion-icon name="chevron-down-outline"></ion-icon>');
        }
    },

    /**
     * Generates QR code image for the given profile
     * @param options
     */
    getProfileQRCode: function () {
        var QRoptions = {
            text: window.location.href,
        };
        $('#qrcode').html('');
        new QRCode(document.getElementById("qrcode"), QRoptions);
        $('#qr-code-dialog').modal('show');
    },

    /**
     * Saves QR Canvas to img
     */
    downloadQRCode: function () {
        var canvas = $("#qrcode canvas")[0];
        var image = canvas.toDataURL();
        var aDownloadLink = document.createElement('a');
        aDownloadLink.download = 'canvas_image.png';
        aDownloadLink.href = image;
        aDownloadLink.click();
    },

    initBioHyperlinks: function () {
        if(app.allow_hyperlinks) {
            $('.description-content').each(function () {
                var linkedText = Autolinker.link($(this).html(), {
                    urls: {
                        schemeMatches: true,
                        wwwMatches: true,
                        tldMatches: false
                    },
                    email: false,
                    phone: false,
                    mention: false,
                    hashtag: false,
                    sanitizeHtml: false,
                    className: "",
                    truncate: {length: 64, location: 'middle'},
                    replaceFn: function (match) {
                        var tag = match.buildTag();
                        tag.setAttr('rel', 'nofollow noopener noreferrer');
                        return tag;
                    }
                });
                $(this).html(linkedText);
            });
        }
    }

};
