<div class="mb-3">
    <div class="card py-3 px-3">
        <div class="form-check form-switch">
            <input type="checkbox" class="form-check-input" id="public_profile" {{Auth::user()->public_profile ? 'checked' : ''}}>
            <label class="form-check-label" for="public_profile">{{__('Is public account')}}</label>
        </div>
        <div class="mt-2 pt-1">
            <p class="mb-1">{{__('Having your profile set to private means:')}}</p>
            <ul class="mt-1 mb-1">
                <li>{{__('It will be hidden for search engines and unregistered users entirely.')}}</li>
                <li>{{__('It will also be generally hidden from suggestions and user searches on our platform.')}}</li>
            </ul>
        </div>
    </div>

    @if(\App\Providers\ProfileMonetizationServiceProvider::openProfilesAllowed())
        <div class="card py-3 px-3  mt-3">
            <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input" id="open_profile" {{Auth::user()->open_profile ? 'checked' : ''}}>
                <label class="form-check-label" for="open_profile">{{__('Open profile')}}</label>
            </div>
            <div class="mt-2 pt-1">
                <p class="mb-1">{{__('Having your profile set to open means:')}}</p>
                <ul class="mt-1 mb-1">
                    <li>{{__('Both registered and unregistered users will be able to see your posts.')}}</li>
                    <li>{{__('If account is private, the content will only be available for registered used.')}}</li>
                    <li>{{__('Paid posts will still be locked for open profiles.')}}</li>
                </ul>
            </div>
        </div>
    @endif


    @if(getSetting('security.allow_geo_blocking'))
        <div class="mb-3 card py-3 mt-3">
            <div class="">
                <div class="form-check form-switch">
                    <div class="ms-3">
                        <input type="checkbox" class="form-check-input" id="enable_geoblocking" {{Auth::user()->enable_geoblocking ? 'checked' : ''}}>
                        <label class="form-check-label" for="enable_geoblocking">{{__('Enable Geo-blocking')}}</label>
                    </div>
                    <div class="ms-3 mt-1">
                        <small class="fa-details-label text-sm text-muted">{{__("If enabled, visitors from certain countries will be restricted access.")}}</small>
                    </div>
                </div>
            </div>
            <div class="mb-3 px-2 mx-3 mt-2">
                <label for="countrySelect">
                    <span>{{__('Country')}}</span>
                </label>
                <select class="country-select form-select form-select-sm uifield-country" id="countrySelect" required multiple="multiple">
                    @foreach($countries as $country)
                        @if($country->name !== 'All')
                            <option>{{$country->name}}</option>
                        @endif
                    @endforeach
                </select>
            </div>
        </div>
    @endif

    @if(getSetting('security.allow_users_2fa_switch'))
        <div class="mb-3 card py-3 mt-3">

            <div class="form-check form-switch">
                <div class="ms-3">
                    <input type="checkbox" class="form-check-input" id="enable_2fa" {{Auth::user()->enable_2fa ? 'checked' : ''}}>
                    <label class="form-check-label" for="enable_2fa">{{__('Enable email 2FA')}}</label>
                </div>
                <div class="ms-3 mt-1">
                    <small class="fa-details-label text-sm text-muted">{{__("If enabled, access from new devices will be restricted until verified.")}}</small>
                </div>
            </div>

            <div class="allowed-devices mx-3 mt-2 {{Auth::user()->enable_2fa ? '' : 'd-none'}}">
                <div class="lists-wrapper mt-2">
                    <div class="px-2 list-item">
                        @if($verifiedDevicesCount)
                            <p class="h6 fw-bolder mb-2 text-bold-600">{{__("Allowed devices")}}</p>
                            @include('elements.settings.user-devices-list', ['type' => 'verified'])
                        @endif
                        @if($unverifiedDevicesCount)
                            <p class="h6 fw-bolder mb-2 text-bold-600 mt-3">{{__("Un-verified devices")}}</p>
                            @include('elements.settings.user-devices-list', ['type' => 'unverified'])
                        @endif
                    </div>
                </div>
            </div>
        </div>

    @endif

</div>

@include('elements.settings.device-delete-dialog')
