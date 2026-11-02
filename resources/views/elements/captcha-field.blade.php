<div class="captcha-field mb-3">
    <div class="d-flex justify-content-center">
        <x-captcha-container
            data-language="{{GenericHelper::getPreferredLanguage()}}"
            data-theme="{{GenericHelper::getSiteTheme()}}"
        />
    </div>
    @if ($errors->has('cf-turnstile-response') || $errors->has('h-captcha-response') || $errors->has('g-recaptcha-response'))
        <span class="text-danger mt-1" role="alert">
            <strong>{{ __("Please check the captcha field.") }}</strong>
        </span>
    @endif
</div>
