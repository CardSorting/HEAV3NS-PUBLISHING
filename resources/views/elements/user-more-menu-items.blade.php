@php
    $currentMenuRoute = Route::currentRouteName();
    $isHelpMenuActive = $currentMenuRoute === 'pages.get' && request()->route('slug') === 'help';
    $isSettingsMenuActive = $currentMenuRoute === 'my.settings'
        && request()->route('type') !== 'subscriptions';
    $isMyStreamsMenuActive = str_starts_with((string) $currentMenuRoute, 'my.streams.');
@endphp

@auth
    @if(GenericHelper::isEmailEnforcedAndValidated())
        @if(GenericHelper::needsCreatorOnboarding())
            <a class="dropdown-item d-flex align-items-center" href="{{ GenericHelper::getCreatorOnboardingUrl() }}" @if($currentMenuRoute === 'my.settings' && request()->route('type') === 'creator') aria-current="page" @endif>
                <span class="side-menu-create-option-icon">@include('elements.icon', ['icon' => 'person-add-outline', 'variant' => 'medium', 'centered' => false])</span>
                <span class="side-menu-create-option-label">{{ GenericHelper::getCreatorOnboardingLabel() }}</span>
            </a>
        @endif
        @if(($showVaultMenuItem ?? true) && AttachmentHelper::isVaultEnabled())
            <a class="dropdown-item d-flex align-items-center" href="{{ route('my.vault.index') }}" @if(request()->routeIs('my.vault.*')) aria-current="page" @endif>
                <span class="side-menu-create-option-icon">@include('elements.icon', ['icon' => 'albums-outline', 'variant' => 'medium', 'centered' => false])</span>
                <span class="side-menu-create-option-label">{{ __('Media Vault') }}</span>
            </a>
        @endif
        <a class="dropdown-item d-flex align-items-center"
           href="{{route('my.settings')}}"
           @if($isSettingsMenuActive) aria-current="page" @endif>
            <span class="side-menu-create-option-icon">
                @include('elements.icon',['icon'=>'settings-outline','variant'=>'medium','centered'=>false])
            </span>
            <span class="side-menu-create-option-label">{{__('Settings')}}</span>
        </a>

        @if(getSetting('streams.streaming_driver') !== 'none')
            <a class="dropdown-item d-flex align-items-center"
               href="{{route('my.streams.get')}}"
               @if($isMyStreamsMenuActive) aria-current="page" @endif>
                <span class="side-menu-create-option-icon">
                    @include('elements.icon',['icon'=>'play-circle-outline','variant'=>'medium','centered'=>false])
                </span>
                <span class="side-menu-create-option-label">{{__('My streams')}}</span>
            </a>
        @endif
    @endif
@endauth

@if($showHelpMenuItem ?? true)
<a class="dropdown-item d-flex align-items-center"
   href="{{route('pages.get',['slug'=>'help'])}}"
   @if($isHelpMenuActive) aria-current="page" @endif>
    <span class="side-menu-create-option-icon">
        @include('elements.icon',['icon'=>'help-circle-outline','variant'=>'medium','centered'=>false])
    </span>
    <span class="side-menu-create-option-label">{{__('Help and support')}}</span>
</a>
@endif

@if(getSetting('site.allow_theme_switch') || getSetting('site.allow_language_switch'))
    <div class="dropdown-divider" role="separator"></div>

    @if(getSetting('site.allow_theme_switch'))
        <button type="button"
                class="dropdown-item d-flex align-items-center dark-mode-switcher"
                data-current-theme="{{ GenericHelper::getSiteTheme() }}"
                aria-label="{{ GenericHelper::getThemeSwitchLabel() }}">
            <span class="side-menu-create-option-icon">
                <span data-theme-light-class="d-none" class="d-inline-flex align-items-center {{ GenericHelper::isDarkMode() ? '' : 'd-none' }}">
                    @include('elements.icon',['icon'=>'contrast-outline','variant'=>'medium','centered'=>false])
                </span>
                <span data-theme-dark-class="d-none" class="d-inline-flex align-items-center {{ GenericHelper::isDarkMode() ? 'd-none' : '' }}">
                    @include('elements.icon',['icon'=>'contrast-outline','variant'=>'medium','centered'=>false])
                </span>
            </span>
            <span class="side-menu-create-option-label"
                  data-theme-toggle-label
                  data-theme-light-label="{{ __('Light mode') }}"
                  data-theme-dark-label="{{ __('Dark mode') }}"
                  data-theme-auto-label="{{ __('Auto (system)') }}">
                {{ GenericHelper::getThemeSwitchLabel() }}
            </span>
        </button>
    @endif

    @if(getSetting('site.allow_language_switch'))
        <button type="button"
                class="dropdown-item d-flex align-items-center user-more-language-switcher"
                data-bs-toggle="modal"
                data-bs-target="#language-selector-dialog">
            <span class="side-menu-create-option-icon">
                @include('elements.icon',['icon'=>'language','variant'=>'medium','centered'=>false])
            </span>
            <span class="side-menu-create-option-label">{{__('Language')}}</span>
        </button>
    @endif
@endif

@auth
    <div class="dropdown-divider" role="separator"></div>

    <button type="submit"
            form="user-navigation-logout-form"
            class="dropdown-item d-flex align-items-center side-menu-more-session-action">
        <span class="side-menu-create-option-icon">
            @include('elements.icon',['icon'=>'log-out-outline','variant'=>'medium','centered'=>false])
        </span>
        <span class="side-menu-create-option-label">{{__('Log out')}}</span>
    </button>
@endauth
