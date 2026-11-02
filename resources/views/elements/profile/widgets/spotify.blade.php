@if(!empty($spotifyAccount))
    @php($acc = $spotifyAccount)
    @php($artists = $acc->top_artists ?? [])

    <div class="card mt-3 rounded-2 profile-spotify-widget post-media-swiper">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="card-title mb-0 text-uppercase fs-point-85 fw-bold">{{ __('Spotify') }}</h5>
            </div>

            {{-- If nothing to show, don't render the rail --}}
            @if(!empty($spotifyAnthem) || (!empty($artists) && count($artists)))
                <div class="swiper profile-spotify-swiper">
                    <div class="swiper-wrapper">

                        {{-- Anthem --}}
                        @if(!empty($spotifyAnthem))
                            <div class="swiper-slide profile-spotify-slide profile-spotify-slide--anthem">
                                <a href="{{ $spotifyAnthem['url'] ?? '#' }}"
                                   target="_blank" rel="noopener"
                                   class="d-block text-reset">
                                    <div class="text-muted small mb-2">{{ __('My Anthem') }}</div>

                                    <img src="{{ $spotifyAnthem['image'] ?? '' }}" class="profile-spotify-cover rounded" alt="">

                                    <div class="mt-2 d-flex align-items-center">
                                        <div class="me-2">
                                            @include('elements.icon',['icon'=>'spotify','centered'=>true,'classes'=>'', 'variant' => 'medium'])
                                        </div>
                                        <div class="min-width-0">
                                            <div class="fw-bold text-truncate">{{ $spotifyAnthem['name'] ?? '' }}</div>
                                            <div class="text-muted small text-truncate">{{ $spotifyAnthem['artist'] ?? '' }}</div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endif

                        {{-- Top artists --}}
                        @foreach($artists as $a)
                            <div class="swiper-slide profile-spotify-slide profile-spotify-slide--artist">
                                <a href="{{ $a['url'] ?? '#' }}" target="_blank" rel="noopener" class="d-block text-reset">

                                    <div class="text-muted small mb-2">{{ __('My Top Spotify Artists') }}</div>

                                    <img src="{{ $a['image'] ?? '' }}"
                                         class="profile-spotify-cover rounded" alt="">

                                    <div class="mt-2 d-flex align-items-center">
                                        <div class="me-2">
                                            @include('elements.icon',['icon'=>'spotify','centered'=>true,'classes'=>'', 'variant' => 'medium'])
                                        </div>

                                        <div class="min-width-0">
                                            <div class="fw-bold text-truncate">{{ $a['name'] ?? '' }}</div>
                                            <div class="text-muted small text-truncate">{{ __('Top Artist') }}</div>
                                        </div>
                                    </div>

                                </a>
                            </div>
                        @endforeach

                    </div>

                    <div class="swiper-button swiper-button-next p-pill-white">@include('elements.icon',['icon'=>'chevron-forward-outline'])</div>
                    <div class="swiper-button swiper-button-prev p-pill-white">@include('elements.icon',['icon'=>'chevron-back-outline'])</div>

                </div>
            @else
                <div class="text-muted small">{{ __('Spotify data not available.') }}</div>
            @endif
        </div>
    </div>
@endif
