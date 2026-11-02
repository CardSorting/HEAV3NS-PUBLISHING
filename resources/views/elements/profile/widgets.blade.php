<div class="profile-widgets-area pb-3">

    @include('elements.profile.widgets.latest-media')
    @include('elements.profile.widgets.subscribe')

    @if(getSetting('site.ads_sidebar_spot'))
        <div class="mt-3">
            {!! getSetting('site.ads_sidebar_spot') !!}
        </div>
    @endif

    @if(getSetting('profiles.spotify_enabled'))
        @if(!empty($spotifyAccount))
            @include('elements.profile.widgets.spotify', [
                'spotifyAccount' => $spotifyAccount,
                'spotifyAnthem' => $spotifyAnthem,
            ])
        @endif
    @endif

    @include('template.footer-feed')

</div>
