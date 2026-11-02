@auth
    @if(GenericHelper::isEmailEnforcedAndValidated())
        <a class="dropdown-item d-flex align-items-center"
           href="{{route('profile', ['username' => Auth::user()->username])}}"
           @if(Route::currentRouteName() === 'profile' && request()->route('username') === Auth::user()->username) aria-current="page" @endif>
            <span class="side-menu-create-option-icon">
                @include('elements.icon', ['icon' => 'person-circle-outline', 'variant' => 'medium', 'centered' => false])
            </span>
            <span class="side-menu-create-option-label">{{__('My profile')}}</span>
        </a>
    @endif

    @if(\App\Providers\GenericHelperServiceProvider::shouldRenderExploreMenu())
        <a class="dropdown-item d-flex align-items-center"
           href="{{route('search.get')}}"
           @if(Route::currentRouteName() === 'search.get' && request()->get('filter') !== 'live') aria-current="page" @endif>
            <span class="side-menu-create-option-icon">
                @include('elements.icon', ['icon' => 'compass-outline', 'variant' => 'medium', 'centered' => false])
            </span>
            <span class="side-menu-create-option-label">{{__('Explore')}}</span>
        </a>
    @endif

    @if(GenericHelper::isEmailEnforcedAndValidated())
        @if(getSetting('reels.reels_enabled'))
            <a class="dropdown-item d-flex align-items-center"
               href="{{route('reels.index')}}"
               @if(in_array(Route::currentRouteName(), ['reels.index', 'reels.get'])) aria-current="page" @endif>
                <span class="side-menu-create-option-icon">
                    @include('elements.icon', ['icon' => 'film-outline', 'variant' => 'medium', 'centered' => false])
                </span>
                <span class="side-menu-create-option-label">{{__('Reels')}}</span>
            </a>
        @endif

        @if(getSetting('streams.streaming_driver') !== 'none')
            <a class="dropdown-item d-flex align-items-center"
               href="{{route('search.get', ['filter' => 'live'])}}"
               @if(in_array(Route::currentRouteName(), ['public.stream.get', 'public.vod.get']) || (Route::currentRouteName() === 'search.get' && request()->get('filter') === 'live')) aria-current="page" @endif>
                <span class="side-menu-create-option-icon">
                    @include('elements.icon', ['icon' => 'play-circle-outline', 'variant' => 'medium', 'centered' => false])
                </span>
                <span class="side-menu-create-option-label">{{__('Streams')}}</span>
            </a>
        @endif

        <a class="dropdown-item d-flex align-items-center"
           href="{{route('my.bookmarks')}}"
           @if(Route::currentRouteName() === 'my.bookmarks') aria-current="page" @endif>
            <span class="side-menu-create-option-icon">
                @include('elements.icon', ['icon' => 'bookmark-outline', 'variant' => 'medium', 'centered' => false])
            </span>
            <span class="side-menu-create-option-label">{{__('Bookmarks')}}</span>
        </a>

        <a class="dropdown-item d-flex align-items-center"
           href="{{route('my.lists.all')}}"
           @if(in_array(Route::currentRouteName(), ['my.lists.all', 'my.lists.show'])) aria-current="page" @endif>
            <span class="side-menu-create-option-icon">
                @include('elements.icon', ['icon' => 'list-outline', 'variant' => 'medium', 'centered' => false])
            </span>
            <span class="side-menu-create-option-label">{{__('Lists')}}</span>
        </a>

        <div class="dropdown-divider" role="separator"></div>
    @endif
@endauth
