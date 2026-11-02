@php
    $isDark = GenericHelper::isDarkMode();
    $collectBillingDetails = \App\Services\Payments\CheckoutBilling::collectsDetails();
    $currencySymbol = \App\Providers\SettingsServiceProvider::getWebsiteCurrencySymbol();
    $currencyLeft = \App\Providers\SettingsServiceProvider::leftAlignedCurrencyPosition();
    $tipMin = (float) getSetting('payments.min_tip_value');
    $tipMax = (float) getSetting('payments.max_tip_value');
    $tipPresets = collect([5, 10, 20, 50, 100])
        ->filter(fn ($value) => $value >= $tipMin && ($tipMax <= 0 || $value <= $tipMax))
        ->take(4)
        ->values();
    $walletDepositUrl = route('my.settings', ['type' => 'wallet', 'active' => 'deposit']);
    $walletTotal = Auth::check() && Auth::user()->wallet ? (float) Auth::user()->wallet->total : 0;
    $walletTotalLabel = \App\Providers\SettingsServiceProvider::getWebsiteFormattedAmount(number_format($walletTotal, 2, '.', ''));
    $stripeEnabled = getSetting('payments.stripe_secret_key') && getSetting('payments.stripe_public_key') && !getSetting('payments.stripe_checkout_disabled');

    // Wallet credit is rendered separately, first in the list. "flow" drives the trust line under the pay button.
    $checkoutProviders = [
        ['key' => 'stripe', 'enabled' => $stripeEnabled, 'hidden' => false, 'title' => __('Card'), 'name' => 'Stripe', 'flow' => 'redirect'],
        ['key' => 'paypal', 'enabled' => config('paypal.client_id') && config('paypal.secret') && !getSetting('payments.paypal_checkout_disabled'), 'hidden' => false, 'title' => 'PayPal', 'name' => 'PayPal', 'flow' => 'paypal'],
        ['key' => 'nowpayments', 'enabled' => getSetting('payments.nowpayments_api_key') && !getSetting('payments.nowpayments_checkout_disabled'), 'hidden' => true, 'title' => __('Crypto'), 'name' => 'NOWPayments', 'flow' => 'crypto'],
        ['key' => 'ccbill', 'enabled' => \App\Providers\PaymentsServiceProvider::ccbillCredentialsProvided(), 'hidden' => true, 'title' => __('Card'), 'name' => 'CCBill', 'flow' => 'redirect'],
        ['key' => 'paystack', 'enabled' => getSetting('payments.paystack_secret_key') && !getSetting('payments.paystack_checkout_disabled'), 'hidden' => true, 'title' => 'Paystack', 'name' => 'Paystack', 'flow' => 'redirect'],
        ['key' => 'yookassa', 'enabled' => getSetting('payments.yookassa_shop_id') && getSetting('payments.yookassa_secret_key') && !getSetting('payments.yookassa_checkout_disabled'), 'hidden' => true, 'title' => 'YooMoney', 'name' => 'YooMoney', 'flow' => 'redirect'],
        ['key' => 'mollie', 'enabled' => getSetting('payments.mollie_api_key') && !getSetting('payments.mollie_checkout_disabled'), 'hidden' => true, 'title' => 'Mollie', 'name' => 'Mollie', 'flow' => 'redirect'],
        ['key' => 'flutterwave', 'enabled' => getSetting('payments.flutterwave_secret_key') && !getSetting('payments.flutterwave_checkout_disabled'), 'hidden' => true, 'title' => 'Flutterwave', 'name' => 'Flutterwave', 'flow' => 'redirect'],
        ['key' => 'coingate', 'enabled' => getSetting('payments.coingate_api_token') && !getSetting('payments.coingate_checkout_disabled'), 'hidden' => true, 'title' => __('Crypto'), 'name' => 'CoinGate', 'flow' => 'crypto'],
        ['key' => 'xendit', 'enabled' => getSetting('payments.xendit_secret_key') && !getSetting('payments.xendit_checkout_disabled'), 'hidden' => true, 'title' => 'Xendit', 'name' => 'Xendit', 'flow' => 'redirect'],
        ['key' => 'paddle', 'enabled' => getSetting('payments.paddle_api_key') && getSetting('payments.paddle_hosted_checkout_url') && !getSetting('payments.paddle_checkout_disabled'), 'hidden' => true, 'title' => __('Card'), 'name' => 'Paddle', 'flow' => 'redirect'],
        ['key' => 'cryptocom', 'enabled' => getSetting('payments.cryptocom_secret_key') && !getSetting('payments.cryptocom_checkout_disabled'), 'hidden' => true, 'title' => __('Crypto'), 'name' => 'Crypto.com Pay', 'flow' => 'crypto'],
        ['key' => 'oxxo', 'enabled' => $stripeEnabled && getSetting('payments.stripe_oxxo_provider_enabled'), 'hidden' => true, 'title' => 'OXXO', 'subtitle' => __('Cash payment'), 'name' => 'Stripe', 'flow' => 'redirect'],
        ['key' => 'mercado', 'enabled' => getSetting('payments.mercado_access_token') && !getSetting('payments.mercado_checkout_disabled'), 'hidden' => true, 'title' => 'Mercado Pago', 'name' => 'Mercado Pago', 'flow' => 'redirect'],
        ['key' => 'verotel', 'enabled' => getSetting('payments.verotel_merchant_id') && getSetting('payments.verotel_shop_id') && getSetting('payments.verotel_signature_key') && !getSetting('payments.verotel_checkout_disabled'), 'hidden' => true, 'title' => __('Card'), 'name' => 'Verotel', 'flow' => 'redirect'],
        ['key' => 'razorpay', 'enabled' => getSetting('payments.razorpay_api_key') && getSetting('payments.razorpay_api_secret') && !getSetting('payments.razorpay_checkout_disabled'), 'hidden' => true, 'title' => 'Razorpay', 'name' => 'Razorpay', 'flow' => 'redirect'],
    ];
@endphp

<div class="row checkout-dialog {{ $isDark ? 'checkout-theme-dark' : 'checkout-theme-light' }}"
     data-theme-light-class="checkout-theme-light"
     data-theme-dark-class="checkout-theme-dark">
    <div class="col-lg-6 mx-auto">
        {{-- Paypal and stripe actual buttons --}}
        <div class="paymentOption paymentPP d-none">
            <form id="pp-buyItem" method="post" action="{{route('payment.initiatePayment')}}">
                @csrf
                <input type="hidden" name="amount" id="payment-deposit-amount" value="">
                <input type="hidden" name="transaction_type" id="payment-type" value="">
                <input type="hidden" name="post_id" id="post" value="">
                <input type="hidden" name="user_message_id" id="userMessage" value="">
                <input type="hidden" name="recipient_user_id" id="recipient" value="">
                <input type="hidden" name="provider" id="provider" value="">
                @if($collectBillingDetails)
                <input type="hidden" name="first_name" id="paymentFirstName" value="">
                <input type="hidden" name="last_name" id="paymentLastName" value="">
                <input type="hidden" name="billing_address" id="paymentBillingAddress" value="">
                <input type="hidden" name="city" id="paymentCity" value="">
                <input type="hidden" name="state" id="paymentState" value="">
                <input type="hidden" name="postcode" id="paymentPostcode" value="">
                <input type="hidden" name="country" id="paymentCountry" value="">
                @endif
                <input type="hidden" name="taxes" id="paymentTaxes" value="">
                <input type="hidden" name="stream" id="stream" value="">
                <button class="payment-button" type="submit"></button>
            </form>
        </div>

        <div class="paymentOption ms-2 paymentStripe d-none">
            <button id="stripe-checkout-button">{{__('Checkout')}}</button>
        </div>

        <!-- Modal -->
        <div class="checkout-popup modal fade" id="checkout-center" data-collect-billing-details="{{ $collectBillingDetails ? 'true' : 'false' }}" tabindex="-1" role="dialog" aria-labelledby="payment-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down" role="document">
                <div class="modal-content">
                    <div class="modal-header gap-3 px-3 px-sm-4">
                        <img src="" alt="" class="user-avatar rounded-circle object-fit-cover flex-shrink-0">
                        <div class="flex-grow-1 overflow-hidden">
                            <h5 class="modal-title fw-bolder lh-sm" id="payment-title"></h5>
                            <div class="checkout-recipient d-flex gap-1 small text-body-secondary text-nowrap">
                                <span class="name fw-semibold text-body text-truncate"></span>
                                <span class="username text-truncate"></span>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{__('Close')}}"></button>
                    </div>

                    <div class="modal-body vstack gap-4 p-3 p-sm-4">
                        {{-- Tips: the buyer picks the amount --}}
                        <div class="checkout-amount-input d-none">
                            <div class="d-flex justify-content-between align-items-baseline gap-3">
                                <label for="checkout-amount" class="small fw-semibold text-body-emphasis">{{__('Amount')}}</label>
                                <span class="small text-body-secondary" id="checkout-amount-hint">{{__('Min :min · Max :max', ['min' => \App\Providers\SettingsServiceProvider::getWebsiteFormattedAmount(getSetting('payments.min_tip_value')), 'max' => \App\Providers\SettingsServiceProvider::getWebsiteFormattedAmount(getSetting('payments.max_tip_value'))])}}</span>
                            </div>
                            <div class="input-group has-validation mt-2 mb-0">
                                @if($currencyLeft)
                                    <span class="input-group-text px-3 fs-5 fw-semibold text-body-secondary" aria-hidden="true">{{$currencySymbol}}</span>
                                @endif
                                <input class="form-control fs-5 fw-semibold uifield-amount" id="checkout-amount" type="text" inputmode="decimal" autocomplete="off" placeholder="0" aria-describedby="checkout-amount-hint">
                                @if(!$currencyLeft)
                                    <span class="input-group-text px-3 fs-5 fw-semibold text-body-secondary" aria-hidden="true">{{$currencySymbol}}</span>
                                @endif
                                <div class="invalid-feedback">{{__('Please enter a valid amount.')}}</div>
                            </div>
                            @if($tipPresets->isNotEmpty())
                                <div class="row g-2 mt-1">
                                    @foreach($tipPresets as $tipPreset)
                                        <div class="col">
                                            <button type="button" class="checkout-amount-preset checkout-choice focus-ring w-100 py-2 border rounded-3 bg-transparent fw-semibold text-body" data-amount="{{$tipPreset}}" aria-pressed="false">{{\App\Providers\SettingsServiceProvider::getWebsiteFormattedAmount($tipPreset)}}</button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Subscriptions, unlocks and streams: fixed price item --}}
                        <div class="checkout-item d-none">
                            <div class="d-flex align-items-center gap-3 p-3 border rounded-3 bg-body-secondary">
                                <span class="checkout-item-icon d-flex flex-shrink-0 p-2 rounded-3 bg-body-tertiary fs-4 lh-1 text-body-secondary" aria-hidden="true">
                                    <span class="d-none" data-item-icon="subscription">@include('elements.icon',['icon'=>'repeat-outline','centered'=>false])</span>
                                    <span class="d-none" data-item-icon="post-unlock">@include('elements.icon',['icon'=>'image-outline','centered'=>false])</span>
                                    <span class="d-none" data-item-icon="message-unlock">@include('elements.icon',['icon'=>'chatbubble-ellipses-outline','centered'=>false])</span>
                                    <span class="d-none" data-item-icon="stream-access">@include('elements.icon',['icon'=>'videocam-outline','centered'=>false])</span>
                                </span>
                                <span class="d-flex flex-column flex-grow-1 overflow-hidden lh-sm">
                                    <span class="checkout-item-name fw-semibold text-body-emphasis"></span>
                                    <span class="checkout-item-meta small text-body-secondary"></span>
                                </span>
                                <span class="checkout-item-price flex-shrink-0 fw-bolder text-body-emphasis"></span>
                            </div>
                        </div>

                        <fieldset>
                            <legend class="small fw-semibold text-body-emphasis mb-2">{{__('Pay with')}}</legend>
                            <div class="checkout-methods-list radio-group vstack gap-2">
                                <div class="checkout-payment-method credit-payment-method">
                                    <label class="checkout-payment-provider credit-payment-provider checkout-choice d-flex align-items-center gap-3 mb-0 px-3 py-2 border rounded-3 pointer-cursor" data-value="credit" data-provider-flow="wallet">
                                        <input type="radio" class="checkout-payment-input form-check-input mt-0 me-0" name="checkout-payment-provider" value="credit">
                                        <span class="checkout-provider-logo d-flex align-items-center justify-content-center flex-shrink-0 border rounded-2 bg-white text-dark fs-5" aria-hidden="true">@include('elements.icon',['icon'=>'wallet-outline','centered'=>false])</span>
                                        <span class="d-flex flex-column flex-grow-1 overflow-hidden lh-sm">
                                            <span class="checkout-provider-title fw-semibold text-body-emphasis">{{__('Wallet balance')}}</span>
                                            <span class="credit-available-note small text-body-secondary">{{__('Instant, no redirect')}}</span>
                                            <span class="credit-insufficient-note small text-body-secondary d-none">{{__('Not enough balance')}} · <a href="{{$walletDepositUrl}}" target="_blank" rel="noopener" class="pe-auto fw-semibold text-decoration-none">{{__('Top up')}}</a></span>
                                        </span>
                                        <span class="available-credit flex-shrink-0 fw-semibold text-body-emphasis">{{$walletTotalLabel}}</span>
                                    </label>
                                </div>
                                @foreach($checkoutProviders as $checkoutProvider)
                                    @if($checkoutProvider['enabled'])
                                        <div class="checkout-payment-method {{$checkoutProvider['key']}}-payment-method {{$checkoutProvider['hidden'] ? 'd-none' : ''}}">
                                            <label class="checkout-payment-provider {{$checkoutProvider['key']}}-payment-provider checkout-choice d-flex align-items-center gap-3 mb-0 px-3 py-2 border rounded-3 pointer-cursor" data-value="{{$checkoutProvider['key']}}" data-provider-name="{{$checkoutProvider['name']}}" data-provider-flow="{{$checkoutProvider['flow']}}">
                                                <input type="radio" class="checkout-payment-input form-check-input mt-0 me-0" name="checkout-payment-provider" value="{{$checkoutProvider['key']}}">
                                                <span class="checkout-provider-logo d-flex flex-shrink-0 px-2 py-1 border rounded-2 bg-white" aria-hidden="true"><img src="{{asset('/img/logos/'.$checkoutProvider['key'].'.svg')}}" alt="" class="w-100 h-100 object-fit-contain"></span>
                                                <span class="d-flex flex-column flex-grow-1 overflow-hidden lh-sm">
                                                    <span class="checkout-provider-title fw-semibold text-body-emphasis">{{$checkoutProvider['title']}}</span>
                                                    @if($checkoutProvider['title'] !== $checkoutProvider['name'] || isset($checkoutProvider['subtitle']))
                                                        <span class="small text-body-secondary">{{$checkoutProvider['subtitle'] ?? __('via :provider', ['provider' => $checkoutProvider['name']])}}</span>
                                                    @endif
                                                </span>
                                            </label>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                            <button type="button" class="checkout-methods-toggle d-none mt-1 py-2 px-0 border-0 bg-transparent small fw-semibold text-body-secondary" aria-expanded="false">
                                <span class="checkout-methods-toggle-label"></span>
                                @include('elements.icon',['icon'=>'chevron-down-outline','centered'=>false,'classes'=>'ms-1 align-middle'])
                            </button>
                            <div class="payment-error text-danger small fw-semibold mt-2 d-none">{{__('Please select your payment method')}}</div>
                        </fieldset>

                        @if($collectBillingDetails)
                            <div class="checkout-billing">
                                <div class="checkout-billing-summary d-none">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="flex-grow-1 overflow-hidden">
                                            <div class="small fw-semibold text-body-emphasis">{{__('Billing address')}}</div>
                                            <div class="checkout-billing-summary-value small text-body-secondary text-truncate"></div>
                                        </div>
                                        <a class="checkout-billing-edit small fw-semibold text-decoration-none p-2 m-n2" data-bs-toggle="collapse" href="#billingInformation" role="button" aria-controls="billingInformation" aria-expanded="false">{{__('Edit')}}</a>
                                    </div>
                                </div>
                                <div id="billingInformation" class="collapse show">
                                    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                                        <div>
                                            <div class="small fw-semibold text-body-emphasis">{{__('Billing address')}}</div>
                                            <div class="small text-body-secondary">{{__('Used for taxes and invoices.')}}</div>
                                        </div>
                                        <a class="checkout-billing-done small fw-semibold text-decoration-none p-2 m-n2" href="#billingInformation" role="button">{{__('Done')}}</a>
                                    </div>
                                    <form id="billing-agreement-form">
                                        <div class="row g-3">
                                            <div class="col-6">
                                                <label for="billingFirstName"><span class="form-label d-block small fw-semibold">{{__('First name')}}</span></label>
                                                <input type="text" id="billingFirstName" name="firstName" autocomplete="given-name" onchange="checkout.validateFirstNameField();" required class="form-control uifield-first_name">
                                            </div>
                                            <div class="col-6">
                                                <label for="billingLastName"><span class="form-label d-block small fw-semibold">{{__('Last name')}}</span></label>
                                                <input type="text" id="billingLastName" name="lastName" autocomplete="family-name" onblur="checkout.validateLastNameField()" required class="form-control uifield-last_name">
                                            </div>
                                            <div class="col-6">
                                                <label for="countrySelect"><span class="form-label d-block small fw-semibold">{{__('Country')}}</span></label>
                                                <select class="country-select form-select uifield-country" id="countrySelect" autocomplete="country-name" required onchange="checkout.validateCountryField()"></select>
                                            </div>
                                            <div class="col-6">
                                                <label for="billingCity"><span class="form-label d-block small fw-semibold">{{__('City')}}</span></label>
                                                <input type="text" id="billingCity" name="billingCity" autocomplete="address-level2" onblur="checkout.validateCityField()" required class="form-control uifield-city">
                                            </div>
                                            <div class="col-6">
                                                <label for="billingState"><span class="form-label d-block small fw-semibold">{{__('State')}}</span></label>
                                                <input type="text" id="billingState" name="billingState" autocomplete="address-level1" onblur="checkout.validateStateField()" required class="form-control uifield-state">
                                            </div>
                                            <div class="col-6">
                                                <label for="billingPostcode"><span class="form-label d-block small fw-semibold">{{__('Postcode')}}</span></label>
                                                <input type="text" id="billingPostcode" name="billingPostcode" autocomplete="postal-code" onblur="checkout.validatePostcodeField()" required class="form-control uifield-postcode">
                                            </div>
                                            <div class="col-12">
                                                <label for="billingAddress"><span class="form-label d-block small fw-semibold">{{__('Address')}}</span></label>
                                                <input type="text" id="billingAddress" name="billingAddress" autocomplete="street-address" placeholder="{{__('Street address, apartment, suite, unit')}}" onblur="checkout.validateBillingAddressField()" required class="form-control uifield-billing_address">
                                            </div>
                                        </div>
                                        <div class="billing-agreement-error error text-danger d-none">{{__('Please complete all billing details')}}</div>
                                    </form>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="modal-footer d-block px-3 px-sm-4 pt-3 pb-4" style="--bs-modal-footer-gap: 0">
                        <div class="checkout-summary-breakdown small text-body-secondary mb-1 d-none">
                            <div class="d-flex justify-content-between gap-3">
                                <span>{{__('Subtotal')}}</span>
                                <span class="subtotal-amount"><b class="fw-normal">{{\App\Providers\SettingsServiceProvider::getWebsiteFormattedAmount('0.00')}}</b></span>
                            </div>
                            <div class="taxes-details"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-baseline gap-3 text-body-emphasis">
                            <span class="checkout-total-label fw-semibold">{{__('Total')}}</span>
                            <span class="total-amount fs-4"><b>{{\App\Providers\SettingsServiceProvider::getWebsiteFormattedAmount('0.00')}}</b></span>
                        </div>
                        <div class="checkout-included-taxes small text-body-secondary d-none"></div>
                        <div class="checkout-renewal-note small text-body-secondary d-none"></div>
                        {{-- Disabled while taxes are re-quoted; no fade, so switching amounts doesn't flicker --}}
                        <button type="button" class="btn btn-primary btn-lg w-100 mt-3 mb-0 checkout-continue-btn" style="--bs-btn-disabled-opacity: 1">
                            <span class="checkout-continue-label">{{__('Continue')}}</span>
                            <span class="checkout-continue-icon d-none" aria-hidden="true">@include('elements.icon',['icon'=>'open-outline','centered'=>false,'classes'=>'ms-1 align-text-bottom'])</span>
                            <span class="spinner-border spinner-border-sm ms-2 d-none" role="status">
                                <span class="visually-hidden">{{__('Loading...')}}</span>
                            </span>
                        </button>
                        <div class="checkout-trust small text-body-secondary text-center mt-2 pt-1 d-none">
                            @include('elements.icon',['icon'=>'lock-closed-outline','centered'=>false,'classes'=>'me-1 align-text-bottom'])<span class="checkout-trust-text"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
