/**
 * Component used for handling checkout dialog actions
 */
"use strict";
/* global app, trans, trans_choice, launchToast, getWebsiteFormattedAmount, getTaxDescription */

$(function () {
    // Tip amount: refresh the dialog while typing, quote taxes once the value settles
    $('#checkout-amount').on('input', function () {
        checkout.onAmountInput();
    });
    $('#checkout-amount').on('change', function () {
        checkout.commitAmount();
    });

    // Tip amount shortcuts
    $('.checkout-amount-preset').on('click', function () {
        $('#checkout-amount').val($(this).attr('data-amount')).removeClass('is-invalid');
        checkout.commitAmount();
    });

    // Checkout proceed button event listener
    $('.checkout-continue-btn').on('click', function () {
        checkout.initPayment();
    });

    $('.form-check').on('change', function () {
        $('.error-message').hide();
    });

    // Payment method radios
    $('.checkout-payment-input').on('change', function () {
        checkout.selectPaymentProvider(this.value);
    });

    // More / fewer payment options
    $('.checkout-methods-toggle').on('click', function () {
        $('.checkout-methods-list').toggleClass('is-expanded');
        checkout.layoutPaymentProviders();
    });

    // Billing address: a summary row while collapsed, the form while expanded
    $('#billingInformation').on('show.bs.collapse', function () {
        $('.checkout-billing-summary').addClass('d-none');
    });
    $('#billingInformation').on('hidden.bs.collapse', function () {
        checkout.updateBillingSummary();
        $('.checkout-billing-summary').removeClass('d-none');
    });
    $('.checkout-billing-done').on('click', function (e) {
        e.preventDefault();
        checkout.closeBillingForm();
    });
    $('#billing-agreement-form').on('input change', function () {
        checkout.updateContinueButton();
    });

    $('#checkout-center').on('show.bs.modal', function (e) {
        var collectBillingDetails = checkout.collectsBillingDetails();
        var postId = $(e.relatedTarget).data('post-id');
        var recipientId = $(e.relatedTarget).data('recipient-id');
        var amount = $(e.relatedTarget).data('amount');
        var type = $(e.relatedTarget).data('type');
        var username = $(e.relatedTarget).data('username');
        var firstName = collectBillingDetails ? $(e.relatedTarget).data('first-name') : undefined;
        var lastName = collectBillingDetails ? $(e.relatedTarget).data('last-name') : undefined;
        var billingAddress = collectBillingDetails ? $(e.relatedTarget).data('billing-address') : undefined;
        var name = $(e.relatedTarget).data('name');
        var avatar = $(e.relatedTarget).data('avatar');
        var country = collectBillingDetails ? $(e.relatedTarget).data('country') : undefined;
        var city = collectBillingDetails ? $(e.relatedTarget).data('city') : undefined;
        var state = collectBillingDetails ? $(e.relatedTarget).data('state') : undefined;
        var postcode = collectBillingDetails ? $(e.relatedTarget).data('postcode') : undefined;
        var availableCredit = $(e.relatedTarget).data('available-credit');
        var streamId = $(e.relatedTarget).data('stream-id');
        var userMessageId = $(e.relatedTarget).data('message-id');

        checkout.initiatePaymentData(
            type,
            amount,
            postId,
            recipientId,
            firstName,
            lastName,
            billingAddress,
            country,
            city,
            state,
            postcode,
            availableCredit,
            streamId,
            userMessageId
        );

        checkout.updateUserDetails(avatar, username, name);
        checkout.resetCheckoutState();
        $('#checkout-amount').val(typeof amount === 'undefined' ? '' : amount);

        // Populate select, preselect if checkout.paymentData.country is set,
        // and then auto-fetch BE quote from fillCountrySelectOptions success callback.
        checkout.fillCountrySelectOptions();

        // Prefill billing inputs (including address fields)
        checkout.prefillBillingDetails();

        const months = checkout.getSubscriptionMonths(type);
        if (months) {
            // Subscriptions only offer providers that support recurring payments.
            // CCBill only allows 30 or 90 days subscriptions.
            checkout.togglePaymentProviders(false, checkout.oneTimePaymentProcessorClasses);
            checkout.togglePaymentProvider(!app.ccBillRecurringDisabled && months <= 3, '.ccbill-payment-method');
            checkout.togglePaymentProvider(!app.stripeRecurringDisabled, '.stripe-payment-method');
            checkout.togglePaymentProvider(!app.paypalRecurringDisabled, '.paypal-payment-method');
            checkout.togglePaymentProvider(!app.localWalletRecurringDisabled, '.credit-payment-method');
            checkout.togglePaymentProvider(!app.verotelRecurringDisabled, '.verotel-payment-method');
            checkout.toggleCryptoPaymentProviders(false);
        } else {
            checkout.togglePaymentProviders(true, checkout.oneTimePaymentProcessorClasses);
        }

        checkout.renderPurchaseDetails(type, amount);

        if (collectBillingDetails) {
            checkout.syncBillingSection(!!(firstName && lastName && billingAddress && city && state && postcode && country));
        }

        checkout.syncAmountPresets();
        checkout.layoutPaymentProviders();
        checkout.updateCreditAvailability(parseFloat(amount || 0));
        checkout.ensurePaymentProviderSelection();
        checkout.updateContinueButton();
    });

    $('#checkout-center').on('hidden.bs.modal', function () {
        $(this).find('#billing-agreement-form').trigger('reset');
        checkout.resetCheckoutState();
    });

    // Country change triggers BE-driven quote
    $('.country-select').on('change', function () {
        // keep paymentData.country in sync with selected name
        checkout.validateCountryField();
        checkout.updatePaymentSummaryData();
    });
});

/**
 * Checkout class
 */
var checkout = {
    allowedPaymentProcessors: ['stripe', 'paypal', 'credit', 'nowpayments', 'ccbill', 'paystack', 'yookassa', 'mollie', 'flutterwave', 'coingate', 'xendit', 'paddle', 'cryptocom', 'oxxo', 'mercado', 'verotel', 'razorpay'],
    billingFields: ['first_name', 'last_name', 'billing_address', 'country', 'state', 'postcode', 'city'],
    // When more than one extra option would be hidden, only this many payment options show up front
    visiblePaymentMethodsLimit: 4,
    paymentData: {},

    // keep a reference to last quote request (abort on fast changes)
    _taxQuoteXhr: null,
    _taxQuoteReady: false,
    // Total currently shown in the summary, null while it is being (re)calculated
    _summaryTotal: null,
    _amountTimer: null,

    collectsBillingDetails: function () {
        return $('#checkout-center').attr('data-collect-billing-details') !== 'false';
    },

    oneTimePaymentProcessorClasses: [
        '.nowpayments-payment-method',
        '.ccbill-payment-method',
        '.stripe-payment-method',
        '.paypal-payment-method',
        '.paystack-payment-method',
        '.yookassa-payment-method',
        '.mollie-payment-method',
        '.flutterwave-payment-method',
        '.coingate-payment-method',
        '.xendit-payment-method',
        '.paddle-payment-method',
        '.cryptocom-payment-method',
        '.oxxo-payment-method',
        '.mercado-payment-method',
        '.credit-payment-method',
        '.verotel-payment-method',
        '.razorpay-payment-method',
    ],

    isTipType: function (type) {
        return type === 'tip' || type === 'chat-tip';
    },

    getSubscriptionMonths: function (type) {
        return {
            'one-month-subscription': 1,
            'three-months-subscription': 3,
            'six-months-subscription': 6,
            'yearly-subscription': 12,
        }[type] || 0;
    },

    /**
     * Initiates the payment data payload
     */
    initiatePaymentData: function (type, amount, post, recipient, firstName, lastName, billingAddress, country, city, state, postcode, availableCredit, streamId, messageId) {
        if (checkout._taxQuoteXhr && checkout._taxQuoteXhr.readyState !== 4) {
            checkout._taxQuoteXhr.abort();
        }
        checkout._taxQuoteReady = false;
        checkout._summaryTotal = null;
        checkout.paymentData = {
            type: type,
            amount: amount,
            post: post,
            recipient: recipient,
            availableCredit: availableCredit,
            stream: streamId,
            messageId: messageId,

            // defaults
            taxes: { data: [], subtotal: "0.00", total: "0.00", taxesTotalAmount: "0.00" },
            totalAmount: typeof amount !== 'undefined' ? parseFloat(amount).toFixed(2) : "0.00",
        };
        if (checkout.collectsBillingDetails()) {
            Object.assign(checkout.paymentData, { firstName, lastName, billingAddress, country, city, state, postcode });
        }
    },

    /**
     * Clears per-opening state: pending amount commits, errors, the busy button and the expanded methods list
     */
    resetCheckoutState: function () {
        clearTimeout(checkout._amountTimer);
        checkout._summaryTotal = null;
        checkout.setContinueBusy(false);
        $('#checkout-amount').removeClass('is-invalid');
        $('.payment-error').addClass('d-none');
        $('.checkout-methods-list').removeClass('is-expanded');
        $('.taxes-details').empty();
        $('.checkout-summary-breakdown').addClass('d-none');
        $('.checkout-included-taxes').empty().addClass('d-none');
    },

    /**
     * Fills the dialog title and the purchase block: an amount input for tips, the item and its price otherwise
     */
    renderPurchaseDetails: function (type, amount) {
        const months = checkout.getSubscriptionMonths(type);
        const isTip = checkout.isTipType(type);
        const titles = {
            'tip': trans('Send a tip'),
            'chat-tip': trans('Send a tip'),
            'post-unlock': trans('Unlock post'),
            'message-unlock': trans('Unlock message'),
            'stream-access': trans('Join stream'),
        };
        $('#payment-title').text(months ? trans('Subscribe') : (titles[type] || ''));

        $('.checkout-amount-input').toggleClass('d-none', !isTip);
        $('.checkout-item').toggleClass('d-none', isTip);
        if (!isTip) {
            const price = parseFloat(amount || 0);
            let name = trans(type);
            let meta = trans('One-time payment');
            if (months) {
                meta = months > 1 ? trans(':amount / month', {amount: getWebsiteFormattedAmount((price / months).toFixed(2))}) : '';
            } else if (type === 'post-unlock') {
                name = trans('Locked post');
            } else if (type === 'message-unlock') {
                name = trans('Locked message');
            } else if (type === 'stream-access') {
                name = trans('Stream access');
            }
            $('.checkout-item-icon [data-item-icon]').addClass('d-none');
            $('.checkout-item-icon [data-item-icon="' + (months ? 'subscription' : type) + '"]').removeClass('d-none');
            $('.checkout-item-name').text(name);
            $('.checkout-item-meta').text(meta).toggleClass('d-none', meta === '');
            $('.checkout-item-price').html(getWebsiteFormattedAmount(price.toFixed(2)));
        }

        $('.checkout-total-label').text(months ? trans('Due today') : trans('Total'));
        checkout.updateRenewalNote();
    },

    /**
     * Subscriptions state the renewal terms under the total
     */
    updateRenewalNote: function () {
        const months = checkout.getSubscriptionMonths(checkout.paymentData.type);
        const $note = $('.checkout-renewal-note');
        if (!months) {
            $note.addClass('d-none');
            return;
        }
        const total = checkout._summaryTotal !== null ? checkout._summaryTotal : checkout.paymentData.amount;
        const amount = getWebsiteFormattedAmount(parseFloat(total || 0).toFixed(2));
        const text = months === 1
            ? trans('Renews monthly at :amount. Cancel anytime.', {amount: amount})
            : trans('Renews every :interval at :amount. Cancel anytime.', {interval: trans_choice('months', months, {number: months}).trim(), amount: amount});
        $note.text(text).removeClass('d-none');
    },

    /**
     * Updates the payment form
     */
    updatePaymentForm: function () {
        $('#payment-type').val(checkout.paymentData.type);
        $('#post').val(checkout.paymentData.post);
        $('#recipient').val(checkout.paymentData.recipient);
        $('#provider').val(checkout.paymentData.provider);

        if (checkout.collectsBillingDetails()) {
            $('#paymentFirstName').val(checkout.paymentData.firstName);
            $('#paymentLastName').val(checkout.paymentData.lastName);
            $('#paymentBillingAddress').val(checkout.paymentData.billingAddress);
            $('#paymentCountry').val(checkout.paymentData.country); // name
            $('#paymentState').val(checkout.paymentData.state);
            $('#paymentPostcode').val(checkout.paymentData.postcode);
            $('#paymentCity').val(checkout.paymentData.city);
        }

        // The server applies taxes to this base amount during payment initiation.
        $('#payment-deposit-amount').val(checkout.paymentData.totalAmount);

        // this is BE quote not FE calculated
        $('#paymentTaxes').val(JSON.stringify(checkout.paymentData.taxes));

        $('#stream').val(checkout.paymentData.stream);
        $('#userMessage').val(checkout.paymentData.messageId);
    },

    stripe: null,

    /**
     * Instantiates the payment session
     */
    initPayment: function () {
        if (!checkout.checkoutAmountValidation()) {
            $('#checkout-amount').trigger('focus');
            return false;
        }
        if (checkout.collectsBillingDetails() && !checkout.paymentData.country) {
            $('.country-select').addClass('is-invalid');
            $('#billingInformation').collapse('show');
            return false;
        }
        if (!checkout._taxQuoteReady) {
            checkout.updatePaymentSummaryData(function () { checkout.initPayment(); });
            return false;
        }

        let processor = checkout.getSelectedPaymentMethod();
        if (!processor) {
            $('.payment-error').removeClass('d-none');
        }

        if (processor) {
            $('.paymentProcessorError').hide();
            $('.error-message').hide();
            if (checkout.allowedPaymentProcessors.includes(processor)) {
                checkout.updatePaymentForm();
                checkout.setContinueBusy(true);
                checkout.validateAllFields(() => {
                    checkout.rememberPaymentProvider(processor);
                    $('.payment-button').trigger('click');
                });
            }
        }
    },

    /**
     * Runs backend validation check for billing data
     * @param callback
     */
    validateAllFields: function (callback) {
        checkout.clearFormErrors();
        $.ajax({
            type: 'POST',
            data: $('#pp-buyItem').serialize(),
            url: app.baseUrl + '/payment/initiate/validate',
            success: function () {
                callback();
            },
            error: function (result) {
                checkout.setContinueBusy(false);
                const response = result.responseJSON || {};
                let unmatchedErrors = [];
                let billingError = false;
                $.each(response.errors || {}, function (field, error) {
                    let fieldElement = $('.uifield-' + field);
                    if (!fieldElement.length) {
                        unmatchedErrors.push(Array.isArray(error) ? error.join(' ') : error);
                        return;
                    }
                    if (checkout.billingFields.includes(field)) {
                        billingError = true;
                    }
                    fieldElement.addClass('is-invalid');
                    fieldElement.parent().append(
                        `
                            <span class="invalid-feedback checkout-server-error" role="alert">
                                <strong>${error}</strong>
                            </span>
                        `
                    );
                });
                // Billing errors are only visible with the billing form expanded
                if (billingError) {
                    $('#billingInformation').collapse('show');
                }
                if (unmatchedErrors.length || !response.errors) {
                    launchToast('danger', trans('Error'), unmatchedErrors.join(' ') || response.message || trans('Something went wrong, please try again'));
                }
            }
        });
    },

    /**
     * Clears up dialog (all) form errors
     */
    clearFormErrors: function () {
        $('.checkout-server-error').remove();
        $('#checkout-center .is-invalid').removeClass('is-invalid');
    },

    /**
     * Toggles the pay button spinner and blocks double submits
     */
    setContinueBusy: function (busy) {
        const $spinner = $('.checkout-continue-btn .spinner-border');
        if (busy) {
            $spinner.removeClass('d-none');
        } else {
            $spinner.addClass('d-none');
        }
        $('.checkout-continue-btn').prop('disabled', busy);
    },

    /**
     * Returns currently selected payment method
     */
    getSelectedPaymentMethod: function () {
        const selected = checkout.allowedPaymentProcessors.find(function (provider) {
            return $('.' + provider + '-payment-provider').hasClass('selected');
        });
        if (selected) {
            checkout.paymentData.provider = selected;
            return selected;
        }
        return false;
    },

    /**
     * Marks a payment provider as selected, or clears the selection when name is empty
     */
    selectPaymentProvider: function (name) {
        checkout.allowedPaymentProcessors.forEach(function (provider) {
            const $provider = $('.' + provider + '-payment-provider');
            if (provider === name) {
                $provider.addClass('selected');
            } else {
                $provider.removeClass('selected');
            }
            $provider.find('input').prop('checked', provider === name);
        });
        $('.payment-error').addClass('d-none');
        checkout.layoutPaymentProviders();
        checkout.updateContinueButton();
    },

    isPaymentProviderVisible: function (provider) {
        const $method = $('.' + provider + '-payment-method');
        return $method.length > 0 && !$method.hasClass('d-none');
    },

    hasEnoughCredit: function (total) {
        const credit = parseFloat(checkout.paymentData.availableCredit || 0);
        return credit > 0 && credit >= parseFloat(total || 0);
    },

    /**
     * Keeps a usable provider selected: the current one, else the wallet when it covers the total, else the last used one
     */
    ensurePaymentProviderSelection: function () {
        const total = checkout._summaryTotal !== null ? checkout._summaryTotal : checkout.paymentData.amount;
        const creditUsable = checkout.isPaymentProviderVisible('credit') && checkout.hasEnoughCredit(total);
        const current = checkout.getSelectedPaymentMethod();
        if (current && checkout.isPaymentProviderVisible(current) && (current !== 'credit' || creditUsable)) {
            checkout.selectPaymentProvider(current);
        } else if (creditUsable) {
            checkout.selectPaymentProvider('credit');
        } else {
            checkout.selectFallbackPaymentProvider();
        }
    },

    /**
     * Selects the last used provider when it is offered, else the first visible one (never the wallet)
     */
    selectFallbackPaymentProvider: function () {
        const remembered = checkout.getRememberedPaymentProvider();
        if (remembered && remembered !== 'credit' && checkout.isPaymentProviderVisible(remembered)) {
            checkout.selectPaymentProvider(remembered);
            return;
        }
        const fallback = checkout.allowedPaymentProcessors.find(function (provider) {
            return provider !== 'credit' && checkout.isPaymentProviderVisible(provider);
        });
        checkout.selectPaymentProvider(fallback || null);
    },

    rememberPaymentProvider: function (provider) {
        try {
            if (typeof window !== 'undefined' && window.localStorage) {
                window.localStorage.setItem('checkout.lastProvider', provider);
            }
        } catch {
            // Storage can be unavailable (e.g. private browsing); remembering is optional
        }
    },

    getRememberedPaymentProvider: function () {
        try {
            if (typeof window !== 'undefined' && window.localStorage) {
                return window.localStorage.getItem('checkout.lastProvider');
            }
        } catch {
            // Storage can be unavailable (e.g. private browsing)
        }
        return null;
    },

    /**
     * Shows the first few payment options and tucks the rest behind "More payment options"
     */
    layoutPaymentProviders: function () {
        if (typeof document === 'undefined') return;
        const list = document.querySelector('#checkout-center .checkout-methods-list');
        const toggle = document.querySelector('#checkout-center .checkout-methods-toggle');
        if (!list || !toggle) return;

        const methods = Array.from(list.querySelectorAll('.checkout-payment-method'));
        const visible = methods.filter(function (method) {
            return !method.classList.contains('d-none');
        });
        // Hiding a single option behind a toggle saves nothing, so only collapse when two or more would be hidden.
        // Extra options use Bootstrap's .collapse (hidden until .show) so availability can keep using .d-none.
        const limit = visible.length > checkout.visiblePaymentMethodsLimit + 1 ? checkout.visiblePaymentMethodsLimit : visible.length;
        methods.forEach(function (method) {
            method.classList.toggle('collapse', visible.indexOf(method) >= limit);
        });

        const overflow = visible.length - limit;
        const selectedIsHidden = !!list.querySelector('.checkout-payment-method.collapse .checkout-payment-provider.selected');
        if (selectedIsHidden) {
            list.classList.add('is-expanded');
        }
        const expanded = list.classList.contains('is-expanded');
        methods.forEach(function (method) {
            method.classList.toggle('show', expanded && method.classList.contains('collapse'));
        });
        toggle.classList.toggle('d-none', overflow === 0 || selectedIsHidden);
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        toggle.querySelector('.checkout-methods-toggle-label').textContent = expanded
            ? trans('Fewer payment options')
            : trans('More payment options (:count)', {count: overflow});
    },

    /**
     * Enables the wallet option only when the balance covers the total
     */
    updateCreditAvailability: function (total) {
        const credit = parseFloat(checkout.paymentData.availableCredit || 0);
        const enough = checkout.hasEnoughCredit(total);
        const $credit = $('.credit-payment-provider');
        $('.available-credit').html(getWebsiteFormattedAmount(credit.toFixed(2)));
        $credit.css('pointer-events', enough ? 'auto' : 'none');
        $credit.find('input').prop('disabled', !enough);
        if (enough) {
            $credit.removeClass('is-unavailable');
            $('.credit-available-note').removeClass('d-none');
            $('.credit-insufficient-note').addClass('d-none');
            return;
        }
        $credit.addClass('is-unavailable');
        $('.credit-available-note').addClass('d-none');
        $('.credit-insufficient-note').removeClass('d-none');
        if ($credit.hasClass('selected')) {
            checkout.selectFallbackPaymentProvider();
        }
    },

    /**
     * Pay button: names what is still missing, or the amount about to be paid
     */
    updateContinueButton: function () {
        const type = checkout.paymentData.type;
        const provider = checkout.getSelectedPaymentMethod();
        let label;
        let pending = true;
        if (checkout.isTipType(type) && !checkout.isTipAmountValid($('#checkout-amount').val())) {
            label = trans('Enter an amount');
        } else if (!checkout.isBillingComplete()) {
            label = trans('Add your billing address');
        } else if (!provider) {
            label = trans('Choose a payment method');
        } else {
            pending = false;
            const isSubscription = checkout.getSubscriptionMonths(type) > 0;
            if (checkout._summaryTotal === null) {
                label = isSubscription ? trans('Subscribe') : trans('Pay');
            } else {
                const amount = getWebsiteFormattedAmount(parseFloat(checkout._summaryTotal).toFixed(2));
                label = isSubscription ? trans('Subscribe for :amount', {amount: amount}) : trans('Pay :amount', {amount: amount});
            }
        }

        // Still clickable while pending: a click runs the validation and points at what is missing
        const $button = $('.checkout-continue-btn');
        $button.find('.checkout-continue-label').text(label);
        $button.attr('aria-disabled', pending ? 'true' : 'false');
        if (pending) {
            $button.addClass('opacity-50');
        } else {
            $button.removeClass('opacity-50');
        }
        if (!pending && provider !== 'credit') {
            $button.find('.checkout-continue-icon').removeClass('d-none');
        } else {
            $button.find('.checkout-continue-icon').addClass('d-none');
        }
        checkout.updateTrustLine(pending ? null : provider);
    },

    /**
     * One line under the pay button saying where the payment happens
     */
    updateTrustLine: function (provider) {
        const $trust = $('.checkout-trust');
        const $provider = provider ? $('.' + provider + '-payment-provider') : null;
        const flow = $provider ? $provider.attr('data-provider-flow') : null;
        const name = $provider ? ($provider.attr('data-provider-name') || '') : '';
        let text = '';
        if (flow === 'wallet') {
            text = trans('Paid instantly from your wallet balance.');
        } else if (flow === 'paypal') {
            text = trans("You'll confirm the payment on PayPal, then come back here.");
        } else if (flow === 'crypto' && name) {
            text = trans('Secure checkout by :provider. Crypto payments can take a few minutes to confirm.', {provider: name});
        } else if (name) {
            text = trans("Secure checkout by :provider. You'll come back here afterwards.", {provider: name});
        }
        if (!text) {
            $trust.addClass('d-none');
            return;
        }
        $trust.find('.checkout-trust-text').text(text);
        $trust.removeClass('d-none');
    },

    isTipAmountValid: function (value) {
        if (typeof value !== 'string' || value.trim() === '') return false;
        const amount = Number(value);
        return !isNaN(amount) && amount >= app.tipMinAmount && amount <= app.tipMaxAmount;
    },

    isBillingComplete: function () {
        if (!checkout.collectsBillingDetails()) return true;
        const values = [
            $('input[name="firstName"]').val(),
            $('input[name="lastName"]').val(),
            $('input[name="billingAddress"]').val(),
            $('input[name="billingCity"]').val(),
            $('input[name="billingState"]').val(),
            $('input[name="billingPostcode"]').val(),
            checkout.paymentData.country,
        ];
        return values.every(function (value) {
            return typeof value === 'string' ? value.trim() !== '' : !!value;
        });
    },

    /**
     * Validates the amount field
     * @returns {boolean}
     */
    checkoutAmountValidation: function () {
        // Tips min-max validation
        if (!checkout.isTipType(checkout.paymentData.type)) {
            return true;
        }
        if (checkout.isTipAmountValid($('#checkout-amount').val())) {
            $('#checkout-amount').removeClass('is-invalid');
            return true;
        }
        $('#checkout-amount').addClass('is-invalid');
        return false;
    },

    /**
     * Tip amount typing: keep digits and one decimal separator, refresh the dialog, quote once typing pauses
     */
    onAmountInput: function () {
        const $input = $('#checkout-amount');
        const raw = String($input.val() || '');
        let clean = raw.replace(/,/g, '.').replace(/[^0-9.]/g, '');
        const dot = clean.indexOf('.');
        if (dot !== -1) {
            clean = clean.slice(0, dot + 1) + clean.slice(dot + 1).replace(/\./g, '').slice(0, 2);
        }
        if (clean !== raw) {
            $input.val(clean);
        }
        if (checkout.isTipAmountValid(clean)) {
            $input.removeClass('is-invalid');
        }
        // The total on screen belongs to the previous amount until the new quote arrives
        if (Number(clean) !== checkout.paymentData.amount) {
            checkout._summaryTotal = null;
            $('.total-amount b').text('…');
        }
        checkout.syncAmountPresets();
        checkout.updateContinueButton();
        clearTimeout(checkout._amountTimer);
        checkout._amountTimer = setTimeout(checkout.commitAmount, 450);
    },

    /**
     * Applies the typed tip amount: validates it and fetches a new tax quote when it changed
     */
    commitAmount: function () {
        clearTimeout(checkout._amountTimer);
        checkout.syncAmountPresets();
        const value = String($('#checkout-amount').val() || '');
        const isEmpty = value.trim() === '';
        if (isEmpty) {
            $('#checkout-amount').removeClass('is-invalid');
        }
        if (isEmpty || !checkout.checkoutAmountValidation()) {
            // Nothing valid to quote: drop any pending quote and show an empty summary
            if (checkout._taxQuoteXhr && checkout._taxQuoteXhr.readyState !== 4) {
                checkout._taxQuoteXhr.abort();
            }
            checkout._taxQuoteReady = false;
            checkout.paymentData.amount = 0;
            checkout.renderPaymentSummary({subtotal: '0.00', total: '0.00', data: []});
            checkout.updateCreditAvailability(0);
            checkout.updateContinueButton();
            return;
        }

        const amount = Number(value);
        const quoteInFlight = checkout._taxQuoteXhr && checkout._taxQuoteXhr.readyState !== 4;
        if (amount === checkout.paymentData.amount && (checkout._taxQuoteReady || quoteInFlight)) {
            checkout.updateContinueButton();
            return;
        }
        checkout.paymentData.amount = amount;
        checkout.updatePaymentSummaryData();
    },

    syncAmountPresets: function () {
        const value = String($('#checkout-amount').val() || '');
        $('.checkout-amount-preset').each(function () {
            const active = value !== '' && Number(this.getAttribute('data-amount')) === Number(value);
            $(this).toggleClass('active', active).attr('aria-pressed', active ? 'true' : 'false');
        });
    },

    /**
     * Validates FN field
     */
    validateFirstNameField: function () {
        if (!checkout.collectsBillingDetails()) return;
        let firstNameField = $('input[name="firstName"]');
        checkout.paymentData.firstName = firstNameField.val();
    },

    /**
     * Validates LN field
     */
    validateLastNameField: function () {
        if (!checkout.collectsBillingDetails()) return;
        let lastNameField = $('input[name="lastName"]');
        checkout.paymentData.lastName = lastNameField.val();
    },

    /**
     * Validates Adress field
     */
    validateBillingAddressField: function () {
        if (!checkout.collectsBillingDetails()) return;
        let billingAddressField = $('input[name="billingAddress"]');
        checkout.paymentData.billingAddress = billingAddressField.val();
    },

    /**
     * Validates city field
     */
    validateCityField: function () {
        if (!checkout.collectsBillingDetails()) return;
        let cityField = $('input[name="billingCity"]');
        checkout.paymentData.city = cityField.val();
    },

    /**
     * Validates state field
     */
    validateStateField: function () {
        if (!checkout.collectsBillingDetails()) return;
        let stateField = $('input[name="billingState"]');
        checkout.paymentData.state = stateField.val();
    },

    /**
     * Validates the ZIP code
     */
    validatePostcodeField: function () {
        if (!checkout.collectsBillingDetails()) return;
        let postcodeField = $('input[name="billingPostcode"]');
        checkout.paymentData.postcode = postcodeField.val();
    },

    /**
     * Validates the country field
     */
    validateCountryField: function () {
        if (!checkout.collectsBillingDetails()) return;
        var $countryField = $('.country-select');
        var selected = $countryField.find('option:selected');
        var name = (selected && selected.length) ? (selected.text() || '').trim() : '';

        if (name) {
            $countryField.removeClass('is-invalid');
            checkout.paymentData.country = name;
        } else {
            checkout.paymentData.country = '';
        }
    },

    /**
     * Prefills user billing data, if available
     */
    prefillBillingDetails: function () {
        if (!checkout.collectsBillingDetails()) return;
        $('input[name="firstName"]').val(checkout.paymentData.firstName);
        $('input[name="lastName"]').val(checkout.paymentData.lastName);
        $('input[name="billingCity"]').val(checkout.paymentData.city);
        $('input[name="billingState"]').val(checkout.paymentData.state);
        $('input[name="billingPostcode"]').val(checkout.paymentData.postcode);
        $('input[name="billingAddress"]').val(checkout.paymentData.billingAddress);
    },

    /**
     * Saved billing details collapse to a one line summary; missing ones open the form
     */
    syncBillingSection: function (complete) {
        $('#billingInformation').toggleClass('show', !complete);
        $('.checkout-billing-summary').toggleClass('d-none', !complete);
        $('.checkout-billing-edit').attr('aria-expanded', complete ? 'false' : 'true');
        checkout.updateBillingSummary();
    },

    updateBillingSummary: function () {
        const data = checkout.paymentData;
        const name = [data.firstName, data.lastName].filter(Boolean).join(' ');
        const place = [data.city, data.country].filter(Boolean).join(', ');
        $('.checkout-billing-summary-value').text([name, place].filter(Boolean).join(' · '));
    },

    /**
     * "Done" on the billing form: collapse back to the summary once every field is filled
     */
    closeBillingForm: function () {
        checkout.validateFirstNameField();
        checkout.validateLastNameField();
        checkout.validateBillingAddressField();
        checkout.validateCityField();
        checkout.validateStateField();
        checkout.validatePostcodeField();
        checkout.validateCountryField();

        const fields = ['#billingFirstName', '#billingLastName', '#billingCity', '#billingState', '#billingPostcode', '#billingAddress'];
        const missing = fields.filter(function (selector) {
            return String($(selector).val() || '').trim() === '';
        });
        fields.forEach(function (selector) {
            $(selector).toggleClass('is-invalid', missing.includes(selector));
        });
        $('.country-select').toggleClass('is-invalid', !checkout.paymentData.country);
        if (missing.length) {
            $(missing[0]).trigger('focus');
            return;
        }
        if (!checkout.paymentData.country) {
            return;
        }
        $('#billingInformation').collapse('hide');
    },

    /**
     * Updates user details
     */
    updateUserDetails: function (userAvatar, username, name) {
        $('#checkout-center .user-avatar').attr('src', userAvatar);
        $('#checkout-center .checkout-recipient .name').text(name);
        $('#checkout-center .checkout-recipient .username').text('@' + username);
    },

    /**
     * Fetches list of countries (no need to attach taxes to options anymore)
     * Preselects by checkout.paymentData.country (name) if provided
     * Then triggers BE quote once options exist
     */
    fillCountrySelectOptions: function () {
        if (!checkout.collectsBillingDetails()) {
            checkout.updatePaymentSummaryData();
            return;
        }
        $.ajax({
            type: 'GET',
            url: app.baseUrl + '/countries',
            success: function (result) {
                if (result && result.countries && result.countries.length > 0) {
                    let $select = $('.country-select');

                    // destroy selectize before rebuilding options
                    if ($select[0] && $select[0].selectize) {
                        $select[0].selectize.destroy();
                    }

                    $select.empty();

                    let hasSavedCountry = false;
                    $.each(result.countries, function (i, item) {
                        const isSelected = !!checkout.paymentData.country && checkout.paymentData.country === item.name;
                        hasSavedCountry = hasSavedCountry || isSelected;
                        $select.append($('<option>', {
                            value: item.id, // keep id as value
                            text: item.name,
                            selected: isSelected
                        }));
                    });

                    // Without a saved country the browser would preselect the first one and quote its taxes
                    if (!hasSavedCountry) {
                        $select.prop('selectedIndex', -1);
                    }

                    checkout.initCountrySelectize();

                    // sync paymentData.country + update quote
                    checkout.updatePaymentSummaryData();
                }
            }
        });
    },

    /**
     * Renders the totals. The backend charges subtotal + exclusive + fixed taxes; inclusive taxes are already part
     * of the subtotal, so they are a note under the total rather than lines that would not add up.
     */
    renderPaymentSummary: function (quote) {
        const taxes = (quote.data || []).filter(function (tax) {
            return !tax.hidden;
        });
        const addedTaxes = taxes.filter(function (tax) {
            return tax.taxType !== 'inclusive';
        });
        const includedTaxes = taxes.filter(function (tax) {
            return tax.taxType === 'inclusive';
        });

        const $details = $('.taxes-details');
        $details.empty();
        addedTaxes.forEach(function (tax) {
            $details.append(
                '<div class="d-flex justify-content-between gap-3">' +
                    '<span>' + checkout.escapeHtml(getTaxDescription(tax.taxName, tax.taxPercentage, tax.taxType)) + '</span>' +
                    '<span class="country-tax">' + getWebsiteFormattedAmount(tax.taxAmount) + '</span>' +
                '</div>'
            );
        });
        if (addedTaxes.length) {
            $('.checkout-summary-breakdown').removeClass('d-none');
        } else {
            $('.checkout-summary-breakdown').addClass('d-none');
        }

        const $included = $('.checkout-included-taxes');
        $included.empty();
        includedTaxes.forEach(function (tax) {
            // Described as an exclusive tax to drop the "incl." suffix, the note already says it is included
            const description = getTaxDescription(tax.taxName, tax.taxPercentage, 'exclusive');
            $included.append('<div>' + checkout.escapeHtml(trans('Includes :amount :tax', {amount: getWebsiteFormattedAmount(tax.taxAmount), tax: description})) + '</div>');
        });
        if (includedTaxes.length) {
            $included.removeClass('d-none');
        } else {
            $included.addClass('d-none');
        }
        $('.subtotal-amount b').html(getWebsiteFormattedAmount(quote.subtotal));
        $('.total-amount b').html(getWebsiteFormattedAmount(quote.total));
        checkout._summaryTotal = quote.total;
        checkout.updateRenewalNote();
    },

    /**
     * A failed quote leaves no trustworthy totals, so the summary shows placeholders until the next quote
     */
    clearPaymentSummary: function () {
        $('.taxes-details').empty();
        $('.checkout-summary-breakdown').addClass('d-none');
        $('.checkout-included-taxes').empty().addClass('d-none');
        $('.subtotal-amount b, .total-amount b').text('…');
        checkout._summaryTotal = null;
    },

    escapeHtml: function (value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    },

    /**
     * BE-driven: fetch quote and render taxes/totals.
     * - Called on amount change, country change, and after countries load.
     */
    updatePaymentSummaryData: function (onReady) {
        const collectBillingDetails = checkout.collectsBillingDetails();
        const $countrySelect = $('.country-select');
        const countryName = collectBillingDetails && $countrySelect.val()
            ? ($countrySelect.find('option:selected').text() || '').trim()
            : null;

        // Keep paymentData.country in sync (important for form submit)
        if (collectBillingDetails) {
            checkout.paymentData.country = countryName || '';
        }

        // Discard the previous quote before any new selection is used.
        if (checkout._taxQuoteXhr && checkout._taxQuoteXhr.readyState !== 4) {
            checkout._taxQuoteXhr.abort();
        }
        checkout._taxQuoteReady = false;

        // Base amount: tips/deposits use input; for other types BE overrides internally
        const baseAmount = parseFloat(checkout.paymentData.amount || 0);

        // Full billing needs a selected country before requesting a quote.
        // Without billing details the backend still quotes global taxes.
        if (collectBillingDetails && !countryName) {
            checkout.paymentData.taxes = { data: [], subtotal: baseAmount.toFixed(2), total: baseAmount.toFixed(2), taxesTotalAmount: "0.00" };
            checkout.paymentData.totalAmount = baseAmount.toFixed(2);

            checkout.renderPaymentSummary(checkout.paymentData.taxes);
            $('.checkout-continue-btn').prop('disabled', false);
            checkout.updateCreditAvailability(baseAmount);
            checkout.updateContinueButton();
            return;
        }

        const payload = {
            transaction_type: checkout.paymentData.type,
            amount: baseAmount, // used only for tip/deposit; ignored otherwise
            recipient_user_id: checkout.paymentData.recipient,
            post_id: checkout.paymentData.post,
            user_message_id: checkout.paymentData.messageId,
            stream: checkout.paymentData.stream,
        };
        if (collectBillingDetails) {
            payload.country = countryName;
        }

        // The current lines and totals stay on screen until the new quote replaces them,
        // so switching amounts doesn't resize (and re-center) the dialog
        $('.checkout-continue-btn').prop('disabled', true);

        checkout._taxQuoteXhr = $.ajax({
            type: 'POST',
            url: app.baseUrl + '/payment/taxes/quote',
            data: payload,
            success: function (res) {
                const quote = (res && res.quote) ? res.quote : null;
                if (!quote) {
                    $('.checkout-continue-btn').prop('disabled', false);
                    checkout.clearPaymentSummary();
                    launchToast('danger', trans('Error'), trans('Something went wrong, please try again'));
                    checkout.updateContinueButton();
                    return;
                }

                checkout.paymentData.taxes = quote;
                // Payment initiation expects the base amount and recomputes taxes.
                checkout.paymentData.totalAmount = baseAmount;
                checkout._taxQuoteReady = true;
                $('.checkout-continue-btn').prop('disabled', false);

                checkout.renderPaymentSummary(quote);
                // Credit gating uses total
                checkout.updateCreditAvailability(parseFloat(quote.total || 0));
                checkout.updateContinueButton();
                if (typeof onReady === 'function') onReady();
            },
            error: function (result, status) {
                if (status === 'abort') return;
                $('.checkout-continue-btn').prop('disabled', false);
                checkout.clearPaymentSummary();
                const response = result.responseJSON || {};
                const errors = Object.values(response.errors || {}).flat().join(' ');
                launchToast('danger', trans('Error'), errors || response.message || trans('Something went wrong, please try again'));
                checkout.updateContinueButton();
            }
        });
    },

    toggleCryptoPaymentProviders: function (toggle) {
        let nowPaymentsPaymentMethod = $('.nowpayments-payment-method');
        if (toggle) {
            if (nowPaymentsPaymentMethod.hasClass('d-none')) nowPaymentsPaymentMethod.removeClass('d-none');
        } else {
            if (!nowPaymentsPaymentMethod.hasClass('d-none')) nowPaymentsPaymentMethod.addClass('d-none');
        }
    },

    togglePaymentProvider: function (toggle, paymentMethodClass) {
        let paymentMethod = $(paymentMethodClass);
        if (toggle) {
            if (paymentMethod.hasClass('d-none')) paymentMethod.removeClass('d-none');
        } else {
            if (!paymentMethod.hasClass('d-none')) paymentMethod.addClass('d-none');
        }
    },

    togglePaymentProviders: function (toggle, paymentMethodClasses) {
        paymentMethodClasses.forEach(function (paymentMethodClass) {
            checkout.togglePaymentProvider(toggle, paymentMethodClass);
        });
    },

    initCountrySelectize: function () {
        let $select = $('.country-select');
        if (!$select.length) return;
        if (typeof $select.selectize !== 'function') return;

        // already initialized
        if ($select[0] && $select[0].selectize) return;

        $select.selectize({
            placeholder: trans("Select a country"),
            allowEmptyOption: true,
            searchField: ['text'],
            sortField: [{ field: 'text', direction: 'asc' }],
            closeAfterSelect: true,
            onChange: function () {
                // sync paymentData.country (uses selected text)
                checkout.validateCountryField();
                // fetch BE quote + update UI
                checkout.updatePaymentSummaryData();
            }
        });
    },

};
