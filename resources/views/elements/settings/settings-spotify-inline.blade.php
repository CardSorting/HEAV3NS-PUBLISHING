@if(!$spotifyAccount)
    <a class="btn btn-success w-100 mb-0" href="{{ route('my.settings.spotify.redirect') }}">
        <div class="d-flex align-items-center justify-content-center">
            @include('elements.icon',['icon'=>'spotify-white','centered'=>true,'classes'=>'me-2 text-primary', 'variant' => 'medium'])
            {{ __('Sign in with Spotify') }}
        </div>
    </a>
    <small class="text-muted d-block mt-2">
        {{ __('Connect to show top artists and pick an anthem.') }}
    </small>
@else
    <div class="d-flex align-items-center mb-3">
        <img src="{{ $spotifyAccount->avatar ?: asset('img/default-avatar.png') }}"
             class="rounded-circle me-2 spotify-card-avatar">
        <div class="flex-grow-1">
            <div class="fw-bold">{{ $spotifyAccount->display_name ?: $spotifyAccount->spotify_id }}</div>
            <div class="text-muted small">{{ '@'.$spotifyAccount->spotify_id }}</div>
        </div>

        <div class="h-pill h-pill-primary me-1 rounded" id="spotify-disconnect" data-bs-toggle="tooltip" data-bs-placement="top" title="{{__("Logout")}}">
            @include('elements.icon',['icon'=>'log-out-outline', 'variant' => 'medium'])
        </div>

    </div>

    <div class="mb-1">
        <div class="mb-2">{{ __('Spotify anthem') }}</div>

        <div class="input-group mb-3">
            <input type="text" class="form-control" id="spotify-track-q" placeholder="{{ __('Search a track...') }}">
            <button class="btn btn-primary mb-0" id="spotify-track-search" type="button">
                {{ __('Search') }}
            </button>
        </div>

        <div id="spotify-track-results" class="small"></div>

        <div class="mt-2" id="spotify-anthem-current" data-track-id="{{ $spotifyAccount->anthem_track_id }}">
            @if(!empty($spotifyAnthem))
                <div class="d-flex align-items-center border rounded p-2">
                    <img src="{{ $spotifyAnthem['image'] ?? '' }}" class="rounded me-2 spotify-card-avatar">
                    <div class="flex-grow-1">
                        <div class="fw-bold">{{ $spotifyAnthem['name'] ?? '' }}</div>
                        <div class="text-muted small">{{ $spotifyAnthem['artist'] ?? '' }}</div>
                    </div>
                    @if(!empty($spotifyAnthem['url']))
                        <a class="me-1" href="{{ $spotifyAnthem['url'] }}" target="_blank" rel="noopener" data-bs-toggle="tooltip" data-bs-placement="top" title="{{__("Open in Spotify")}}">
                            @include('elements.icon',['icon'=>'spotify','centered'=>true,'classes'=>'', 'variant' => 'medium'])
                        </a>
                    @endif
                </div>
            @else
                <div class="text-muted small">{{ __('No anthem selected yet.') }}</div>
            @endif
        </div>
    </div>

    <hr>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="">{{ __('Top Spotify Artists') }}</div>
        <div class="h-pill h-pill-primary me-1 rounded" id="spotify-refresh" data-bs-toggle="tooltip" data-bs-placement="top" title="{{__("Refresh")}}">
            @include('elements.icon',['icon'=>'refresh-outline', 'variant' => 'small'])
        </div>
    </div>

    <div class="d-flex flex-wrap" id="spotify-top-artists">
        @foreach(($spotifyAccount->top_artists ?? []) as $a)
            <div class="me-2 mb-2 text-center spotify-artist-wrapper">
                <img src="{{ $a['image'] ?? '' }}" class="rounded spotify-artist-tile" >
                <div class="small mt-1 text-truncate">{{ $a['name'] ?? '' }}</div>
            </div>
        @endforeach
    </div>
@endif

<div id="svg-store" class="d-none">
    <div data-icon="spotify">
        @include('elements.icon',['icon'=>'spotify','centered'=>true,'classes'=>'', 'variant' => 'medium'])
    </div>
</div>
