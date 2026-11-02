@if(getSetting('site.allow_language_switch'))
    <button type="button" class="text-link footer-preference-button d-flex align-items-center border-0 bg-transparent p-0" onclick="openLanguageSelectorDialog()" aria-haspopup="dialog" aria-controls="language-selector-dialog">
        <span class="d-flex justify-content-center align-items-center">
            <span class="icon-wrapper d-flex justify-content-center align-items-center" aria-hidden="true">
                @include('elements.icon',['icon'=>'language','variant'=>'small', 'classes' => 'me-1'])
            </span>
            <span class="d-block  text-truncate side-menu-label ms-1">{{__("Language")}}</span>
        </span>
    </button>
@endif
