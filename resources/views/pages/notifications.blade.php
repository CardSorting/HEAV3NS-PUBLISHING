@extends('layouts.user-no-nav')

@section('page_title', __('Notifications'))

@section('styles')
    {!!
        Minify::stylesheet([
            '/css/pages/notifications.css'
         ])->withFullUrl()
    !!}
@stop

@section('scripts')
    {!!
        Minify::javascript([
            '/js/pages/notifications.js'
         ])->withFullUrl()
    !!}
@stop

@section('content')
    <div class="d-flex flex-wrap">
        <div class="col-12 pe-0 min-vh-100 pt-4 border-end px-0">
            <div class="px-3 pb-4 border-bottom">
                <h5 class="text-truncate fw-bold text-dark-r mb-0">{{__('Notifications')}}</h5>
            </div>
            <div class="mt-3 inline-border-tabs">
                @include('elements.notifications.notifications-menu')
            </div>
            @include('elements.notifications.notifications-wrapper', ['notifications' => $notifications])
        </div>
    </div>
@stop
