<!doctype html>
<html class="h-100"
      dir="{{ GenericHelper::getSiteDirection() }}"
      lang="{{ session('locale') }}"
      data-bs-theme="{{ GenericHelper::getSiteTheme() }}"
      data-theme="{{ GenericHelper::getSiteTheme() }}">
<head>
    @include('template.head')
</head>
<body class="d-flex flex-column">
@include('elements.impersonation-header')
@include('elements.global-announcement')
@include('template.header')
<div class="flex-fill">
    @yield('content')
</div>
@if(\App\Providers\InstallerServiceProvider::checkIfInstalled() && !request()->is('age-check*') && app(\App\Services\AgeCheck\AgeGate::class)->isBuiltIn())
    @include('elements.site-entry-approval-box')
@endif
@include('template.footer')
@include('template.jsVars')
@include('template.jsAssets')
@include('elements.language-selector-box')
@if (getSetting('site.pwa_enabled') && getSetting('site.pwa_install_prompt_enabled'))
    @include('elements.pwa-banner')
@endif
</body>
</html>
