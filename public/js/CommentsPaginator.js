/**
 * Paginator component - used for posts (feed+profile) pagination
 */
"use strict";
/* global app, trans, launchToast, initTooltips, MentionSuggestions, TextareaHighlighter, Post */

var CommentsPaginator = {

    nextPageUrl: '',
    container: '',
    targetCommentID: null,
    mentionSuggestionsLoaded: false,

    init: function (route,container) {
        CommentsPaginator.nextPageUrl = route;
        CommentsPaginator.container = container;
    },

    /**
     * Loads up paginated results and appends them to the page
     * @param post_id
     * @param limit
     */
    loadResults: function (post_id, limit = 9) {
        let postElement = $('*[data-postID="'+post_id+'"] .post-comments');
        $.ajax({
            type: 'GET',
            data: {
                'post_id': post_id,
                'limit': limit,
                'target_comment_id': CommentsPaginator.targetCommentID,
                'include_mention_contacts': (
                    !CommentsPaginator.mentionSuggestionsLoaded
                    && app.enable_mentions
                    && app.enable_mention_suggestions
                    && typeof MentionSuggestions !== 'undefined'
                ) ? 1 : 0,
            },
            url: CommentsPaginator.nextPageUrl,
            success: function (result) {
                let targetCommentID = CommentsPaginator.targetCommentID;
                CommentsPaginator.targetCommentID = null;

                if (Object.prototype.hasOwnProperty.call(result.data, 'mentionContacts')) {
                    MentionSuggestions.init({
                        target: '.new-comment-textarea, .edit-comment-textarea',
                        source: result.data.mentionContacts
                    });
                    CommentsPaginator.mentionSuggestionsLoaded = true;
                }

                if(result.data.comments.length > 0){
                    let htmlOut = [];
                    $.map(result.data.comments,function (comment) {
                        htmlOut.push(comment.html);
                    });
                    CommentsPaginator.appendCommentsResults(result.data.comments);
                    if(result.data.hasMore){
                        postElement.find('.show-all-comments-label').removeClass('d-none');
                        CommentsPaginator.nextPageUrl = result.data.next_page_url;
                    }
                    else{
                        postElement.find('.show-all-comments-label').addClass('d-none');
                    }
                }
                else{
                    postElement.find('.no-comments-label').removeClass('d-none');
                }
                $(CommentsPaginator.container).find('.comments-loading-box').addClass('d-none'); // Hiding out the loading element
                initTooltips();
                Post.initCommentTogglers(postElement);
                TextareaHighlighter.init({
                    selector: '.new-comment-textarea, .edit-comment-textarea'
                });

                if(targetCommentID){
                    let targetComment = $('#comment-'+targetCommentID);
                    if(targetComment.length){
                        targetComment[0].scrollIntoView({behavior: 'smooth', block: 'center'});
                    }
                }
            },
            error: function (result) {
                launchToast('danger',trans('Error'),result.responseJSON.message);
            }
        });
    },

    /**
     * Appends the new comments to the comments box
     * @param comments
     */
    appendCommentsResults: function(comments){
        // Building up the HTML array
        let htmlOut = [];
        let commentIDs = [];
        $.map(comments,function (comments) {
            htmlOut.push(comments.html);
            commentIDs.push(comments.id);
        });
        // Appending the output
        if(typeof CommentsPaginator.container === 'string'){
            $(CommentsPaginator.container).append(htmlOut.join("\n")).fadeIn('slow');
        }
        else{
            CommentsPaginator.container.append(htmlOut.join("\n")).fadeIn('slow');
        }
    },

};
