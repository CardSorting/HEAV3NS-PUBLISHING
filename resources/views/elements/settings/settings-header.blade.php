<div class="d-flex justify-content-between">
    <div>
        @if($type == 'generic')
            <div class="ms-3">
                <h5 class="fw-bold text-dark-r mb-0">{{__('Settings')}}</h5>
                <p class="small text-muted fw-normal mt-1 mb-0 lh-base">{{__('Manage your account')}}</p>
            </div>
        @else
            <div class="ms-3">
                <h5 class="fw-bold text-dark-r mt-0 mt-md-3 mb-0">{{ ucfirst(__($activeSettingsTab))}}</h5>
                <p class="small text-muted fw-normal mt-1 mb-0 lh-base">{{__($currentSettingTab['heading'])}}</p>
            </div>
        @endif
    </div>
    <div class="d-flex align-content-center pt-2">
        <div class="w-40 navbar-toggler d-lg-none me-4 h-pill h-pill-primary rounded d-flex justify-content-center align-items-center " data-bs-toggle="collapse" data-bs-target="#settingsNav" aria-controls="settingsNav" aria-expanded="false" aria-label="Toggle navigation">
            @include('elements.icon',['icon'=>'options-outline','variant'=>'medium','centered'=>true])
        </div>
    </div>
</div>
