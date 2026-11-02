/*
* Post create page
 */
"use strict";
/* global PostCreate, PostComposer, FileUpload, mediaSettings, isAllowedToPost, AiSuggestions, app, TextareaHighlighter, MentionSuggestions */

$(function () {
    // Initing button save
    $('.post-create-button').on('click',function () {
        PostCreate.save('create');
    });

    $('.draft-clear-button').on('click',function () {
        PostCreate.clearDraft();
    });
    // Populating draft data, if available
    const draftData = PostCreate.populateDraftData();
    PostCreate.initPostDraft(draftData);
    PostComposer.init();
    if(isAllowedToPost){
        // Initiating file manager
        FileUpload.initDropZone('#dropzone-uploader','/attachment/upload/post', mediaSettings.use_chunked_uploads);
        PostComposer.bindUploads(FileUpload.myDropzone);
    }
    if (app.ai_text_enabled) {
        AiSuggestions.init({
            target: '#dropzone-uploader',
            editorGetter: null, // no EasyMDE on post create (unless you do have one)
        });
    }

    TextareaHighlighter.init('#dropzone-uploader');
    $('#dropzone-uploader').on('input', function () {
        $(this).removeClass('is-invalid');
        PostCreate.scheduleDraftSave();
    });
    if(app.enable_mention_suggestions && app.enable_mentions){
        MentionSuggestions.init({
            target: '#dropzone-uploader',
            source: window.app.mentionContacts
        });
    }

});

window.addEventListener('pagehide', function () { PostCreate.saveDraftData(); });


// Saving draft data before unload
window.addEventListener('beforeunload', function (event) {
    // Keep the user on the page until the server has accepted the upload.
    if(FileUpload.isLoading === true){
        event.preventDefault();
        event.returnValue = '';
    }
    if(!PostCreate.isSavingRedirect){
        PostCreate.saveDraftData();
    }
});
