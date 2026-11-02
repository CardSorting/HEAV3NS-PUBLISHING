@php
    $requiresSubscription = \App\Providers\ProfileMonetizationServiceProvider::canReceiveProfileSubscriptions($user);
    $lockedReelsTitle = trans_choice(
        !empty($lockedEmptyState) || $profileReelsCounts['accessible'] === 0 ? 'profile_locked_reels' : 'profile_locked_reels_with_public',
        $profileReelsCounts['locked'],
        ['count' => $profileReelsCounts['locked']]
    );
    $lockedReelsCopy = $requiresSubscription ? __("Subscribe to view this creator's locked reels.") : __('Follow this creator to view their locked reels.');
@endphp
@if(!empty($lockedEmptyState))
    @component('elements.empty-state', ['icon' => 'lock-closed-outline', 'title' => $lockedReelsTitle, 'copy' => $lockedReelsCopy, 'classes' => 'profile-reels-locked profile-reels-locked-empty-state border rounded-2', 'emptyStateActionsClasses' => 'w-100'])
        @slot('emptyStateActions')
            @include('elements.profile.profile-reels-locked-action')
        @endslot
    @endcomponent
@else
    <div class="reel-grid-locked-card profile-reels-locked">
        <div class="reel-grid-media reel-grid-locked-media border">
            <div class="reel-grid-locked-content d-flex flex-column align-items-center justify-content-center position-absolute top-0 bottom-0 start-0 end-0 text-center">
                <ion-icon name="lock-closed-outline" class="text-primary flex-shrink-0" aria-hidden="true"></ion-icon>
                <h5 class="fw-bold mb-0">
                    {{$lockedReelsTitle}}
                </h5>
                <p class="text-muted mb-0">
                    {{$lockedReelsCopy}}
                </p>
                @include('elements.profile.profile-reels-locked-action')
            </div>
        </div>
    </div>
@endif
