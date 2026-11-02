<form method="POST" action="{{ route('login') }}">
    @csrf
    @if(getSetting('profiles.social_auth_facebook_client_id') || getSetting('profiles.social_auth_twitter_client_id') || getSetting('profiles.social_auth_google_client_id'))
        <div class="my-1">
            <p class="mb-0">
                {{__("Don't have an account?")}}
                @if(isset($mode) && $mode == 'ajax')
                    <a href="javascript:void(0);" onclick="LoginModal.changeActiveTab('register')" class="text-primary text-gradient fw-bold">{{__('Sign up')}}</a>
                @else
                    <a href="{{route('register')}}" class="text-primary text-gradient fw-bold">{{__('Sign up')}}</a>
                @endif
            </p>
        </div>
    @endif
    <div class="mb-3">
        <label for="email" class="col-form-label">{{ __('E-Mail Address') }}</label>
        <div class="">
            <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"  name="email" value="{{ old('email') }}" autocomplete="email" autofocus>
            @error('email')
            <span class="invalid-feedback" role="alert">
                <strong>{{ $message }}</strong>
            </span>
            @enderror
        </div>
    </div>

    <div class="mb-3">
        <label for="password" class="col-form-label">{{ __('Password') }}</label>
        <div class="">
            @include('elements.password-field', [
                'id' => 'password',
                'name' => 'password',
                'errorName' => 'password',
                'autocomplete' => 'current-password',
            ])
            @error('password')
            <span class="invalid-feedback d-block" role="alert">
                <strong>{{ $message }}</strong>
            </span>
            @enderror
        </div>
    </div>

    <div class="loginHelpers mb-3 d-flex flex-row-reverse">
        @if (Route::has('password.request'))
            <div class="ms-auto">
                @if(isset($mode) && $mode == 'ajax')
                    <a href="javascript:void(0);" onclick="LoginModal.changeActiveTab('forgot')" class="" id="forgotPass-label">{{ __('Forgot Your Password?') }}</a>
                @else
                    <a href="{{ route('password.request') }}" class="" id="forgotPass-label">{{ __('Forgot Your Password?') }}</a>
                @endif
            </div>
        @endif
    </div>

    <div class="clearfix"></div>
    <div class="row mb-0 mt-4">
        <div class="col">
            <button type="submit" class="btn btn-grow btn-lg btn-primary bg-gradient-primary w-100">
                {{__('Login')}}
            </button>
        </div>
    </div>

</form>

@if(!getSetting('profiles.social_auth_facebook_client_id') && !getSetting('profiles.social_auth_twitter_client_id') && !getSetting('profiles.social_auth_google_client_id'))
    <hr>
    <div class=" text-center py-2">
        <p class="">
            {{__("Don't have an account?")}}
            @if(isset($mode) && $mode == 'ajax')
                <a href="javascript:void(0);" onclick="LoginModal.changeActiveTab('register')" class="text-primary text-gradient fw-bold">{{__('Sign up')}}</a>
            @else
                <a href="{{route('register')}}" class="text-primary text-gradient fw-bold">{{__('Sign up')}}</a>
            @endif
        </p>
    </div>
@endif
