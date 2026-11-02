/**
 * Deposit page component
 */
"use strict";
/* global app, trans, onEnter, ManualPaymentUploads, bootstrap, launchToast */

$(function () {
    // Deposit amount change event listener
    $('#deposit-amount').on('change', function () {
        if (!DepositSettings.depositAmountValidation()) {
            return false;
        }

        // update payment amount
        DepositSettings.amount = $('#deposit-amount').val();
    });

    // Checkout proceed button event listener
    $('.deposit-continue-btn').on('click', function () {
        DepositSettings.initPayment();
    });

    $('input[name="payment-radio-option"]').on('change', function () {
        $('.error-message').hide();
        $('.invalid-files-error').hide();
        $('.payment-error').hide();
        DepositSettings.triggerManualPaymentDetails();
    });

    DepositSettings.triggerManualPaymentDetails();
    DepositSettings.initBillingSection();
    DepositSettings.initReceivingDetailCopy();
    DepositSettings.focusValidationError($('.deposit-validation-errors')[0]);

    onEnter('.deposit-form-wrapper', function () {
        DepositSettings.initPayment();
    });

});


/**
 * Deposit class
 */
var DepositSettings = {

    stripe: null,
    paymentProvider: null,
    amount: null,

    initReceivingDetailCopy: function () {
        $(document).off('click.manualReceivingDetails', '[data-manual-receiving-copy]')
            .on('click.manualReceivingDetails', '[data-manual-receiving-copy]', function (event) {
                event.preventDefault();
                DepositSettings.copyReceivingDetail(this);
            });
    },

    copyReceivingDetail: async function (button) {
        const value = button.getAttribute('data-copy-value');
        if (!value) return false;
        let copied = false;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            try {
                await navigator.clipboard.writeText(value);
                copied = true;
            } catch {
                // Clipboard permission can be denied even on HTTPS; try the native fallback.
            }
        }
        if (!copied) copied = this.copyReceivingText(value);
        launchToast(copied ? 'success' : 'danger', copied ? trans('Success') : trans('Error'),
            copied ? trans('Copied!') : trans('Could not copy payment details.'));
        return copied;
    },

    copyReceivingText: function (value) {
        const previousFocus = document.activeElement;
        const textarea = document.createElement('textarea');
        textarea.value = value;
        textarea.readOnly = true;
        textarea.tabIndex = -1;
        textarea.className = 'position-fixed top-0 start-0 opacity-0 pe-none';
        document.body.appendChild(textarea);
        try {
            textarea.select();
            return document.execCommand('copy');
        } catch {
            return false;
        } finally {
            textarea.remove();
            if (previousFocus && typeof previousFocus.focus === 'function') previousFocus.focus({preventScroll: true});
        }
    },

    /**
     * Instantiates new payment session
     */
    initPayment: function () {
        if (!DepositSettings.depositAmountValidation()) {
            DepositSettings.focusValidationError($('#deposit-amount')[0]);
            return false;
        }
        if (!DepositSettings.validateBillingDetails()) return false;

        let processor = DepositSettings.getSelectedPaymentMethod();
        if (processor !== false) {
            $('.paymentProcessorError').hide();
            $('.error-message').hide();
            if(processor === 'manual'){
                let paymentValidation = DepositSettings.manualPaymentValidation();
                if(!paymentValidation) {
                    const panel = DepositSettings.selectedManualPanel()[0];
                    const firstError = panel?.querySelector('.is-invalid:not(:disabled):not([type="hidden"])')
                        || document.querySelector('.invalid-files:not(.d-none)');
                    if (firstError) {
                        DepositSettings.focusValidationError(firstError);
                    } else {
                        $('.payment-error').removeClass('d-none').show();
                        DepositSettings.focusValidationError($('.payment-error')[0]);
                    }
                    return false;
                }
            }
            DepositSettings.updateDepositForm();
            $('.payment-button').trigger('click');
        } else {
            $('.payment-error').removeClass('d-none').show();
            DepositSettings.focusValidationError($('.payment-error')[0]);
        }
    },

    focusValidationError: function (element) {
        if (!element) return;
        element.focus({preventScroll: true});
        element.scrollIntoView({behavior: 'smooth', block: 'center'});
    },

    /**
     * Returns currently selected payment method
     */
    getSelectedPaymentMethod: function () {
        const selected = $('input[name="payment-radio-option"]:checked');
        const val = selected.val();
        DepositSettings.provider = null;
        DepositSettings.manualMethodId = null;
        if (val) {
            if (val === 'payment-manual') {
                DepositSettings.manualMethodId = selected.attr('data-manual-method-id') || null;
            }
            switch (val) {
            case 'payment-stripe':
                DepositSettings.provider = 'stripe';
                break;
            case 'payment-paypal':
                DepositSettings.provider = 'paypal';
                break;
            case 'payment-manual':
                DepositSettings.provider = 'manual';
                break;
            case 'payment-nowpayments':
                DepositSettings.provider = 'nowpayments';
                break;
            case 'payment-ccbill':
                DepositSettings.provider = 'ccbill';
                break;
            case 'payment-paystack':
                DepositSettings.provider = 'paystack';
                break;
            case 'payment-yookassa':
                DepositSettings.provider = 'yookassa';
                break;
            case 'payment-mollie':
                DepositSettings.provider = 'mollie';
                break;
            case 'payment-flutterwave':
                DepositSettings.provider = 'flutterwave';
                break;
            case 'payment-coingate':
                DepositSettings.provider = 'coingate';
                break;
            case 'payment-xendit':
                DepositSettings.provider = 'xendit';
                break;
            case 'payment-paddle':
                DepositSettings.provider = 'paddle';
                break;
            case 'payment-cryptocom':
                DepositSettings.provider = 'cryptocom';
                break;
            case 'payment-oxxo':
                DepositSettings.provider = 'oxxo';
                break;
            case 'payment-mercado':
                DepositSettings.provider = 'mercado';
                break;
            case 'payment-verotel':
                DepositSettings.provider = 'verotel';
                break;
            case 'payment-razorpay':
                DepositSettings.provider = 'razorpay';
                break;
            }
            return DepositSettings.provider || false;
        }
        return false;
    },

    /**
     * Show payment details on deposit form
     */
    triggerManualPaymentDetails: function() {
        const paymentMethod = this.getSelectedPaymentMethod();
        const isManual = paymentMethod === 'manual';
        $('.manual-details').toggleClass('d-none', !isManual);
        $('.manual-method-panel').addClass('d-none').find('.manual-request-field').prop('disabled', true);
        if (isManual) {
            DepositSettings.selectedManualPanel().removeClass('d-none').find('.manual-request-field').prop('disabled', false);
        }
        $('.deposit-continue-btn').text(isManual ? trans('Submit for review') : trans('Add funds'));
        const summary = $('.deposit-provider-summary');
        summary.text(summary.attr(isManual ? 'data-manual-message' : 'data-provider-message'));
        $('.invalid-files').addClass('d-none');
    },

    /**
     * Updates deposit form with predefined values
     */
    updateDepositForm: function () {
        $('#payment-type').val('deposit');
        $('#provider').val(DepositSettings.provider);
        $('#wallet-deposit-amount').val($('#deposit-amount').val());
        $('#manual-method-id').val(DepositSettings.provider === 'manual' ? DepositSettings.manualMethodId : '');
    },

    initBillingSection: function () {
        const section = $('#deposit-billing-information');
        if (!section.length) return;

        section.on('show.bs.collapse', function () {
            $('.deposit-billing-summary').addClass('d-none');
            $('.deposit-billing-edit').attr('aria-expanded', 'true');
        }).on('hidden.bs.collapse', function () {
            DepositSettings.updateBillingSummary();
            $('.deposit-billing-summary').removeClass('d-none');
            $('.deposit-billing-edit').attr('aria-expanded', 'false');
        });
        $('.deposit-billing-done').on('click', function (event) {
            event.preventDefault();
            if (DepositSettings.validateBillingDetails()) bootstrap.Collapse.getOrCreateInstance(section[0], {toggle: false}).hide();
        });
        $('#deposit-billing-information input, #deposit-billing-information select').on('input change', function () {
            $(this).removeClass('is-invalid');
            $('.deposit-billing-error').addClass('d-none');
        });
    },

    updateBillingSummary: function () {
        const name = [$('#deposit-billing-first_name').val(), $('#deposit-billing-last_name').val()]
            .map(value => String(value || '').trim()).filter(Boolean).join(' ');
        const place = [$('#deposit-billing-city').val(), $('#deposit-billing-country').val()]
            .map(value => String(value || '').trim()).filter(Boolean).join(', ');
        $('.deposit-billing-summary-value').text([name, place].filter(Boolean).join(' · '));
    },

    validateBillingDetails: function () {
        let firstInvalid = null;
        $('#deposit-billing-information input, #deposit-billing-information select').each(function () {
            const invalid = !String($(this).val() || '').trim() || !this.checkValidity();
            $(this).toggleClass('is-invalid', invalid);
            if (invalid && !firstInvalid) firstInvalid = this;
        });
        $('.deposit-billing-error').toggleClass('d-none', !firstInvalid);
        if (!firstInvalid) return true;

        const section = $('#deposit-billing-information');
        if (section.hasClass('show')) {
            DepositSettings.focusValidationError(firstInvalid);
        } else {
            section.one('shown.bs.collapse', function () { DepositSettings.focusValidationError(firstInvalid); });
            bootstrap.Collapse.getOrCreateInstance(section[0], {toggle: false}).show();
        }
        return false;
    },

    /**
     * Validates deposit amount field
     * @returns {boolean}
     */
    depositAmountValidation: function () {
        const depositAmount = $('#deposit-amount').val();
        if (!depositAmount || !Number.isFinite(Number(depositAmount)) || Number(depositAmount) < Number(app.depositMinAmount) || Number(depositAmount) > Number(app.depositMaxAmount)) {
            $('#deposit-amount').addClass('is-invalid');
            $('.deposit-amount-feedback').addClass('d-block');
            return false;
        } else {
            $('#deposit-amount').removeClass('is-invalid');
            $('.deposit-amount-feedback').removeClass('d-block');
            $('#wallet-deposit-amount').val(depositAmount);
            return true;
        }
    },

    /**
     * Validates manual payment files
     * @returns {boolean}
     */
    manualPaymentValidation: function () {
        let hasErrors = false;
        const panel = DepositSettings.selectedManualPanel();
        if (!panel.length) {
            return false;
        }
        panel.find('.manual-request-field').each(function () {
            if (this.type === 'file') return;
            const invalid = !this.checkValidity() || (this.required && !String($(this).val() || '').trim());
            $(this).toggleClass('is-invalid', invalid);
            hasErrors = hasErrors || invalid;
        });
        if (typeof ManualPaymentUploads !== 'undefined' && !ManualPaymentUploads.validate(panel[0])) hasErrors = true;
        const minimumAttachments = Number(panel.attr('data-minimum-attachments') || 0);
        const customFileCount = typeof ManualPaymentUploads !== 'undefined' ? ManualPaymentUploads.countUploaded(panel[0]) : 0;
        const filesInvalid = customFileCount < minimumAttachments;
        $('.invalid-files').toggleClass('d-none', !filesInvalid)
            .text(trans('Please attach the required payment proof.') + ' (' + minimumAttachments + ')');
        hasErrors = hasErrors || filesInvalid;
        return !hasErrors;
    },

    selectedManualPanel: function () {
        return $('.manual-method-panel').filter(function () {
            return $(this).attr('data-method-id') === String(DepositSettings.manualMethodId);
        });
    }
};
