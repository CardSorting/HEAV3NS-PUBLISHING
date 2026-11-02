@php
    $depositPaymentMethods = [
        [
            'enabled' => config('paypal.client_id') && config('paypal.secret'),
            'id' => 'deposit-payment-paypal',
            'value' => 'payment-paypal',
            'name' => __('Paypal'),
            'logo' => asset('/img/logos/paypal.svg'),
            'type' => __('Wallet & cards'),
            'note' => __('Pay with PayPal balance or linked cards.'),
        ],
        [
            'enabled' => getSetting('payments.stripe_secret_key') && getSetting('payments.stripe_public_key'),
            'id' => 'deposit-payment-stripe',
            'value' => 'payment-stripe',
            'name' => __('Stripe'),
            'logo' => asset('/img/logos/stripe.svg'),
            'type' => __('Cards & local methods'),
            'note' => __('Secure card checkout with supported local options.'),
        ],
        [
            'enabled' => getSetting('payments.nowpayments_api_key'),
            'id' => 'deposit-payment-nowpayments',
            'value' => 'payment-nowpayments',
            'name' => __('NowPayments'),
            'logo' => asset('/img/logos/nowpayments.svg'),
            'type' => __('Crypto'),
            'note' => __('Pay using supported cryptocurrencies.'),
        ],
        [
            'enabled' => getSetting('payments.mercado_access_token'),
            'id' => 'deposit-payment-mercado',
            'value' => 'payment-mercado',
            'name' => __('MercadoPago'),
            'logo' => asset('/img/logos/mercado.svg'),
            'type' => __('Cards & wallet'),
            'note' => __('Regional card and wallet payments.'),
        ],
        [
            'enabled' => \App\Providers\PaymentsServiceProvider::ccbillCredentialsProvided(),
            'id' => 'deposit-payment-ccbill',
            'value' => 'payment-ccbill',
            'name' => __('CCBill'),
            'logo' => asset('/img/logos/ccbill.svg'),
            'type' => __('Card payments'),
            'note' => __('Card checkout through CCBill.'),
        ],
        [
            'enabled' => getSetting('payments.paystack_secret_key'),
            'id' => 'deposit-payment-paystack',
            'value' => 'payment-paystack',
            'name' => __('Paystack'),
            'logo' => asset('/img/logos/paystack.svg'),
            'type' => __('Cards & bank'),
            'note' => __('Card and bank payments where supported.'),
        ],
        [
            'enabled' => getSetting('payments.yookassa_shop_id') && getSetting('payments.yookassa_secret_key'),
            'id' => 'deposit-payment-yookassa',
            'value' => 'payment-yookassa',
            'name' => __('YooMoney'),
            'logo' => asset('/img/logos/yookassa.svg'),
            'type' => __('Cards & wallet'),
            'note' => __('YooKassa checkout with supported methods.'),
        ],
        [
            'enabled' => getSetting('payments.mollie_api_key'),
            'id' => 'deposit-payment-mollie',
            'value' => 'payment-mollie',
            'name' => __('Mollie'),
            'logo' => asset('/img/logos/mollie.svg'),
            'type' => __('Cards & local methods'),
            'note' => __('Mollie checkout with regional options.'),
        ],
        [
            'enabled' => getSetting('payments.flutterwave_secret_key'),
            'id' => 'deposit-payment-flutterwave',
            'value' => 'payment-flutterwave',
            'name' => __('Flutterwave'),
            'logo' => asset('/img/logos/flutterwave.svg'),
            'type' => __('Cards & mobile money'),
            'note' => __('Card and mobile money payments.'),
        ],
        [
            'enabled' => getSetting('payments.coingate_api_token'),
            'id' => 'deposit-payment-coingate',
            'value' => 'payment-coingate',
            'name' => __('CoinGate'),
            'logo' => asset('/img/logos/coingate.svg'),
            'type' => __('Crypto'),
            'note' => __('Pay using supported cryptocurrencies.'),
        ],
        [
            'enabled' => getSetting('payments.xendit_secret_key'),
            'id' => 'deposit-payment-xendit',
            'value' => 'payment-xendit',
            'name' => __('Xendit'),
            'logo' => asset('/img/logos/xendit.svg'),
            'type' => __('Cards, e-wallets & bank'),
            'note' => __('Regional payment methods through Xendit.'),
        ],
        [
            'enabled' => getSetting('payments.paddle_api_key') && getSetting('payments.paddle_hosted_checkout_url'),
            'id' => 'deposit-payment-paddle',
            'value' => 'payment-paddle',
            'name' => __('Paddle'),
            'logo' => asset('/img/logos/paddle.svg'),
            'type' => __('Cards & wallets'),
            'note' => __('Hosted checkout with card and wallet options.'),
        ],
        [
            'enabled' => getSetting('payments.cryptocom_secret_key'),
            'id' => 'deposit-payment-cryptocom',
            'value' => 'payment-cryptocom',
            'name' => __('Crypto.com'),
            'logo' => asset('/img/logos/cryptocom.svg'),
            'type' => __('Crypto'),
            'note' => __('Pay using Crypto.com supported assets.'),
        ],
        [
            'enabled' => getSetting('payments.stripe_secret_key') && getSetting('payments.stripe_public_key') && getSetting('payments.stripe_oxxo_provider_enabled'),
            'id' => 'deposit-payment-oxxo',
            'value' => 'payment-oxxo',
            'name' => __('Oxxo'),
            'logo' => asset('/img/logos/oxxo.svg'),
            'type' => __('Cash voucher'),
            'note' => __('Pay in cash using an OXXO voucher.'),
        ],
        [
            'enabled' => getSetting('payments.verotel_merchant_id') && getSetting('payments.verotel_shop_id') && getSetting('payments.verotel_signature_key'),
            'id' => 'deposit-payment-verotel',
            'value' => 'payment-verotel',
            'name' => __('Verotel'),
            'logo' => asset('/img/logos/verotel.svg'),
            'type' => __('Card payments'),
            'note' => __('Card checkout through Verotel.'),
        ],
        [
            'enabled' => getSetting('payments.razorpay_api_key') && getSetting('payments.razorpay_api_secret'),
            'id' => 'deposit-payment-razorpay',
            'value' => 'payment-razorpay',
            'name' => __('Razorpay'),
            'logo' => asset('/img/logos/razorpay.svg'),
            'type' => __('Cards, UPI & banking'),
            'note' => __('Regional checkout with card and bank options.'),
        ],
    ];
    $manualDepositMethods = \App\Model\ManualPaymentMethod::enabledFor(\App\Model\ManualPaymentMethod::DEPOSIT)
        ->map(fn ($method) => [
            'id' => $method->id,
            'name' => $method->name,
            'instructions' => $method->instructions,
            'receiving_details' => $method->receiving_details ?? [],
            'request_fields' => $method->request_fields ?? [],
            'submission_settings' => $method->submission_settings ?? [],
        ])->all();
    if (!getSetting('payments.allow_manual_payments')) {
        $manualDepositMethods = [];
    }
    foreach ($manualDepositMethods as $manualMethod) {
        $depositPaymentMethods[] = [
            'enabled' => true,
            'id' => 'deposit-payment-manual-'.$manualMethod['id'],
            'value' => 'payment-manual',
            'manual_method_id' => $manualMethod['id'],
            'name' => $manualMethod['name'], 'logo' => null,
            'type' => __('Manual review'),
            'note' => __('Send payment using the instructions, then submit for review.'),
            'icon' => 'business-outline', 'full_width_when_odd' => true,
        ];
    }
    $enabledDepositPaymentMethods = array_values(array_filter($depositPaymentMethods, fn ($method) => $method['enabled']));
    $enabledDepositPaymentMethodCount = count($enabledDepositPaymentMethods);
    $preferredDepositMethod = old('provider')
        ? ['provider' => old('provider'), 'manual_method_id' => old('manual_method_id')]
        : ($lastDepositMethod ?? null);
    $selectedDepositMethodId = app(\App\Services\Settings\WalletPaymentMethodDefaults::class)
        ->depositOptionId($enabledDepositPaymentMethods, $preferredDepositMethod);
@endphp

@if($errors->any())
    <div class="alert alert-danger deposit-validation-errors" role="alert" tabindex="-1">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="deposit-nice-shell">
    <div class="deposit-card">
        <div class="deposit-card-title">{{__('Add funds')}}</div>
        <div class="deposit-card-copy">{{__('Choose how much you want to add to your wallet before continuing to payment.')}}</div>
        <div class="deposit-amount-field">
            <div class="input-group">
                <span class="input-group-text" id="amount-label">@include('elements.icon',['icon'=>'cash-outline','variant'=>'medium'])</span>
                <input class="form-control" placeholder="{{\App\Providers\PaymentsServiceProvider::getDepositLimitAmounts()}}"
                       aria-label="{{__('Amount')}}"
                       aria-describedby="amount-label"
                       id="deposit-amount"
                       type="number"
                       min="{{\App\Providers\PaymentsServiceProvider::getDepositMinimumAmount()}}"
                       step="1"
                       max="{{\App\Providers\PaymentsServiceProvider::getDepositMaximumAmount()}}">
            </div>
            <div class="invalid-feedback deposit-amount-feedback">{{__('Please enter a valid amount.')}}</div>
        </div>
    </div>

    @if(\App\Services\Payments\CheckoutBilling::collectsDetails())
        @php
            $billingFields = [
                'first_name' => ['label' => __('First name'), 'autocomplete' => 'given-name'],
                'last_name' => ['label' => __('Last name'), 'autocomplete' => 'family-name'],
                'billing_address' => ['label' => __('Address'), 'autocomplete' => 'street-address'],
                'city' => ['label' => __('City'), 'autocomplete' => 'address-level2'],
                'state' => ['label' => __('State'), 'autocomplete' => 'address-level1'],
                'postcode' => ['label' => __('Postal code'), 'autocomplete' => 'postal-code'],
            ];
            $billingValues = [];
            foreach ([...array_keys($billingFields), 'country'] as $billingField) {
                $value = old($billingField, Auth::user()->{$billingField});
                $billingValues[$billingField] = is_scalar($value) ? (string) $value : '';
            }
            $billingComplete = collect($billingValues)->every(fn ($value, $field) => mb_strlen(trim($value)) >= ($field === 'billing_address' ? 3 : 1) && mb_strlen($value) <= 255)
                && $countries->contains('name', $billingValues['country']);
            $billingExpanded = !$billingComplete || $errors->hasAny(array_keys($billingValues));
            $billingSummary = trim($billingValues['first_name'].' '.$billingValues['last_name']).' · '.$billingValues['city'].', '.$billingValues['country'];
        @endphp
        <div class="deposit-card">
            <div class="deposit-billing-summary {{ $billingExpanded ? 'd-none' : '' }}">
                <div class="d-flex align-items-center gap-3">
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="deposit-card-title mb-1">{{ __('Billing details') }}</div>
                        <div class="deposit-billing-summary-value small text-body-secondary text-truncate">{{ $billingSummary }}</div>
                    </div>
                    <a class="deposit-billing-edit small fw-semibold text-decoration-none p-2 m-n2" data-bs-toggle="collapse" href="#deposit-billing-information" role="button" aria-controls="deposit-billing-information" aria-expanded="{{ $billingExpanded ? 'true' : 'false' }}">{{ __('Edit') }}</a>
                </div>
            </div>
            <div id="deposit-billing-information" class="collapse {{ $billingExpanded ? 'show' : '' }}">
                <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                    <div>
                        <div class="deposit-card-title mb-1">{{ __('Billing details') }}</div>
                        <div class="small text-body-secondary">{{ __('Used for taxes and invoices.') }}</div>
                    </div>
                    <a class="deposit-billing-done small fw-semibold text-decoration-none p-2 m-n2" href="#deposit-billing-information" role="button" aria-controls="deposit-billing-information">{{ __('Done') }}</a>
                </div>
                <div class="row g-3">
                    @foreach($billingFields as $billingField => $billingDefinition)
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="deposit-billing-{{ $billingField }}">{{ $billingDefinition['label'] }}</label>
                            <input id="deposit-billing-{{ $billingField }}" form="wallet-deposit" name="{{ $billingField }}"
                                   class="form-control @error($billingField) is-invalid @enderror" type="text"
                                   value="{{ $billingValues[$billingField] }}"
                                   autocomplete="{{ $billingDefinition['autocomplete'] }}"
                                   minlength="{{ $billingField === 'billing_address' ? 3 : 1 }}" maxlength="255" required>
                            @error($billingField)<span class="invalid-feedback" role="alert">{{ $message }}</span>@enderror
                        </div>
                    @endforeach
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="deposit-billing-country">{{ __('Country') }}</label>
                        <select id="deposit-billing-country" form="wallet-deposit" name="country"
                                class="form-select @error('country') is-invalid @enderror" required>
                            <option value="">{{ __('Select country') }}</option>
                            @foreach($countries as $country)
                                <option value="{{ $country->name }}" @selected($billingValues['country'] === $country->name)>{{ $country->name }}</option>
                            @endforeach
                        </select>
                        @error('country')<span class="invalid-feedback" role="alert">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="deposit-billing-error text-danger small mt-2 d-none">{{ __('Please complete all billing details') }}</div>
            </div>
        </div>
    @endif

    <div class="deposit-card">
        <div class="deposit-card-title">{{__('Payment method')}}</div>
        <div class="deposit-card-copy">{{__('Select the provider you want to use for your wallet top-up.')}}</div>
        @if(!$enabledDepositPaymentMethodCount)
            <p class="text-muted" role="status">{{ __('No deposit methods are currently available.') }}</p>
        @endif
        <div class="payment-method row g-2">
            @foreach($enabledDepositPaymentMethods as $method)
                <div class="{{!empty($method['full_width_when_odd']) && $enabledDepositPaymentMethodCount % 2 === 1 ? 'col-12' : 'col-12 col-md-6'}}">
                    <div class="form-check deposit-provider-option h-100">
                        <input type="radio" id="{{$method['id']}}" name="payment-radio-option" class="form-check-input"
                               value="{{$method['value']}}"
                               @checked($method['id'] === $selectedDepositMethodId)
                               @if(array_key_exists('manual_method_id', $method)) data-manual-method-id="{{ $method['manual_method_id'] }}" @endif>
                        <label class="form-check-label stepTooltip d-flex align-items-center w-100 h-100 mb-0 py-2 pe-3 ps-5 border rounded" for="{{$method['id']}}" title="">
                            <span class="deposit-provider-logo d-inline-flex align-items-center justify-content-center flex-shrink-0 me-3" aria-hidden="true">
                                @if($method['logo'])
                                    <img src="{{$method['logo']}}" alt="">
                                @else
                                    @include('elements.icon',['icon'=>$method['icon'],'variant'=>'medium'])
                                @endif
                            </span>
                            <span class="deposit-provider-copy d-flex flex-column text-break">
                                <span class="fw-bold text-body">{{$method['name']}}</span>
                                <span class="small text-primary fw-bold">{{$method['type']}}</span>
                                <span class="small text-muted text-truncate mt-1">{{$method['note']}}</span>
                            </span>
                        </label>
                    </div>
                </div>
            @endforeach
        </div>

        @if(count($manualDepositMethods))
            <div class="manual-details d-none">
                <h5 class="mt-4 mb-3">{{ __('Payment instructions') }}</h5>
                @foreach($manualDepositMethods as $manualMethod)
                    @include('elements.settings.manual-deposit-method', [
                        'manualMethod' => $manualMethod, 'receiptHints' => GenericHelper::getManualDepositReceiptHints($manualMethod),
                    ])
                @endforeach
                <div class="text-danger invalid-files d-none" role="alert" tabindex="-1"></div>
            </div>
        @endif
        <div class="deposit-provider-summary" data-provider-message="{{ __('You will be redirected to the selected provider to complete the wallet top-up securely.') }}" data-manual-message="{{ __('Your deposit will be pending until an admin confirms the payment.') }}">{{__('You will be redirected to the selected provider to complete the wallet top-up securely.')}}</div>
    </div>

    <div>
        <div class="payment-error error text-danger d-none mt-1" role="alert" tabindex="-1">{{__('Please select your payment method')}}</div>
        <button class="btn btn-primary w-100 rounded me-0 mt-3 deposit-continue-btn" type="submit" @disabled(!$enabledDepositPaymentMethodCount)>{{__('Add funds')}}</button>
    </div>
</div>
