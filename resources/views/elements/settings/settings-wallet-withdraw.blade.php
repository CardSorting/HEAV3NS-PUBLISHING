@if (Auth::user()->stripe_account_id && !Auth::user()->stripe_onboarding_verified)
    @include('elements.stripe-connect-pending-onboarding')
@endif

@if (WithdrawalsHelper::isTaxInfoRequiredForWithdrawals())
    @include('elements.dac7-withdraw-enforced')
@endif

@include('elements/settings/settings-wallet-withdrawal/fees-and-pending-balance')
@include('elements/settings/settings-wallet-withdrawal/withdrawal-input-container')
@include('elements/settings/settings-wallet-withdrawal/saved-payout-accounts')
