<div
    id="pwa-install-prompt"
    class="pwa-install-prompt alert border shadow-sm rounded d-none mb-0 home-bg-section"
    role="dialog"
    aria-live="polite"
    aria-label="Install app prompt"
>
    <div class="d-flex align-items-center">
        <div class="me-3">
            <img src="{{ getSetting('site.pwa_icon') ? Storage::url(getSetting('site.pwa_icon')) : asset('img/rounded-logo-gradient.svg') }}"
                 alt="{{ getSetting('site.name') }}"
                 class="pwa-install-prompt__icon rounded">
        </div>

        <div class="flex-grow-1 min-w-0">
            <div class="fw-bold text-body mb-1">
                {{__("Install")}} {{ getSetting('site.name') }}
            </div>
            <div class="text-muted small mb-0">
                {{__("Add the app to your home screen for faster access.")}}
            </div>
        </div>

        <div class="ms-3 d-flex align-items-center">
            <button type="button" class="btn btn-sm btn-primary me-2 mb-0" id="pwa-install-button">
                {{__("Install")}}
            </button>

            <button type="button" class="btn-close pwa-install-prompt__close ms-1 flex-shrink-0" id="pwa-install-dismiss" aria-label="Dismiss"></button>
        </div>
    </div>
</div>
