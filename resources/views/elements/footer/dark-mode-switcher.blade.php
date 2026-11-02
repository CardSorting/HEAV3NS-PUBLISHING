@if(getSetting('site.allow_theme_switch'))
    <button type="button"
            class="text-link footer-preference-button d-flex align-items-center dark-mode-switcher border-0 bg-transparent p-0"
            data-current-theme="{{ GenericHelper::getSiteTheme() }}"
            aria-label="{{ GenericHelper::getThemeSwitchLabel() }}">
        <span class="d-flex justify-content-center align-items-center">
            <span class="icon-wrapper d-flex justify-content-center align-items-center" aria-hidden="true">
                <span data-theme-light-class="d-none" class="d-inline-flex align-items-center {{ GenericHelper::isDarkMode() ? '' : 'd-none' }}">
                    @include('elements.icon',['icon'=>'contrast-outline','variant'=>'small','centered'=>false,'classes'=>'me-1'])
                </span>
                <span data-theme-dark-class="d-none" class="d-inline-flex align-items-center {{ GenericHelper::isDarkMode() ? 'd-none' : '' }}">
                    @include('elements.icon',['icon'=>'contrast','variant'=>'small','centered'=>false,'classes'=>'me-1'])
                </span>
            </span>
            <span class="d-block text-truncate side-menu-label ms-1"
                  data-theme-toggle-label
                  data-theme-light-label="{{ __('Light') }}"
                  data-theme-dark-label="{{ __('Dark') }}"
                  data-theme-auto-label="{{ __('Auto (system)') }}">
                {{ GenericHelper::getThemeSwitchLabel(true) }}
            </span>
        </span>
    </button>
@endif
