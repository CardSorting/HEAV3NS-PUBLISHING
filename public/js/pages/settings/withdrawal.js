/**
 * Money settings component
 */
"use strict";
/* global app, bootstrap, launchToast, trans, updateButtonState, user, onEnter, ManualPaymentUploads */

$(function () {
    Wallet.handleTaxInformationEnforce();
    Wallet.setPaymentMethodUi();
    Wallet.handleStripeConnect();
    Wallet.updatePayoutAccountFields();
    // Deposit amount change event listener
    $('#withdrawal-amount').on('change', function () {
        if (!Wallet.withdrawalAmountValidation()) {
            return false;
        }
    });
    // Checkout proceed button event listener
    $('.withdrawal-continue-btn').on('click', function () {
        Wallet.initWithdrawal();
    });
    $('#payment-methods').on('change', function() {
        Wallet.setPaymentMethodUi();
        Wallet.handleStripeConnect();
    });
    $('#payout-account-method').on('change', function () { Wallet.updatePayoutAccountFields(); });
    $('.manual-withdrawal-request-fields :input').on('change keyup', function () { $(this).removeClass('is-invalid'); });
    $('#withdrawal-payout-account-id').on('change keyup', function () {
        $(this).removeClass('is-invalid');
    });

    $('.open-payout-account-modal').on('click', function (event) {
        event.preventDefault();
        Wallet.preparePayoutAccountModal($(this).data('mode'));
    });

    $('#payout-account-add').on('click', function () { Wallet.preparePayoutAccountModal('create'); });
    $('.payout-account-back').on('click', function () {
        Wallet.resetPayoutAccountModalUrl();
        Wallet.preparePayoutAccountModal('manage');
        Wallet.focusPayoutAccountView();
    });
    $(document).on('click', '.payout-account-edit', function (event) {
        event.preventDefault();
        Wallet.loadPayoutAccount($(this).attr('data-url'));
    });
    $('#payout-account-dialog').on('click', '.payout-account-delete', function () {
        if (Wallet.payoutAccountBusy) return;
        $('#payout-account-delete-form').attr('action', $(this).attr('data-url'));
        $('#payout-account-delete-name').text($('#payout-account-delete-name').data('label').replace(':name', $(this).attr('data-label')));
        Wallet.showPayoutAccountView('delete');
        Wallet.focusPayoutAccountView();
    });
    $('#payout-account-form, #payout-account-delete-form').on('submit', function (event) {
        event.preventDefault();
        Wallet.submitPayoutAccountForm($(this));
    });
    $('#payout-account-form').on('input change', ':input', function () { $(this).removeClass('is-invalid'); });
    $('#payout-account-dialog').on('hide.bs.modal', function (event) {
        if (Wallet.payoutAccountBusy) event.preventDefault();
    }).on('shown.bs.modal', function () { Wallet.focusPayoutAccountView(); });

    $('#payout-account-dialog').on('hidden.bs.modal', function () {
        Wallet.resetPayoutAccountModalUrl();
        Wallet.preparePayoutAccountModal('manage');
    });

    if(window.payoutAccountModalShouldOpen) {
        const dialog = document.getElementById('payout-account-dialog');
        if (dialog) {
            Wallet.showPayoutAccountView($(dialog).attr('data-view'));
            bootstrap.Modal.getOrCreateInstance(dialog).show();
        }
    }

    onEnter('.withdrawals-form-wrapper', function () {
        Wallet.initWithdrawal();
    });

});

var Wallet = {

    payoutAccountBusy: false,

    withdrawalBusy: false,

    /**
     * Instantiate withdrawal request
     * @returns {boolean}
     */
    initWithdrawal: function () {

        if (Wallet.withdrawalBusy) return false;
        const requestPanel = document.querySelector('.manual-withdrawal-request-fields:not(.d-none)');
        if (typeof ManualPaymentUploads !== 'undefined' && requestPanel && !ManualPaymentUploads.validate(requestPanel)) return false;

        const provider = Wallet.getSelectedProvider();
        if(user.dac7_tax_required || !provider) { return false; }
        if (Wallet.requiresSavedPayoutAccount() && !$('#withdrawal-payout-account-id').val()) {
            $('#withdrawal-payout-account-id').addClass('is-invalid');
            return false;
        }

        if(provider === 'stripe_connect') {
            if (!user.stripe_connect_verified || !user.user_country_id) {
                return false;
            }
        }

        let submitButton = $('.withdrawal-continue-btn');
        updateButtonState('loading',submitButton, trans('Request withdrawal'),'white');

        if(!Wallet.withdrawalAmountValidation()){
            updateButtonState('loaded',submitButton, trans('Request withdrawal'));
            return false;
        }

        $('.withdrawal-error-message').hide();
        Wallet.withdrawalBusy = true;
        if (typeof ManualPaymentUploads !== 'undefined' && requestPanel) ManualPaymentUploads.setSubmitting(requestPanel, true);
        $.ajax({
            type: 'POST',
            data: {
                amount: $('#withdrawal-amount').val(),
                payout_account_id: $('#withdrawal-payout-account-id').val(),
                manual_method_id: Wallet.isManualMethod() ? provider : null,
                method: Wallet.isManualMethod() ? null : provider,
                submitted_fields: Wallet.getSubmittedFields(),
            },
            url: app.baseUrl + '/withdrawals/request',
            success: function (result) {
                Wallet.withdrawalBusy = false;
                if (typeof ManualPaymentUploads !== 'undefined' && requestPanel) {
                    ManualPaymentUploads.setSubmitting(requestPanel, false);
                    if (result.success) ManualPaymentUploads.clear(requestPanel);
                }
                // eslint-disable-next-line no-undef
                const msgType = result.success ? 'success' : 'danger';
                const msgLabel = result.success ? trans('Success') : trans('Error');
                launchToast(msgType, msgLabel, result.message);

                // append new amounts
                $('.wallet-total-amount').html(result.totalAmount);
                $('.wallet-pending-amount').html(result.pendingBalance);

                // clear inputs
                if (result.success) {
                    $('#withdrawal-amount').val('');
                    $(requestPanel).find(':input').val('');
                }

                // Clearing up err messages
                $('#withdrawal-amount').removeClass('is-invalid');
                $('#withdrawal-payout-account-id').removeClass('is-invalid');

                updateButtonState('loaded',submitButton, trans('Request withdrawal'));

            },
            error: function (result) {
                Wallet.withdrawalBusy = false;
                if (typeof ManualPaymentUploads !== 'undefined' && requestPanel) ManualPaymentUploads.setSubmitting(requestPanel, false);
                if(result.status === 429) {
                    launchToast('danger', trans('Error'), result.responseJSON.message || trans('Too many attempts. Please try again later.'));
                } else if((result.status === 422 || result.status === 500) && result.responseJSON && result.responseJSON.errors) {
                    $.each(result.responseJSON.errors, function (field, messages) {
                        if (field === 'amount') {
                            $('#withdrawal-amount').addClass('is-invalid');
                        }
                        if(field === 'method' || field === 'manual_method_id') { $('#payment-methods').addClass('is-invalid'); }
                        if(field.indexOf('submitted_fields.') === 0) {
                            const key = field.substring('submitted_fields.'.length).split('.')[0];
                            $('.manual-withdrawal-request-fields :input:enabled').filter(function () { return $(this).data('field-key') === key; }).addClass('is-invalid');
                            if (requestPanel) {
                                $(requestPanel).find('.manual-document-field').filter(function () { return this.dataset.fieldKey === key; })
                                    .find('.manual-document-status').text(Array.isArray(messages) ? messages[0] : messages);
                            }
                        }
                        if(field === 'payout_account_id'){
                            $('#withdrawal-payout-account-id').addClass('is-invalid');
                        }
                    });
                }

                if(result.responseJSON && result.responseJSON.message) {
                    launchToast('danger', trans('Error'), result.responseJSON.message);
                }
                updateButtonState('loaded',submitButton, trans('Request withdrawal'));
            }
        });
    },

    /**
     * Validates the withdrawal amount
     * @returns {boolean}
     */
    withdrawalAmountValidation: function () {
        let withdrawalAmount = $('#withdrawal-amount').val();
        if (withdrawalAmount.length === 0
            || (withdrawalAmount.length > 0 && (parseFloat(withdrawalAmount) < parseFloat(app.withdrawalsMinAmount)
                || parseFloat(withdrawalAmount) > parseFloat(app.withdrawalsMaxAmount)))) {
            $('#withdrawal-amount').addClass('is-invalid');
            return false;
        } else {
            $('#withdrawal-amount').removeClass('is-invalid');
            return true;
        }
    },

    getSelectedProvider: function () {
        return $('#payment-methods').val();
    },

    requiresSavedPayoutAccount: function () {
        return Wallet.isManualMethod();
    },

    isManualMethod: function () {
        return /^[1-9][0-9]*$/.test(Wallet.getSelectedProvider() || '');
    },

    getSubmittedFields: function () {
        const fields = {};
        $('.manual-withdrawal-request-fields :input:enabled').each(function () {
            const key = $(this).data('field-key');
            if (!key || this.type === 'file') return;
            if (this.classList && this.classList.contains('manual-document-id')) {
                if (!fields[key]) fields[key] = [];
                fields[key].push($(this).val());
            } else {
                fields[key] = $(this).val();
            }
        });
        return fields;
    },

    refreshPayoutAccountOptions: function () {
        const method = Wallet.getSelectedProvider();
        const $select = $('#withdrawal-payout-account-id');
        let $matching = $();
        $select.find('option[value!=""]').each(function () {
            const matches = String($(this).data('method-id')) === String(method);
            $(this).prop('hidden', !matches).prop('disabled', !matches);
            if (matches) { $matching = $matching.add(this); }
        });
        $select.prop('disabled', !$matching.length);
        $select.find('option[value=""]').text($select.data($matching.length ? 'placeholder-label' : 'empty-label'));
        const $selected = $select.find('option:selected');
        if (!$selected.val() || $selected.prop('disabled')) {
            const $default = $matching.filter(function () { return String($(this).data('default')) === '1'; }).first();
            $select.val(($default.length ? $default : $matching.first()).val() || '');
        }
        return $matching.length;
    },

    updatePayoutAccountFields: function () {
        const method = $('#payout-account-method').val();
        $('.manual-payout-account-fields').each(function () {
            const selected = String($(this).data('method-id')) === String(method);
            $(this).toggleClass('d-none', !selected).find(':input').prop('disabled', !selected);
        });
    },

    setPaymentMethodUi: function () {
        const usesSavedPayoutAccounts = Wallet.requiresSavedPayoutAccount();
        Wallet.refreshPayoutAccountOptions();

        $('.saved-payout-account-box').toggleClass('d-none', !usesSavedPayoutAccounts);
        $('.saved-payout-account-helper').toggleClass('d-none', !usesSavedPayoutAccounts);
        $('.manual-withdrawal-request-fields').each(function () {
            const selected = String($(this).data('method-id')) === String(Wallet.getSelectedProvider());
            $(this).toggleClass('d-none', !selected).find(':input').prop('disabled', !selected);
        });
        $('#payment-methods').removeClass('is-invalid');

        if(!usesSavedPayoutAccounts) {
            $('#withdrawal-payout-account-id').removeClass('is-invalid');
        }
    },

    handleStripeConnect: function() {
        const provider = Wallet.getSelectedProvider();
        if(provider === 'stripe_connect') {
            const blocked = !user.stripe_connect_verified || !user.user_country_id || user.dac7_tax_required;
            $('.withdrawal-continue-btn').toggleClass('disabled', blocked).prop('disabled', blocked);
            $('#withdrawal-amount').prop('disabled', !user.stripe_connect_verified || !user.user_country_id);
            $('.saved-payout-account-box').addClass('d-none');
            if(!user.stripe_connect_verified || !user.user_country_id) {
                $('.stripe-connect-label').removeClass('d-none');
                $('.stripe-connect-buttons').removeClass('d-none');
                $('.withdrawal-continue-btn').addClass('disabled');
                $('#withdrawal-amount').attr("disabled", true);
            }
            if(user.stripe_connect_verified) {
                $('.update-stripe-connect-box').removeClass('d-none');
            }
            $('.stripe-connect-pending-onboarding').removeClass('d-none');
        } else {
            Wallet.setPaymentMethodUi();
            $('.withdrawal-continue-btn').removeClass('disabled');
            $('#withdrawal-amount').attr("disabled", false);
            $('.stripe-connect-label').addClass('d-none');
            $('.stripe-connect-buttons').addClass('d-none');
            $('.update-stripe-connect-box').addClass('d-none');
            $('.stripe-connect-pending-onboarding').addClass('d-none');

            const unavailable = !provider || (Wallet.requiresSavedPayoutAccount() && !$('#withdrawal-payout-account-id').val()) || user.dac7_tax_required;
            $('.withdrawal-continue-btn').toggleClass('disabled', unavailable).prop('disabled', unavailable);
        }
    },

    preparePayoutAccountModal: function (mode) {
        if (Wallet.payoutAccountBusy) return;
        Wallet.resetPayoutAccountForm();
        if ($('#payout-account-method option:enabled').filter(function () { return $(this).val() === Wallet.getSelectedProvider(); }).length) {
            $('#payout-account-method').val(Wallet.getSelectedProvider());
        }
        Wallet.updatePayoutAccountFields();
        Wallet.showPayoutAccountView(mode === 'create' ? 'create' : 'manage');
        Wallet.focusPayoutAccountView();
    },

    showPayoutAccountView: function (mode) {
        const form = mode === 'create' || mode === 'edit';
        $('#payout-account-dialog').attr('data-view', mode);
        $('#payout-account-manage-view, #payout-account-manage-actions').toggleClass('d-none', mode !== 'manage');
        $('#payout-account-form-view, #payout-account-form-actions').toggleClass('d-none', !form);
        $('#payout-account-delete-view, #payout-account-delete-actions').toggleClass('d-none', mode !== 'delete');
        $('#payout-account-dialog .modal-header .payout-account-back').toggleClass('d-none', mode === 'manage');
        $('#payout-account-form-title').text($('#payout-account-form-title').data(mode + '-label'));
        $('#payout-account-dialog-description').text($('#payout-account-dialog-description').data((form ? 'form' : mode) + '-label'));
        $('#payout-account-submit').text($('#payout-account-submit').data((mode === 'edit' ? 'edit' : 'create') + '-label'));
        $('#payout-account-method-help').toggleClass('d-none', mode !== 'edit');
        $('#payout-account-status, #payout-account-error').addClass('d-none');
        $('#payout-account-dialog .modal-body').scrollTop(0);
        const dialog = document.getElementById('payout-account-dialog');
        if (dialog) bootstrap.Modal.getInstance(dialog)?.handleUpdate();
    },

    focusPayoutAccountView: function () {
        const view = $('#payout-account-dialog').attr('data-view');
        const selector = view === 'create' ? '#payout-account-method' : view === 'edit'
            ? '.manual-payout-account-fields :input:enabled' : '#payout-account-dialog';
        $(selector).first().get(0)?.focus();
    },

    setPayoutAccountBusy: function (busy) {
        Wallet.payoutAccountBusy = busy;
        $('#payout-account-loading').toggleClass('d-none', !busy);
        $('#payout-account-dialog').attr('aria-busy', busy ? 'true' : 'false').find('button').prop('disabled', busy);
    },

    loadPayoutAccount: function (url) {
        if (!url || Wallet.payoutAccountBusy) return;
        Wallet.preparePayoutAccountModal('manage');
        const dialog = document.getElementById('payout-account-dialog');
        if (!dialog) return;
        bootstrap.Modal.getOrCreateInstance(dialog).show();
        Wallet.setPayoutAccountBusy(true);
        $.ajax({
            url: url,
            dataType: 'json',
            timeout: 15000,
            success: function (result) {
                const account = result.account;
                const method = String(account.manual_method_id);
                if (!$('#payout-account-method option:enabled').filter(function () { return $(this).val() === method; }).length) {
                    Wallet.showPayoutAccountErrors({responseJSON: {message: trans('This method is currently unavailable for withdrawals.')}});
                    return;
                }
                $('#payout-account-method').val(method).prop('disabled', true);
                $('#payout-account-method-lock').val(method).prop('disabled', false);
                $('#payout-account-mode').val('edit');
                $('#payout-account-id').val(account.id);
                $('#payout-account-label').val(account.label || '');
                $('#payout-account-default').prop('checked', account.is_default);
                Wallet.updatePayoutAccountFields();
                $('.manual-payout-account-fields :input:enabled').each(function () {
                    $(this).val(account.account_details?.[$(this).data('field-key')] ?? '');
                });
                Wallet.showPayoutAccountView('edit');
                Wallet.focusPayoutAccountView();
            },
            error: Wallet.showPayoutAccountErrors,
            complete: function () {
                Wallet.setPayoutAccountBusy(false);
            }
        });
    },

    submitPayoutAccountForm: function ($form) {
        if (Wallet.payoutAccountBusy || !$form.get(0).reportValidity()) return;
        const data = $form.serialize();
        $form.find('.is-invalid').removeClass('is-invalid');
        $('#payout-account-error, #payout-account-status').addClass('d-none');
        Wallet.setPayoutAccountBusy(true);
        $.ajax({
            type: 'POST',
            url: $form.attr('action'),
            data: data,
            dataType: 'json',
            timeout: 15000,
            success: function (result) {
                Wallet.refreshPayoutAccounts(result);
                Wallet.resetPayoutAccountModalUrl();
                Wallet.showPayoutAccountView('manage');
                $('#payout-account-status').removeClass('d-none alert-info').addClass('alert-success').text(result.message);
            },
            error: Wallet.showPayoutAccountErrors,
            complete: function () {
                Wallet.setPayoutAccountBusy(false);
                if ($('#payout-account-dialog').attr('data-view') === 'manage') Wallet.focusPayoutAccountView();
            }
        });
    },

    refreshPayoutAccounts: function (result) {
        $('#payout-account-list').html(result.accounts_html);
        const $select = $('#withdrawal-payout-account-id');
        const selectedId = result.saved_account_id || $select.val();
        const placeholder = $select.find('option[value=""]').text();
        $select.empty().append($('<option>', {value: '', text: placeholder}));
        result.accounts.forEach(function (account) {
            $select.append($('<option>', {value: account.id, text: account.label + ' - ' + account.summary})
                .attr('data-method-id', account.manual_method_id).attr('data-default', account.is_default ? '1' : '0'));
        });
        const savedAccount = result.accounts.find(function (account) { return String(account.id) === String(result.saved_account_id); });
        if (savedAccount) $('#payment-methods').val(String(savedAccount.manual_method_id));
        $select.val(String(selectedId || '')).removeClass('is-invalid');
        Wallet.setPaymentMethodUi();
        Wallet.handleStripeConnect();
    },

    showPayoutAccountErrors: function (result) {
        const response = result.responseJSON || {};
        $('#payout-account-error').removeClass('d-none').text(response.message || trans('Could not update payout accounts. Please try again.'));
        $.each(response.errors || {}, function (field, messages) {
            let $input = $();
            if (field === 'manual_method_id') $input = $('#payout-account-method');
            if (field === 'label') $input = $('#payout-account-label');
            if (field === 'isDefault') $input = $('#payout-account-default');
            if (field.indexOf('account_details.') === 0) {
                const key = field.substring('account_details.'.length);
                $input = $('.manual-payout-account-fields :input:enabled').filter(function () { return $(this).data('field-key') === key; });
            }
            $input.addClass('is-invalid').closest('.mb-3, .col-12, .form-check').find('.invalid-feedback').first().text(Array.isArray(messages) ? messages[0] : messages);
        });
        $('#payout-account-form .is-invalid:visible').first().get(0)?.focus();
    },

    resetPayoutAccountForm: function () {
        const $form = $('#payout-account-form');
        if (!$form.length) return;

        const formElement = $form.get(0);
        if (formElement) {
            formElement.reset();
        }

        $('#payout-account-method').prop('disabled', false);
        if ($('#payout-account-method option:selected').prop('disabled')) {
            $('#payout-account-method').val($('#payout-account-method option:enabled').first().val() || '');
        }
        $('#payout-account-method-lock').prop('disabled', true);
        $('.manual-payout-account-fields :input').val('');
        $('#payout-account-mode').val('create');
        $('#payout-account-id').val('');
        $('#payout-account-label').val('');
        $('#payout-account-default').prop('checked', false);
        $form.find('.is-invalid').removeClass('is-invalid');
        Wallet.updatePayoutAccountFields();

    },

    resetPayoutAccountModalUrl: function () {
        if(!window.history || !window.history.replaceState) {
            return;
        }

        const url = new URL(window.location.href);
        url.searchParams.delete('editPayoutAccount');

        if(url.searchParams.get('active') !== 'withdraw') {
            url.searchParams.set('active', 'withdraw');
        }

        const nextUrl = url.searchParams.toString() ? url.pathname + '?' + url.searchParams.toString() : url.pathname;
        window.history.replaceState({}, '', nextUrl);
    },

    handleTaxInformationEnforce() {
        if(user.dac7_tax_required) {
            $('.withdrawal-continue-btn').addClass('disabled');
        }
    }
};
