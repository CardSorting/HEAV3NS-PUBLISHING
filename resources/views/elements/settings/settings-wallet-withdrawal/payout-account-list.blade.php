@forelse($payoutAccounts->groupBy('manual_method_id') as $methodAccounts)
    @php($method = $methodAccounts->first()->manualMethod)
    <section class="{{ $loop->first ? '' : 'mt-3' }}">
        <h6 class="small text-muted mb-1">{{ $method?->name ?: __('Unavailable method') }}</h6>
        <div class="list-group list-group-flush">
            @foreach($methodAccounts as $payoutAccount)
                <div class="list-group-item px-0 py-2">
                    <div class="d-flex align-items-center justify-content-between gap-3">
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-semibold text-truncate" title="{{ $payoutAccount->display_label }}">{{ $payoutAccount->display_label }}</span>
                                @if($payoutAccount->is_default)<span class="badge rounded-pill bg-body-tertiary text-body-secondary fw-normal flex-shrink-0 px-2 py-1">{{ __('Default') }}</span>@endif
                            </div>
                            <div class="small text-muted text-truncate" title="{{ $payoutAccount->masked_details }}">{{ $payoutAccount->masked_details }}</div>
                        </div>
                        <div class="d-flex align-items-center gap-1 flex-shrink-0">
                            @if($method?->enabled)
                                <a class="btn btn-link text-sm d-inline-flex align-items-center fw-normal text-body text-decoration-none text-capitalize shadow-none mb-0 px-2 py-1 payout-account-edit" href="{{ route('my.settings', ['type' => 'wallet', 'active' => 'withdraw', 'editPayoutAccount' => $payoutAccount->id]) }}" data-url="{{ route('my.settings.payout-accounts.show', ['payoutAccount' => $payoutAccount->id]) }}">{{ __('Edit') }}</a>
                            @endif
                            <div class="dropdown">
                                <button class="btn dropdown-toggle ellipsis-dropdown-toggle m-0" type="button" id="payout-account-actions-{{ $payoutAccount->id }}" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Actions for :account', ['account' => $payoutAccount->display_label]) }}">
                                    @include('elements.icon', ['icon' => 'ellipsis-horizontal-outline', 'centered' => false])
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="payout-account-actions-{{ $payoutAccount->id }}">
                                    <li>
                                        <button class="dropdown-item d-flex align-items-center gap-2 text-danger payout-account-delete" type="button" data-url="{{ route('my.settings.payout-accounts.delete', ['payoutAccount' => $payoutAccount->id]) }}" data-label="{{ $payoutAccount->display_label }}">
                                            @include('elements.icon', ['icon' => 'trash-outline', 'variant' => 'small', 'centered' => false]){{ __('Delete') }}
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    @if(!$method?->enabled)
                        <div class="small text-muted mt-2">{{ __('This method is currently unavailable for withdrawals.') }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@empty
    @include('elements.empty-state', ['icon' => 'wallet-outline', 'title' => __('No payout accounts yet'), 'copy' => __('Add an account to receive withdrawals using your preferred payment method.')])
@endforelse
