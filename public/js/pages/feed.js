/*
* Feed page & component
 */
"use strict";
/* global app, initialPostIDs, paginatorConfig, PostsPaginator, Post, SuggestionsSlider, initStickyComponent, getCookie */

$(function () {
    if(typeof paginatorConfig !== 'undefined'){
        if((paginatorConfig.total > 0 && paginatorConfig.total > paginatorConfig.per_page) && paginatorConfig.hasMore) {
            PostsPaginator.initScrollLoad();
        }
        PostsPaginator.init(paginatorConfig.next_page_url, '.posts-wrapper');
    }
    else{
        // eslint-disable-next-line no-console
        console.error('Pagination failed to initialize.');
    }
    PostsPaginator.initPostsGalleries(initialPostIDs);
    PostsPaginator.initPostsHyperLinks();
    // Animate polls
    Post.animatePollResults();

    Post.setActivePage('feed');
    if(getCookie('app_prev_post') !== null){
        PostsPaginator.scrollToLastPost(getCookie('app_prev_post'));
    }
    Post.initPostsMediaModule();
    // Initing read more/less toggler based on clip property
    PostsPaginator.initDescriptionTogglers();
    SuggestionsSlider.init('#suggestions-box');
    SuggestionsSlider.init('#suggestions-box-expired');
    if(app.feedDisableRightClickOnMedia === true){
        Post.disablePostsRightClick();
    }
    Feed.initMobileBrandSearch();
    Feed.initMobileSuggestions();

    if (window.StoriesSwiper) {
        window.StoriesSwiper.containerId = "stories-swiper"; // optional, default already
        window.StoriesSwiper.init({
            feedUrl: `${app.baseUrl}/stories/feed`
        });
    }

});

window.onunload = function(){
    // Reset scrolling to top
    window.scrollTo(0,0);
};

$(window).scroll(function () {
    initStickyComponent('.feed-widgets','sticky');
});

// eslint-disable-next-line no-unused-vars
var Feed = {
    initMobileSuggestions: function () {
        const section = document.getElementById('feed-mobile-suggestions');
        const slot = document.getElementById('feed-mobile-suggestions-slot');
        const posts = document.querySelector('.posts-wrapper');
        if (!section || !slot || !posts) return;

        const viewport = window.matchMedia('(max-width: 767.98px)');
        const state = {section, slot, posts, viewport, anchor: null};
        let pending = false;
        const schedulePlacement = function () {
            if (pending) return;
            pending = true;
            // Reconcile after all pagination handlers have inserted their widgets.
            window.requestAnimationFrame(function () {
                pending = false;
                Feed.placeMobileSuggestions(state);
            });
        };

        $(document).on('posts:appended.mobileSuggestions posts:removed.mobileSuggestions', schedulePlacement);
        viewport.addEventListener('change', schedulePlacement);
        schedulePlacement();
    },

    placeMobileSuggestions: function (state) {
        if (!state.viewport.matches) {
            state.slot.appendChild(state.section);
            return;
        }

        const posts = Array.from(state.posts.children).filter(node => node.matches('.post-box[data-postID]'));
        const hasFreeGap = function (post) {
            for (let next = post.nextElementSibling; next; next = next.nextElementSibling) {
                if (next.matches('.post-box[data-postID]')) break;
                if (next.matches('.feed-reels-widget')) return false;
            }
            return true;
        };

        // Keep the same post anchor when pages are appended or prepended.
        if (!posts.includes(state.anchor) || !hasFreeGap(state.anchor)) {
            const preferredIndex = Math.min(4, posts.length - 1);
            state.anchor = posts.filter(hasFreeGap).sort((left, right) =>
                Math.abs(posts.indexOf(left) - preferredIndex) - Math.abs(posts.indexOf(right) - preferredIndex)
            )[0] || null;
        }

        if (!state.anchor) {
            // Short/empty feeds still offer discovery, outside the posts empty state.
            state.slot.appendChild(state.section);
            return;
        }

        const separator = state.anchor.nextElementSibling;
        const after = separator && separator.tagName === 'HR' ? separator : state.anchor;
        if (after.nextElementSibling !== state.section) {
            after.after(state.section);
        }
    },

    initMobileBrandSearch: function () {
        var $header = $(".mobile-feed-brand-header");

        if (!$header.length) {
            return;
        }

        $header.on("click", ".mobile-feed-search-button", function (event) {
            var $input = $header.find(".mobile-feed-search-input");

            if (!$header.hasClass("is-searching")) {
                event.preventDefault();
                Feed.openMobileBrandSearch($header);
                return;
            }

            if ($.trim($input.val()) === "") {
                event.preventDefault();
                Feed.closeMobileBrandSearch($header);
            }
        });

        $header.on("submit", ".mobile-feed-search-form", function (event) {
            if (!$header.hasClass("is-searching")) {
                event.preventDefault();
                Feed.openMobileBrandSearch($header);
                return;
            }

            if ($.trim($header.find(".mobile-feed-search-input").val()) === "") {
                event.preventDefault();
                Feed.closeMobileBrandSearch($header);
            }
        });

        $header.on("keydown", ".mobile-feed-search-input", function (event) {
            if (event.key === "Escape") {
                Feed.closeMobileBrandSearch($header);
            }
        });

        $(document).on("click.mobileBrandSearch", function (event) {
            if (
                $header.hasClass("is-searching") &&
                $header.has(event.target).length === 0 &&
                $.trim($header.find(".mobile-feed-search-input").val()) === ""
            ) {
                Feed.closeMobileBrandSearch($header);
            }
        });
    },

    openMobileBrandSearch: function ($header) {
        $header.addClass("is-searching");
        $header.find(".mobile-feed-search-input").trigger("focus");
    },

    closeMobileBrandSearch: function ($header) {
        $header.removeClass("is-searching");
        $header.find(".mobile-feed-search-input").trigger("blur");
    }
};
