/*
* Post view page
 */
"use strict";
/* global app, postVars, Post, CommentsPaginator, PostsPaginator, TextareaHighlighter */

$(function () {
    Post.setActivePage('post');
    Post.initPostsMediaModule();
    if(app.feedDisableRightClickOnMedia === true){
        Post.disablePostsRightClick();
    }
    // Initing read more/less toggler based on clip property
    PostsPaginator.initDescriptionTogglers();
    PostsPaginator.initPostsHyperLinks();
    // Animate polls
    Post.animatePollResults();
    Post.initGalleryModule('.post-box');
    Post.initGalleryModule('.recent-media');

    CommentsPaginator.init(app.baseUrl+'/posts/comments','.post-comments-wrapper');
    CommentsPaginator.targetCommentID = postVars.comment_id || null;
    Post.showPostComments(postVars.post_id,9);
    Post.scrollToPostWhenTogglingDescription = false;
    if(app.enable_post_description_excerpts){
        Post.toggleFullDescription(postVars.post_id);
    }

    $('.post-comments').removeClass('d-none');
    TextareaHighlighter.init({
        selector: '.new-comment-textarea, .edit-comment-textarea'
    });

});

$(window).scroll(function(){
    var top = $(window).scrollTop();
    if ($(".main-wrapper").offset().top < top) {
        $(".profile-widgets-area").addClass("sticky-profile-widgets");
    } else {
        $(".profile-widgets-area").removeClass("sticky-profile-widgets");
    }
});
