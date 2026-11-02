@extends('layouts.user-no-nav')
@section('page_title', __('Your feed'))

{{-- Page specific CSS --}}
@section('styles')
    {!! Minify::stylesheet(['/css/components/gif-media.css', '/css/components/gif-picker.css'])->withFullUrl() !!}
    {!!
    Minify::stylesheet(array_merge([
            '/libs/photoswipe/dist/photoswipe.css',
            '/css/pages/checkout.css',
            '/libs/photoswipe/dist/default-skin/default-skin.css',
            '/css/pages/feed.css',
            '/css/components/file-attachments.css',
            '/css/posts/post.css',
            '/css/pages/search.css',
            '/libs/@selectize/selectize/dist/css/selectize.bootstrap5.css'
         ],$additionalAssets['css']))->withFullUrl()
    !!}

    {{-- This one breakes when minified by our tools --}}
    <link rel="stylesheet" href="{{asset('/libs/swiper/swiper-bundle.min.css')}}">
    @if(getSetting('feed.post_box_max_height'))
        @include('elements.feed.fixed-height-feed-posts', ['height' => getSetting('feed.post_box_max_height')])
    @endif
@stop

{{-- Page specific JS --}}
@section('scripts')
    @include('elements.gif.assets', ['surfaces' => ['post_comments', 'reel_comments', 'messenger']])
    {!!
        Minify::javascript(array_merge([
            '/js/PostsPaginator.js',
            '/js/CommentsPaginator.js',
            '/js/Post.js',
            '/js/SuggestionsSlider.js',
            '/js/pages/lists.js',
            '/js/pages/feed.js',
            '/js/pages/feed-ads.js',
            '/js/pages/checkout.js',
            '/libs/swiper/swiper-bundle.min.js',
            '/js/plugins/media/photoswipe.js',
            '/libs/photoswipe/dist/photoswipe-ui-default.min.js',
            '/js/plugins/media/mediaswipe.js',
            '/js/plugins/media/mediaswipe-loader.js',
            '/libs/autolinker/dist/autolinker.min.js',
            '/js/TextareaHighlighter.js',
            '/js/MentionSuggestions.js',
            '/libs/@selectize/selectize/dist/js/selectize.min.js'
             ],$additionalAssets['js']))->withFullUrl()
    !!}
    @include('elements.feed.video-autoplay-assets')
@stop

@section('content')

    <div class="container" data-public-creation-access
         data-can-view-creation-page="{{ GenericHelper::canViewCreationPage() ? 'true' : 'false' }}"
         data-story-creation-visible="{{ GenericHelper::canViewCreationPage() || GenericHelper::canAccessCreatorOnboarding() ? 'true' : 'false' }}"
         data-onboarding-url="{{ GenericHelper::getCreatorOnboardingUrl() }}"
         data-onboarding-label="{{ GenericHelper::getCreatorOnboardingLabel() }}">
        <div class="row">
            <div class="col-12 col-sm-12 col-lg-8 col-md-7 second p-0">
                @include('elements.feed.mobile-brand-header')

                @if(getSetting('stories.stories_enabled'))
                    <!-- Stories row -->
                    <div class="px-3 py-2 mobile-search-content-offset neutral-bg">
                        <div id="stories-swiper" class="swiper-container stories-swiper">
                            {{-- Skeleton is visible by default; JS should remove/hide it once stories render --}}
                            @include('elements.preloading.stories-swiper-skeleton', ['limit' => 8])
                            <div class="swiper-wrapper">
                                {{-- JS will populate slides --}}
                            </div>
                        </div>
                    </div>
                @endif

                <div class="{{getSetting('stories.stories_enabled') ? '' : 'mobile-search-content-offset'}}">
                    @include('elements.message-alert',['classes'=>'pt-4 pb-4 px-2'])
                    @include('elements.feed.posts-load-more')
                    @if(!getSetting('feed.hide_suggestions_slider') && getSetting('feed.mobile_suggestions_enabled', true) && $suggestions->isNotEmpty())
                        <div id="feed-mobile-suggestions-slot">
                            @include('elements.feed.mobile-suggestions', ['profiles' => $suggestions])
                        </div>
                    @endif
                    <div class="feed-box mt-0 pt-4 posts-wrapper">
                        @include('elements.feed.posts-wrapper',[
                            'posts' => $posts,
                            'emptyStateView' => 'elements.feed.home-feed-empty-state',
                        ])
                    </div>
                    @include('elements.feed.ad', ['postOffset' => ($posts->currentPage() - 1) * $posts->perPage(), 'postsPerPage' => $posts->perPage()])
                    @include('elements.feed.posts-loading-spinner')
                </div>
            </div>
            <div class="col-12 col-sm-12 col-md-5 col-lg-4 first border-start order-0 px-3 pt-3 pb-5 min-vh-100 suggestions-wrapper d-none d-md-block">

                <div class="feed-widgets">
                    @if(!getSetting('feed.search_widget_hide'))
                        <div class="mb-3">
                            @include('elements.search-box')
                        </div>
                    @endif
                    @if(!getSetting('feed.hide_suggestions_slider'))
                        @include('elements.feed.suggestions-box',[
                             'id' => 'suggestions-box',
                             'profiles' => $suggestions,
                             'isMobile' => false,
                             'hideControls' => false,
                             'title' => __('Suggestions'),
                             'perPage' => (int)getSetting('feed.feed_suggestions_card_per_page'),
                        ])
                    @endif

                    @if(!getSetting('feed.expired_subs_widget_hide'))
                        @if($expiredSubscriptions->count())
                            <div class="mt-3">
                                @include('elements.feed.suggestions-box',[
                                    'id' => 'suggestions-box-expired',
                                    'profiles' => $expiredSubscriptions,
                                    'isMobile' => false,
                                    'hideControls' => true,
                                    'title' => __('Expired subscriptions'),
                                    'perPage' => (int)getSetting('feed.expired_subs_widget_card_per_page'),
                                ])
                            </div>
                        @endif
                    @endif

                    @if(getSetting('feed.enable_hashtags') && !getSetting('feed.popular_hashtags_widget_disable'))
                        @include('elements.feed.hashtags-box')
                    @endif

                    @if(getSetting('site.ads_sidebar_spot'))
                        <div class="mt-3">
                            {!! getSetting('site.ads_sidebar_spot') !!}
                        </div>
                    @endif

                    @include('template.footer-feed')

                </div>

            </div>
        </div>
        @include('elements.checkout.checkout-box')
    </div>

    <div class="d-none">
        <ion-icon name="heart"></ion-icon>
        <ion-icon name="heart-outline"></ion-icon>
    </div>

    @include('elements.standard-dialog',[
        'dialogName' => 'comment-delete-dialog',
        'title' => __('Delete comment'),
        'content' => __('Are you sure you want to delete this comment?'),
        'actionLabel' => __('Delete'),
        'actionFunction' => 'Post.deleteComment();',
    ])
    @if(getSetting('stories.stories_enabled'))
        @include('elements.stories.delete-dialog')
    @endif
    @php
        $feedReelsWidgetEnabled = getSetting('reels.feed_widget_enabled');
        $showFeedReelsWidget = getSetting('reels.reels_enabled') && $feedReelsWidgetEnabled !== false && (string) $feedReelsWidgetEnabled !== '0';
    @endphp
    @if($showFeedReelsWidget)
        <div id="feed-reels-player-host"
             class="feed-reels-player-host"
             data-external-host="1"
             data-allow-progress-scrubbing="{{ getSetting('reels.allow_progress_scrubbing') ? 1 : 0 }}"
             data-base-url="{{ route('feed') }}"
             data-permalink-template="{{ route('reels.get', ['reel_id' => '__REEL_ID__']) }}">
        </div>
        @include('elements.reels.icon-preload')
        @include('elements.reels.delete-dialogs')
    @endif
    @include('elements.messenger.send-user-message')

@stop
