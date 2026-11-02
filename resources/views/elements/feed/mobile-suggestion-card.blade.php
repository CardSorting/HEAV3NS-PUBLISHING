<a href="{{ route('profile', ['username' => $profile->username]) }}" class="feed-mobile-suggestion-card flex-shrink-0 border rounded overflow-hidden bg-body text-body text-decoration-none">
    <div class="position-relative mb-2">
        <img src="{{ $profile->cover }}" class="feed-mobile-suggestion-cover w-100 object-fit-cover d-block" alt="" loading="lazy">
        <img src="{{ $profile->avatar }}" class="feed-mobile-suggestion-avatar position-absolute top-100 start-50 translate-middle rounded-circle border border-2 bg-body object-fit-cover" alt="" loading="lazy">
    </div>
    <div class="px-2 pt-4 pb-3 text-center">
        <div class="d-flex align-items-center justify-content-center small fw-semibold mb-1">
            <span class="text-truncate">{{ $profile->name }}</span>
            @if(GenericHelper::isUserVerified($profile))
                <span class="d-inline-flex align-items-center flex-shrink-0" role="img" aria-label="{{ __('Verified user') }}">
                    @include('elements.icon', ['icon' => 'verified', 'centered' => true, 'classes' => 'ms-1'])
                </span>
            @endif
            @include('elements.user-staff-badge', ['user' => $profile, 'badgeClasses' => ''])
        </div>
        <div class="small text-body-secondary text-truncate">{{ '@'.$profile->username }}</div>
        <div class="small text-primary mt-2">{{ __('View profile') }}</div>
    </div>
</a>
