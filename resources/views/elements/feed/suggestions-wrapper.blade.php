<div class="suggestions-content">
    @if (count($profiles) > 0)
        @php(app(\App\Services\UserStaffBadge::class)->preload($profiles))
        <div class="swiper mySwiper">
            <div class="swiper-wrapper">
                @foreach ($profiles->chunk(GenericHelper::isMobileDevice() ? 2 : $perPage) as $profilesSet)
                    <div class="swiper-slide px-1">
                        @foreach ($profilesSet as $k => $profile)
                            @include('elements.feed.suggestion-card', [
                                'profile' => $profile,
                                'isListMode' => false,
                                'isListManageable' => false,
                                'classes' => 'mb-2',
                                'cardRadius' => 'rounded'
                            ])
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>

        <div class="swiper-pagination-wrapper">
            <div class="swiper-pagination"></div>
        </div>
    @else
        <p class="text-center">{{ __('No suggestions available') }}</p>
    @endif
</div>
