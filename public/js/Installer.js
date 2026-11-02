/**
 * Installer component JS side
 */
"use strict";
/* global bootstrap, trans, updateButtonState */

$(function () {
    window.addEventListener('pageshow', Installer.refreshRestoredPage);
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();

    Installer.initDatabaseWarning();

    // Preventing double click on final install step form
    $('.finalInstallStepForm').on('submit', function (e) {
        updateButtonState('loading', $('.finalInstallStepForm .btn-primary'), 'Installing', 'white');
        if(Installer.state.isInstalling === true){
            e.preventDefault();
        }
        setTimeout(function () {
            Installer.state.isInstalling = true;
        }, 100);
    });

    Installer.initProgress();

});

// eslint-disable-next-line no-unused-vars
var Installer = {

    state: {
        isInstalling : false,
        progressRequestRunning: false,
        progressRetryTimer: null,
    },

    refreshRestoredPage: function (event) {
        // Back/Forward can restore an old setup form without contacting the server.
        if(event.persisted){
            window.location.reload();
        }
    },

    initDatabaseWarning: function () {
        $('[data-installer-database-form] input[name^="db_"]').on('input change', function () {
            // The server must check the new connection before it can be acknowledged.
            $('[data-installer-database-warning]').addClass('d-none');
            $('#confirm-non-empty-database').prop('checked', false).prop('disabled', true);
        });
    },

    initProgress: function () {
        const $progress = $('.installer-progress');
        if(!$progress.length){
            return;
        }

        $('.installer-progress-retry').off('click').on('click', function () {
            Installer.clearProgressRetry();
            Installer.advanceInstallation();
        });

        const failedStep = $progress.data('failed-step');
        if(failedStep){
            Installer.markProgressStepFailed(String(failedStep));
            Installer.showProgressError($progress.data('message-failed'));
            $('.installer-progress-restart').removeClass('d-none');
            if(failedStep === 'license'){
                $('.installer-progress-change-details').removeClass('d-none');
            }
            return;
        }

        Installer.advanceInstallation();
    },

    advanceInstallation: function () {
        const $progress = $('.installer-progress');
        if(!$progress.length || Installer.state.progressRequestRunning){
            return;
        }

        Installer.clearProgressRetry();
        Installer.state.progressRequestRunning = true;
        Installer.hideProgressError();
        $('.installer-progress-steps [data-install-state="failed"]').each(function () {
            Installer.setProgressStepStatus($(this), 'in-progress');
        });
        $('.installer-progress-message').text($progress.data('message-working'));

        $.ajax({
            url: $progress.data('advance-url'),
            method: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'X-Installer-Token': $progress.data('installation-token'),
            },
        }).done(function (response) {
            Installer.state.progressRequestRunning = false;
            Installer.updateProgressSteps(response.completed_steps || [], response.next_step || null);

            if(response.finished && response.redirect){
                window.location.assign(response.redirect);
                return;
            }

            Installer.state.progressRetryTimer = setTimeout(function () {
                Installer.advanceInstallation();
            }, 250);
        }).fail(function (xhr) {
            Installer.state.progressRequestRunning = false;

            const response = xhr.responseJSON || {};
            if(xhr.status === 423 || response.retryable === true){
                $('.installer-progress-message').text($progress.data('message-busy'));
                Installer.state.progressRetryTimer = setTimeout(function () {
                    Installer.advanceInstallation();
                }, 2500);
                return;
            }

            if(response.failed_step){
                Installer.markProgressStepFailed(response.failed_step);
                $('.installer-progress-restart').removeClass('d-none');
            }

            if(response.failed_step === 'license'){
                $('.installer-progress-change-details').removeClass('d-none');
            }

            let message = response.message;
            if(!message && xhr.status === 0){
                message = 'The server did not return a response. The step may still be running; wait a moment and retry.';
            }
            if(!message){
                message = 'Installation request failed (HTTP '+xhr.status+').';
            }

            Installer.showProgressError(message);
        });
    },

    updateProgressSteps: function (completedSteps, nextStep) {
        $('.installer-progress-steps [data-install-step]').each(function () {
            const $step = $(this);
            const step = String($step.data('install-step'));

            if(completedSteps.indexOf(step) !== -1){
                Installer.setProgressStepStatus($step, 'complete');
            }
            else if(step === nextStep){
                Installer.setProgressStepStatus($step, 'in-progress');
            }
            else{
                Installer.setProgressStepStatus($step, 'pending');
            }
        });
    },

    setProgressStepStatus: function ($step, state) {
        const colors = {
            complete: 'text-success-emphasis',
            'in-progress': 'text-primary',
            pending: 'text-body-secondary',
            failed: 'text-danger-emphasis',
        };

        $step.attr('data-install-state', state);
        if(state === 'in-progress'){
            $step.attr('aria-current', 'step');
        }
        else{
            $step.removeAttr('aria-current');
        }

        $step.find('.installer-step-status')
            .removeClass('text-success-emphasis text-primary text-body-secondary text-danger-emphasis')
            .addClass(colors[state]);
        $step.find('.installer-step-label').text($('.installer-progress').data('label-'+state));
        $step.find('.installer-step-complete').toggleClass('d-none', state !== 'complete');
        $step.find('.installer-step-spinner').toggleClass('d-none', state !== 'in-progress');
        $step.find('.installer-step-failed').toggleClass('d-none', state !== 'failed');
    },

    markProgressStepFailed: function (step) {
        $('.installer-progress-steps [data-install-step]').each(function () {
            if(String($(this).data('install-step')) === step){
                Installer.setProgressStepStatus($(this), 'failed');
            }
        });
    },

    showProgressError: function (message) {
        $('.installer-progress-message').text('');
        $('.installer-progress-error-message').text(message);
        $('.installer-progress-error').removeClass('d-none');
    },

    hideProgressError: function () {
        $('.installer-progress-error').addClass('d-none');
        $('.installer-progress-error-message').text('');
        $('.installer-progress-change-details').addClass('d-none');
        $('.installer-progress-restart').addClass('d-none');
        const confirmation = document.getElementById('installer-restart-confirmation');
        if(confirmation){
            bootstrap.Collapse.getInstance(confirmation)?.hide();
        }
    },

    clearProgressRetry: function () {
        if(Installer.state.progressRetryTimer){
            clearTimeout(Installer.state.progressRetryTimer);
            Installer.state.progressRetryTimer = null;
        }
    },

    /**
     * Toggles password field between password and text types
     */
    togglePasswordField: function (field = 'password') {
        var tooltipTitle;
        if($('#'+field).attr('type') === 'password'){
            $('#'+field).attr('type','text');
            $('.hide-pass').removeClass('d-none');
            $('.show-pass').addClass('d-none');
            tooltipTitle = trans('Hide password');
        }
        else{
            $('#'+field).attr('type','password');
            $('.show-pass').removeClass('d-none');
            $('.hide-pass').addClass('d-none');
            tooltipTitle = trans('Show password');
        }
        $('.h-pill').each(function () {
            var tooltip = bootstrap.Tooltip.getInstance(this);
            if (tooltip) tooltip.dispose();
            $(this).attr('title', tooltipTitle);
            bootstrap.Tooltip.getOrCreateInstance(this).show();
        });
    },



};
