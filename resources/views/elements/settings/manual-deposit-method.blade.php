<div class="manual-method-panel d-none"
     data-method-id="{{ $manualMethod['id'] }}"
     data-minimum-attachments="{{ (int) ($manualMethod['submission_settings']['minimum_attachments'] ?? 0) }}">
    @if(trim($manualMethod['instructions'] ?? '') !== '')
        <div class="manual-method-instructions mb-3">{{ $manualMethod['instructions'] }}</div>
    @else
        <p>{{ __('Send the payment using the details below, then submit your request for review.') }}</p>
    @endif
    @include('elements.settings.manual-payment-receiving-details', [
        'details' => GenericHelper::getManualPaymentReceivingDetails($manualMethod['receiving_details'], $manualMethod['id']),
    ])
    @foreach($manualMethod['request_fields'] as $field)
        @include('elements.settings.manual-deposit-field', [
            'field' => $field, 'methodId' => $manualMethod['id'], 'minimumReceiptHint' => $receiptHints['upload'],
        ])
    @endforeach
    @if($receiptHints['request'] !== '')
        <p class="small text-body-secondary mb-2">{{ $receiptHints['request'] }}</p>
    @endif
</div>
