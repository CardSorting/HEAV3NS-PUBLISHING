@php(app(\App\Services\UserStaffBadge::class)->preload($profiles))
<section id="feed-mobile-suggestions" class="feed-mobile-suggestions d-md-none bg-body py-3" aria-labelledby="feed-mobile-suggestions-title">
    <div class="d-flex align-items-center justify-content-between gap-2 px-3 mb-3">
        <h2 id="feed-mobile-suggestions-title" class="h6 fw-bolder mb-0">{{ __('Suggested creators') }}</h2>
        <a href="{{ route('search.get', ['filter' => 'people']) }}" class="feed-mobile-suggestions-link fw-bolder text-nowrap">{{ __('Explore') }}</a>
    </div>
    <div class="feed-mobile-suggestions-track d-flex flex-nowrap gap-2 overflow-x-auto px-3 pb-2" tabindex="0" role="group" aria-label="{{ __('Suggested creators') }}">
        @foreach($profiles as $profile)
            @include('elements.feed.mobile-suggestion-card', ['profile' => $profile])
        @endforeach
    </div>
    <hr class="mb-0">
</section>
