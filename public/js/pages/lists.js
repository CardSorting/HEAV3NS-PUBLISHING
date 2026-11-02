/*
* Feed page & component
 */
"use strict";
/* global app, user, listVars, profileVars, launchToast, redirect, trans, trans_choice, listsPaginatorConfig, ListsPaginator, updateButtonState, updateTooltipTitle */

$(function () {
    if(typeof listsPaginatorConfig !== 'undefined'){
        ListsPaginator.init(listsPaginatorConfig);
    }
});

var Lists = {

    state : {
        listMemberToDelete: null,
        reportSubmitting: false,
    },

    /**
     * Shows up confirmation box for list clear
     */
    showListClearConfirmation: function(){
        $('#list-clear-dialog').modal('show');
    },

    /**
     * Clears all members within a list
     */
    clearList: function(){
        $.ajax({
            type: 'POST',
            data: { list_id: listVars.list_id },
            dataType: 'json',
            url: app.baseUrl + '/my/lists/members/clear',
            success: function (result) {
                // eslint-disable-next-line no-unused-vars
                let element = $('*[data-memberuserid="'+Lists.state.listMemberToDelete+'"]');
                if(result.success){
                    redirect(app.baseUrl+'/my/lists');
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
     * Removes list member
     */
    removeListMember: function(){
        const requestMethod = 'DELETE';
        const requestUrl = app.baseUrl + '/my/lists/members/delete';
        let data = {
            'list_id': listVars.list_id,
            'user_id': Lists.state.listMemberToDelete
        };
        $('#list-member-delete-dialog').modal('show');
        $.ajax({
            type: requestMethod,
            data: data,
            dataType: 'json',
            url: requestUrl,
            success: function (result) {
                if(result.success){
                    Lists.updateSideMenuFollowStats(result.follow_stats);
                    let element = $('*[data-memberuserid="'+Lists.state.listMemberToDelete+'"]');
                    launchToast('success',trans('Success'),result.message);
                    $('#list-member-delete-dialog').modal('hide');
                    element.parent().fadeOut(300,function(){
                        $(this).remove();
                        if($('.list-members-row .suggestion-box').length === 0){
                            ListsPaginator.toggleMembersEmptyState(false);
                        }
                    });
                }
                else{
                    $('#list-member-delete-dialog').modal('hide');
                    launchToast('danger',trans('Error'),result.message);
                }
            },
            error: function (result) {
                $('#list-member-delete-dialog').modal('hide');
                launchToast('danger',trans('Error'),result.responseJSON.message);
            }
        });
    },

    /**
     * Shows up confirmation box for removing a user from a list
     * @param member_id
     */
    showListMemberRemoveModal: function(member_id){
        $('#list-member-delete-dialog').modal('show');
        Lists.state.listMemberToDelete = member_id;
    },

    /**
     * Shows up list create dialog
     */
    showListAddModal: function(){
        $('#list-add-user-dialog').modal('show');
        Lists.initListsCheckboxes();
    },

    /**
     * Initiates list checkboxes ( on profile )
     */
    initListsCheckboxes: function(){
        let listCheckboxes = $('input[type=checkbox]');
        listCheckboxes.unbind('click');
        listCheckboxes.on('click',function () {
            let type = 'add';
            if(!$(this).is(':checked')){
                type = 'remove';
            }
            Lists.updateListMember(
                $(this).data('listid'),
                profileVars.user_id,
                type
            );
        });
    },

    /**
     * Updates list member
     * @param list_id
     * @param user_id
     * @param type
     * @param showMessages
     * @returns {boolean}
     */
    updateListMember: function(list_id, user_id, type, showMessages = true){
        let data = {
            'list_id': list_id,
            'user_id': user_id
        };
        let requestMethod = 'POST';
        let requestUrl = app.baseUrl + '/my/lists/members/save';
        if(type !== 'add'){
            requestMethod = 'DELETE';
            requestUrl = app.baseUrl + '/my/lists/members/delete';
        }
        data.returnData = showMessages;
        let element = $('*[data-listid="'+list_id+'"]');
        $.ajax({
            type: requestMethod,
            data: data,
            url: requestUrl,
            success: function (result) {
                Lists.updateSideMenuFollowStats(result.follow_stats);
                if(type === 'add'){
                    if(showMessages){
                        $('.lists-wrapper').append(result.data);
                        launchToast('success',trans('Success'),result.message);
                        element.parent().find('.list-subtitle').html(` ${trans_choice('members',result.data.members_count,{number:result.data.members_count.toString()})} - ` + ` ${trans_choice('posts', result.data.posts_count,{number:result.data.posts_count.toString()})}`);
                    }
                }
                else{
                    if(showMessages){
                        launchToast('success',trans('Success'),result.message);
                        element.parent().find('.list-subtitle').html(` ${trans_choice('members',result.data.members_count,{number:result.data.members_count.toString()})} - ` + ` ${trans_choice('posts', result.data.posts_count,{number:result.data.posts_count.toString()})}`);
                    }
                }

                if(!showMessages){
                    window.reload();
                }

            },
            error: function (result) {
                let checkInputState = true;
                if(type === 'add'){
                    checkInputState = false;
                }
                element.prop('checked',checkInputState);
                launchToast('danger',trans('Error'),result.responseJSON.message);
            }
        });
        return true;
    },

    /**
     * TODO: User array param, add stream and message report support
     * Posts user report
     * @param user_id
     * @param post_id
     * @param message_id
     * @param stream_id
     * @param type
     * @param details
     */
    postReport: function(user_id, post_id, message_id, stream_id, story_id, reel_id, reel_comment_id, type, details, buttonElement = null){
        if (Lists.state.reportSubmitting) {
            return;
        }

        Lists.state.reportSubmitting = true;
        Lists.setReportButtonLoading(buttonElement, true);

        $.ajax({
            type: 'POST',
            data: {
                user_id,
                post_id,
                message_id,
                stream_id,
                story_id,
                reel_id,
                reel_comment_id,
                type,
                details,
            },
            url: app.baseUrl + '/report/content',
            success: function (response) {
                launchToast('success',trans('Success'),response.message || trans('Report submitted'));
                $('#report-user-post').trigger('content-report:submitted').modal('hide');
                $('#post_report_details').val('');
            },
            error: function (result) {
                var response = result.responseJSON || {};
                launchToast('danger', trans('Error'), response.message || trans('Something went wrong.'));
            },
            complete: function () {
                Lists.state.reportSubmitting = false;
                Lists.setReportButtonLoading(buttonElement, false);
            }
        });
    },

    setReportButtonLoading: function(buttonElement, loading){
        let button = buttonElement ? $(buttonElement) : $('.submit-report-button');

        button.prop('disabled', loading);

        if (typeof updateButtonState === 'function') {
            updateButtonState(loading ? 'loading' : 'loaded', button, trans('Report'), 'white');
            button.prop('disabled', loading);
        } else {
            button.toggleClass('disabled', loading);
        }
    },

    /**
     * Shows up user report box
     * @param user_id
     * @param post_id
     * @param message_id
     * @param stream_id
     */
    showReportBox: function(user_id, post_id = null, message_id = null, stream_id = null, story_id = null, reel_id = null, reel_comment_id = null) {
        let dialogElement = $('#report-user-post');
        dialogElement.modal('show');
        $('.submit-report-button').off('click.report').on('click.report',function () {
            Lists.postReport(
                user_id,
                post_id,
                message_id,
                stream_id,
                story_id,
                reel_id,
                reel_comment_id,
                $('#reasonExamples').val(),
                $('#post_report_details').val(),
                this
            );
        });
    },

    /**
     * Shows up list management confirmation box
     * @param type
     * @param user_id
     */
    showListManagementConfirmation: function(type ,user_id){
        let dialogElement = $('#post-lists-management-dialog');
        dialogElement.modal('show');
        $('.post-list-management-btn').unbind();
        if(type === 'unfollow'){
            dialogElement.find('.block-user-label').addClass('d-none');
            dialogElement.find('.unfollow-user-label').removeClass('d-none');
            $('.post-list-management-btn').on('click',function () {
                Lists.manageList(user.lists['Following'], user_id, 'unfollow');
            });
        }
        else if(type === 'block'){
            dialogElement.find('.block-user-label').removeClass('d-none');
            dialogElement.find('.unfollow-user-label').addClass('d-none');
            $('.post-list-management-btn').on('click',function () {
                Lists.manageList(user.lists['Blocked'], user_id, 'block');
            });
        }

    },

    /**
     * Manages a list method
     * @param list_id
     * @param user_id
     * @param type
     */
    manageList: function(list_id, user_id, type){
        let innerType = '';
        switch (type) {
        case 'unfollow':
            innerType = 'remove';
            break;
        case 'block':
            innerType = 'add';
            break;
        }

        Lists.updateListMember(list_id,user_id,innerType, false);
    },

    /**
     * Method used for removing a list
     */
    removeList: function(){
        $.ajax({
            type: 'DELETE',
            data: {
                'id': listVars.list_id
            },
            dataType: 'json',
            url: app.baseUrl+'/my/lists/delete',
            success: function (result) {
                if(result.success){
                    redirect(app.baseUrl+'/my/lists');
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
     * Shows up lists delete confirmation
     */
    showListDeleteConfirmation: function(){
        $('#list-delete-dialog').modal('show');
    },

    /**
     * Shows up list management dialog
     * @param mode
     */
    showListEditDialog: function (mode) {
        $('#list-update-dialog').modal('show');
        if(mode === 'edit' && $('#list-name').val().length === 0){
            $('#list-name').val(listVars.name);
        }
    },

    /**
     * Method used for updating a list
     * @param type
     */
    updateList: function (type) {
        let data = {
            'name': $('#list-name').val(),
            'type': type
        };
        if(type === 'edit'){
            data.list_id = listVars.list_id;
        }
        $.ajax({
            type: 'POST',
            data: data,
            url: app.baseUrl + '/my/lists/save',
            success: function (result) {
                if(type === 'create'){
                    let listItems = $('.lists-wrapper .list-item');

                    if(listItems.length >= 3) {
                        // Insert after the *third* .list-item (0-based index => .eq(2))
                        listItems.eq(2).after(result.data);
                        listItems.eq(2).after('<hr class="my-2">');
                    } else {
                        // Fallback if there aren't at least 3 items:
                        $('.lists-wrapper').append('<hr class="my-2">');
                        $('.lists-wrapper').append(result.data);
                    }

                    $('#list-update-dialog').modal('hide');
                    launchToast('success',trans('Success'),trans('List added')+'.');
                    $('#list-name').val('');
                }
                else{
                    $('.list-name-label').html($('#list-name').val());
                    $('#list-update-dialog').modal('hide');
                    launchToast('success',trans('Success'),trans('List renamed')+'.');
                }
            },
            error: function (result) {
                $.each(result.responseJSON.errors,function (field) {
                    if(field === 'name'){
                        $('#list-name').addClass('is-invalid');
                        $('#list-name').focus();
                    }
                });
            }
        });
    },

    /**
     * Manage user follows action
     * @returns {boolean}
     */
    manageFollowsAction: function (userId) {
        $.ajax({
            type: 'POST',
            data: {
                user_id: userId,
            },
            url: app.baseUrl + '/my/lists/manage/follows',
            success: function (result) {
                // eslint-disable-next-line no-undef
                $('.manage-follows-text').text(result.text);
                Lists.updateSideMenuFollowStats(result.follow_stats);
                window.reload();
            }
        });
    },

    /**
     * Toggles user follow state from search results.
     * @param userId
     * @param buttonElement
     * @returns {boolean}
     */
    toggleSearchFollow: function(userId, buttonElement){
        let $button = $(buttonElement);
        if($button.hasClass('disabled')){
            return false;
        }

        let followingListId = Lists.getFollowingListId();
        if(!followingListId){
            launchToast('danger', trans('Error'), trans('Something went wrong.'));
            return false;
        }

        let action = $button.data('follow-action') === 'unfollow' ? 'unfollow' : 'follow';
        let requestMethod = action === 'unfollow' ? 'DELETE' : 'POST';
        let requestUrl = app.baseUrl + (action === 'unfollow' ? '/my/lists/members/delete' : '/my/lists/members/save');
        let $buttons = $('.search-follow-button[data-user-id="'+userId+'"]');

        $buttons.prop('disabled', true).addClass('disabled');

        $.ajax({
            type: requestMethod,
            data: {
                list_id: followingListId,
                user_id: userId,
                returnData: false,
            },
            url: requestUrl,
            success: function (result) {
                if(result.success){
                    Lists.updateSearchFollowButtons(userId, action === 'unfollow' ? 'follow' : 'unfollow');
                    Lists.updateSideMenuFollowStats(result.follow_stats);
                    return;
                }

                launchToast('danger', trans('Error'), result.message || trans('Something went wrong.'));
            },
            error: function (result) {
                let message = result.responseJSON && result.responseJSON.message ? result.responseJSON.message : trans('Something went wrong.');
                launchToast('danger', trans('Error'), message);
            },
            complete: function () {
                $buttons.prop('disabled', false).removeClass('disabled');
            }
        });

        return true;
    },

    /**
     * Gets the logged in user's following list ID.
     * @returns {null|*}
     */
    getFollowingListId: function(){
        if(typeof user === 'undefined' || !user.lists){
            return null;
        }

        if(user.lists.Following || user.lists.following){
            return user.lists.Following || user.lists.following;
        }

        let listNames = Object.keys(user.lists);
        for(let i = 0; i < listNames.length; i++){
            if(listNames[i].toLowerCase() === 'following'){
                return user.lists[listNames[i]];
            }
        }

        return null;
    },

    /**
     * Updates visible search follow controls for a user.
     * @param userId
     * @param nextAction
     */
    updateSearchFollowButtons: function(userId, nextAction){
        let $buttons = $('.search-follow-button[data-user-id="'+userId+'"]');
        $buttons.each(function () {
            let $button = $(this);
            let title = nextAction === 'unfollow' ? $button.data('unfollow-title') : $button.data('follow-title');
            let followClass = $button.data('follow-class');
            let unfollowClass = $button.data('unfollow-class');

            $button
                .data('follow-action', nextAction)
                .attr('data-follow-action', nextAction)
                .attr('aria-label', title)
                .removeClass(followClass+' '+unfollowClass)
                .addClass(nextAction === 'unfollow' ? unfollowClass : followClass)
                .text(title);

            updateTooltipTitle($button, title);

            if($.fn.tooltip){
                $button.tooltip('hide');
            }
        });
    },

    /**
     * Updates the logged-in user's sidebar follower/following totals.
     * @param followStats
     */
    updateSideMenuFollowStats: function(followStats){
        if(!followStats || typeof followStats !== 'object'){
            return;
        }

        Lists.updateSideMenuStat('followers', followStats.followers_count, 'fans');
        Lists.updateSideMenuStat('following', followStats.following_count, 'following');
    },

    /**
     * Updates one translated sidebar statistic without injecting translated HTML.
     */
    updateSideMenuStat: function(type, count, translationKey){
        count = Number.parseInt(count, 10);
        if(!Number.isFinite(count) || count < 0){
            return;
        }

        const token = '__side_menu_number__';
        const label = trans_choice(translationKey, count, {number: token});
        const parts = label.split(token);
        const formatted = Lists.shortFollowCount(count);

        $('[data-side-menu-stat="'+type+'"]').each(function () {
            $(this)
                .empty()
                .append(document.createTextNode(parts[0] || ''))
                .append($('<strong>', {class: 'side-menu-account-stat-value', text: formatted}))
                .append(document.createTextNode(parts.slice(1).join(token)));
        });
    },

    /** Keep client-side count formatting aligned with short_number(). */
    shortFollowCount: function(count){
        if(count < 1000){
            return count.toString();
        }

        const units = ['', 'K', 'M', 'B', 'T'];
        let unit = 0;
        let value = count;
        while(value >= 1000 && unit < units.length - 1){
            value /= 1000;
            unit++;
        }

        return value.toFixed(1).replace(/\.0$/, '') + units[unit];
    }

};
