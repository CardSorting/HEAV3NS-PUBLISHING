/*
* Post create page
 */
"use strict";
/* global FileUpload, PostCreate, PostComposer, postData, mediaSettings, AiSuggestions, TextareaHighlighter, app, MentionSuggestions */

$(function () {
    // Initing button save
    $('.post-create-button').on('click',function () {
        PostCreate.save('update',postData.id);
    });
    PostCreate.initPostDraft(postData,'edit');
    PostCreate.postPrice = postData.price;
    PostComposer.init();
    if(!$('.post-create-button').prop('disabled')){
        FileUpload.initDropZone('#dropzone-uploader','/attachment/upload/post', mediaSettings.use_chunked_uploads);
        PostComposer.bindUploads(FileUpload.myDropzone);
    }
    if(app.ai_text_enabled) AiSuggestions.init({target: '#dropzone-uploader', editorGetter: null});
    if(postData.hasPoll){
        PostCreate.savePoll();
    }
    TextareaHighlighter.init('#dropzone-uploader');
    $('#dropzone-uploader').on('input', function () {
        $(this).removeClass('is-invalid');
    });
    if(app.enable_mention_suggestions && app.enable_mentions){
        MentionSuggestions.init({
            target: '#dropzone-uploader',
            source: window.app.mentionContacts
        });
    }
});

// Saving draft data before unload
window.addEventListener('beforeunload', function (event) {
    // Keep the user on the page until the server has accepted the upload.
    if(FileUpload.isLoading === true){
        event.preventDefault();
        event.returnValue = '';
    }
});
