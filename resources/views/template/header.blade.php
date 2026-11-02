@php
    $isHomeNavbar = Route::currentRouteName() === 'home';
    $isSuspended = Auth::check() && Auth::user()->isBanned();
    $showGuestNavbarLinks = Route::currentRouteName() !== 'profile';
    $showMobileUserMenu = Auth::check() || $showGuestNavbarLinks;
    $navbarExpandClass = $isHomeNavbar ? 'navbar-expand-lg' : 'navbar-expand-md';
    $mobileUserMenuVisibilityClass = $isHomeNavbar ? 'd-lg-none' : 'd-md-none';
    $desktopGuestLinksVisibilityClass = $isHomeNavbar ? 'd-none d-lg-flex' : 'd-none d-md-flex';
    $themeNavbarClass = GenericHelper::getNavbarThemeClass();
    $themeBackgroundClass = GenericHelper::getNavbarBackgroundClass();
    $themeLogo = GenericHelper::getCurrentThemeLogo();
    $lightNavbarClasses = 'navbar-light'.($isHomeNavbar ? '' : ' bg-white');
    $darkNavbarClasses = 'navbar-dark'.($isHomeNavbar ? '' : ' bg-dark');
@endphp

<nav class="navbar sticky-nav z-index-4 top {{ $navbarExpandClass }} {{ $themeNavbarClass }} {{ $isHomeNavbar ? 'app-navbar app-navbar-home app-navbar-top' : $themeBackgroundClass . ' shadow-sm' }}"
     data-theme-light-class="{{ $lightNavbarClasses }}"
     data-theme-dark-class="{{ $darkNavbarClasses }}"
     style="top: var(--ga-h);">
    <div class="container app-navbar-inner">
        <a class="navbar-brand" href="{{ $isSuspended ? route('account.suspension') : route('home') }}">
            <img src="{{ $themeLogo }}"
                 data-theme-light-src="{{ asset(getSetting('site.light_logo')) }}"
                 data-theme-dark-src="{{ asset(getSetting('site.dark_logo')) }}"
                 class="d-inline-block align-top"
                 alt="{{__("Site logo")}}">
        </a>
        <button class="navbar-toggler{{ $showMobileUserMenu ? ' mobile-user-menu-trigger' : '' }}"
                type="button"
                @unless($showMobileUserMenu)
                    data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                @endunless
                aria-expanded="false"
                aria-label="{{ __('Toggle navigation') }}" >
            <span class="navbar-toggler-icon"></span>
        </button>

        @if($showMobileUserMenu)
            <div class="mobile-user-menu {{ $mobileUserMenuVisibilityClass }}" hidden>
                <div class="dropdown-menu dropdown-menu-end navbar-user-menu mobile-user-menu-panel show">
                    @auth
                        @if($isSuspended)
                            @include('elements.account.suspended-navigation-items')
                        @else
                            <a class="dropdown-item d-flex align-items-center" href="{{route('feed')}}">
                                @include('elements.icon',['icon'=>'home-outline','variant'=>'small', 'classes' => 'me-2 text-muted'])
                                {{__('Feed')}}
                            </a>

                            <a class="dropdown-item d-flex align-items-center" href="{{route('my.notifications')}}">
                                @include('elements.icon',['icon'=>'notifications-outline','variant'=>'small', 'classes' => 'me-2 text-muted'])
                                {{__('Notifications')}}
                            </a>

                            <a class="dropdown-item d-flex align-items-center" href="{{route('my.messenger.get')}}">
                                @include('elements.icon',['icon'=>'chatbubble-outline','variant'=>'small','classes'=>'me-2 text-muted'])
                                {{__('Messenger')}}
                            </a>

                            <a class="dropdown-item d-flex align-items-center" href="{{route('my.bookmarks')}}">
                                @include('elements.icon',['icon'=>'bookmark-outline','variant'=>'small','classes'=>'me-2 text-muted'])
                                {{__('Bookmarks')}}
                            </a>

                            <a class="dropdown-item d-flex align-items-center" href="{{route('my.lists.all')}}">
                                @include('elements.icon',['icon'=>'list-outline','variant'=>'small','classes'=>'me-2 text-muted'])
                                {{__('Lists')}}
                            </a>

                            <a class="dropdown-item d-flex align-items-center" href="{{route('my.settings')}}">
                                @include('elements.icon',['icon'=>'settings-outline','variant'=>'small','classes'=>'me-2 text-muted'])
                                {{__('Settings')}}
                            </a>

                            <a class="dropdown-item d-flex align-items-center" href="{{route('profile',['username'=>Auth::user()->username])}}">
                                @include('elements.icon',['icon'=>'person-circle-outline','variant'=>'small','classes'=>'me-2 text-muted'])
                                {{__('Profile')}}
                            </a>
                        @endif

                        <div class="dropdown-divider"></div>

                        <a class="dropdown-item d-flex align-items-center" href="{{ route('logout') }}"
                           onclick="event.preventDefault();document.getElementById('mobile-navbar-logout-form').submit();">
                            @include('elements.icon',['icon'=>'log-out-outline','variant'=>'small','classes'=>'me-2 text-muted'])
                            {{ __('Logout') }}
                        </a>
                        <form id="mobile-navbar-logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    @else
                        @if($showGuestNavbarLinks)
                            <a class="dropdown-item d-flex align-items-center" href="{{ route('login') }}">
                                @include('elements.icon',['icon'=>'log-in-outline','variant'=>'small','classes'=>'me-2 text-muted'])
                                {{ __('Login') }}
                            </a>

                            @if (Route::has('register'))
                                <a class="dropdown-item d-flex align-items-center" href="{{ route('register') }}">
                                    @include('elements.icon',['icon'=>'person-add-outline','variant'=>'small','classes'=>'me-2 text-muted'])
                                    {{ __('Register') }}
                                </a>
                            @endif
                        @endif
                    @endauth
                </div>
            </div>
        @endif

        <div class="collapse navbar-collapse ps-3 ps-md-0" id="navbarSupportedContent">
            <!-- Left Side Of Navbar -->
            <ul class="navbar-nav me-auto">
            {{-- Not used at the moment --}}
            </ul>

            <!-- Right Side Of Navbar -->
            <ul class="navbar-nav ms-auto d-flex align-items-center {{ Auth::check() ? 'app-navbar-auth-links' : 'app-navbar-guest-links ' . $desktopGuestLinksVisibilityClass }}">
                <!-- Authentication Links -->
                @guest
                    @if($showGuestNavbarLinks)
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                        </li>
                        @if (Route::has('register'))
                            <li class="nav-item">
                                <a class="btn btn-primary bg-gradient-primary btn-grow btn-round mb-0 ms-3" href="{{ route('register') }}">{{ __('Register') }}</a>
                            </li>
                        @endif
                    @endif
                @else

                    @if(!$isSuspended)
                        @if(GenericHelper::canViewCreationPage() || GenericHelper::canAccessCreatorOnboarding())
                            <li class="nav-item">
                                <a class="nav-link ms-0" href="{{ GenericHelper::canViewCreationPage() ? route('posts.create') : GenericHelper::getCreatorOnboardingUrl() }}">{{ GenericHelper::canViewCreationPage() ? __('Create') : GenericHelper::getCreatorOnboardingLabel() }}</a>
                            </li>
                        @endif
                        <li class="nav-item">
                            <a class="nav-link ms-0" href="{{ route('feed') }}">{{ __('Feed') }}</a>
                        </li>
                    @endif

                    <li class="nav-item dropdown navbar-user-dropdown">
                        <a id="navbarDropdown" class="nav-link dropdown-toggle text-end text-truncate d-flex align-items-center navbar-user-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <div class="text-truncate max-width-150 navbar-user-name">{{ Auth::user()->name }}</div>
                            <img src="{{Auth::user()->avatar}}" class="rounded-circle home-user-avatar navbar-user-avatar">
                        </a>
                        <div class="dropdown-menu dropdown-menu-end navbar-user-menu" aria-labelledby="navbarDropdown">
                            @if($isSuspended)
                                @include('elements.account.suspended-navigation-items')
                            @else
                                <a class="dropdown-item d-flex align-items-center" href="{{route('feed')}}">
                                    @include('elements.icon',['icon'=>'home-outline','variant'=>'small', 'classes' => 'me-2 text-muted'])
                                    {{__('Feed')}}
                                </a>

                                <a class="dropdown-item d-flex align-items-center" href="{{route('my.notifications')}}">
                                    @include('elements.icon',['icon'=>'notifications-outline','variant'=>'small', 'classes' => 'me-2 text-muted'])
                                    {{__('Notifications')}}
                                </a>

                                <a class="dropdown-item d-flex align-items-center" href="{{route('my.messenger.get')}}">
                                    @include('elements.icon',['icon'=>'chatbubble-outline','variant'=>'small','classes'=>'me-2 text-muted'])
                                    {{__('Messenger')}}
                                </a>

                                <a class="dropdown-item d-flex align-items-center" href="{{route('my.bookmarks')}}">
                                    @include('elements.icon',['icon'=>'bookmark-outline','variant'=>'small','classes'=>'me-2 text-muted'])
                                    {{__('Bookmarks')}}
                                </a>

                                <a class="dropdown-item d-flex align-items-center" href="{{route('my.lists.all')}}">
                                    @include('elements.icon',['icon'=>'list-outline','variant'=>'small','classes'=>'me-2 text-muted'])
                                    {{__('Lists')}}
                                </a>

                                <a class="dropdown-item d-flex align-items-center" href="{{route('my.settings')}}">
                                    @include('elements.icon',['icon'=>'settings-outline','variant'=>'small','classes'=>'me-2 text-muted'])
                                    {{__('Settings')}}
                                </a>

                                <a class="dropdown-item d-flex align-items-center" href="{{route('profile',['username'=>Auth::user()->username])}}">
                                    @include('elements.icon',['icon'=>'person-circle-outline','variant'=>'small','classes'=>'me-2 text-muted'])
                                    {{__('Profile')}}
                                </a>
                            @endif

                            <div class="dropdown-divider"></div>

                            <a class="dropdown-item d-flex align-items-center" href="{{ route('logout') }}"
                               onclick="event.preventDefault();document.getElementById('logout-form').submit();">
                                @include('elements.icon',['icon'=>'log-out-outline','variant'=>'small','classes'=>'me-2 text-muted'])
                                {{ __('Logout') }}
                            </a>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                @csrf
                            </form>
                        </div>
                    </li>
                @endguest
            </ul>

        </div>
    </div>
</nav>
