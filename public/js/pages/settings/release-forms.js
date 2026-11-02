/**
 * Release forms settings component
 */
"use strict";
/* global app, mediaSettings, Dropzone, FileUpload, launchToast, trans */

Dropzone.autoDiscover = false;

$(function () {
    ReleaseFormsSettings.initUploader();
});

var ReleaseFormsSettings = {

    myDropzone: null,
    uploadedFiles: [],

    initUploader: function () {
        let selector = '.dropzone';
        ReleaseFormsSettings.myDropzone = new window.Dropzone(selector, {
            url: app.baseUrl + '/my/settings/release-forms/upload',
            previewTemplate: document.querySelector('#tpl').innerHTML,
            paramName: "file",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            maxFilesize: mediaSettings.max_file_upload_size,
            addRemoveLinks: true,
            dictRemoveFile: "x",
            acceptedFiles: mediaSettings.release_forms_file_extensions,
            dictDefaultMessage: trans("Drop files here to upload"),
            autoDiscover: false,
            previewsContainer: ".dropzone-previews",
            autoProcessQueue: true,
            parallelUploads: 1,
            dictInvalidFileType: trans("You can't upload files of this type."),
        });

        ReleaseFormsSettings.myDropzone.on("addedfile", file => {
            FileUpload.updatePreviewElement(file, true);
        });

        ReleaseFormsSettings.myDropzone.on("success", (file, response) => {
            ReleaseFormsSettings.uploadedFiles.push(response.attachmentID);
            file.upload.assetSrc = response.path;
            file.upload.attachmentID = response.attachmentID;
        });

        ReleaseFormsSettings.myDropzone.on("removedfile", function(file) {
            ReleaseFormsSettings.removeAsset(file.upload.attachmentID);
        });

        ReleaseFormsSettings.myDropzone.on("error", (file, errorMessage) => {
            if (typeof errorMessage.errors !== 'undefined') {
                $.each(errorMessage.errors, function (field, error) {
                    launchToast('danger', trans('Error'), error);
                });
            }
            else if (typeof errorMessage.message !== 'undefined') {
                launchToast('danger', trans('Error'), errorMessage.message);
            }
            else {
                launchToast('danger', trans('Error'), errorMessage);
            }

            ReleaseFormsSettings.myDropzone.removeFile(file);
        });
    },

    removeAsset: function (file) {
        $.ajax({
            type: 'POST',
            data: {
                'assetSrc': file,
            },
            url: app.baseUrl + '/my/settings/release-forms/upload/delete',
            success: function () {
                ReleaseFormsSettings.uploadedFiles = ReleaseFormsSettings.uploadedFiles.filter(item => item !== file);
                launchToast('success', trans('Success'), trans('Attachment removed.'));
            },
            error: function () {
                launchToast('danger', trans('Error'), trans('Failed to remove the attachment.'));
            }
        });
    }
};
