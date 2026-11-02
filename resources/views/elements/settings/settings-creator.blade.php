@include('elements.message-alert')

@if(!GenericHelper::isCreatorAccessEnforced())
    <p>{{ __('Everyone can publish on this site. No creator application is required.') }}</p>
@else
    <div class="onboarding-page creator-onboarding">
        @if($creatorSummaryHeading)
            <header class="creator-onboarding-header mb-4" role="status">
                <h2 class="creator-onboarding-title fw-bold mb-2">{{ $creatorSummaryHeading }}</h2>
                <p class="text-muted mb-0">{{ $creatorSummaryBody }}</p>
            </header>
        @endif

        <section aria-labelledby="creator-requirements-heading">
            <h3 id="creator-requirements-heading" class="creator-onboarding-section-title fw-semibold mb-2">{{ __('Publishing requirements') }}</h3>
            <ul class="onboarding-list">
                <li class="onboarding-row">
                    <div class="onboarding-row-icon {{ $creatorEnrollment?->status === \App\Model\CreatorEnrollment::APPROVED_STATUS ? 'text-success' : '' }}" aria-hidden="true">
                        @include('elements.icon', ['icon' => $creatorApplicationIcon, 'variant' => 'mediumish'])
                    </div>
                    <div class="onboarding-row-copy">
                        <div class="onboarding-row-heading">
                            <h4 class="mb-0">{{ __('Creator application') }}</h4>
                            <span class="onboarding-status">{{ $creatorApplicationStatus }}</span>
                        </div>
                        <p class="text-muted mb-0 mt-1">{{ $creatorApplicationCopy }}</p>
                        @if($creatorEnrollment?->status === \App\Model\CreatorEnrollment::REJECTED_STATUS || $creatorEnrollment?->status === \App\Model\CreatorEnrollment::REVOKED_STATUS)
                            <p class="mb-0 mt-2 text-break">{{ $creatorEnrollment->rejection_reason }}</p>
                        @endif
                    </div>
                </li>
                @if($creatorRequiresIdentity)
                    <li class="onboarding-row">
                        <div class="onboarding-row-icon {{ $creatorIdentityComplete ? 'text-success' : '' }}" aria-hidden="true">
                            @include('elements.icon', ['icon' => $creatorIdentityIcon, 'variant' => 'mediumish'])
                        </div>
                        <div class="onboarding-row-copy">
                            <div class="onboarding-row-heading">
                                <h4 class="mb-0">{{ __('Identity verification') }}</h4>
                                <span class="onboarding-status">{{ $creatorIdentityStatus }}</span>
                            </div>
                            <p class="text-muted mb-0 mt-1">{{ $creatorIdentityCopy }}</p>
                            @if($creatorIdentityActionUrl)
                                <a class="onboarding-row-action mt-1" href="{{ $creatorIdentityActionUrl }}">{{ $creatorIdentityActionLabel }}</a>
                            @endif
                        </div>
                    </li>
                @endif
            </ul>
        </section>

        @if($creatorAcceptsApplications && (!$creatorEnrollment || $creatorEnrollment->status === \App\Model\CreatorEnrollment::REJECTED_STATUS))
            <form id="creator-application" class="creator-application-panel border rounded-3 p-3 p-sm-4 mt-4" method="POST" action="{{ route('my.settings.creator.apply') }}" aria-labelledby="creator-application-heading">
                @csrf
                <h3 id="creator-application-heading" class="creator-application-title fw-semibold {{ $creatorAgreement ? 'mb-1' : 'mb-3' }}">{{ __('Your application') }}</h3>
                @if($creatorAgreement)
                    <p class="text-muted mb-3">{{ GenericHelper::isInstantCreatorActivation() ? __('Accept the agreement to activate creator access.') : __('Tell us about your content and accept the agreement.') }}</p>
                @endif
                @if((string) $creatorApplicationInstructions !== '')
                    <div class="onboarding-instructions mb-3 text-break">
                        {{ $creatorApplicationInstructions }}
                    </div>
                @endif
                @if(!GenericHelper::isInstantCreatorActivation())
                    <div class="mb-3">
                        <label class="form-label" for="creator-application-message">{{ __('Tell us about your content and include any relevant links.') }}</label>
                        <textarea class="form-control @error('application_message') is-invalid @enderror" id="creator-application-message" name="application_message" rows="4" maxlength="5000" @required($creatorApplicationRequiresMessage)>{{ $creatorApplicationMessage }}</textarea>
                        @if(!$creatorApplicationRequiresMessage)
                            <div class="form-text">{{ __('Optional') }}</div>
                        @endif
                        @error('application_message')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                @endif
                @if($creatorAgreement)
                    <div class="form-check creator-application-agreement mb-1">
                        <input class="form-check-input @error('agreement_accepted') is-invalid @enderror" type="checkbox" id="creator-agreement-accepted" name="agreement_accepted" value="1" required @checked(old('agreement_accepted'))>
                        <label class="form-check-label" for="creator-agreement-accepted">
                            {{ __('I have read and accept the') }} <a href="{{ route('pages.get', ['slug' => $creatorAgreement->slug]) }}" target="_blank" rel="noopener">{{ __('Creator Agreement') }}</a>.
                        </label>
                        @error('agreement_accepted')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                @endif
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary mb-0">{{ $creatorEnrollment ? __('Apply again') : __('Apply') }}</button>
                </div>
            </form>
        @endif
    </div>
@endif
