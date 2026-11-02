<a href="https://www.youtube-nocookie.com/embed/{{ $videoId }}"
   rel="mswp" data-media-type="youtube" data-youtube-id="{{ $videoId }}"
   data-width="640" data-height="360"
   class="post-youtube-preview no-long-press w-100 d-flex align-items-center justify-content-center text-decoration-none"
   aria-label="{{ __('Open YouTube video') }}">
    <span class="d-flex flex-column align-items-center gap-2 p-4 text-center">
        <span class="post-youtube-play d-inline-flex align-items-center justify-content-center rounded-3 bg-danger text-white p-2" aria-hidden="true">
            @include('elements.icon', ['icon' => 'play', 'variant' => 'large'])
        </span>
        <span class="fw-bold text-white">{{ __('YouTube video') }}</span>
        <span class="small text-white-50">{{ __('Open to watch') }}</span>
    </span>
</a>
