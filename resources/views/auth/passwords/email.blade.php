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
                            @if (session('status'))
                                <div class="alert alert-success text-white" role="alert">
                                    {{ session('status') }}
                                </div>
                            @endif
                            @include('auth.passwords.email-form')
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 d-none d-md-flex bg-image p-0 m-0">
            <div class="d-flex m-0 p-0 bg-gradient-primary w-100 h-100">
                <img src="{{asset('/img/pattern-lines.svg')}}" alt="pattern-lines" class="img-fluid opacity-6">
            </div>
        </div>
    </div>
</div>
@endsection
