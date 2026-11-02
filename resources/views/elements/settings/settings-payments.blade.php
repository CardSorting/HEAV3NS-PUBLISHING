<div class="settings-payments-page">
    <div class="settings-payments-overview mb-0">
        <div class="settings-payments-summary row g-3 mb-0">
            <div class="col-6 col-xl-3">
                <div class="settings-payments-stat-card d-flex flex-column">
                    <div class="d-flex align-items-center justify-content-between gap-1">
                        <div class="settings-payments-stat-label text-truncate flex-grow-1 mb-0">{{ __('Total') }}</div>
                        <span class="settings-data-stat-icon">@include('elements.icon',['icon'=>'list-outline','variant'=>'small','centered'=>true])</span>
                    </div>
                    <div class="settings-payments-stat-value mt-1">{{ number_format((int) $paymentSummary['total_entries']) }}</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="settings-payments-stat-card d-flex flex-column">
                    <div class="d-flex align-items-center justify-content-between gap-1">
                        <div class="settings-payments-stat-label text-truncate flex-grow-1 mb-0">{{ __('Received') }}</div>
                        <span class="settings-data-stat-icon">@include('elements.icon',['icon'=>'download-outline','variant'=>'small','centered'=>true])</span>
                    </div>
                    <div class="settings-payments-stat-value mt-1">{{ \App\Providers\SettingsServiceProvider::getWebsiteFormattedAmount(number_format((float) $paymentSummary['received_amount'], 2, '.', ',')) }}</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="settings-payments-stat-card d-flex flex-column">
                    <div class="d-flex align-items-center justify-content-between gap-1">
                        <div class="settings-payments-stat-label text-truncate flex-grow-1 mb-0">{{ __('Sent') }}</div>
                        <span class="settings-data-stat-icon">@include('elements.icon',['icon'=>'send-outline','variant'=>'small','centered'=>true])</span>
                    </div>
                    <div class="settings-payments-stat-value mt-1">{{ \App\Providers\SettingsServiceProvider::getWebsiteFormattedAmount(number_format((float) $paymentSummary['sent_amount'], 2, '.', ',')) }}</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="settings-payments-stat-card d-flex flex-column">
                    <div class="d-flex align-items-center justify-content-between gap-1">
                        <div class="settings-payments-stat-label text-truncate flex-grow-1 mb-0">{{ __('Withdrawals') }}</div>
                        <span class="settings-data-stat-icon">@include('elements.icon',['icon'=>'wallet-outline','variant'=>'small','centered'=>true])</span>
                    </div>
                    <div class="settings-payments-stat-value mt-1">{{ \App\Providers\SettingsServiceProvider::getWebsiteFormattedAmount(number_format((float) $paymentSummary['withdrawal_amount'], 2, '.', ',')) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="table-wrapper settings-payments-table table-hover-primary-soft">
        <div class="settings-payments-table-head">
            <div class="d-grid align-items-center border-bottom fw-semibold settings-payments-header-row">
                <div>{{ __('Type') }}</div>
                <div>{{ __('Status') }}</div>
                <div class="text-end">{{ __('Amount') }}</div>
                <div class="d-none d-lg-block">{{ __('From') }}</div>
                <div class="d-none d-lg-block">{{ __('To') }}</div>
                <div class="d-flex justify-content-center fw-normal settings-payments-action-cell">
                    @include('elements.settings.payments-filters')
                </div>
            </div>
        </div>

        <div class="settings-payments-table-body">
            @foreach($payments as $payment)
                @php($paymentPresentation = $paymentPresentations[$payment->id])
                @php($typePresentation = $paymentPresentation['typePresentation'])
                <div class="d-grid align-items-center border-bottom settings-payments-row table-hover-item">
                    <div class="text-break">
                        @if($typePresentation['url'])
                            <a href="{{ $typePresentation['url'] }}" class="text-dark-r settings-payments-cell-main">
                                {{ $typePresentation['label'] }}
                            </a>
                        @elseif($typePresentation['tooltip'])
                            <span class="settings-payments-cell-main" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $typePresentation['tooltip'] }}">
                                {{ $typePresentation['label'] }}
                            </span>
                        @else
                            <span class="settings-payments-cell-main">{{ $typePresentation['label'] }}</span>
                        @endif

                        @if($typePresentation['meta'])
                            <div class="settings-payments-meta text-muted small">{{ $typePresentation['meta'] }}</div>
                        @endif
                    </div>

                    <div>
                        <span class="badge bg-{{ $paymentPresentation['statusClass'] }} settings-data-status-badge">
                            {{ $paymentStatusOptions[$payment->status] ?? ucfirst(__($payment->status)) }}
                        </span>
                    </div>

                    <div class="text-end text-break">
                        <div class="settings-payments-amount fw-semibold">{{ $paymentPresentation['formattedAmount'] }}</div>
                        <div class="settings-payments-meta text-muted small">{{ $paymentPresentation['createdDateLabel'] }}</div>
                    </div>

                    <div class="text-truncate d-none d-lg-block">
                        @if($paymentPresentation['senderProfileUrl'])
                            <a href="{{ $paymentPresentation['senderProfileUrl'] }}" class="text-dark-r settings-payments-party-link" title="{{ $paymentPresentation['senderDisplayName'] }}">
                                {{ $paymentPresentation['senderDisplayName'] }}
                            </a>
                        @else
                            —
                        @endif
                    </div>

                    <div class="text-truncate d-none d-lg-block">
                        @if($paymentPresentation['receiverProfileUrl'])
                            <a href="{{ $paymentPresentation['receiverProfileUrl'] }}" class="text-dark-r settings-payments-party-link" title="{{ $paymentPresentation['receiverDisplayName'] }}">
                                {{ $paymentPresentation['receiverDisplayName'] }}
                            </a>
                        @else
                            —
                        @endif
                    </div>

                    <div class="d-flex justify-content-center settings-payments-action-cell">
                        @if($paymentPresentation['canViewInvoice'])
                            <div class="dropdown dropstart">
                                <a class="btn dropdown-toggle ellipsis-dropdown-toggle settings-payments-action-btn m-0" data-bs-toggle="dropdown" href="#" role="button" aria-label="{{__('Payment actions')}}" aria-haspopup="true" aria-expanded="false">
                                    @include('elements.icon',['icon'=>'ellipsis-horizontal-outline','centered'=>false])
                                </a>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item d-flex align-items-center" href="{{ $paymentPresentation['invoiceUrl'] }}">
                                        @include('elements.icon',['icon'=>'document-outline','centered'=>false,'classes'=>'me-2']) {{ __('View invoice') }}
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @if($payments->count())
        <div class="settings-payments-pagination mt-3 px-0 px-md-2 pb-2">
            {{ $payments->withQueryString()->links() }}
        </div>
    @else
        <div class="settings-payments-empty-state text-center">
            <div class="settings-payments-empty-title">
                {{ $hasActivePaymentFilters ? __('No payments match these filters.') : __('No payments yet') }}
            </div>
            <div class="settings-payments-empty-copy text-muted">
                {{ $hasActivePaymentFilters ? __('Try changing or resetting your filters.') : __('Your purchases, earnings, and withdrawals will show up here.') }}
            </div>
            @if($hasActivePaymentFilters)
                <a class="btn btn-outline-secondary mt-3" href="{{ route('my.settings', ['type' => 'payments']) }}">{{ __('Reset') }}</a>
            @endif
        </div>
    @endif
</div>
