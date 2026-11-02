@php
    $isDarkTheme = GenericHelper::isDarkMode();
    $streamsMenuRoutes = ['public.stream.get', 'public.vod.get'];
    $isStreamsMenuActive = in_array(Route::currentRouteName(), $streamsMenuRoutes) || (Route::currentRouteName() == 'search.get' && request()->get('filter') == 'live');
    $isSettingsMoreMenuActive = Route::currentRouteName() === 'my.settings'
        && request()->route('type') !== 'subscriptions';
    $isMoreMenuActive = $isSettingsMoreMenuActive
        || str_starts_with((string) Route::currentRouteName(), 'my.streams.')
        || (Route::currentRouteName() === 'pages.get' && request()->route('slug') === 'help');
    $sideMenuUser = Auth::user();
    $sideMenuFollowStats = $sideMenuUser
        ? ListsHelper::getUserFollowStats($sideMenuUser->id)
        : null;
    $sideMenuFollowingUrl = $sideMenuFollowStats && $sideMenuFollowStats['following_list_id']
        ? route('my.lists.show', ['list_id' => $sideMenuFollowStats['following_list_id']])
        : route('my.lists.all');
    $sideMenuStatsAreLinked = $sideMenuUser && GenericHelper::isEmailEnforcedAndValidated();
    // Escape translated labels before adding emphasis, preserving each language's number placement.
    $sideMenuFansLabel = $sideMenuFollowStats
        ? str_replace(':number', '<strong class="side-menu-account-stat-value">'.e(short_number($sideMenuFollowStats['followers_count'])).'</strong>', e(trans_choice('fans', $sideMenuFollowStats['followers_count'])))
        : '';
    $sideMenuFollowingLabel = $sideMenuFollowStats
        ? str_replace(':number', '<strong class="side-menu-account-stat-value">'.e(short_number($sideMenuFollowStats['following_count'])).'</strong>', e(trans_choice('following', $sideMenuFollowStats['following_count'])))
        : '';
@endphp

<div class="side-menu px-1 px-md-2 px-lg-3 {{$isDarkTheme ? 'side-menu-theme-dark' : 'side-menu-theme-light'}}"
     data-theme-light-class="side-menu-theme-light"
     data-theme-dark-class="side-menu-theme-dark">
    <div class="user-details side-menu-account mb-4 d-flex align-items-center flex-row-no-rtl">
        <a href="{{$sideMenuUser ? route('profile', ['username' => $sideMenuUser->username]) : route('login')}}"
           class="side-menu-account-link side-menu-account-avatar-link ms-0 ms-md-2"
           aria-label="{{$sideMenuUser ? __('My profile') : __('Login')}}">
            @if(Auth::check())
                <img src="{{$sideMenuUser->avatar}}" class="rounded-circle user-avatar" alt="">
            @else
                <div class="avatar-placeholder">
                    @include('elements.icon',['icon'=>'person-circle','variant'=>'xlarge text-muted'])
                </div>
            @endif
        </a>
        @if($sideMenuUser)
            <div class="side-menu-account-copy d-none d-lg-flex justify-content-center flex-column overflow-hidden">
                <a href="{{route('profile', ['username' => $sideMenuUser->username])}}"
                   class="side-menu-account-link side-menu-account-username text-truncate pe-3">
                    <span>@</span>{{$sideMenuUser->username}}
                </a>
                <div class="side-menu-account-stats">
                    @if($sideMenuStatsAreLinked)
                        <a href="{{route('my.lists.show', ['list_id' => \App\Model\UserList::FOLLOWERS_TYPE])}}" class="side-menu-account-stat" data-side-menu-stat="followers">
                            {!! $sideMenuFansLabel !!}
                        </a>
                    @else
                        <span class="side-menu-account-stat" data-side-menu-stat="followers">
                            {!! $sideMenuFansLabel !!}
                        </span>
                    @endif
                    <span class="side-menu-account-stat-group">
                        <span class="side-menu-account-stat-separator" aria-hidden="true">·</span>
                        @if($sideMenuStatsAreLinked)
                            <a href="{{$sideMenuFollowingUrl}}" class="side-menu-account-stat" data-side-menu-stat="following">
                                {!! $sideMenuFollowingLabel !!}
                            </a>
                        @else
                            <span class="side-menu-account-stat" data-side-menu-stat="following">
                                {!! $sideMenuFollowingLabel !!}
                            </span>
                        @endif
                    </span>
                </div>
            </div>
        @endif
    </div>
    <ul class="nav flex-column user-side-menu side-menu-primary-nav">
        <li class="nav-item ">
            <a href="{{Auth::check() ? route('feed') : route('home')}}" class="h-pill h-pill-primary nav-link {{Route::currentRouteName() == 'feed' ? 'active' : ''}} d-flex justify-content-between">
                <div class="d-flex justify-content-center align-items-center">
                    <div class="icon-wrapper d-flex justify-content-center align-items-center">
                        @include('elements.icon',['icon'=>'home-outline','variant'=>'large'])
                    </div>
                    <span class="d-none d-md-block d-xl-block d-lg-block ms-2 text-truncate side-menu-label">{{__('Home')}}</span>
                </div>
            </a>
        </li>
        @if(GenericHelper::isEmailEnforcedAndValidated())
            <li class="nav-item">
                <a href="{{route('my.notifications')}}" class="nav-link h-pill h-pill-primary {{Route::currentRouteName() == 'my.notifications' ? 'active' : ''}} d-flex justify-content-between">
                    <div class="d-flex justify-content-center align-items-center">
                        <div class="icon-wrapper d-flex justify-content-center align-items-center position-relative">
                            @include('elements.icon',['icon'=>'notifications-outline','variant'=>'large'])
                            <div class="menu-notification-badge notifications-menu-count {{(isset($notificationsCountOverride) && $notificationsCountOverride->total > 0 ) || (NotificationsHelper::getUnreadNotifications()->total > 0) ? '' : 'd-none'}}">
                                {{!isset($notificationsCountOverride) ? NotificationsHelper::getUnreadNotifications()->total : $notificationsCountOverride->total}}
                            </div>
                        </div>
                        <span class="d-none d-md-block d-xl-block d-lg-block ms-2 text-truncate side-menu-label">{{__('Notifications')}}</span>
                    </div>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{route('my.messenger.get')}}" class="nav-link {{Route::currentRouteName() == 'my.messenger.get' ? 'active' : ''}} h-pill h-pill-primary d-flex justify-content-between">
                    <div class="d-flex justify-content-center align-items-center">
                        <div class="icon-wrapper d-flex justify-content-center align-items-center position-relative">
                            @include('elements.icon',['icon'=>'chatbubble-outline','variant'=>'large'])
                            <div class="menu-notification-badge chat-menu-count {{(NotificationsHelper::getUnreadMessages() > 0) ? '' : 'd-none'}}">
                                {{NotificationsHelper::getUnreadMessages()}}
                            </div>
                        </div>
                        <span class="d-none d-md-block d-xl-block d-lg-block ms-2 text-truncate side-menu-label">{{__('Messages')}}</span>
                    </div>
                </a>
            </li>
        @endif

        @if(\App\Providers\GenericHelperServiceProvider::shouldRenderExploreMenu())
            <li class="nav-item">
                <a href="{{route('search.get')}}" class="nav-link {{Route::currentRouteName() == 'search.get' && (request()->get('filter') != 'live') ? 'active' : ''}} h-pill h-pill-primary d-flex justify-content-between">
                    <div class="d-flex justify-content-center align-items-center">
                        <div class="icon-wrapper d-flex justify-content-center align-items-center">
                            @include('elements.icon',['icon'=>'compass-outline','variant'=>'large'])
                        </div>
                        <span class="d-none d-md-block d-xl-block d-lg-block ms-2 text-truncate side-menu-label">{{__('Explore')}}</span>
                    </div>
                </a>
            </li>
        @endif

        @if(AttachmentHelper::isVaultEnabled() && GenericHelper::isEmailEnforcedAndValidated())
            <li class="nav-item">
                <a href="{{ route('my.vault.index') }}" class="nav-link h-pill h-pill-primary {{ request()->routeIs('my.vault.*') ? 'active' : '' }} d-flex justify-content-between" @if(request()->routeIs('my.vault.*')) aria-current="page" @endif>
                    <div class="d-flex justify-content-center align-items-center">
                        <div class="icon-wrapper d-flex justify-content-center align-items-center">@include('elements.icon', ['icon' => 'albums-outline', 'variant' => 'large'])</div>
                        <span class="d-none d-md-block d-xl-block d-lg-block ms-2 text-truncate side-menu-label">{{ __('Media Vault') }}</span>
                    </div>
                </a>
            </li>
        @endif

        @if(getSetting('reels.reels_enabled') && GenericHelper::isEmailEnforcedAndValidated())
            <li class="nav-item">
                <a href="{{route('reels.index')}}" class="nav-link {{in_array(Route::currentRouteName(), ['reels.index', 'reels.get']) ? 'active' : ''}} h-pill h-pill-primary d-flex justify-content-between">
                    <div class="d-flex justify-content-center align-items-center">
                        <div class="icon-wrapper d-flex justify-content-center align-items-center">
                            @include('elements.icon',['icon'=>'film-outline','variant'=>'large'])
                        </div>
                        <span class="d-none d-md-block d-xl-block d-lg-block ms-2 text-truncate side-menu-label">{{__('Reels')}}</span>
                    </div>
                </a>
            </li>
        @endif

        @if(GenericHelper::isEmailEnforcedAndValidated())
            @if(getSetting('streams.streaming_driver') !== 'none')
                <li class="nav-item">
                    <a href="{{route('search.get')}}?filter=live" class="nav-link {{$isStreamsMenuActive ? 'active' : ''}} h-pill h-pill-primary d-flex justify-content-between">
                        <div class="d-flex justify-content-center align-items-center">
                            <div class="icon-wrapper d-flex justify-content-center align-items-center position-relative">
                                @include('elements.icon',['icon'=>'play-circle-outline','variant'=>'large'])
                                <div class="menu-notification-badge streams-menu-count {{(StreamsHelper::getPublicLiveStreamsCount() > 0) ? '' : 'd-none'}}">
                                    {{StreamsHelper::getPublicLiveStreamsCount()}}
                                </div>
                            </div>
                            <span class="d-none d-md-block d-xl-block d-lg-block ms-2 text-truncate side-menu-label">{{__('Streams')}}</span>
                        </div>

                    </a>
                </li>
            @endif

            <li class="nav-item">
                <a href="{{route('my.bookmarks')}}" class="nav-link {{Route::currentRouteName() == 'my.bookmarks' ? 'active' : ''}} h-pill h-pill-primary d-flex justify-content-between">
                    <div class="d-flex justify-content-center align-items-center">
                        <div class="icon-wrapper d-flex justify-content-center align-items-center">
                            @include('elements.icon',['icon'=>'bookmark-outline','variant'=>'large'])
                        </div>
                        <span class="d-none d-md-block d-xl-block d-lg-block ms-2 text-truncate side-menu-label">{{__('Bookmarks')}}</span>
                    </div>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{route('my.lists.all')}}" class="nav-link {{in_array(Route::currentRouteName(), ['my.lists.all', 'my.lists.show']) ? 'active' : ''}} h-pill h-pill-primary d-flex justify-content-between">
                    <div class="d-flex justify-content-center align-items-center">
                        <div class="icon-wrapper d-flex justify-content-center align-items-center">
                            @include('elements.icon',['icon'=>'list-outline','variant'=>'large'])
                        </div>
                        <span class="d-none d-md-block d-xl-block d-lg-block ms-2 text-truncate side-menu-label">{{__('Lists')}}</span>
                    </div>
                </a>
            </li>
            @if(\App\Providers\ProfileMonetizationServiceProvider::shouldShowSubscriptionsForUser(Auth::user()))
                <li class="nav-item">
                    <a href="{{route('my.settings',['type'=>'subscriptions'])}}" class="nav-link {{Route::currentRouteName() == 'my.settings' &&  is_int(strpos(Request::path(),'subscriptions')) ? 'active' : ''}} h-pill h-pill-primary d-flex justify-content-between">
                        <div class="d-flex justify-content-center align-items-center">
                            <div class="icon-wrapper d-flex justify-content-center align-items-center">
                                @include('elements.icon',['icon'=>'people-circle-outline','variant'=>'large'])
                            </div>
                            <span class="d-none d-md-block d-xl-block d-lg-block ms-2 text-truncate side-menu-label">{{__('Subscriptions')}}</span>
                        </div>
                    </a>
                </li>
            @endif
            <li class="nav-item">
                <a href="{{route('profile',['username'=>Auth::user()->username])}}" class="nav-link {{Route::currentRouteName() == 'profile' && (request()->route("username") == Auth::user()->username) ? 'active' : ''}} h-pill h-pill-primary d-flex justify-content-between">
                    <div class="d-flex justify-content-center align-items-center">
                        <div class="icon-wrapper d-flex justify-content-center align-items-center">
                            @include('elements.icon',['icon'=>'person-circle-outline','variant'=>'large'])
                        </div>
                        <span class="d-none d-md-block d-xl-block d-lg-block ms-2 text-truncate side-menu-label">{{__('My profile')}}</span>
                    </div>
                </a>
            </li>
        @endif

        @guest
            <li class="nav-item">
                <a href="{{route('login')}}" class="nav-link h-pill h-pill-primary {{Route::currentRouteName() === 'login' ? 'active' : ''}} d-flex justify-content-between">
                    <div class="d-flex justify-content-center align-items-center">
                        <div class="icon-wrapper d-flex justify-content-center align-items-center">
                            @include('elements.icon',['icon'=>'log-in-outline','variant'=>'large'])
                        </div>
                        <span class="d-none d-md-block d-xl-block d-lg-block ms-2 text-truncate side-menu-label">{{__('Login')}}</span>
                    </div>
                </a>
            </li>
        @endguest
    </ul>
    <ul class="nav flex-column user-side-menu side-menu-footer-nav">
        <li class="nav-item dropup side-menu-more-group">
            <button type="button"
                    id="side-menu-more-toggle"
                    class="side-menu-more-toggle nav-link h-pill h-pill-primary {{$isMoreMenuActive ? 'active' : ''}} d-flex justify-content-between"
                    data-bs-toggle="dropdown"
                    data-bs-boundary="viewport"
                    aria-haspopup="true"
                    aria-expanded="false">
                <div class="d-flex justify-content-center align-items-center">
                    <div class="icon-wrapper d-flex justify-content-center align-items-center">
                        @include('elements.icon',['icon'=>'ellipsis-horizontal-circle-outline','variant'=>'large'])
                    </div>
                    <span class="d-none d-md-block d-xl-block d-lg-block ms-2 text-truncate side-menu-label">{{__('More')}}</span>
                </div>
            </button>
            <div class="dropdown-menu {{GenericHelper::getSiteDirection() === 'rtl' ? 'dropdown-menu-end' : ''}} side-menu-create-dropdown side-menu-more-dropdown"
                 aria-labelledby="side-menu-more-toggle">
                @include('elements.user-more-menu-items', ['showVaultMenuItem' => false])
            </div>
        </li>

        @if(GenericHelper::isEmailEnforcedAndValidated())
            @if(GenericHelper::canViewCreationPage() && getSetting('streams.streaming_driver') !== 'none')
                <li class="nav-item-live">
                    <a role="button" class="btn btn-round btn-outline-danger w-100 side-menu-action-btn mb-0" href="{{route('my.streams.get')}}{{StreamsHelper::getUserInProgressStream() ? '' : '?action=create'}}">
                        <div class="d-none d-md-flex d-xl-flex d-lg-flex align-items-center justify-content-center text-truncate new-post-label side-menu-action-content">
                            <span class="side-menu-action-icon stream-on-label {{StreamsHelper::getUserInProgressStream() ? '' : 'd-none'}}">
                                <span class="side-menu-live-dot"><div class="blob red"></div></span>
                            </span>
                            <span class="side-menu-action-icon stream-off-label {{StreamsHelper::getUserInProgressStream() ? 'd-none' : ''}}">
                                @include('elements.icon',['icon'=>'ellipse','variant'=>'','classes'=>'flex-shrink-0'])
                            </span>
                            <span class="side-menu-action-text">
                                <span class="stream-on-label {{StreamsHelper::getUserInProgressStream() ? '' : 'd-none'}}">{{__('On air')}}</span>
                                <span class="stream-off-label {{StreamsHelper::getUserInProgressStream() ? 'd-none' : ''}}">{{__('Go live')}}</span>
                            </span>
                        </div>
                        <div class="d-block d-md-none d-flex align-items-center justify-content-center">@include('elements.icon',['icon'=>'add-circle-outline','variant'=>'medium','classes'=>'flex-shrink-0'])</div>
                    </a>
                </li>
            @endif
        @endif

        @include('elements.side-menu-create-actions', ['createOptions' => GenericHelper::getCreationMenuOptions()])


    </ul>
</div>
