/**
 * Main post class
 */
"use strict";
/* global Swiper, CommentsPaginator, PostsPaginator, GifPicker  */
/* global app */
/* global updateButtonState, redirect, trans, trans_choice, launchToast */
/* global  mswpScanPage, showDialog, hideDialog, initTooltips, bindNoLongPressEvents, TextareaHighlighter, multiLineOverflows  */


var Post = {

    draftData:{
        text: "",
        attachments:[]
    },

    activePage: 'post',
    postID: null,
    commentID: null,
    scrollToPostWhenTogglingDescription: true,
    nativeGalleryResizeBound: false,
    nativeGalleryResizeTimer: null,

    /**
     * Sets the current active page
     * @param page
     */
    setActivePage: function(page){
        Post.activePage = page;
    },

    /**
     * Instantiates the media module for post(s)
     * @returns {*}
     */
    initPostsMediaModule: function () {
        $(".post-box .mySwiper").not(".js-swiper-inited").each(function () {
            var $el = $(this);
            $el.addClass("js-swiper-inited");

            // store instance if you ever need destroy/update
            var swiper = new Swiper(this, {
                slidesPerView: "auto",
                pagination: {
                    el: $el.find(".swiper-pagination")[0],
                    dynamicBullets: true,
                },
                navigation: {
                    nextEl: $el.find(".swiper-button-next")[0],
                    prevEl: $el.find(".swiper-button-prev")[0],
                },
                touchEventsTarget: "container",
                focusableElements: "input, select, option, textarea, button, label",
                // watchOverflow: true, // optional: disables nav if not enough slides
            });

            if ($el.hasClass("post-media-swiper-native-height")) {
                Post.bindNativeGalleryHeight($el, swiper);
            }

            // keep a reference (optional but nice)
            this._swiper = swiper;
        });
    },

    bindNativeGalleryHeight: function ($el, swiper) {
        var refresh = function () {
            Post.updateNativeGalleryHeight($el, swiper);
        };

        $el.find("img.post-media-gallery-image").each(function () {
            if (!this.complete) {
                this.addEventListener("load", refresh, { once: true });
                this.addEventListener("error", refresh, { once: true });
            }
        });

        $el.find("video.video-preview").each(function () {
            if (this.readyState < 1) {
                this.addEventListener("loadedmetadata", refresh, { once: true });
            }
        });

        refresh();

        if (!Post.nativeGalleryResizeBound) {
            Post.nativeGalleryResizeBound = true;
            $(window).on("resize.postNativeGalleryHeight", function () {
                clearTimeout(Post.nativeGalleryResizeTimer);
                Post.nativeGalleryResizeTimer = setTimeout(function () {
                    Post.refreshNativeGalleryHeights();
                }, 150);
            });
        }
    },

    refreshNativeGalleryHeights: function () {
        $(".post-box .post-media-swiper-native-height.js-swiper-inited").each(function () {
            Post.updateNativeGalleryHeight($(this), this._swiper);
        });
    },

    updateNativeGalleryHeight: function ($el, swiper) {
        var width = $el.width();
        var maxHeight = 0;

        if (!width) {
            return;
        }

        $el.find(".post-media-gallery-image, video.video-preview, audio.video-preview, .post-youtube-preview").each(function () {
            if (this.tagName === "AUDIO") {
                // Audio has no aspect ratio. Reserve space for its native controls and gallery navigation.
                maxHeight = Math.max(maxHeight, 160);
                return;
            }
            var ratio = Post.getNativeGalleryMediaRatio(this);

            if (ratio > 0) {
                maxHeight = Math.max(maxHeight, Math.round(width / ratio));
            }
        });

        if (!maxHeight) {
            return;
        }

        $el
            .css("--post-gallery-native-height", maxHeight + "px")
            .addClass("is-gallery-height-ready");

        if (swiper && typeof swiper.update === "function") {
            swiper.update();
        }
    },

    getNativeGalleryMediaRatio: function (media) {
        if (!media) {
            return 0;
        }

        if (media.classList.contains("post-youtube-preview")) {
            return 16 / 9;
        }

        if (media.tagName === "IMG" && media.naturalWidth && media.naturalHeight) {
            return media.naturalWidth / media.naturalHeight;
        }

        if (media.tagName === "VIDEO" && media.videoWidth && media.videoHeight) {
            return media.videoWidth / media.videoHeight;
        }

        return 0;
    },
    
    /**
     * Initiates the gallery swiper module
     * @param gallerySelector
     */
    initGalleryModule: function (gallerySelector = false) {
        mswpScanPage(gallerySelector,'mswp');
    },

    /**
     * Method used for adding a new post comment
     * @param postID
     */
    addComment: function (postID) {
        let postElement = $('*[data-postID="'+postID+'"]');
        let replyToID = postElement.find('.comment-reply-to-id').val();
        let commentArea = postElement.find('.new-post-comment-area');
        let gifComposer = commentArea.find('[data-gif-composer]')[0];
        if (gifComposer && gifComposer.dataset.gifSubmitting) return;
        let gif = typeof GifPicker !== 'undefined' ? GifPicker.getSelection(gifComposer) : null;
        let newCommentButton = commentArea.find('.post-comment-submit');
        let gifWasInert = !!(gifComposer && gifComposer.inert);
        if (gifComposer) {
            gifComposer.dataset.gifSubmitting = '1';
            gifComposer.inert = true;
        }
        updateButtonState('loading',newCommentButton);
        $.ajax({
            type: 'POST',
            data: {
                'message': postElement.find('.new-comment-textarea').val(),
                'post_id': postID,
                'reply_to_id': replyToID || null,
                'gif': gif
            },
            url: app.baseUrl+'/posts/comments/add',
            success: function (result) {
                if(result.success){
                    launchToast('success',trans('Success'),trans('Comment added'));
                    postElement.find('.no-comments-label').addClass('d-none');

                    if(result.is_reply){
                        let thread = postElement.find('.post-comment-thread[data-root-comment-id="'+result.parent_id+'"]');
                        let replies = thread.find('.post-comment-replies').first();
                        let reply = $(result.data);

                        if(!thread.find('*[data-comment-id="'+reply.attr('data-comment-id')+'"]').length){
                            replies.append(reply);
                        }

                        replies.removeClass('d-none');
                        thread.find('.comment-replies-toggle')
                            .attr('data-total', result.thread_replies)
                            .removeClass('d-none');
                        Post.updateCommentRepliesControl(thread);
                    }
                    else{
                        postElement.find('.post-comments-wrapper').prepend(result.data).fadeIn('slow');
                    }

                    Post.initCommentTogglers(postElement);
                    postElement.find('.new-comment-textarea').val('');
                    if (typeof GifPicker !== 'undefined') GifPicker.clear(gifComposer);
                    Post.cancelCommentReply(postID);
                    const commentsCount = parseInt(result.comments, 10);
                    postElement.find('.post-comments-label-count').html(commentsCount);
                    postElement.find('.post-comments-label').html(trans_choice('comments',commentsCount));
                    updateButtonState('loaded',newCommentButton);
                }
                else{
                    launchToast('danger',trans('Error'),result.errors[0]);
                    updateButtonState('loaded',newCommentButton);
                }
                TextareaHighlighter.clear(".new-comment-textarea");
                newCommentButton.blur();
            },
            error: function (result) {
                postElement.find('.new-comment-textarea').addClass('is-invalid');
                if(result.status === 422) {
                    if (result.responseJSON.errors.gif) {
                        launchToast('danger', trans('Error'), result.responseJSON.errors.gif[0]);
                    }
                    $.each(result.responseJSON.errors,function (field,error) {
                        if(field === 'message'){
                            postElement.find('.new-comment-textarea').parent().find('.invalid-feedback').html(error);
                        }
                    });
                    updateButtonState('loaded',newCommentButton);
                }
                else if(result.status === 429){
                    launchToast(
                        'danger',
                        trans('Error'),
                        (result.responseJSON && result.responseJSON.message) ? result.responseJSON.message : trans('Too many attempts. Please try again later.')
                    );
                    updateButtonState('loaded',newCommentButton);
                }
                else if(result.status === 403 || result.status === 404){
                    launchToast('danger',trans('Error'), result.responseJSON.message);
                    updateButtonState('loaded',newCommentButton);
                }
                newCommentButton.blur();
            },
            complete: function () {
                if (gifComposer) {
                    gifComposer.inert = gifWasInert;
                    delete gifComposer.dataset.gifSubmitting;
                }
            }
        });
    },

    /**
     * Shows up post comment delete dialog confirmation dialog
     * @param postID
     * @param commentID
     */
    showDeleteCommentDialog: function(postID, commentID){
        showDialog('comment-delete-dialog');
        Post.commentID = commentID;
        Post.postID = postID;
    },

    /**
     * Deletes post comment
     */
    deleteComment: function(){
        let commentElement = $('*[data-commentID="'+Post.commentID+'"]');
        let postElement = $('*[data-postID="'+Post.postID+'"]');
        $.ajax({
            type: 'DELETE',
            data: {
                'id': Post.commentID
            },
            dataType: 'json',
            url: app.baseUrl+'/posts/comments/delete',
            success: function (result) {
                if(result.success){
                    let isRootComment = commentElement.hasClass('post-comment-root');
                    let thread = commentElement.closest('.post-comment-thread');
                    let removableElement = isRootComment ? thread : commentElement;

                    removableElement.fadeOut("normal", function() {
                        $(this).remove();

                        if(!isRootComment && thread.length){
                            let replies = thread.find('.post-comment-replies').first();
                            let repliesToggle = thread.find('.comment-replies-toggle').first();

                            replies.empty().removeClass('d-none');
                            repliesToggle
                                .attr('data-offset', 0)
                                .attr('data-total', result.thread_replies);

                            if(parseInt(result.thread_replies, 10) > 0){
                                Post.toggleCommentReplies(repliesToggle[0]);
                            }
                            else{
                                Post.updateCommentRepliesControl(thread);
                            }
                        }

                        if(postElement.find('.post-comment-root').length === 0){
                            postElement.find('.no-comments-label').removeClass('d-none');
                        }
                    });

                    const commentsCount = parseInt(result.comments, 10);
                    postElement.find('.post-comments-label-count').html(commentsCount);
                    postElement.find('.post-comments-label').html(trans_choice('comments',commentsCount));

                    launchToast('success',trans('Success'),result.message);
                    hideDialog('comment-delete-dialog');
                }
                else{

                    launchToast('danger',trans('Error'),result.errors[0]);
                    $('#comment-delete-dialog').modal('hide');
                }
            },
            error: function (result) {
                launchToast('danger',trans('Error'),result.responseJSON.message);
                hideDialog('comment-delete-dialog');
            }
        });

    },

    /**
     * Toggle post comment area visibility
     * @param post_id
     */
    showPostComments: function(post_id){
        let postElement = $('*[data-postID="'+post_id+'"] .post-comments');

        // No pagination needed - on feed
        if(typeof postVars === 'undefined'){
            CommentsPaginator.nextPageUrl = '';
        }

        if(CommentsPaginator.nextPageUrl === ''){
            CommentsPaginator.init(app.baseUrl+'/posts/comments',postElement.find('.post-comments-wrapper'));
        }

        const isHidden = postElement.hasClass('d-none');
        if(isHidden){
            if(!postElement.hasClass('latest-comments-loaded')){
                CommentsPaginator.loadResults(post_id,9);
            }
            postElement.removeClass('d-none');
            postElement.addClass('latest-comments-loaded');
        }
        else{
            postElement.addClass('d-none');
        }
    },

    /**
     * Add new reaction
     * Can be used for post or comment reactionn
     * @param type
     * @param id
     */
    reactTo: function (type,id) {
        let reactElement = null;
        let reactionsCountLabel = null;
        let reactionsLabel = null;
        if(type === 'post'){
            reactElement = $('*[data-postID="'+id+'"] .post-footer .react-button');
            reactionsCountLabel = $('*[data-postID="'+id+'"] .post-footer .post-reactions-label-count');
            reactionsLabel = $('*[data-postID="'+id+'"] .post-footer .post-reactions-label');
        }
        else{
            reactElement = $('*[data-commentID="'+id+'"] .react-button');
            reactionsCountLabel = $('*[data-commentID="'+id+'"] .comment-reactions-label-count');
            reactionsLabel = $('*[data-commentID="'+id+'"] .comment-reactions-label');
        }
        const didReact = reactElement.hasClass('active');
        if(didReact){
            reactElement.removeClass('active');
            reactElement.find('.reaction-icon-active').addClass('d-none');
            reactElement.find('.reaction-icon-inactive').removeClass('d-none');
        }
        else{
            reactElement.addClass('active');
            reactElement.find('.reaction-icon-inactive').addClass('d-none');
            reactElement.find('.reaction-icon-active').removeClass('d-none');
        }
        $.ajax({
            type: 'POST',
            data: {
                'type': type,
                'action': (didReact === true ? 'remove' : 'add'),
                'id': id
            },
            dataType: 'json',
            url: app.baseUrl+'/posts/reaction',
            success: function (result) {
                if(result.success){
                    let count = parseInt(reactionsCountLabel.html());
                    if(didReact){
                        count--;
                    }
                    else{
                        count++;
                    }
                    reactionsCountLabel.html(count);
                    reactionsLabel.html(trans_choice('likes',count));
                    // launchToast('success',trans('Success'),result.message);
                }
                else{
                    launchToast('danger',trans('Error'),result.errors[0]);
                }
            },
            error: function (result) {
                launchToast('danger',trans('Error'),result.responseJSON.message);
            }
        });
    },

    startCommentReply: function(postID, commentID, username){
        let postElement = $('*[data-postID="'+postID+'"]');
        let commentArea = postElement.find('.new-post-comment-area');

        commentArea.find('.comment-reply-to-id').val(commentID);
        commentArea.find('.comment-reply-username').text('@'+username);
        commentArea.find('.comment-reply-context').removeClass('d-none').addClass('d-flex');
        commentArea.find('.new-comment-textarea').focus();
    },

    cancelCommentReply: function(postID){
        let commentArea = $('*[data-postID="'+postID+'"] .new-post-comment-area');
        commentArea.find('.comment-reply-to-id').val('');
        commentArea.find('.comment-reply-username').text('');
        commentArea.find('.comment-reply-context').addClass('d-none').removeClass('d-flex');
    },

    toggleCommentReplies: function(buttonElement){
        let button = $(buttonElement);
        let thread = button.closest('.post-comment-thread');
        let replies = thread.find('.post-comment-replies').first();
        let total = parseInt(button.attr('data-total'), 10) || 0;
        let visibleCount = replies.find('.post-comment-reply').length;

        if(replies.hasClass('d-none')){
            replies.removeClass('d-none');
            Post.updateCommentRepliesControl(thread);
            return;
        }

        if(visibleCount >= total){
            replies.addClass('d-none');
            Post.updateCommentRepliesControl(thread);
            return;
        }

        if(button.attr('data-loading') === 'true'){
            return;
        }

        button.attr('data-loading', 'true');
        $.ajax({
            type: 'GET',
            url: app.baseUrl+'/posts/comments/replies',
            data: {
                post_id: button.attr('data-post-id'),
                root_id: button.attr('data-root-id'),
                offset: parseInt(button.attr('data-offset'), 10) || 0
            },
            success: function(result){
                $.each(result.html || [], function(key, html){
                    let reply = $(html);
                    let commentID = reply.attr('data-comment-id');
                    if(!thread.find('*[data-comment-id="'+commentID+'"]').length){
                        replies.append(reply);
                    }
                });

                button.attr('data-offset', result.next_offset);
                button.attr('data-total', result.total);
                Post.initCommentTogglers(thread);
                Post.updateCommentRepliesControl(thread);
            },
            error: function(result){
                launchToast('danger', trans('Error'), result.responseJSON.message);
            },
            complete: function(){
                button.attr('data-loading', 'false');
            }
        });
    },

    updateCommentRepliesControl: function(threadElement){
        let thread = $(threadElement);
        let replies = thread.find('.post-comment-replies').first();
        let button = thread.find('.comment-replies-toggle').first();
        let control = button.closest('.comment-replies-control');
        let total = parseInt(button.attr('data-total'), 10) || 0;
        let visibleCount = replies.find('.post-comment-reply').length;

        if(total === 0){
            replies.addClass('d-none');
            control.addClass('d-none');
            return;
        }

        control.removeClass('d-none');
        if(replies.hasClass('d-none')){
            button.text((button.attr('data-view-template') || '').replace('__COUNT__', total));
        }
        else if(visibleCount < total){
            button.text((button.attr('data-more-template') || '').replace('__COUNT__', total-visibleCount));
        }
        else{
            button.text(button.attr('data-hide-label'));
        }
    },

    /**
     * Shows up the post removal confirmation box
     * @param post_id
     */
    confirmPostRemoval: function (post_id) {
        Post.postID = post_id;
        $('#post-delete-dialog').modal('show');
    },

    /**
     * Removes user post
     */
    removePost: function(){
        const postID = Post.postID;
        let postElement = $('.post-box[data-postID="'+postID+'"], .profile-compact-post-tile[data-postID="'+postID+'"]');
        $.ajax({
            type: 'DELETE',
            data: {
                'id': postID
            },
            dataType: 'json',
            url: app.baseUrl+'/posts/delete',
            success: function (result) {
                if(result.success){
                    if(Post.activePage !== 'post'){
                        $('#post-delete-dialog').modal('hide');
                        postElement.fadeOut("normal", function() {
                            $(this).next('hr').remove();
                            $(this).remove();
                            if(typeof PostsPaginator !== 'undefined'){
                                PostsPaginator.onPostRemoved(postID);
                            }
                        });
                    }
                    else{
                        if(document.referrer.indexOf('feed') > 0){
                            redirect(app.baseUrl + '/feed');
                        }
                        else{
                            redirect(document.referrer);
                        }
                    }
                    launchToast('success',trans('Success'),result.message);

                }
                else{
                    $('#post-delete-dialog').modal('hide');
                    launchToast('danger',trans('Error'),result.errors[0]);
                }
            },
            error: function (result) {
                launchToast('danger',trans('Error'),result.responseJSON.message);
            }
        });
    },

    /**
     * Adds or removes user bookmarks
     * @param id
     */
    togglePostBookmark: function (id) {
        let reactElement = $('*[data-postID="'+id+'"] .bookmark-button');
        const isBookmarked = reactElement.hasClass('is-active');
        $.ajax({
            type: 'POST',
            data: {
                'action': (isBookmarked === true ? 'remove' : 'add'),
                'id': id
            },
            dataType: 'json',
            url: app.baseUrl+'/posts/bookmark',
            success: function (result) {
                if(result.success){
                    if(isBookmarked){
                        reactElement.removeClass('is-active');
                        reactElement.html(trans('Bookmark this post'));
                    }
                    else{
                        reactElement.addClass('is-active');
                        reactElement.html(trans('Remove this bookmark'));
                    }

                    launchToast('success',trans('Success'),result.message);
                }
                else{
                    launchToast('danger',trans('Error'),result.errors[0]);
                }
            },
            error: function (result) {
                launchToast('danger',trans('Error'),result.responseJSON.message);
            }
        });
    },

    /**
     * Function used to pin/unpin a post
     * @param id
     */
    togglePostPin: function (id) {
        let reactElement = $('*[data-postID="'+id+'"] .pin-button');
        const isPinned = reactElement.hasClass('is-active');
        $('.pinned-post-label').addClass('d-none');
        $.ajax({
            type: 'POST',
            data: {
                'action': (isPinned === true ? 'remove' : 'add'),
                'id': id
            },
            dataType: 'json',
            url: app.baseUrl+'/posts/pin',
            success: function (result) {
                if(result.success){
                    if(isPinned){
                        $('*[data-postID="'+id+'"] .pinned-post-label').addClass('d-none');
                        reactElement.removeClass('is-active');
                        reactElement.html(trans('Pin this post'));
                    }
                    else{
                        $('*[data-postID="'+id+'"] .pinned-post-label').removeClass('d-none');
                        reactElement.addClass('is-active');
                        reactElement.html(trans('Un-pin post'));
                    }

                    launchToast('success',trans('Success'),result.message);
                }
                else{
                    launchToast('danger',trans('Error'),result.errors[0]);
                }
            },
            error: function (result) {
                launchToast('danger',trans('Error'),result.responseJSON.message);
            }
        });
    },

    /**
     * Disabling right for posts ( if site wise setting is set to do it )
     */
    disablePostsRightClick: function () {
        $(".post-media, .pswp__item").unbind('contextmenu');
        $(".post-media, .pswp__item").on("contextmenu",function(){
            return false;
        });
        bindNoLongPressEvents();
    },

    /**
     * Toggles post's full/short description
     */
    toggleFullDescription:function (postID) {
        let postElement = $('*[data-postID="'+postID+'"]');
        $('*[data-postID="'+postID+'"] .label-less, *[data-postID="'+postID+'"] .label-more').addClass('d-none');
        if(postElement.find('.post-content-data').hasClass('line-clamp-3')){
            postElement.find('.post-content-data').removeClass('line-clamp-3');
            postElement.find('.label-less').removeClass('d-none');
        }
        else{
            postElement.find('.post-content-data').addClass('line-clamp-3');
            postElement.find('.label-more').removeClass('d-none');
        }
        if(Post.scrollToPostWhenTogglingDescription){
            PostsPaginator.scrollToLastPost(postID);
        }
    },

    initCommentTogglers: function (context) {
        let root = $(context || document);
        let comments = root.hasClass('post-comment') ? root : root.find('.post-comment');

        comments.not('.comment-clamp-initialized').each(function () {
            let comment = $(this);
            let content = comment.find('.comment-content').first();
            let toggle = comment.find('.comment-content-toggle').first();

            if(multiLineOverflows(content)){
                toggle.removeClass('d-none');
            }

            toggle.off('click.commentClamp').on('click.commentClamp', function () {
                let isExpanded = toggle.attr('aria-expanded') === 'true';

                content.toggleClass('line-clamp-3', isExpanded);
                toggle.attr('aria-expanded', isExpanded ? 'false' : 'true');
                toggle.find('.label-more').toggleClass('d-none', !isExpanded);
                toggle.find('.label-less').toggleClass('d-none', isExpanded);
            });

            comment.addClass('comment-clamp-initialized');
        });
    },

    showEditCommentInterface: function (postID, commentID){
        Post.cancelEditCommentInterface();
        let commentElement = $('*[data-commentID="'+commentID+'"]');
        // fill textarea with raw text (not html)
        let raw = commentElement.attr('data-raw') || "";
        commentElement.find('textarea').val(raw);
        commentElement.find('.post-comment-content').addClass('d-none');
        commentElement.find('.post-comment-edit').removeClass('d-none');
        let gifComposer = commentElement.find('.post-comment-edit [data-gif-composer]')[0];
        if (typeof GifPicker !== 'undefined' && gifComposer) {
            GifPicker.setSelection(gifComposer, JSON.parse(gifComposer.getAttribute('data-gif-existing') || 'null'));
        }
    },

    cancelEditCommentInterface: function (){
        $('.post-comment').each(function(key,element) {
            let commentElement = $(element);
            commentElement.find('.post-comment-content').removeClass('d-none');
            commentElement.find('.post-comment-edit').addClass('d-none');
            commentElement.find('.edit-comment-textarea').removeClass('is-invalid');
            // restore raw text into textarea
            let raw = commentElement.attr('data-raw') || "";
            commentElement.find('textarea').val(raw);
            let gifComposer = commentElement.find('.post-comment-edit [data-gif-composer]')[0];
            if (typeof GifPicker !== 'undefined' && gifComposer) {
                GifPicker.setSelection(gifComposer, JSON.parse(gifComposer.getAttribute('data-gif-existing') || 'null'));
            }
        });
    },


    saveEditedComment: function (postID, commentID) {
        let commentElement = $('*[data-commentID="' + commentID + '"]');
        let newCommentButton = commentElement.find('.post-comment-edit').find('.save-comment-edit-button'); // in case it's a span
        let commentContent = commentElement.find('textarea').val();
        let gifComposer = commentElement.find('.post-comment-edit [data-gif-composer]')[0];
        if (gifComposer && gifComposer.dataset.gifSubmitting) return;
        let gifWasInert = !!(gifComposer && gifComposer.inert);
        let data = {
            'message': commentContent,
            'post_id': postID,
            'comment_id': commentID
        };
        if (typeof GifPicker !== 'undefined' && gifComposer) {
            data.gif = GifPicker.getSelection(gifComposer);
        }
        if (gifComposer) {
            gifComposer.dataset.gifSubmitting = '1';
            gifComposer.inert = true;
        }

        updateButtonState('loading', newCommentButton);

        $.ajax({
            type: 'POST',
            data: data,
            url: app.baseUrl + '/posts/comments/edit',
            success: function (result) {
                if (result.success) {
                    launchToast('success', trans('Success'), trans('Comment saved'));

                    // Replace the whole comment block with server-rendered HTML
                    // This preserves linkified hashtags/mentions AND updated data-raw for future edits
                    commentElement.replaceWith(result.data);

                    // Re-init UI things on the newly inserted DOM
                    initTooltips();
                    Post.initCommentTogglers($('*[data-commentID="'+commentID+'"]'));

                    updateButtonState('loaded', newCommentButton);
                } else {
                    launchToast('danger', trans('Error'), result.errors[0]);
                    updateButtonState('loaded', newCommentButton);
                }

                newCommentButton.blur();
            },
            error: function (result) {
                // still reference old element (in case of validation errors)
                commentElement.find('textarea').addClass('is-invalid');

                if (result.status === 422) {
                    if (result.responseJSON.errors.gif) {
                        launchToast('danger', trans('Error'), result.responseJSON.errors.gif[0]);
                    }
                    $.each(result.responseJSON.errors, function (field, error) {
                        if (field === 'message') {
                            commentElement.find('textarea').parent().find('.invalid-feedback').html(error);
                        }
                    });
                    updateButtonState('loaded', newCommentButton);
                } else if (result.status === 403 || result.status === 404) {
                    launchToast('danger', trans('Error'), result.responseJSON.message);
                }

                newCommentButton.blur();
            },
            complete: function () {
                if (gifComposer) {
                    gifComposer.inert = gifWasInert;
                    delete gifComposer.dataset.gifSubmitting;
                }
            }
        });
    },

    /**
     * Add user vote to a given poll
     * @param pollID
     * @param answerID
     */
    voteForPoll: function (pollID, answerID){
        $.ajax({
            type: 'POST',
            data: {
                pollID,
                answerID
            },
            dataType: 'json',
            url: app.baseUrl+'/posts/polls/vote',
            success: function (result) {
                if(result.success){
                    // launchToast('success',trans('Success'),result.message);
                    $('.post-poll-'+pollID).html(result.html);
                    Post.animatePollResults();
                }
                else{
                    launchToast('danger',trans('Error'),result.errors[0]);
                }
            },
            error: function (result) {
                launchToast('danger',trans('Error'),result.responseJSON.message);
            }
        });
    },

    /**
     * Animates the poll results in an UI friendly way
     */
    animatePollResults: function (){
        const bars = document.querySelectorAll('.poll-bar');
        bars.forEach(bar => {
            const finalWidth = bar.getAttribute('data-width') || '0%';

            // If already animated (or already at final width), skip
            if (bar.dataset.animated === 'true' || bar.style.width === finalWidth) {
                return;
            }

            // Otherwise, animate it
            bar.style.width = '0'; // ensure it's back to zero
            setTimeout(() => {
                bar.style.width = finalWidth;
                // Mark it so we know not to re-animate
                bar.dataset.animated = 'true';
            }, 50);
        });
    },

};
