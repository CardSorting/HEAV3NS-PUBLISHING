@extends('layouts.no-nav')
@section('page_title', __('Register'))

@section('page_description', getSetting('site.description'))
@section('share_url', route('home'))
@section('share_title', getSetting('site.name') . ' - ' .  __('Register'))
@section('share_description', getSetting('site.description'))
@section('share_type', 'article')
@section('share_img', GenericHelper::getOGMetaImage())

@if(getSetting('security.captcha_driver') !== 'none' && !Auth::check())
    <x-captcha-js />
@endif

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
                                @include('auth.register-form')
                                @include('auth.social-login-box')
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @include('auth.login-background')
        </div>
    </div>
@endsection
