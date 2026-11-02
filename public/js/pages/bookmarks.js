/*
* Feed page & component
 */
"use strict";
/* global app, paginatorConfig, initialPostIDs, PostsPaginator, Post, initStickyComponent, getCookie */

$(function () {

    if(typeof paginatorConfig !== 'undefined'){
        if((paginatorConfig.total > 0 && paginatorConfig.total > paginatorConfig.per_page) && paginatorConfig.hasMore) {
            PostsPaginator.initScrollLoad();
        }
        PostsPaginator.init(paginatorConfig.next_page_url, '.posts-wrapper', 'POST');
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
    if(app.feedDisableRightClickOnMedia === true){
        Post.disablePostsRightClick();
    }

});

$(window).scroll(function () {
    initStickyComponent('.bookmarks-menu-wrapper','sticky-sm');
});

window.onunload = function(){
    // Reset scrolling to top
    window.scrollTo(0,0);
};

// eslint-disable-next-line no-unused-vars
var Bookmarks = {

};
