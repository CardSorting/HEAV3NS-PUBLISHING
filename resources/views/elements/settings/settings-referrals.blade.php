<div class="settings-data-page settings-referrals-page">
    <div class="py-3 px-3 px-md-5">
        <p class="text-muted text-center mb-3">
            {{ __('Copy your referral link and invite other people to get a fee from their earnings.') }}
        </p>
        <div class="input-group">
            <input type="text" class="form-control text-center"
                   @switch(getSetting('payments.referrals_default_link_page'))
                       @case('profile')
                           value="{{ route('profile', ['ref' => Auth::user()->referral_code, 'username' => Auth::user()->username]) }}"
                       @break
                       @case('home')
                           value="{{ route('home', ['ref' => Auth::user()->referral_code]) }}"
                       @break
                       @case('register')
                           value="{{ route('register', ['ref' => Auth::user()->referral_code]) }}"
                       @break
                   @endswitch
                   placeholder="{{ route('profile', ['ref' => Auth::user()->referral_code, 'username' => Auth::user()->username]) }}"
                   id="copy-input"
                   aria-label="{{ __('Referral link') }}"
                   readonly
            >
            <button class="btn btn-primary mb-0 text-truncate" type="button" id="copy-button"
                    data-bs-toggle="tooltip" data-bs-placement="bottom"
                    title="{{ __('Copy to Clipboard') }}">
                {{ __('Copy') }}
            </button>
        </div>
    </div>

    @if(count($referrals))
        <div class="table-wrapper settings-data-table table-hover-primary-soft">
            <div class="settings-data-table-head">
                <div class="col d-flex align-items-center border-bottom fw-semibold settings-data-header-row">
                    <div class="col-7 col-md-6 text-truncate">{{ __('Referral') }}</div>
                    <div class="col-md-3 text-truncate d-none d-md-block">{{ __('Joined') }}</div>
                    <div class="col-5 col-md-3 text-truncate text-end">{{ __('Earned') }}</div>
                </div>
            </div>

            <div class="settings-data-table-body">
                @foreach($referrals as $referral)
                    @php($referredUser = $referral->usedBy)
                    @php($earnedAmount = \App\Providers\UsersServiceProvider::getTotalAmountEarnedFromRewardsByUsers(Auth::user()->id, $referral->used_by))
                    <div class="col d-flex align-items-center border-bottom settings-data-row table-hover-item">
                        <div class="col-7 col-md-6 d-flex align-items-center text-truncate">
                            @if($referredUser)
                                <a href="{{ route('profile', ['username' => $referredUser->username]) }}" class="settings-subscriptions-avatar-link me-2 flex-shrink-0">
                                    <img class="rounded-circle user-avatar object-fit-cover" width="36" height="36" src="{{ $referredUser->avatar ?: asset('img/default-avatar.jpg') }}" data-fallback-src="{{ asset('img/default-avatar.jpg') }}" alt="">
                                </a>
                                <a href="{{ route('profile', ['username' => $referredUser->username]) }}" class="text-dark-r settings-data-party-link text-truncate">
                                    {{ $referredUser->name }}
                                </a>
                            @else
                                <img class="rounded-circle user-avatar me-2 flex-shrink-0" width="35" src="{{ \App\Providers\GenericHelperServiceProvider::getStorageAvatarPath(null) }}" alt="">
                                <span class="settings-data-cell-main text-truncate">{{ __('Unavailable user') }}</span>
                            @endif
                        </div>
                        <div class="col-md-3 text-muted d-none d-md-block">
                            {{ \Carbon\Carbon::parse($referral->created_at)->format('M d Y') }}
                        </div>
                        <div class="col-5 col-md-3 text-truncate text-end settings-data-amount">
                            {{ \App\Providers\SettingsServiceProvider::getWebsiteFormattedAmount(number_format((float) $earnedAmount, 2, '.', ',')) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="settings-data-pagination mt-3 px-0 px-md-2 pb-2">
            {{ $referrals->onEachSide(1)->links() }}
        </div>
    @else
        <div class="settings-data-empty-state text-center">
            <div class="settings-data-empty-title">{{ __('No referrals yet') }}</div>
            <div class="settings-data-empty-copy text-muted">
                {{ __('People who join using your referral link will appear here.') }}
            </div>
        </div>
    @endif
</div>
