@if(!GenericHelper::canPublishContent())
    <div class="alert alert-warning text-white fw-bold mt-2 mb-3" role="status">
        {{ GenericHelper::getCreatorRestrictionMessage() }}
        @if(GenericHelper::canAccessCreatorOnboarding())
            <a class="text-white text-decoration-underline" href="{{ GenericHelper::getCreatorOnboardingUrl() }}">{{ GenericHelper::getCreatorOnboardingLabel() }}</a>
        @endif
    </div>
@endif
