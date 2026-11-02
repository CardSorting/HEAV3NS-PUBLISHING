<div class="d-flex justify-content-between align-items-center mt-3">
    @if(getSetting('payments.withdrawal_allow_fees') && floatval(getSetting('payments.withdrawal_default_fee_percentage')) > 0)
        <div class="d-flex align-items-center">
            @include('elements.icon',['icon'=>'information-circle-outline','variant'=>'small','centered'=>false,'classes'=>'me-2'])
            <span class="text-start" id="pending-balance" title="{{__("The payouts are manually and it usually take up to 24 hours for a withdrawal to be processed, we will notify you as soon as your request is processed.")}}">
            {{__("A :feeAmount% fee will be applied.",['feeAmount'=>floatval(getSetting('payments.withdrawal_default_fee_percentage'))])}}
        </span>
        </div>
    @else
        <h5></h5>
    @endif
    <div class="d-flex align-items-center">
        @include('elements.icon',['icon'=>'information-circle-outline','variant'=>'small','centered'=>false,'classes'=>'me-2'])
        <span class="text-end" id="pending-balance" title="{{__("The payouts are manually and it usually take up to 24 hours for a withdrawal to be processed, we will notify you as soon as your request is processed.")}}">
            {{__('Pending balance')}} (<b class="wallet-pending-amount">{{\App\Providers\SettingsServiceProvider::getWebsiteFormattedAmount(number_format(Auth::user()->wallet->pendingBalance, 2, '.', ''))}}</b>)
        </span>
    </div>
</div>

@if(getSetting('payments.withdrawal_custom_message_box'))
    <div class="custom-withdrawal-message-box d-none">
        @include('elements.settings.custom-info-box', [
            'message' => getSetting('payments.withdrawal_custom_message_box'),
            'classes' => 'mt-3',
        ])
    </div>
@endif
