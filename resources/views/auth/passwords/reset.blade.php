@extends('layouts.no-nav')
@section('meta')
    <meta name="robots" content="noindex">
@stop
@section('page_title', __('Reset Password'))

@section('content')
    <div class="container-fluid">
        <div class="row g-0">
            <div class="col-md-6">
                <div class="login d-flex align-items-center py-5">
                    <div class="container">
                        <div class="row">
                            <div class="col-lg-7 col-xl-6 mx-auto">
                                <a href="{{action('HomeController@index')}}">
                                    <img class="brand-logo pb-4"
                                         src="{{ GenericHelper::getCurrentThemeLogo() }}"
                                         data-theme-light-src="{{ asset(getSetting('site.light_logo')) }}"
                                         data-theme-dark-src="{{ asset(getSetting('site.dark_logo')) }}"
                                         alt="{{ __('Site logo') }}">
                                </a>
                                <form method="POST" action="{{ route('password.update') }}">
                                    @csrf
                                    <input type="hidden" name="token" value="{{ $token }}">
                                    <div class="mb-3">
                                        <label for="email" class=" col-form-label">{{ __('E-Mail Address') }}</label>
                                        <div class="">
                                            <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ $email ?? old('email') }}" required autocomplete="email" autofocus>
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
                                        <label for="password-confirm" class="col-form-label">{{ __('Confirm Password') }}</label>
                                        <div class="">
                                            @include('elements.password-field', [
                                                'id' => 'password-confirm',
                                                'name' => 'password_confirmation',
                                                'errorName' => 'password_confirmation',
                                                'autocomplete' => 'new-password',
                                                'required' => true,
                                            ])
                                        </div>
                                    </div>
                                    <div class="mb-0">
                                        <div class="">
                                            <button type="submit" class="btn btn-grow btn-lg btn-primary bg-gradient-primary w-100">
                                                {{ __('Reset Password') }}
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 d-none d-md-flex bg-image p-0 m-0">
                <div class="d-flex m-0 p-0 bg-gradient-primary w-100 h-100">
                    <img src="{{asset('/img/pattern-lines.svg')}}" alt="pattern-lines" class="img-fluid opacity-10">
                </div>
            </div>
        </div>
    </div>
@endsection
