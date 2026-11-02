<div class="pt-2 ps-4 pe-4">
    <h5 class="fw-bold d-flex align-items-center">
        <span class="text-truncate">{{$user->name}}</span>
        @if(GenericHelper::isUserVerified($user))
            <span class="d-inline-flex align-items-center flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="{{__('Verified user')}}">
                @include('elements.icon',['icon'=>'verified','centered'=>true,'classes'=>'ms-1 text-primary'])
            </span>
        @endif
        @include('elements.user-staff-badge', ['user' => $user, 'badgeClasses' => 'text-primary'])
        @if($hasActiveStream)
            <span data-bs-toggle="tooltip" data-bs-placement="right" title="{{__('Live streaming')}}">
                            <div class="blob red ms-3"></div>
                            </span>
        @endif
    </h5>
    <h6 class="text-muted"><span class="fw-bold"><span>@</span>{{$user->username}}</span>
        @if(getSetting('profiles.show_online_users_indicator'))
            <span class="fw-bold">•</span>
            @if(GenericHelper::isUserOnline($user->id))
                <span>{{__("Available now")}}</span>
            @else
                @if(getSetting('profiles.record_users_last_activity_time') && $user->last_active_at)
                    <span>{{__("Last seen")}} {{$user->last_active_for_humans}}</span>
                @else
                    <span>{{__("Online recently")}}</span>
                @endif
            @endif
        @endif
    </h6>
    <div class="h-50 align-items-center d-flex d-md-none">
        <div class="d-flex">
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

<div class="pt-2 pb-2 ps-4 pe-4 profile-description-holder">
    <div class="description-content {{$user->bio && !getSetting('profiles.disable_profile_bio_excerpt') ? 'line-clamp-3' : ''}}">
        @if($user->bio)
            @if(getSetting('profiles.allow_profile_bio_markdown'))
                {!!  GenericHelper::parseProfileMarkdownBio($user->bio) !!}
            @else
                {!!GenericHelper::parseSafeHTML($user->bio)!!}
            @endif
        @else
            {{__('No description available.')}}
        @endif
    </div>
    @if($user->bio && !getSetting('profiles.disable_profile_bio_excerpt'))
        <span class="text-primary pointer-cursor show-more-actions d-none" onclick="Profile.toggleFullDescription()">
                            <span class="label-more">{{__('More info')}}</span>
                            <span class="label-less d-none">{{__('Show less')}}</span>
                        </span>
    @endif
</div>

@if(getSetting('profiles.social_links_enabled'))
    <div class="px-4 py-2">
        @include('elements.profile.profile-social-links', [
         'items' => $profileSocialLinks ?? []
     ])
    </div>
@endif

@if(!empty($storiesEnabled) && !empty($allowHighlights) && !empty($hasHighlights))
    <div class="profile-highlights mb-3" id="profile-highlights">
        <div class="d-flex justify-content-between align-items-center px-4 pt-2 pb-1">
            <div class="text-muted text-uppercase small fw-bold">{{ __('Highlights') }}</div>
        </div>

        <div class="swiper profile-highlights-swiper px-4">
            {{-- Skeleton only when highlights exist --}}
            <div class="profile-highlights-skeleton">
                @include('elements.preloading.stories-swiper-skeleton', ['limit' => 4])
            </div>

            <div class="swiper-wrapper" id="profile-highlights-wrapper"></div>
        </div>
    </div>
@endif

<div class="d-flex flex-column flex-md-row justify-content-md-between pb-2 ps-4 pe-4 mb-3 mt-1">

    <div class="d-flex align-items-center me-2 text-truncate mb-0 mb-md-0">
        @include('elements.icon',['icon'=>'calendar-clear-outline','centered'=>false,'classes'=>'me-1'])
        <div class="text-truncate ms-1">
            {{ucfirst($user->created_at->translatedFormat('F d'))}}
        </div>
    </div>
    @if($user->location)
        <div class="d-flex align-items-center me-2 text-truncate mb-0 mb-md-0">
            @include('elements.icon',['icon'=>'location-outline','centered'=>false,'classes'=>'me-1'])
            <div class="text-truncate ms-1">
                {{$user->location}}
            </div>
        </div>
    @endif
    @if(!getSetting('profiles.disable_website_link_on_profile'))
        @if($user->website)
            <div class="d-flex align-items-center me-2 text-truncate mb-0 mb-md-0">
                @include('elements.icon',['icon'=>'globe-outline','centered'=>false,'classes'=>'me-1'])
                <div class="text-truncate ms-1">
                    <a href="{{$user->website}}" target="_blank" rel="nofollow">
                        {{str_replace(['https://','http://','www.'],'',$user->website)}}
                    </a>
                </div>
            </div>
        @endif
    @endif
    @if(getSetting('profiles.allow_gender_pronouns'))
        @if($user->gender_pronoun)
            <div class="d-flex align-items-center me-2 text-truncate mb-0 mb-md-0">
                @include('elements.icon',['icon'=>'male-female-outline','centered'=>false,'classes'=>'me-1'])
                <div class="text-truncate ms-1">
                    {{$user->gender_pronoun}}
                </div>
            </div>
        @endif
    @endif

</div>
