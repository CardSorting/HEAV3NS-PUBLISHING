@php
    $withdrawalMethodOptions = \App\Providers\PaymentsServiceProvider::getWithdrawalMethodOptions();
    $activePayoutAccounts = $payoutAccounts->where('is_active', true);
    $selectedWithdrawalMethod = isset($lastWithdrawalMethod) && array_key_exists($lastWithdrawalMethod, $withdrawalMethodOptions)
        ? (string) $lastWithdrawalMethod
        : (string) (collect($withdrawalMethodOptions)->keys()
            ->first(fn ($methodId) => $activePayoutAccounts->contains('manual_method_id', $methodId))
            ?? array_key_first($withdrawalMethodOptions));
    $methodPayoutAccounts = $activePayoutAccounts->where('manual_method_id', $selectedWithdrawalMethod);
    $selectedWithdrawalPayoutAccountId = ($methodPayoutAccounts->firstWhere('is_default', true) ?: $methodPayoutAccounts->first())?->id;
@endphp
<div class="input-group mb-3 mt-3 withdrawals-form-wrapper">
    <span class="input-group-text" id="amount-label">@include('elements.icon',['icon'=>'cash-outline','variant'=>'medium'])</span>
    <input class="form-control"
           placeholder="{{ \App\Providers\PaymentsServiceProvider::getWithdrawalAmountLimitations() }}"
           aria-label="Username"
           aria-describedby="amount-label"
           id="withdrawal-amount"
           type="number"
           min="{{\App\Providers\PaymentsServiceProvider::getWithdrawalMinimumAmount()}}"
           step="1"
           max="{{\App\Providers\PaymentsServiceProvider::getWithdrawalMaximumAmount()}}">
    <div class="invalid-feedback">{{__('Please enter a valid amount')}}</div>
    <div class="input-group mb-3 mt-3">
        <div class="row gx-0 w-100">
            <div class="mb-3 col-12 col-xl-6 pe-xl-2">
                <label for="payment-methods">{{__('Payment method')}}</label>
                <select class="form-select" id="payment-methods" name="payment-methods">
                    @foreach($withdrawalMethodOptions as $paymentMethodKey => $paymentMethodLabel)
                        <option value="{{ $paymentMethodKey }}" @selected((string) $paymentMethodKey === $selectedWithdrawalMethod)>{{ $paymentMethodLabel }}</option>
                    @endforeach
                </select>
                <small class="form-text text-muted">
                    {{__('Choose how you want to receive this withdrawal.')}}
                </small>
            </div>
            <div class="mb-3 col-12 col-xl-6 ps-xl-2 update-stripe-connect-box d-none">
                <label id="update-stripe-connect-label" for="update-stripe-connect">{{__('Update details')}}</label>
                <a href="{{route('withdrawals.onboarding')}}">
                    <button id="update-stripe-connect" class="btn btn-primary w-100 rounded me-0">{{__('Update')}}</button>
                </a>
            </div>
            <div class="mb-3 col-12 col-xl-6 ps-xl-2 saved-payout-account-box d-none">
                <label for="withdrawal-payout-account-id">{{__('Payout account')}}</label>
                <select class="form-select" id="withdrawal-payout-account-id" name="payout-account-id"
                        data-placeholder-label="{{ __('Select a saved payout account') }}"
                        data-empty-label="{{ __('No saved accounts for this payment method') }}" @disabled($methodPayoutAccounts->isEmpty())>
                    <option value="">{{ $methodPayoutAccounts->isEmpty() ? __('No saved accounts for this payment method') : __('Select a saved payout account') }}</option>
                    @foreach($activePayoutAccounts as $payoutAccount)
                        <option
                            value="{{ $payoutAccount->id }}"
                            data-method-id="{{ $payoutAccount->manual_method_id }}"
                            data-default="{{ $payoutAccount->is_default ? '1' : '0' }}"
                            @selected($payoutAccount->id === $selectedWithdrawalPayoutAccountId)
                        >
                            {{ $payoutAccount->display_label }} - {{ $payoutAccount->masked_details }}
                        </option>
                    @endforeach
                </select>
                <span class="invalid-feedback" role="alert">
                    {{__('Please select a saved payout account.')}}
                </span>
                <small class="form-text d-flex flex-nowrap align-items-center gap-3 saved-payout-account-helper d-none">
                    <a class="open-payout-account-modal text-nowrap" href="#" data-bs-toggle="modal" data-bs-target="#payout-account-dialog" data-mode="create">{{ __('Add account') }}</a>
                    <a class="open-payout-account-modal text-nowrap" href="#" data-bs-toggle="modal" data-bs-target="#payout-account-dialog" data-mode="manage">{{ __('Manage accounts') }}</a>
                </small>
            </div>
        </div>
        @foreach($manualWithdrawalMethods as $method)
            <div class="manual-withdrawal-request-fields d-none w-100" data-method-id="{{ $method->id }}">
                @if($method->instructions)
                    <div class="alert alert-info">{!! nl2br(e($method->instructions)) !!}</div>
                @endif
                @include('elements.settings.manual-payout-fields', ['fields' => $method->request_fields ?? [], 'values' => [], 'prefix' => 'submitted_fields', 'idPrefix' => 'withdrawal-request-'.$method->id])
            </div>
        @endforeach
    </div>
    <div class="stripe-connect-label d-none">
        @if(!Auth::user()->country_id)
            <span>{{__("You must set the country on your profile before you can start onboarding and withdraw money")}}</span>
        @elseif(!Auth::user()->stripe_onboarding_verified)
            <span>{{__("We're using Stripe to get you paid quickly and keep your personal and payment information secure. Thousands of companies around the world trust Stripe to process payments for their users. Set up a Stripe account to get paid with us")}}</span>
        @endif
    </div>
    <div class="payment-error error text-danger d-none mt-3">{{__('Add all required info')}}</div>
    <div class="stripe-connect-buttons d-none w-100">
        @if(!Auth::user()->country_id)
            <div class="mt-3">
                <div>
                    <a href="{{route('my.settings',['type'=>'profile'])}}">
                        <button class="btn btn-primary w-100 rounded me-0">{{__('Set your country')}}</button>
                    </a>
                </div>
            </div>
        @elseif(!Auth::user()->stripe_onboarding_verified)
            <div class="mt-3">
                <div>
                    <a href="{{route('withdrawals.onboarding')}}">
                        <button class="btn btn-primary w-100 rounded me-0">{{!Auth::user()->stripe_account_id ? __('Start onboarding') : __('Update details')}}</button>
                    </a>
                </div>
            </div>
        @endif
    </div>
    @if(!count($withdrawalMethodOptions))
        <p class="text-muted">{{ __('No withdrawal methods are currently available.') }}</p>
    @endif
    <button class="btn btn-primary w-100 rounded me-0 withdrawal-continue-btn" type="submit">{{__('Request withdrawal')}}</button>
</div>
