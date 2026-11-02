<!doctype html>
<html class="h-100" dir="{{GenericHelper::getSiteDirection()}}" lang="{{session('locale')}}" data-bs-theme="light" data-theme="light">
<head>
    <meta charset="utf-8">
    {{-- Page title --}}
    <title>@yield('page_title') - {{config('app.site.name')}} </title>
    {{-- Generic Meta tags --}}
    <meta name="description" content="{{__("Install the script")}}}">
    {{-- CSRF Baby --}}
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @yield('meta')
    {{-- Favicon --}}
    <link rel="shortcut icon" href="{{  asset(config('app.site.favicon')) }}" type="image/x-icon">
    @include('template.fonts')
    @include('template.theme-stylesheets')

    {{-- Global CSS Assets --}}
    {!!
        Minify::stylesheet(
            [
                '/css/app.css',
             ]
             )->withFullUrl()
    !!}
    {{-- Page specific CSS --}}
    @yield('styles')
</head>
<body class="d-flex flex-column">

<div class="flex-fill">
    @yield('content')
</div>

{{-- Global JS Assets --}}
{!!
    Minify::javascript(
        [
        '/libs/jquery/dist/jquery.min.js',
        '/libs/bootstrap/dist/js/bootstrap.bundle.min.js',
        '/js/plugins/toasts.js',
        '/js/Installer.js',
        '/js/app.js',
        ]
    )->withFullUrl()
!!}

{{-- Page specific JS --}}
@yield('scripts')

<script type="module" src="{{asset('/libs/ionicons/dist/ionicons/ionicons.esm.js')}}"></script>

@include('elements.translations')

</body>
</html>
