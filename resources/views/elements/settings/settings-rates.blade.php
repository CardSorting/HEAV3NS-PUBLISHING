@php
    $profileMode = \App\Providers\ProfileMonetizationServiceProvider::mode();
    $isPaidOnlyProfileMode = \App\Providers\ProfileMonetizationServiceProvider::isPaidOnly();
    $isFreeOnlyProfileMode = \App\Providers\ProfileMonetizationServiceProvider::isFreeOnly();
    $userHasPaidProfile = \App\Providers\ProfileMonetizationServiceProvider::userHasPaidProfile(Auth::user());
    $canManageRates = \App\Providers\ProfileMonetizationServiceProvider::shouldShowRatesForUser(Auth::user());
    $lockedProfileModeLabel = $isFreeOnlyProfileMode ? __('free profiles only') : __('paid profiles only');
@endphp

@if(session('success'))
    <div class="alert alert-dismissible alert-success text-white fw-bold mt-2" role="alert">
        {{session('success')}}
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="{{__('Close')}}"></button>
    </div>
@endif

@if($profileMode !== \App\Providers\ProfileMonetizationServiceProvider::MODE_MIXED)
    <div class="alert alert-info text-white fw-bold mt-2" role="alert">
        {{__("Profile type changes are disabled because this site is set to :mode.", ['mode' => $lockedProfileModeLabel])}}
    </div>
@elseif(\App\Providers\ProfileMonetizationServiceProvider::userHasOpenProfile(Auth::user()))
    <div class="alert alert-dismissible alert-warning text-white fw-bold mt-2" role="alert">
        {{__("Your profile is set to 'Open profile', meaning your profile will be treated as free one")}}.
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="{{__('Close')}}"></button>
    </div>
@endif

@if($canManageRates)
<form method="POST" action="{{route('my.settings.rates.save')}}">
    @csrf
    <div class="mb-3">
        <div class="form-check form-switch">
            <input type="checkbox" class="form-check-input" id="paid-profile" name="paid-profile"
                {{$userHasPaidProfile ? 'checked' : ''}}
                {{$profileMode !== \App\Providers\ProfileMonetizationServiceProvider::MODE_MIXED ? 'disabled' : ''}}>
            <label class="form-check-label" for="paid-profile">{{__('Paid profile')}}</label>
        </div>
    </div>
    <div data-free-profile-info class="border rounded-3 bg-body-tertiary p-3 mt-2 {{ $userHasPaidProfile ? 'd-none' : '' }}" role="status" aria-live="polite">
        <div class="d-flex align-items-start gap-3">
            <span class="text-primary flex-shrink-0 pt-1">
                @include('elements.icon', ['icon' => 'information-circle-outline', 'variant' => 'medium', 'centered' => true])
            </span>
            <div>
                <div class="fw-semibold text-dark-r mb-1">{{ __('Your profile is free') }}</div>
                <p class="small text-muted mb-0">
                    {{ __('People can access your profile without a subscription. Enable Paid profile to configure monthly and bundle prices.') }}
                </p>
            </div>
        </div>
    </div>
    <div class="paid-profile-rates {{ $userHasPaidProfile ? '' : 'd-none' }}">
        <div class="mb-3">
            <label for="profile_access_price">{{ __('Your profile subscription price') }}</label>
            <div class="input-group mb-3">
                <input class="form-control {{ $errors->has('profile_access_price') ? 'is-invalid' : '' }}"
                       id="profile_access_price"
                       name="profile_access_price"
                       aria-describedby="emailHelp"
                       value="{{ Auth::user()->profile_access_price }}">
                @if($offer)
                    <span class="input-group-text">{{ __('Old') }}: {{ $offer->old_profile_access_price }}</span>
                @endif
                @if($errors->has('profile_access_price'))
                    <span class="invalid-feedback" role="alert">
                    <strong>{{ __($errors->first('profile_access_price')) }}</strong>
                </span>
                @endif
            </div>
        </div>

        <div class="mb-3">
            <label for="profile_access_price_3_months">{{ __('3 months subscription price') }}</label>
            <div class="input-group mb-3">
                <input
                    type="text"
                    class="form-control {{ $errors->has('profile_access_price_3_months') ? 'is-invalid' : '' }}"
                    id="profile_access_price_3_months"
                    name="profile_access_price_3_months"
                    aria-describedby="profileAccessPrice3MonthsHelp"
                    value="{{ old('profile_access_price_3_months', Auth::user()->profile_access_price_3_months) }}"
                >
                @if($offer && $offer->old_profile_access_price_3_months)
                    <span class="input-group-text">{{ __('Old') }}: {{ $offer->old_profile_access_price_3_months }}</span>
                @endif
                @if($errors->has('profile_access_price_3_months'))
                    <span class="invalid-feedback" role="alert">
                <strong>{{ __($errors->first('profile_access_price_3_months')) }}</strong>
            </span>
                @endif
            </div>
        </div>

        <div class="mb-3">
            <label for="profile_access_price_6_months">{{ __('6 months subscription price') }}</label>
            <div class="input-group mb-3">
                <input
                    type="text"
                    class="form-control {{ $errors->has('profile_access_price_6_months') ? 'is-invalid' : '' }}"
                    id="profile_access_price_6_months"
                    name="profile_access_price_6_months"
                    aria-describedby="profileAccessPrice6MonthsHelp"
                    value="{{ old('profile_access_price_6_months', Auth::user()->profile_access_price_6_months) }}"
                >
                @if($offer && $offer->old_profile_access_price_6_months)
                    <span class="input-group-text">{{ __('Old') }}: {{ $offer->old_profile_access_price_6_months }}</span>
                @endif
                @if($errors->has('profile_access_price_6_months'))
                    <span class="invalid-feedback" role="alert">
                <strong>{{ __($errors->first('profile_access_price_6_months')) }}</strong>
            </span>
                @endif
            </div>
        </div>

        <div class="mb-3">
            <label for="profile_access_price_12_months">{{ __('12 months subscription price') }}</label>
            <div class="input-group mb-3">
                <input
                    type="text"
                    class="form-control {{ $errors->has('profile_access_price_12_months') ? 'is-invalid' : '' }}"
                    id="profile_access_price_12_months"
                    name="profile_access_price_12_months"
                    aria-describedby="profileAccessPrice12MonthsHelp"
                    value="{{ old('profile_access_price_12_months', Auth::user()->profile_access_price_12_months) }}"
                >
                @if($offer && $offer->old_profile_access_price_12_months)
                    <span class="input-group-text">{{ __('Old') }}: {{ $offer->old_profile_access_price_12_months }}</span>
                @endif
                @if($errors->has('profile_access_price_12_months'))
                    <span class="invalid-feedback" role="alert">
                <strong>{{ __($errors->first('profile_access_price_12_months')) }}</strong>
            </span>
                @endif
            </div>
        </div>

        @if(!getSetting('profiles.disable_profile_offers'))
            <div class="mb-3">
                <label for="name">{{__('Is offer until')}}</label>
                <div class="input-group">
                    <div class="input-group-text">
                        <input class="form-check-input mt-0" type="checkbox" aria-label="{{__('Enable offer end date')}}" name="is_offer" id="is_offer" {{Auth::user()->offer && Auth::user()->offer->expires_at ? 'checked' : ''}}>
                    </div>
                    <input type="date" class="form-control {{ $errors->has('profile_access_offer_date') ? 'is-invalid' : '' }}" id="profile_access_offer_date" name="profile_access_offer_date" aria-describedby="emailHelp" value="{{Auth::user()->offer && Auth::user()->offer->expires_at ? Auth::user()->offer->expires_at->format('Y-m-d') : ''}}">

                </div>
                <small class="form-text text-muted">
                    {{__("In order to start a promotion, reduce your monthly prices and select a future promotion end date.")}}
                </small>
                @if($errors->has('profile_access_offer_date'))
                    <span class="invalid-feedback" role="alert">
                    <strong>{{__($errors->first('profile_access_offer_date'))}}</strong>
                </span>
                @endif
            </div>
        @endif

        <button class="btn btn-primary w-100 rounded me-0" type="submit">{{__('Save')}}</button>
    </div>
</form>
@endif
