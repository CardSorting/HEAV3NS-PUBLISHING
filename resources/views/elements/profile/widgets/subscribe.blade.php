@if(\App\Providers\ProfileMonetizationServiceProvider::canReceiveProfileSubscriptions($user))
    @if(Auth::check())
        @if( !(isset($hasSub) && $hasSub) && !(isset($post) && PostsHelper::hasActiveSub(Auth::user()->id, $post->user->id)) && Auth::user()->id !== $user->id)
            <div class="card mt-3 rounded-2">
                <div class="card-body">
                    <h5 class="card-title text-uppercase fs-point-85 fw-bold">{{__('Subscription')}}</h5>
                    <button class="btn btn-round btn-outline-primary w-100 d-flex align-items-center justify-content-center justify-content-lg-between mt-3 mb-0 to-tooltip {{(Auth::check() && !GenericHelper::isEmailEnforcedAndValidated() || !GenericHelper::creatorCanEarnMoney($user)) ? 'disabled' : ''}}"
                            @if(!Auth::user()->email_verified_at && getSetting('site.enforce_email_validation'))
                                data-bs-placement="top"
                            title="{{__('Please verify your account')}}"
                            @elseif(!GenericHelper::creatorCanEarnMoney($user))
                                data-bs-placement="top"
                            title="{{__('This creator cannot earn money yet')}}"
                            @else
                                data-bs-toggle="modal"
                            data-bs-target="#checkout-center"
                            data-type="one-month-subscription"
                            data-recipient-id="{{$user->id}}"
                            data-amount="{{$user->profile_access_price}}"
                            data-first-name="{{Auth::user()->first_name}}"
                            data-last-name="{{Auth::user()->last_name}}"
                            data-billing-address="{{Auth::user()->billing_address}}"
                            data-country="{{Auth::user()->country}}"
                            data-city="{{Auth::user()->city}}"
                            data-state="{{Auth::user()->state}}"
                            data-postcode="{{Auth::user()->postcode}}"
                            data-available-credit="{{Auth::user()->wallet->total}}"
                            data-username="{{$user->username}}"
                            data-name="{{$user->name}}"
                            data-avatar="{{$user->avatar}}"
                        @endif
                    >
                        <span class="d-none d-md-block d-xl-block d-lg-block">{{__('Subscribe')}}</span>
                        <span class="d-none d-lg-block">{{\App\Providers\SettingsServiceProvider::getWebsiteFormattedAmount($user->profile_access_price)}} {{__('for')}} {{trans_choice('days',30,['number'=>30])}}</span>
                    </button>
                </div>
            </div>
        @endif
    @else
        <div class="card mt-3">
            <div class="card-body">
                <h5 class="card-title text-uppercase fs-point-85 fw-bold">{{__('Subscription')}}</h5>
                <button class="btn btn-round btn-outline-primary w-100 d-flex align-items-center justify-content-center justify-content-lg-between mt-3 mb-0"
                        data-bs-toggle="modal"
                        data-bs-target="#login-dialog"
                >
                    <span class="d-none d-md-block d-xl-block d-lg-block">{{__('Subscribe')}}</span>
                    <span class="d-none d-lg-block">{{\App\Providers\SettingsServiceProvider::getWebsiteFormattedAmount($user->profile_access_price)}} {{__('for')}} {{trans_choice('days',30,['number'=>30])}}</span>
                </button>
            </div>
        </div>
    @endif
@elseif(!Auth::check() || (Auth::check() && Auth::user()->id !== $user->id))
    @if(Auth::check())
        <div class="card mt-3">
            <div class="card-body">
                <h5 class="card-title text-uppercase fs-point-85 fw-bold">{{__('Follow this creator')}}</h5>
                <button class="btn btn-round btn-outline-primary w-100 mt-3 mb-0 manage-follow-button" onclick="Lists.manageFollowsAction('{{$user->id}}')">
                    <span class="manage-follows-text">{{\App\Providers\ListsHelperServiceProvider::getUserFollowingType($user->id, true)}}</span>
                </button>
            </div>
        </div>
    @else
        <div class="card mt-3">
            <div class="card-body">
                <h5 class="card-title text-uppercase fs-point-85 fw-bold">{{__('Follow this creator')}}</h5>
                <button class="btn btn-round btn-outline-primary w-100 mt-3 mb-0 text-center"
                        data-bs-toggle="modal"
                        data-bs-target="#login-dialog"
                >
                    <span class="d-none d-md-block d-xl-block d-lg-block">{{__('Follow')}}</span>
                </button>
            </div>
        </div>
    @endif
@endif
