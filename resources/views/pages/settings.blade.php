@php($accountSuspended = Auth::user()->isBanned())
@extends($accountSuspended ? 'layouts.generic' : 'layouts.user-no-nav')

@section('page_title',  ucfirst(__(str_replace('-', ' ', $activeSettingsTab))))

@section('scripts')
    {!!
        Minify::javascript(
            array_merge($additionalAssets['js'],[
                '/js/pages/settings/settings.js',
                '/js/AISuggestions.js',
         ])
        )->withFullUrl()
    !!}
    @if(getSetting('profiles.allow_profile_bio_markdown'))
        <script src="{{asset('/libs/easymde/dist/easymde.min.js')}}"></script>
    @endif
@stop

@section('styles')
    {!!
        Minify::stylesheet(
            array_merge($additionalAssets['css'],[
                '/css/pages/settings.css',
                '/css/components/account-sidebar.css',
                ])
         )->withFullUrl()
    !!}
    <style>
        .selectize-control.multi .selectize-input>div.active {
            background:#{{getSetting('colors.theme_color_code')}};
        }
    </style>
    @if(getSetting('profiles.allow_profile_bio_markdown'))
        <link href="{{asset('/libs/easymde/dist/easymde.min.css')}}" rel="stylesheet">
    @endif
@stop

@section('content')
    <div class="{{ $accountSuspended ? 'container-xl py-3 py-md-4' : '' }}">
        @if($accountSuspended)
            <div class="border rounded-3 bg-body-tertiary d-flex align-items-center gap-2 p-3 mb-3 small" role="status">
                @include('elements.icon', ['icon' => 'shield-outline', 'variant' => 'small', 'classes' => 'flex-shrink-0 text-muted'])
                <div>
                    <a class="fw-semibold" href="{{ route('account.suspension') }}">{{ __('Your account is suspended.') }}</a>
                    <span class="text-muted">{{ __('Essential account settings remain available.') }}</span>
                </div>
            </div>
        @endif
        <div class="{{ $accountSuspended ? 'border rounded-3' : '' }}">
            <div class="d-flex flex-wrap">
                <div class="col-12 col-md-4 col-lg-3 mb-3 pe-0 px-0 settings-menu {{ $accountSuspended ? 'pt-md-3' : '' }}">

                    <div class="settings-menu-wrapper {{ $accountSuspended ? '' : 'sticky-sm' }}">

                        <div class="d-none d-md-block">
                            @include('elements.settings.settings-header',['type'=>'generic'])
                        </div>
                        <div class="d-block d-md-none mt-3">
                            @include('elements.settings.settings-header',['type'=>'settingTab'])
                        </div>
                        <hr class="mt-2 mb-0">
                        <div class="">
                            @include('elements.settings.settings-menu',['availableSettings' => $availableSettings])
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-8 col-lg-9 mb-5 mb-lg-0 border-start settings-content mt-1 mt-md-0 px-0 {{ $accountSuspended ? '' : 'min-vh-100 border-end' }}">
                    <div class="ms-3 d-none d-md-flex justify-content-between">
                        <div>
                            <h5 class="fw-bold text-dark-r mt-0 mt-md-3 mb-0">{{ ucfirst(__(str_replace('-', ' ', $activeSettingsTab)))}}</h5>
                            <p class="small text-muted fw-normal mt-1 mb-0 lh-base">{{__($currentSettingTab['heading'])}}</p>
                        </div>
                    </div>
                    <hr class="{{in_array($activeSettingsTab, ['subscriptions','payments']) ? 'mb-0' : ''}} d-none d-md-block mt-2">
                    <div class="pb-4 {{in_array($activeSettingsTab, ['subscriptions','payments', 'referrals', 'release-forms']) ? '' : 'px-3 px-md-3'}}">
                        @include('elements.settings.settings-'.$activeSettingsTab)
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
