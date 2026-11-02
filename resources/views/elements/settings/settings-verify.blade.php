<div class="onboarding-page verification-onboarding">
    @if(session('success'))
        <div class="alert alert-dismissible alert-success text-white fw-bold mt-2" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-dismissible alert-warning text-white fw-bold mt-2" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif

    @if(GenericHelper::isCreatorAccessEnforced() && GenericHelper::canAccessCreatorOnboarding())
        <a class="onboarding-row-action d-inline-flex align-items-center gap-1 mb-3" href="{{ route('my.settings', ['type' => 'creator']) }}">
            @include('elements.icon', ['icon' => 'arrow-back-outline', 'variant' => 'small', 'classes' => 'onboarding-back-icon'])
            {{ __('Back to creator setup') }}
        </a>
    @endif

    <form class="verify-form" action="{{ route('my.settings.verify.save') }}" method="POST">
        @csrf
        <section aria-labelledby="verification-requirements-heading">
            <h3 id="verification-requirements-heading" class="verification-onboarding-section-title fw-semibold mb-2">{{ __('Verification requirements') }}</h3>
            <ul class="onboarding-list">
                <li class="onboarding-row">
                    <div class="onboarding-row-icon {{ $verificationEmailComplete ? 'text-success' : '' }}" aria-hidden="true">
                        @include('elements.icon', ['icon' => $verificationEmailComplete ? 'checkmark-circle-outline' : 'mail-outline', 'variant' => 'mediumish'])
                    </div>
                    <div class="onboarding-row-copy">
                        <div class="onboarding-row-heading">
                            <h4 class="mb-0">{{ __('Email address') }}</h4>
                            <span class="onboarding-status">{{ $verificationEmailComplete ? __('Complete') : __('Needs confirmation') }}</span>
                        </div>
                        @if(!$verificationEmailComplete)
                            <a class="onboarding-row-action mt-1" href="{{ $verificationEmailActionUrl }}">{{ __('Confirm email') }}</a>
                        @endif
                    </div>
                </li>
                <li class="onboarding-row">
                    <div class="onboarding-row-icon {{ $verificationBirthdateComplete ? 'text-success' : '' }}" aria-hidden="true">
                        @include('elements.icon', ['icon' => $verificationBirthdateComplete ? 'checkmark-circle-outline' : 'calendar-outline', 'variant' => 'mediumish'])
                    </div>
                    <div class="onboarding-row-copy">
                        <div class="onboarding-row-heading">
                            <h4 class="mb-0">{{ __('Birthdate') }}</h4>
                            <span class="onboarding-status">{{ $verificationBirthdateComplete ? __('Complete') : __('Not provided') }}</span>
                        </div>
                        @if(!$verificationBirthdateComplete)
                            <a class="onboarding-row-action mt-1" href="{{ $verificationBirthdateActionUrl }}">{{ __('Add birthdate') }}</a>
                        @endif
                    </div>
                </li>
                <li class="onboarding-row">
                    <div class="onboarding-row-icon {{ $verificationDocumentStatus === 'verified' ? 'text-success' : '' }}" aria-hidden="true">
                        @if($verificationDocumentStatus === 'verified')
                            @include('elements.icon', ['icon' => 'checkmark-circle-outline', 'variant' => 'mediumish'])
                        @elseif($verificationDocumentStatus === 'pending')
                            @include('elements.icon', ['icon' => 'time-outline', 'variant' => 'mediumish'])
                        @elseif($verificationDocumentStatus === 'rejected')
                            @include('elements.icon', ['icon' => 'alert-circle-outline', 'variant' => 'mediumish', 'classes' => 'text-warning'])
                        @else
                            @include('elements.icon', ['icon' => 'shield-outline', 'variant' => 'mediumish'])
                        @endif
                    </div>
                    <div class="onboarding-row-copy">
                        <div class="onboarding-row-heading">
                            <h4 class="mb-0">{{ __('ID documents') }}</h4>
                            <span class="onboarding-status">
                                @if($verificationDocumentStatus === 'verified')
                                    {{ __('Approved') }}
                                @elseif($verificationDocumentStatus === 'pending')
                                    {{ __('Awaiting review') }}
                                @elseif($verificationDocumentStatus === 'rejected')
                                    {{ __('Rejected') }}
                                @else
                                    {{ __('Not submitted') }}
                                @endif
                            </span>
                        </div>
                        <div role="status">
                            @if($verificationDocumentStatus === 'pending')
                                <p class="text-muted mb-0 mt-1">{{ __('Your files have been received. No further upload is needed right now.') }} {{ __('You will be notified when your ID review is complete.') }}</p>
                            @elseif($verificationDocumentStatus === 'verified')
                                <p class="text-muted mb-0 mt-1">{{ $verificationComplete ? __('Your identity verification is complete.') : __('Your ID documents have been approved.') }}</p>
                            @elseif($verificationDocumentStatus === 'rejected')
                                <p class="text-muted mb-0 mt-1">{{ __('Review the explanation and submit new photos below.') }}</p>
                                <p class="mb-0 mt-2 text-break">{{ $verificationRejectionReason }}</p>
                            @else
                                <p class="text-muted mb-0 mt-1">{{ __('Submit your ID documents for review.') }}</p>
                            @endif
                        </div>
                    </div>
                </li>
            </ul>
        </section>

        @if($verificationCanSubmit)
            <section class="verification-documents-panel border rounded-3 p-3 p-sm-4 mt-4" aria-labelledby="verification-documents-heading">
                <h3 id="verification-documents-heading" class="verification-documents-title fw-semibold mb-1">{{ $verificationDocumentStatus === 'rejected' ? __('Upload new ID documents') : __('Your ID documents') }}</h3>
                <p class="text-muted mb-3">{{ __('Please attach clear photos of your ID card back and front side.') }}</p>
                @if((string) $verificationInstructions !== '')
                    <aside class="onboarding-instructions text-muted mb-3">
                        {{ $verificationInstructions }}
                    </aside>
                @endif

                <div class="dropzone-previews dropzone verification-document-dropzone w-100 p-3 border rounded" aria-describedby="verification-file-help">
                    <div class="dz-message my-0" data-dz-message>
                        <button type="button" class="verification-upload-trigger d-flex flex-column align-items-center justify-content-center gap-2 text-body border-0 bg-transparent p-0 mb-0 w-100">
                            <span class="d-inline-flex" aria-hidden="true">
                                @include('elements.icon', ['icon' => 'cloud-upload-outline', 'variant' => 'medium', 'classes' => 'text-muted'])
                            </span>
                            <span>{{ __('Drop files here or') }} <span class="text-primary text-decoration-underline">{{ __('Browse files') }}</span></span>
                        </button>
                    </div>
                </div>
                <small id="verification-file-help" class="form-text text-muted d-block mt-2 mb-3">{{ __('Allowed file types') }}: {{ str_replace(',', ', ', AttachmentHelper::filterExtensions('manualPayments')) }}. {{ __('Max size') }}: 4 {{ __('MB') }}.</small>

                @if(getSetting('compliance.enforce_tos_check_on_id_verify'))
                    <div class="form-check verification-consent mb-1">
                        <input class="form-check-input @error('terms') is-invalid @enderror" id="terms" type="checkbox" name="terms" value="1" required @checked(old('terms'))>
                        <label class="form-check-label" for="terms">
                            {{ __('I have read and accept the') }} <a href="{{ route('pages.get', ['slug' => GenericHelper::getTOSPage()->slug]) }}">{{ __('Terms of Use') }}</a>.
                        </label>
                        @error('terms')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                @endif

                @if(getSetting('compliance.enforce_media_agreement_on_id_verify'))
                    <div class="form-check verification-consent mb-1">
                        <input class="form-check-input @error('media_checkbox') is-invalid @enderror" id="media_checkbox" type="checkbox" name="media_checkbox" value="1" required @checked(old('media_checkbox'))>
                        <label class="form-check-label" for="media_checkbox">
                            {{ __('I agree to the public use and display of my media on this site.') }}
                        </label>
                        @error('media_checkbox')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                @endif

                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn btn-primary mb-0">{{ __('Submit for review') }}</button>
                </div>
            </section>
        @elseif((string) $verificationInstructions !== '')
            <aside class="verification-guidance-panel border rounded-3 p-3 mt-3" aria-labelledby="verification-guidance-heading">
                <h3 id="verification-guidance-heading" class="small text-muted fw-semibold mb-2">{{ __('Verification guidance') }}</h3>
                <div class="onboarding-instructions text-muted">
                    {{ $verificationInstructions }}
                </div>
            </aside>
        @endif
    </form>
    @include('elements.uploaded-file-preview-template')
</div>
