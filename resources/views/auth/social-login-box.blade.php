@if(getSetting('profiles.social_auth_facebook_client_id') || getSetting('profiles.social_auth_twitter_client_id') || getSetting('profiles.social_auth_google_client_id'))
    <div class="social-login-links">

        <div class="strike mt-2">
            <span>{{__("Or use social")}}</span>
        </div>

        <div class="mt-4 d-flex flex-column gap-3">
            @if(getSetting('profiles.social_auth_facebook_client_id'))
                <div class="d-flex justify-content-center">
                    <a href="{{url('',['socialAuth','facebook'])}}" rel="nofollow" class="btn w-100 btn-outline-primary btn-round">
                        <div class="d-flex align-items-center justify-content-center gap-2">
                            <img src="{{asset('/img/logos/facebook-logo.svg')}}" class="social-media-icon flex-shrink-0" alt="" aria-hidden="true"/>
                            <span>
                                {{__("Sign in with")}} {{__("Facebook")}}
                            </span>
                        </div>
                    </a>
                </div>
            @endif

            @if(getSetting('profiles.social_auth_twitter_client_id'))
                <div class="d-flex justify-content-center">
                    <a href="{{url('',['socialAuth','twitter'])}}" rel="nofollow" class="btn w-100 btn-outline-primary btn-round">
                        <div class="d-flex align-items-center justify-content-center gap-2">
                            <img src="{{asset('/img/logos/x-logo.svg')}}" class="social-media-icon flex-shrink-0" alt="" aria-hidden="true"/>
                            <span>
                                {{__("Sign in with")}} {{__("X")}}
                            </span>
                        </div>
                    </a>
                </div>
            @endif

            @if(getSetting('profiles.social_auth_google_client_id'))
                <div class="d-flex justify-content-center">
                    <a href="{{url('',['socialAuth','google'])}}" rel="nofollow" class="btn w-100 btn-outline-primary btn-round">
                        <div class="d-flex align-items-center justify-content-center gap-2">
                            <img src="{{asset('/img/logos/google-logo.svg')}}" class="social-media-icon flex-shrink-0" alt="" aria-hidden="true"/>
                            <span>
                                {{__("Sign in with")}} {{__("Google")}}
                            </span>
                        </div>
                    </a>
                </div>
            @endif
        </div>
    </div>

@endif
