<form method="POST" action="{{ route('register') }}" id="register-form">
    @csrf

    @if(getSetting('profiles.social_auth_facebook_client_id') || getSetting('profiles.social_auth_twitter_client_id') || getSetting('profiles.social_auth_google_client_id'))
        <div class="my-1">
            <p class="mb-0">
                {{__('Already got an account?')}}
                @if(isset($mode) && $mode == 'ajax')
                    <a href="javascript:void(0);" onclick="LoginModal.changeActiveTab('login')" class="text-primary text-gradient fw-bold">{{__('Sign in')}}</a>
                @else
                    <a href="{{route('login')}}" class="text-primary text-gradient fw-bold">{{__('Sign in')}}</a>
                @endif
            </p>
        </div>
    @endif

    <div class="mb-3">
        <label for="name" class="col-form-label">{{ __('Name') }}</label>
        <div class="">
            <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}"  autocomplete="name" autofocus>
            @error('name')
            <span class="invalid-feedback" role="alert">
                <strong>{{ $message }}</strong>
            </span>
            @enderror
        </div>
    </div>

    <div class="mb-3">
        <label for="email" class=" col-form-label ">{{ __('E-Mail Address') }}</label>
        <div class="">
            <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email">
            @error('email')
            <span class="invalid-feedback" role="alert">
                <strong>{{ $message }}</strong>
            </span>
            @enderror
        </div>
    </div>

    <div class="mb-3">
        <label for="password" class=" col-form-label ">{{ __('Password') }}</label>
        <div class="">
            @include('elements.password-field', [
                'id' => 'password',
                'name' => 'password',
                'errorName' => 'password',
                'autocomplete' => 'new-password',
                'required' => true,
            ])

            @error('password')
            <span class="invalid-feedback d-block" role="alert">
                <strong>{{ $message }}</strong>
            </span>
            @enderror
        </div>
    </div>

    <div class="mb-3">
        <label for="password-confirm" class=" col-form-label ">{{ __('Confirm Password') }}</label>

        <div class="">
            @include('elements.password-field', [
                'id' => 'password-confirm',
                'name' => 'password_confirmation',
                'errorName' => 'password_confirmation',
                'autocomplete' => 'new-password',
                'required' => true,
            ])
            @error('password_confirmation')
            <span class="invalid-feedback d-block" role="alert">
                <strong>{{ $message }}</strong>
            </span>
            @enderror
        </div>
    </div>

    <div class="mb-3">
        <div class="form-check">
            <input class="form-check-input @error('terms') is-invalid @enderror" id="tosAgree" type="checkbox" name="terms" value="1" placeholder="{{ __('Terms and Conditions') }}">
            <label class="form-check-label" for="tosAgree">
                <span>{{ __('I agree to the') }} <a href="{{route('pages.get',['slug'=>GenericHelper::getTOSPage()->slug])}}">{{ __('Terms of Use') }}</a> {{ __('and') }} <a href="{{route('pages.get',['slug'=>GenericHelper::getPrivacyPage()->slug])}}">{{ __('Privacy Policy') }}</a>.</span>
            </label>
        </div>
    </div>

    @if(getSetting('security.captcha_driver') !== 'none' && !Auth::check())
        @include('elements.captcha-field')
    @endif

    <div class="row mb-0">
        <div class="col">
            <button type="submit" class="btn btn-grow btn-lg btn-primary bg-gradient-primary w-100">
                {{ __('Register') }}
            </button>
        </div>
    </div>

</form>
@if(!getSetting('profiles.social_auth_facebook_client_id') && !getSetting('profiles.social_auth_twitter_client_id') && !getSetting('profiles.social_auth_google_client_id'))
    <hr>
    <div class=" text-center">
        <p class="mb-4">
            {{__('Already got an account?')}}
            @if(isset($mode) && $mode == 'ajax')
                <a href="javascript:void(0);" onclick="LoginModal.changeActiveTab('login')" class="text-primary text-gradient fw-bold">{{__('Sign in')}}</a>
            @else
                <a href="{{route('login')}}" class="text-primary text-gradient fw-bold">{{__('Sign in')}}</a>
            @endif
        </p>
    </div>
@endif
