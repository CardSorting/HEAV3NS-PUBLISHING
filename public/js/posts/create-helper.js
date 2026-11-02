/**
 * Post create (helper) component
 */
"use strict";
/* global app, bootstrap, Post, user, FileUpload, updateButtonState, launchToast, trans, redirect */
/* global passesMinMaxPPPostLimits, TextareaHighlighter, PostComposer */

$(function () {
    $("#post-price").keypress(function(e) {
        if(e.which === 13) {
            PostCreate.savePostPrice();
        }
    });
});

var PostCreate = {
    // Paid post price
    postPrice : 0,
    isSavingRedirect: false,
    postNotifications: false,
    postReleaseDate: null,
    postExpireDate: null,
    pollAnswers: [],
    isSaving: false,
    draftEnabled: false,
    isRestoringDraft: false,
    draftSaveTimer: null,

    refreshComposer: function () {
        if(typeof PostComposer !== 'undefined') PostComposer.refresh();
        PostCreate.scheduleDraftSave();
    },

    submitLabel: function () {
        return typeof PostComposer !== 'undefined' ? PostComposer.submitLabel() : trans('Save');
    },

    /**
     * Toggles post notification state
     */
    togglePostNotifications: function(){
        let buttonIcon = '';
        if(PostCreate.postNotifications === true){
            PostCreate.postNotifications = false;
            buttonIcon = `<div class="d-flex justify-content-center align-items-center me-1"><ion-icon class="icon-medium" name="notifications-off-outline"></ion-icon></div>`;
        }
        else{
            buttonIcon = `<div class="d-flex justify-content-center align-items-center me-1"><ion-icon class="icon-medium" name="notifications-outline"></ion-icon></div>`;
            PostCreate.postNotifications = true;
        }
        $('.post-notification-icon').html(buttonIcon);
        PostCreate.refreshComposer();
    },

    /**
     * Shows up the post price setter dialog
     */
    showSetPricePostDialog: function(){
        if(Number(PostCreate.postPrice) > 0){
            $('#post-price').val(PostCreate.postPrice).removeClass('is-invalid');
            $('.post-price-error').addClass('d-none');
        }
        $('#post-set-price-dialog').modal('show');
    },

    /**
     * Saves the post price into the state
     */
    savePostPrice: function(){
        const price = Number($('#post-price').val());
        let hasError = false;
        if(!passesMinMaxPPPostLimits(price)){
            hasError = 'min';
        }
        if(PostCreate.postExpireDate !== null){
            hasError = 'ppv';
        }
        if(hasError){
            $('.post-price-error').addClass('d-none');
            $('#post-set-price-dialog .'+hasError+'-error').removeClass('d-none');
            $('#post-price').addClass('is-invalid');
            return false;
        }
        PostCreate.postPrice = price;
        PostCreate.refreshComposer();
        $('#post-set-price-dialog').modal('hide');
        $('#post-price').removeClass('is-invalid');
    },
    /**
     * Clears up post price
     */
    clearPostPrice: function(){
        PostCreate.postPrice = 0;
        $('#post-price').val(0);
        $('#post-set-price-dialog').modal('hide');
        $('#post-price').removeClass('is-invalid');
        PostCreate.refreshComposer();
    },

    /**
     * Initiates the post draft data, if available
     * @param data
     * @param type
     */
    initPostDraft: function(data, type = 'draft'){
        PostCreate.draftEnabled = type === 'draft';
        PostCreate.isRestoringDraft = true;
        if(type === 'draft'){
            const draft = PostCreate.normalizeDraft(data);
            Post.draftData = draft;
            FileUpload.attachaments = draft.attachments;
            $('#dropzone-uploader').val(draft.text);
            PostCreate.restoreDraftSettings(draft);
        }
        else if(data){
            // Editing a published post must never load or overwrite the new-post draft.
            Post.draftData = data;
            FileUpload.attachaments = (data.attachments || []).map(FileUpload.normalizeAttachment);
            $('#dropzone-uploader').val(data.text);
        }
        PostCreate.isRestoringDraft = false;
    },

    emptyDraft: function () {
        return {text: '', attachments: [], price: 0, pollAnswers: [], postNotifications: false, postReleaseDate: null, postExpireDate: null};
    },

    normalizeDraft: function (data) {
        data = data && typeof data === 'object' && !Array.isArray(data) ? data : {};
        const draft = PostCreate.emptyDraft();
        draft.text = typeof data.text === 'string' ? data.text : '';
        draft.attachments = (Array.isArray(data.attachments) ? data.attachments : [])
            .filter(function (item) { return item && ['string', 'number'].indexOf(typeof (item.attachmentID || item.id)) !== -1; })
            .map(function (item) { return {attachmentID: item.attachmentID || item.id}; });
        const price = Number(data.price);
        // Retain saved prices even if admin limits changed; normal validation still applies.
        draft.price = Number.isFinite(price) && price > 0 ? price : 0;
        draft.pollAnswers = (Array.isArray(data.pollAnswers) ? data.pollAnswers : [])
            .filter(function (answer) { return answer && typeof answer.value === 'string' && answer.value.trim(); })
            .map(function (answer) { return {id: null, value: answer.value.trim()}; });
        draft.postNotifications = data.postNotifications === true;
        ['postReleaseDate', 'postExpireDate'].forEach(function (field) {
            if(typeof data[field] === 'string' && /^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2})?$/.test(data[field])){
                draft[field] = data[field].replace(' ', 'T');
            }
        });
        return draft;
    },

    restoreDraftSettings: function (draft) {
        PostCreate.postPrice = draft.price;
        PostCreate.pollAnswers = $('.post-poll-summary').length ? draft.pollAnswers : [];
        PostCreate.postNotifications = !!$('.post-notification-summary').length && draft.postNotifications;
        PostCreate.postReleaseDate = $('.post-schedule-summary').length ? draft.postReleaseDate : null;
        PostCreate.postExpireDate = $('.post-schedule-summary').length ? draft.postExpireDate : null;
        $('#post-price').val(draft.price || $('#post-price').attr('value')).removeClass('is-invalid');
        $('#post_release_date').val(PostCreate.postReleaseDate || '');
        $('#post_expire_date').val(PostCreate.postExpireDate || '').removeClass('is-invalid');
        PostCreate.restorePollInputs(PostCreate.pollAnswers);
    },

    restorePollInputs: function (answers) {
        const wrapper = $('.poll-questions-wrapper');
        wrapper.find('.poll-answer').slice(2).remove();
        wrapper.find('input[name="questions"]').val('').removeClass('is-invalid');
        answers.forEach(function (answer, index) {
            if(index >= 2) PostCreate.appendNewPollQuestion();
            // Assign values through the DOM, never interpolate draft text into HTML.
            wrapper.find('input[name="questions"]').eq(index).val(answer.value);
        });
    },

    /**
     * Clears up post draft data
     */
    clearDraft: function(){
        PostCreate.isRestoringDraft = true;
        FileUpload.clearSelection();
        PostCreate.restoreDraftSettings(PostCreate.emptyDraft());
        $('#dropzone-uploader').val('').removeClass('is-invalid').trigger('input');
        TextareaHighlighter.clear('#dropzone-uploader');
        PostCreate.clearDraftData();
        PostCreate.refreshComposer();
        PostCreate.isRestoringDraft = false;
    },

    getDraftStorageKey: function () {
        return typeof user !== 'undefined' && user.user_id ? 'postDraft:' + user.user_id : 'draftData';
    },

    scheduleDraftSave: function () {
        if(!PostCreate.draftEnabled || PostCreate.isRestoringDraft || PostCreate.isSavingRedirect) return;
        window.clearTimeout(PostCreate.draftSaveTimer);
        PostCreate.draftSaveTimer = window.setTimeout(PostCreate.saveDraftData, 150);
    },

    /**
     * Saves post draft data
     */
    saveDraftData: function(){
        if(!PostCreate.draftEnabled || PostCreate.isRestoringDraft || PostCreate.isSavingRedirect) return;
        window.clearTimeout(PostCreate.draftSaveTimer);
        const draft = PostCreate.normalizeDraft({
            text: $('#dropzone-uploader').val(),
            attachments: FileUpload.getDraftAttachments(),
            price: PostCreate.postPrice,
            pollAnswers: PostCreate.pollAnswers,
            postNotifications: PostCreate.postNotifications,
            postReleaseDate: PostCreate.postReleaseDate,
            postExpireDate: PostCreate.postExpireDate
        });
        Post.draftData = draft;
        const empty = !draft.text.trim() && !draft.attachments.length && !draft.price && !draft.pollAnswers.length
            && !draft.postNotifications && !draft.postReleaseDate && !draft.postExpireDate;
        try {
            if(empty) localStorage.removeItem(PostCreate.getDraftStorageKey());
            else localStorage.setItem(PostCreate.getDraftStorageKey(), JSON.stringify(draft));
        } catch (error) {
            // Browser storage can be unavailable; the current composer remains usable.
        }
    },

    /**
     * Clears up draft data
     */
    clearDraftData: function(){
        window.clearTimeout(PostCreate.draftSaveTimer);
        Post.draftData = PostCreate.emptyDraft();
        try {
            localStorage.removeItem(PostCreate.getDraftStorageKey());
        } catch (error) {
            // Keep clearing the in-memory draft even when storage is unavailable.
        }
    },


    /**
     * Populates create/edit post form with draft data
     * @returns {boolean|any}
     */
    populateDraftData: function(){
        try {
            const key = PostCreate.getDraftStorageKey();
            let stored = localStorage.getItem(key);
            const legacy = stored === null && key !== 'draftData';
            if(legacy) stored = localStorage.getItem('draftData');
            const draft = JSON.parse(stored || 'null');
            if(!draft || typeof draft !== 'object' || Array.isArray(draft)) return false;
            if(legacy){
                try {
                    // Preserve existing text/media drafts while adopting account-scoped storage.
                    localStorage.setItem(key, JSON.stringify(draft));
                    localStorage.removeItem('draftData');
                } catch (error) {
                    // Restoration still works if the migration cannot be persisted.
                }
            }
            return draft;
        } catch (error) {
            return false;
        }
    },

    /**
     * Save new / update post
     * @param type
     * @param postID
     */
    save: function (type = 'create', postID = false, forceSave = false) {
        if(PostCreate.isSaving) return false;
        if(FileUpload.isRestoring || FileUpload.restoreFailed || FileUpload.pendingVaultSelections > 0) return false;
        // Warning for any file that might still be uploading or a video transcoding
        if(FileUpload.isBusy() && forceSave === false){
            FileUpload.showUnfinishedUploadsDialog(function () {
                PostCreate.save(type, postID, true);
            });
            return false;
        }

        // Check if locked message has at least one attachment
        if(PostCreate.postPrice > 0 && FileUpload.attachaments.length === 0){
            if(!app.isTextOnlyPPVAllowed){
                $('#no-attachments-locked-post').modal('show');
                return false;
            }
        }

        if(PostCreate.savePostScheduleSettings() === false){
            PostCreate.showPostScheduleDialog();
            return false;
        }
        PostCreate.isSaving = true;
        updateButtonState('loading',$('.post-create-button'), false, 'light');
        let route = app.baseUrl + '/posts/save';
        let data = {
            'attachments': FileUpload.attachaments,
            'text': $('#dropzone-uploader').val(),
            'price': PostCreate.postPrice,
            'postNotifications' : PostCreate.postNotifications,
            'postReleaseDate': PostCreate.postReleaseDate,
            'postExpireDate': PostCreate.postExpireDate,
            'pollAnswers' : PostCreate.pollAnswers
        };
        if(type === 'create'){
            data.type = 'create';
        }
        else{
            data.type = 'update';
            data.id = postID;
        }
        $.ajax({
            type: 'POST',
            data: data,
            url: route,
            success: function (result) {
                if (!result || (result.success !== true && result.success !== 'true')) {
                    launchToast('danger', trans('Error'), (result && result.message) || trans('An internal error has occurred.'));
                    PostCreate.isSaving = false;
                    updateButtonState('loaded', $('.post-create-button'), PostCreate.submitLabel());
                    $('#confirm-post-save').modal('hide');
                    return;
                }
                if (result.publication_review) {
                    launchToast('success', trans('Success'), result.message || result.publication_review.label);
                }
                if(type === 'create'){
                    PostCreate.isSavingRedirect = true;
                    PostCreate.clearDraftData();
                    TextareaHighlighter.clear("#dropzone-uploader");
                    redirect(app.baseUrl+'/'+user.username);
                }
                else{
                    redirect(app.baseUrl+'/posts/'+postID+'/'+user.username);
                }
                PostCreate.isSaving = false;
                updateButtonState('loaded',$('.post-create-button'), PostCreate.submitLabel());
                $('#confirm-post-save').modal('hide');
            },
            error: function (result) {
                if (result.status === 422 || result.status === 500) {
                    const errors = result.responseJSON && result.responseJSON.errors;

                    let inlineError = null;

                    if (errors && typeof errors === 'object') {
                        const preferredFields = ['post', 'text'];

                        for (const field of preferredFields) {
                            if (Array.isArray(errors[field]) && errors[field].length > 0) {
                                inlineError = errors[field][0];
                                break;
                            }
                        }

                        if (!inlineError) {
                            for (const key in errors) {
                                if (Array.isArray(errors[key]) && errors[key].length > 0) {
                                    inlineError = errors[key][0];
                                    break;
                                }
                            }
                        }
                    }

                    if (inlineError) {
                        $('.post-invalid-feedback').html(inlineError);
                        $('#dropzone-uploader').addClass('is-invalid').focus();
                    } else if (result.responseJSON && result.responseJSON.message) {
                        launchToast('danger', trans('Error'), result.responseJSON.message);
                    }

                } else if (result.status === 429) {
                    launchToast(
                        'danger',
                        trans('Error'),
                        (result.responseJSON && result.responseJSON.message) ? result.responseJSON.message : trans('Too many attempts. Please try again later.')
                    );
                } else if (result.status === 403) {
                    launchToast(
                        'danger',
                        trans('Error'),
                        (result.responseJSON && result.responseJSON.message) ? result.responseJSON.message : 'Post not found.'
                    );
                }

                $('#confirm-post-save').modal('hide');
                PostCreate.isSaving = false;
                updateButtonState('loaded', $('.post-create-button'), PostCreate.submitLabel());
            }

        });
    },

    /**
     * Shows up the post scheduling setting setter dialog
     */
    showPostScheduleDialog: function(){
        $('#post-set-schedule-dialog').modal('show');
    },

    /**
     * Saves the post post scheduling setting into the state
     */
    savePostScheduleSettings: function(){

        if(Number(PostCreate.postPrice) > 0 && $('#post_expire_date').val().length > 0){
            $('#post_expire_date').addClass('is-invalid');
            return false;
        }

        PostCreate.postReleaseDate = $('#post_release_date').val().length ? $('#post_release_date').val() : null;
        PostCreate.postExpireDate = $('#post_expire_date').val().length ? $('#post_expire_date').val() : null;
        $('#post-set-schedule-dialog').modal('hide');
        $('#post_expire_date').removeClass('is-invalid');
        PostCreate.refreshComposer();

    },

    /**
     * Clears up post scheduling setting
     */
    clearPostScheduleSettings: function(){
        PostCreate.postReleaseDate = null;
        PostCreate.postExpireDate = null;
        $('#post_release_date').val('');
        $('#post_expire_date').val('');
        $('#post_expire_date').removeClass('is-invalid');
        PostCreate.refreshComposer();
    },

    /**
     * Shows up the post price setter dialog
     */
    showPollEditDialog: function(){
        $('#post-set-poll-dialog').modal('show');
    },

    /**
     * Creates a new poll UI question in the UI
     */
    appendNewPollQuestion: function () {
        const wrapper = $('.poll-questions-wrapper');
        wrapper.append(pollQuestionInputElement());

        const tooltipTrigger = wrapper.find('.poll-answer:last [data-bs-toggle="tooltip"]').get(0);
        if (tooltipTrigger && typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            bootstrap.Tooltip.getOrCreateInstance(tooltipTrigger);
        }
    },

    /**
     * Deletes the poll UI question from ui
     * @param el
     */
    deletePollAnswer: function (el){
        $(el).parent().parent().remove();
    },

    /**
     * Saves the poll to the backend
     */
    savePoll: function (){
        const questionsArray = [];
        let allValid = true;

        $('.poll-questions-wrapper input[name="questions"]').each(function() {
            const input = $(this);
            const value = input.val().trim();
            // get the input's "id" attribute (could be undefined if not set)
            const questionId = input.attr('id') || null;

            if (!value) {
                // Mark as invalid
                input.addClass('is-invalid');
                allValid = false;
            } else {
                // Clear invalid state
                input.removeClass('is-invalid');

                // Push an object with both ID and value
                questionsArray.push({
                    id: questionId,
                    value: value
                });
            }
        });

        if (allValid) {
            $('#post-set-poll-dialog').modal('hide');
            // Store the array of question objects
            PostCreate.pollAnswers = questionsArray;
            PostCreate.refreshComposer();
        }
    },

    /**
     * Clears up poll UI on the create page
     */
    clearPoll: function (){
        PostCreate.pollAnswers = [];
        PostCreate.restorePollInputs([]);
        $('#post-set-poll-dialog').modal('hide');
        PostCreate.refreshComposer();
    }

};

// eslint-disable-next-line no-unused-vars
function pollQuestionInputElement() {
    return `<div class="poll-answer mb-3">
                <div class="d-flex align-items-center">
                    <input class="form-control" name="questions" placeholder="${trans("Enter a poll question")}">
                    <div class="ms-1 h-pill h-pill-primary rounded react-button w-32 d-flex align-items-center" data-bs-toggle="tooltip" data-bs-placement="top" title="${trans("Cancel")}" onclick="PostCreate.deletePollAnswer(this)">
                        <ion-icon name="close-outline"></ion-icon>
                   </div>
               </div>
            </div>`;
}
