@include('elements.message-alert',['classes'=>'px-2 pt-4'])
@if(\App\Providers\ProfileMonetizationServiceProvider::canReceiveProfileSubscriptions($user))
    @if( (!Auth::check() || Auth::user()->id !== $user->id) && !$hasSub)
        <div class="p-4 subscription-holder">
            <h6 class="fw-bold text-uppercase mb-3">{{__('Subscription')}}</h6>
            @if(count($offer) && $offer['discountAmount']['30'] > 0)
                <h5 class="m-0 fw-bold">{{__('Limited offer main label',['discount'=> round($offer['discountAmount']['30']), 'days_remaining'=> $offer['daysRemaining'] ])}}</h5>
                <small class="">{{__('Offer ends label',['date'=>$offer['expiresAt']->format('d M')])}}</small>
            @endif
            @if($hasSub)
                <button class="btn btn-round btn-lg btn-primary w-100 mt-3 mb-2 text-center">
                    <span>{{__('Subscribed')}}</span>
                </button>
            @else

                @if(Auth::check())
                    @if(!GenericHelper::isEmailEnforcedAndValidated())
                        <i>{{__('Your email address is not verified.')}} <a href="{{route('verification.notice')}}">{{__("Click here")}}</a> {{__("to re-send the confirmation email.")}}</i>
                    @endif
                @endif

                @include('elements.checkout.subscribe-button-30')
                <div class="d-flex justify-content-between">
                    @if($user->profile_access_price_6_months || $user->profile_access_price_12_months)
                        <small>
                            <div class="pointer-cursor d-flex align-items-center" onclick="Profile.toggleBundles()">
                                <div class="label-more">{{__('Subscriptions bundles')}}</div>
                                <div class="label-less d-none">{{__('Hide bundles')}}</div>
                                <div class="ms-1 label-icon">
                                    @include('elements.icon',['icon'=>'chevron-down-outline','centered'=>false])
                                </div>
                            </div>
                        </small>
                    @endif
                    @if(count($offer) && $offer['discountAmount']['30'] > 0)
                        <small class="">{{__('Regular price label',['currency'=> getSetting('payments.currency_code') ?? 'USD','amount'=>$user->offer->old_profile_access_price])}}</small>
                    @endif
                </div>

                @if($user->profile_access_price_6_months || $user->profile_access_price_12_months || $user->profile_access_price_3_months)
                    <div class="subscription-bundles d-none mt-4">
                        @if($user->profile_access_price_3_months)
                            @include('elements.checkout.subscribe-button-90')
                        @endif

                        @if($user->profile_access_price_6_months)
                            @include('elements.checkout.subscribe-button-182')
                        @endif

                        @if($user->profile_access_price_12_months)
                            @include('elements.checkout.subscribe-button-365')
                        @endif

                    </div>
                @endif
            @endif
        </div>
        <div class="bg-separator border-top border-bottom"></div>
    @endif
@elseif(!Auth::check() || (Auth::check() && Auth::user()->id !== $user->id))
    <div class=" p-4 subscription-holder">
        <h6 class="fw-bold text-uppercase mb-3">{{__('Follow this creator')}}</h6>
        @if(Auth::check())
            <button class="btn btn-round btn-lg btn-primary w-100 mt-3 mb-0 manage-follow-button" onclick="Lists.manageFollowsAction('{{$user->id}}')">
                <span class="manage-follows-text">{{\App\Providers\ListsHelperServiceProvider::getUserFollowingType($user->id, true)}}</span>
            </button>
        @else
            <button class="btn btn-round btn-lg btn-primary w-100 mt-3 mb-0 text-center"
                    data-bs-toggle="modal"
                    data-bs-target="#login-dialog"
            >
                <span class="">{{__('Follow')}}</span>
            </button>
        @endif
    </div>
    <div class="bg-separator border-top border-bottom"></div>
@endif
