@if(getSetting('site.ads_feed_enabled', false) && trim((string) getSetting('site.ads_feed_code', '')) !== '')
    <template id="feed-ad-template"
              data-frequency="{{ max(1, (int) getSetting('site.ads_feed_frequency', 6)) }}"
              data-visibility="{{ getSetting('site.ads_feed_visibility', 'mobile') === 'all' ? 'all' : 'mobile' }}"
              data-per-page="{{ $postsPerPage ?? 0 }}"
              data-post-offset="{{ $postOffset }}">
        <section class="feed-ad border-bottom px-3 py-3 text-center" aria-label="{{ __('Advertisement') }}">
            <div class="small text-muted text-start mb-2">{{ __('Advertisement') }}</div>
            <div class="feed-ad-content overflow-hidden"></div>
        </section>
    </template>
    <script id="feed-ad-code" type="application/json">@json(getSetting('site.ads_feed_code'))</script>
@endif
