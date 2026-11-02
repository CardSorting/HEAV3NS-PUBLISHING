@extends('layouts.generic')
@section('page_title', app()->isDownForMaintenance() ? __('Maintenance') : __($exception->getMessage() ?: 'Service Unavailable'))

@section('content')
    <div class="container">
        <div class=" d-flex justify-content-center align-items-center min-vh-65" >
            <div class="error-container d-flex flex-column">
                <div class="d-flex justify-content-center align-items-center">
                    <img src="{{asset('/img/500.svg')}}" alt="">
                </div>
                <div class="text-center">
                    @if (app()->isDownForMaintenance())
                        <h3 class="fw-bold">{{ __('We will be back soon') }}</h3>
                        <p class="text-muted">{{ __('The site is temporarily closed for maintenance. Please check back soon.') }}</p>
                        @if (app(\App\Services\SiteMaintenanceService::class)->state()['manual'])
                            <a href="{{ url('/admin') }}">{{ __('Administration') }}</a>
                        @endif
                    @else
                        <h3 class="fw-bold">503 | {{ __($exception->getMessage() ?: 'Service Unavailable') }}</h3>
                        <div class="d-flex justify-content-center mt-2">
                            <a href="{{route('home')}}" class="right">{{__('Go home')}} »</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@stop
