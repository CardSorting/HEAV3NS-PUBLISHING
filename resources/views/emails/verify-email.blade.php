@component('mail::layout')
@slot('header')
@component('mail::header', ['url' => config('app.url')])
<!-- header here -->
<img src="{{ asset(getSetting('site.light_logo')) }}" class="mail-logo" width="250">
@endcomponent
@endslot

# {{ __('Confirm your email address') }}

{{ __('Please confirm your email address to finish setting up your account.') }}

<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin: 30px auto;">
<tr>
<td align="center">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center" bgcolor="{{ $buttonBackground }}" style="
    background-color: {{ $buttonBackground }} !important;
    border-radius: 6px;
    padding: 10px 18px;
">
<a href="{{ $verificationUrl }}" target="_blank" style="
    -webkit-text-fill-color: {{ $buttonForeground }} !important;
    color: {{ $buttonForeground }} !important;
    display: inline-block;
    font-size: 16px;
    font-weight: 600;
    line-height: 20px;
    text-decoration: none;
">{{ __('Confirm email address') }}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>

{{ __('This secure verification link will expire in :count minutes.', ['count' => $expirationMinutes]) }}

{{ __('If you did not create an account, you can safely ignore this email.') }}

@slot('subcopy')
@component('mail::subcopy')
{{ __('If the button is not visible or does not work, copy and paste this URL into your browser:') }}

<span class="break-all">[{{ $verificationUrl }}]({{ $verificationUrl }})</span>
@endcomponent
@endslot

@slot('footer')
@component('mail::footer')
© {{ date('Y') }} {{ $siteName }}. {{ __('All rights reserved.') }}
@endcomponent
@endslot
@endcomponent
