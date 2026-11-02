<div class="">
    <div class="profile-cover-bg">
        <img class="card-img-top centered-and-cropped" src="{{$user->cover}}">
    </div>
</div>

<div class="container d-flex justify-content-between align-items-center">
    <div class="z-index-3 avatar-holder position-relative d-inline-block d-flex">
        @php
            $isOwnProfile = Auth::check() && Auth::id() === $user->id;
        @endphp

        <div class="profile-avatar-wrap
                        {{ \App\Providers\StoriesServiceProvider::hasViewableStoriesForViewer($user) ? 'profile-has-stories cursor-pointer' : '' }}
                        {{ !$isOwnProfile && \App\Providers\StoriesServiceProvider::storiesSeenForViewer($user) === true ? 'profile-stories-seen' : '' }}
                        {{ !$isOwnProfile && \App\Providers\StoriesServiceProvider::storiesSeenForViewer($user) === false ? 'profile-stories-unseen' : '' }}
                    ">
            <img src="{{ $user->avatar }}" class="profile-avatar-img">

            @if(getSetting('profiles.show_online_users_indicator') && GenericHelper::isUserOnline($user->id))
                <span class="online-indicator"></span>
            @endif
        </div>

        @if(!getSetting('profiles.hide_profile_followers_count'))
            <div class="d-flex flex-column h-100">
                <div class="h-50"></div>
                <div class="h-50 align-items-center d-none d-md-flex">
                    <div class="d-flex ms-2">
                        <div class="d-flex me-2 align-items-center">
                            <div class="me-1 fw-bolder">
                                {{ short_number(GenericHelper::getTotalLikesForUser($user->id)) }}
                            </div>
                            <div class="text-muted">{{ucfirst(trans_choice('likes', GenericHelper::getTotalLikesForUser($user->id)))}}</div>
                        </div>

                        <div class="d-flex me-1 align-items-center">
                            <div class="me-1 fw-bolder">
                                {{ short_number(count(ListsHelper::getUserFollowers($user->id))) }}
                            </div>
                            <div class="text-muted">{{ucfirst(trans_choice('followers', count(ListsHelper::getUserFollowers($user->id))))}}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div>
        @if(!Auth::check() || Auth::user()->id !== $user->id)
            <div class="d-flex flex-row">
                @if(Auth::check())
                    <div class="">
                        <span class="p-pill ms-2 pointer-cursor to-tooltip"
                              @if(!Auth::user()->email_verified_at && getSetting('site.enforce_email_validation'))
                                  data-bs-placement="top"
                              title="{{__('Please verify your account')}}"
                              @elseif(!\App\Providers\GenericHelperServiceProvider::creatorCanEarnMoney($user))
                                  data-bs-placement="top"
                              title="{{__('This creator cannot earn money yet')}}"
                              @else
                                  data-bs-placement="top"
                              title="{{__('Send a tip')}}"
                              data-bs-toggle="modal"
                              data-bs-target="#checkout-center"
                              data-type="tip"
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
                              data-recipient-id="{{$user->id}}"
                              @endif
                        >
                         @include('elements.icon',['icon'=>'cash-outline'])
                        </span>
                    </div>
                    <div class="">
                        @if($hasSub || $viewerHasChatAccess)
                            <span class="p-pill ms-2 pointer-cursor" data-bs-toggle="tooltip" data-bs-placement="top" title="{{__('Send a message')}}" onclick="StoryDM.showNewMessageDialog()">
                                @include('elements.icon',['icon'=>'chatbubbles-outline'])
                            </span>
                        @else
                            <span class="p-pill ms-2 pointer-cursor" data-bs-toggle="tooltip" data-bs-placement="top" title="{{__('DMs unavailable without subscription')}}">
                                @include('elements.icon',['icon'=>'chatbubbles-outline'])
                            </span>
                        @endif
                    </div>
                    <span class="p-pill ms-2 pointer-cursor" data-bs-toggle="tooltip" data-bs-placement="top" title="{{__('Add to your lists')}}" onclick="Lists.showListAddModal();">
                        @include('elements.icon',['icon'=>'list-outline'])
                    </span>
                @endif
                @if(getSetting('profiles.allow_profile_qr_code'))
                    <div>
                        <span class="p-pill ms-2 pointer-cursor" data-bs-toggle="tooltip" data-bs-placement="top" title="{{__('Get profile QR code')}}" onclick="Profile.getProfileQRCode()">
                            @include('elements.icon',['icon'=>'qr-code-outline'])
                        </span>
                    </div>
                @endif
                <span class="p-pill ms-2 pointer-cursor" data-bs-toggle="tooltip" data-bs-placement="top" title="{{__('Copy profile link')}}" onclick="shareOrCopyLink()">
                    @include('elements.icon',['icon'=>'share-social-outline'])
                </span>
            </div>
        @else
            <div class="d-flex flex-row">
                <div class="me-2">
                    <a href="{{route('my.settings')}}" class="p-pill p-pill-text ms-2 pointer-cursor">
                        @include('elements.icon',['icon'=>'settings-outline','classes'=>'me-1'])
                        <span class="d-none d-md-block">{{__('Edit profile')}}</span>
                        <span class="d-block d-md-none">{{__('Edit')}}</span>
                    </a>
                </div>
                @if(getSetting('profiles.allow_profile_qr_code'))
                    <div>
                        <span class="p-pill ms-2 pointer-cursor" data-bs-toggle="tooltip" data-bs-placement="top" title="{{__('Get profile QR code')}}" onclick="Profile.getProfileQRCode()">
                            @include('elements.icon',['icon'=>'qr-code-outline'])
                        </span>
                    </div>
                @endif
                <div>
                    <span class="p-pill ms-2 pointer-cursor" data-bs-toggle="tooltip" data-bs-placement="top" title="{{__('Copy profile link')}}" onclick="shareOrCopyLink()">
                        @include('elements.icon',['icon'=>'share-social-outline'])
                    </span>
                </div>
            </div>
        @endif
    </div>
</div>
