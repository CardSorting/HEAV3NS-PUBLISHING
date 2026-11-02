@if(getSetting('feed.autoplay_videos', false))
    {!! Minify::javascript(['/js/posts/feed-video-playback.js'])->withFullUrl() !!}
@endif
