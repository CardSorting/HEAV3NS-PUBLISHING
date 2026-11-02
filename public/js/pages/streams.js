/*
* Feed page & component
 */
"use strict";
/*
 global app, launchToast, redirect, trans, copyToClipboard, openCreateDialog, openDetailsDialog,
 openEditDialog, hasActiveStream, inProgressStreamCover, mediaSettings, updateButtonState, AiSuggestions,
 stream, streamingDriver, Broadcast
*/

$(function () {
    // If on user (my) streams page
    if((typeof openCreateDialog !== 'undefined' && openCreateDialog !== false) || (typeof openEditDialog !== 'undefined' && openEditDialog !== false)){
        if(typeof hasActiveStream !== 'undefined' && hasActiveStream === true){
            Streams.showStreamEditDialog('edit', stream.id);
        }
        else if (typeof openCreateDialog !== 'undefined' && openCreateDialog !== false) {
            Streams.showStreamEditDialog();
        }
    }
    if(typeof openDetailsDialog !== 'undefined' && openDetailsDialog !== false){
        $('.show-stream-details-label').click();
    }
    if(typeof hasActiveStream !== 'undefined'){
        Streams.hasActiveStream = hasActiveStream;
    }
    Streams.initUploader();
    if(inProgressStreamCover.length){
        Streams.state.streamPosterToEdit = inProgressStreamCover;
    }

    Streams.initCoverChangeEvents();

    if(app.ai_text_enabled) {
        AiSuggestions.init({ target: '#stream-name', type: 'stream' });
    }

    $('#stream-name, #stream-access_price').on('keypress', function(e) {
        if (e.which === 13) { // 13 = Enter key
            e.preventDefault(); // Prevent form submission if inside a form
            Streams.updateStream();
        }
    });


});

var Streams = {

    state : {
        hasActiveStream: null,
        streamIdToDelete: null,
        streamIdToEdit: null,
        streamPosterToEdit: null,
        streamDialogMode: 'create',
        isStreamSaving:false,
    },

    dropzone: null,

    getModalInstance: function (selector) {
        const element = document.querySelector(selector);
        if (!element || !window.bootstrap || !window.bootstrap.Modal) return null;

        return window.bootstrap.Modal.getOrCreateInstance(element);
    },

    showModal: function (selector) {
        const modal = Streams.getModalInstance(selector);
        if (modal) modal.show();
    },

    hideModal: function (selector) {
        const modal = Streams.getModalInstance(selector);
        if (modal) modal.hide();
    },

    replaceModal: function (currentSelector, nextSelector) {
        const currentElement = document.querySelector(currentSelector);
        if (!currentElement || !currentElement.classList.contains('show')) {
            Streams.hideModal(currentSelector);
            Streams.showModal(nextSelector);
            return;
        }

        currentElement.addEventListener('hidden.bs.modal', function () {
            Streams.showModal(nextSelector);
        }, {once: true});
        Streams.hideModal(currentSelector);
    },

    updateDisplayedStreamName: function (name) {
        $('.active-stream-name').each(function () {
            const container = $(this);
            const linkedName = container.find('a').first();
            (linkedName.length ? linkedName : container).text(name);
        });
    },

    /**
     * Instantiates the media uploader for avatar / cover
     */
    initUploader:function () {
        if (!Streams.canPublish() || !document.querySelector('.profile-cover-bg')) return;
        let selector = '';
        selector = '.profile-cover-bg';
        Streams.dropzone = new window.Dropzone(selector, {
            url: app.baseUrl + '/my/streams/poster-upload',
            previewTemplate: document.querySelector('.dz-preview').innerHTML.replace('d-none', ''),
            paramName: "file", // The name that will be used to transfer the file
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            clickable:[`${selector} .upload-button`],
            maxFilesize: mediaSettings.max_file_upload_size, // MB
            addRemoveLinks: true,
            dictRemoveFile: "x",
            acceptedFiles: mediaSettings.allowed_file_extensions,
            autoDiscover: false,
            sending: function(file) {
                file.previewElement.innerHTML = "";
            },
            success: function(file, response) {
                $(selector).css('background-image', 'url(' + response.assetSrc + ')');
                file.previewElement.innerHTML = "";
                Streams.state.streamPosterToEdit = response.assetPath;
            },
            error: function(file, errorMessage) {
                if(typeof errorMessage === 'string'){
                    launchToast('danger','Error ',errorMessage,'now');
                }
                else{
                    launchToast('danger','Error ',errorMessage.errors.file,'now');
                }
                file.previewElement.innerHTML = "";
            },
            dictInvalidFileType: trans("You can't upload files of this type."),
        });
    },

    /**
     * Instantiates the events needed for changing
     */
    initCoverChangeEvents: function(){
        $('.profile-cover-bg').on('tap', function(e) {
            e.preventDefault();
            $('.profile-cover-bg .actions-holder').toggleClass('d-none');
        });

        $('.profile-cover-bg').on({
            mouseenter: function() {
                $('.profile-cover-bg .actions-holder').removeClass('d-none');
            },
            mouseleave: function() {
                $('.profile-cover-bg .actions-holder').addClass('d-none');
            }
        });
    },

    /**
     * Shows up stream management dialog
     * @param mode
     */
    canPublish: function () {
        const creation = document.querySelector('[data-stream-creation]');
        return !creation || creation.dataset.canPublish !== 'false';
    },

    showStreamEditDialog: function (mode = 'create', id = null) {
        if (mode === 'edit' && !id) return;
        Streams.state.streamDialogMode = mode;
        Streams.streamIdToEdit = id;
        let dialogModal = $('#stream-update-dialog');
        if(mode === 'create'){
            dialogModal.find('.create-label').removeClass('d-none');
            dialogModal.find('.edit-label').addClass('d-none');
        }
        else{
            dialogModal.find('.create-label').addClass('d-none');
            dialogModal.find('.edit-label').removeClass('d-none');
        }
        Streams.showModal('#stream-update-dialog');
    },

    /**
     * Shows up stream stop dialog
     * @param mode
     */
    showStreamStopDialog: function () {
        Streams.showModal('#stream-stop-dialog');
    },

    /**
     * Shows up the stream details dialog
     * @param id
     * @param server
     * @param key
     */
    showStreamDetailsDialog: function(id, server, key){
        $('#stream-details-dialog #stream-url').val('rtmp://'+server);
        $('#stream-details-dialog #stream-key').val(key);
        $('#stream-details-dialog #stream-url-larix').val('rtmp://'+server+'/'+key);
        Streams.showModal('#stream-details-dialog');
    },

    /**
     * Shows up stream delete dialog
     * @param mode
     */
    showStreamDeleteDialog: function (streamId) {
        Streams.streamIdToDelete = streamId;
        Streams.showModal('#stream-delete-dialog');
    },

    /**
     * Method used for updating a stream
     * @param type
     */
    updateStream: function () {
        const type = Streams.state.streamDialogMode;
        if (type === 'create' && !Streams.canPublish()) return;
        if (type === 'edit' && (!Streams.hasActiveStream || !Streams.streamIdToEdit)) return;
        if(Streams.state.isStreamSaving) return false;
        let submitButton = $('.stream-save-btn');
        updateButtonState('loading',submitButton, trans('Save'),'white');
        $('.stream-save-btn').addClass('disabled');
        Streams.state.isStreamSaving = true;
        let endpoint = 'init';
        let data = {
            'name': $('#stream-name').val(),
            'price': ($('#stream-access_price').val() ? $('#stream-access_price').val() : 0),
            'requires_subscription':  $('#requires_subscription').is(':checked'),
            'is_public':  $('#is_public').is(':checked'),
            'type': type,
            'poster' : Streams.state.streamPosterToEdit
        };
        if(type === 'edit'){
            endpoint = 'edit';
            data.id = Streams.streamIdToEdit;
        }
        $.ajax({
            type: 'POST',
            data: data,
            url: app.baseUrl + '/my/streams/'+endpoint,
            success: function (result) {
                if(result.success){
                    if(type === 'create'){
                        if(streamingDriver === 'pushr'){
                            Streams.streamIdToEdit = result.data.id;
                            Streams.hasActiveStream = true;
                            //TODO: Move this into it's onw f
                            // Appending the new stream to the list
                            $('.active-stream-container').html(result.html);
                            // Populating & showing up stream details dialog
                            $('#stream-details-dialog #stream-url').val('rtmp://'+result.data.rtmp_server);
                            $('#stream-details-dialog #stream-key').val(result.data.rtmp_key);
                            $('#stream-details-dialog #stream-url-larix').val('rtmp://'+result.data.rtmp_server+'/'+result.data.rtmp_key);
                            $("#requires_subscription").prop("checked", false);
                            if(result.data.requires_subscription){
                                $("#requires_subscription").prop("checked", true);
                            }
                            $("#is_public").prop("checked", false);
                            if(result.data.requires_subscription){
                                $("#is_public").prop("checked", true);
                            }
                            Streams.replaceModal('#stream-update-dialog', '#stream-details-dialog');
                            // Updating go live/on air button states
                            $('.stream-on-label').removeClass('d-none');
                            $('.stream-off-label').addClass('d-none');
                            $('.nav-item-live a').attr('href', $('.nav-item-live a').attr('href').replace('?action=create',''));
                            launchToast('success',trans('Success'),trans('Stream started')+'.');
                        }
                        else if(streamingDriver === 'livekit'){
                            redirect(app.baseUrl+'/my/streams/broadcast');
                        }
                    }
                    else{
                        $('.active-stream-poster').attr('src',result.data.poster);
                        Streams.updateDisplayedStreamName($('#stream-name').val());
                        launchToast('success',trans('Success'),result.message);
                        Streams.hideModal('#stream-update-dialog');
                    }
                    // Clearing up prev form errors
                    $('input').removeClass('is-invalid');
                }
                else{
                    launchToast('danger',trans('Error'),result.message);
                }
                $('.stream-save-btn').removeClass('disabled');
                updateButtonState('loaded',submitButton, trans('Save'));
                Streams.state.isStreamSaving = false;
            },
            error: function (result) {
                if (result.status === 429) {
                    launchToast(
                        'danger',
                        trans('Error'),
                        (result.responseJSON && result.responseJSON.message) ? result.responseJSON.message : trans('Too many attempts. Please try again later.')
                    );
                } else if (result.responseJSON && result.responseJSON.errors) {
                    $.each(result.responseJSON.errors,function (field, value) {
                        if(field === 'name'){
                            $('#stream-name').addClass('is-invalid');
                            $('#stream-name').parent().find('.invalid-feedback strong').text(value[0]);
                            $('#stream-name').focus();
                        }
                        if(field === 'price') {
                            $('#stream-access_price').addClass('is-invalid');
                            $('#stream-access_price').parent().find('.invalid-feedback strong').text(value[0]);
                        }

                    });
                } else if (result.responseJSON && result.responseJSON.message) {
                    launchToast('danger', trans('Error'), result.responseJSON.message);
                }
                $('.stream-save-btn').removeClass('disabled');
                updateButtonState('loaded',submitButton, trans('Save'));
                Streams.state.isStreamSaving = false;
            }
        });
    },

    /**
     * Method used for ending a stream
     */
    endStream: function(){

        let submitButton = $('.end-stream-label');
        updateButtonState('loading',submitButton, trans('End stream'),'white');

        $.ajax({
            type: 'POST',
            data: {},
            dataType: 'json',
            url: app.baseUrl+'/my/streams/stop',
            success: function (result) {
                if(result.success){
                    if(typeof Broadcast !== 'undefined'){
                        Broadcast.allowNavigation = true;
                    }
                    redirect(app.baseUrl+'/my/streams');
                }
                else{
                    launchToast('danger',trans('Error'),result.message);
                    Streams.hideModal('#stream-stop-dialog');
                }
                updateButtonState('loaded',submitButton, trans('End stream'));

            },
            error: function (result) {
                launchToast('danger',trans('Error'),result.responseJSON.message);
                updateButtonState('loaded',submitButton, trans('End stream'));

            }
        });
    },

    /**
     * Method used for removing a stream
     */
    deleteStream: function(){
        $.ajax({
            type: 'DELETE',
            data: {id:Streams.streamIdToDelete},
            dataType: 'json',
            url: app.baseUrl+'/my/streams/delete',
            success: function (result) {
                if(result.success){
                    redirect(app.baseUrl+'/my/streams');
                }
                else{
                    launchToast('danger',trans('Error'),result.message);
                    Streams.hideModal('#stream-delete-dialog');
                }
            },
            error: function (result) {
                launchToast('danger',trans('Error'),result.responseJSON.message);
            }
        });
    },

    /**
     * Copy stream info to clipboard function
     * @param type
     */
    copyStreamData:function (type) {
        let content = '';
        if(type === 'url'){
            content = $('#stream-details-dialog #stream-url').val();
        }
        if(type === 'key'){
            content = $('#stream-details-dialog #stream-key').val();
        }
        if(type === 'mobile-url'){
            content = $('#stream-details-dialog #stream-url-larix').val();
        }
        copyToClipboard(content,'#stream-details-dialog');
        launchToast('success', trans('Success'), trans('Link copied to clipboard')+'.', 'now');
    }

};
