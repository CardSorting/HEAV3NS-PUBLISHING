@extends('layouts.user-no-nav')

@section('page_title', __('Discover'))
@section('share_url', route('home'))
@section('share_title', getSetting('site.name') . ' - ' . getSetting('site.slogan'))
@section('share_description', getSetting('site.description'))
@section('share_type', 'article')
@section('share_img', GenericHelper::getOGMetaImage())

@section('meta')
    <meta name="robots" content="noindex">
@stop

@section('scripts')
    @include('elements.gif.assets', ['surfaces' => ['post_comments']])
    {!!
        Minify::javascript([
            '/js/PostsPaginator.js',
            '/js/UsersPaginator.js',
            '/js/StreamsPaginator.js',
            '/js/CommentsPaginator.js',
            '/js/Post.js',
            '/js/SuggestionsSlider.js',
            '/js/pages/lists.js',
            '/js/pages/checkout.js',
            '/libs/swiper/swiper-bundle.min.js',
            '/js/plugins/media/photoswipe.js',
            '/libs/photoswipe/dist/photoswipe-ui-default.min.js',
            '/js/plugins/media/mediaswipe.js',
            '/js/plugins/media/mediaswipe-loader.js',
            '/libs/autolinker/dist/autolinker.min.js',
            '/js/TextareaHighlighter.js',
            '/js/MentionSuggestions.js',
            '/js/pages/search.js',
            '/libs/@selectize/selectize/dist/js/selectize.min.js'
         ])->withFullUrl()
    !!}
    @include('elements.feed.video-autoplay-assets')
@stop

@section('styles')
    {!! Minify::stylesheet(['/css/components/gif-media.css', '/css/components/gif-picker.css'])->withFullUrl() !!}
    {!!
        Minify::stylesheet([
            '/libs/photoswipe/dist/photoswipe.css',
            '/css/pages/checkout.css',
            '/libs/photoswipe/dist/default-skin/default-skin.css',
            '/css/pages/feed.css',
            '/css/posts/post.css',
            '/css/components/file-attachments.css',
            '/css/pages/search.css',
            '/libs/@selectize/selectize/dist/css/selectize.bootstrap5.css',
         ])->withFullUrl()
    !!}
    {{-- This one breakes when minified by our tools --}}
    <link rel="stylesheet" href="{{asset('/libs/swiper/swiper-bundle.min.css')}}">
    @if(getSetting('feed.post_box_max_height'))
        @include('elements.feed.fixed-height-feed-posts', ['height' => getSetting('feed.post_box_max_height')])
    @endif
@stop

@section('content')
    <div class="d-flex flex-wrap">
        <div class="col-12 col-sm-12 col-lg-8 col-md-7 second p-0 px-0 search-results-column">
            <div class="d-flex neutral-bg fixed-top-m px-3 py-2 py-md-3 feed-mobile-search align-items-center">
                    <span class="h-pill h-pill-primary rounded search-back-button d-flex justify-content-center align-items-center flex-shrink-0" onClick="Search.goBack()">
                        @include('elements.icon',['icon'=>'arrow-back-outline','variant'=>'small','centered'=>true])
                    </span>
                <div class="col ps-2">
                    @include('elements.search-box', ['formClasses' => 'd-md-none', 'inputClasses' => ' mobile-search-input'])
                    @include('elements.search-box', ['formClasses' => 'd-none d-md-block'])
                </div>
                @if($activeFilter == 'people')
                    <span class="h-pill h-pill-primary rounded search-back-button d-flex justify-content-center align-items-center flex-shrink-0 ms-2" data-bs-toggle="collapse" href="#colappsableFilters" role="button" aria-expanded="false" aria-controls="colappsableFilters">
                             @include('elements.icon',['icon'=>'options-outline','variant'=>'medium','centered'=>true])
                        </span>
                @endif
            </div>
            <div class="py-2 mobile-search-content-offset">
                @if($activeFilter == 'people')
                    <div class="mobile-search-filter collapse {{$searchFilterExpanded ? 'show' : ''}}"  id="colappsableFilters">
                        @include('elements.search.search-filters')
                    </div>
                @endif
                <div class="inline-border-tabs mt-3">
                    <nav class="nav nav-pills nav-justified bookmarks-nav">
                        @foreach($availableFilters as $filter)
                            <a class="nav-item nav-link {{$filter == $activeFilter ? 'active' : ''}}" href="{{route('search.get',array_merge(['query'=>isset($searchTerm) && $searchTerm ? $searchTerm : ''],['filter'=>$filter]))}}">
                                <div class="d-flex justify-content-center fw-bold">
                                        <span class="d-md-none">
                                        @switch($filter)
                                                @case('live')
                                                    @include('elements.icon',['icon'=>'play-outline','centered' => false,'variant'=>'medium'])
                                                    @break
                                                @case('top')
                                                    @include('elements.icon',['icon'=>'flame-outline','centered' => false,'variant'=>'medium'])
                                                    @break
                                                @case('latest')
                                                    @include('elements.icon',['icon'=>'time-outline','centered' => false,'variant'=>'medium'])
                                                    @break
                                                @case('people')
                                                    @include('elements.icon',['icon'=>'people-outline','centered' => false,'variant'=>'medium'])
                                                    @break
                                                @case('photos')
                                                    @include('elements.icon',['icon'=>'image-outline','centered' => false,'variant'=>'medium'])
                                                    @break
                                                @case('videos')
                                                    @include('elements.icon',['icon'=>'videocam-outline','centered' => false,'variant'=>'medium'])
                                                    @break
                                            @endswitch
                                            </span>
                                    @if($filter == 'live') <div class="blob red d-none d-md-block"></div> @endif
                                    <span class="d-none d-md-block ms-2">{{ucfirst(trim( (in_array($filter,['videos','people']) ? trans_choice($filter,2,['number'=>'']) : __(ucfirst($filter))) )) }}</span>
                                </div>
                            </a>
                        @endforeach
                    </nav>
                </div>
            </div>

            @include('elements.message-alert',['classes'=>'p-2'])

            @if(isset($posts))
                @include('elements.feed.posts-load-more')
                <div class="feed-box mt-0 pt-2 posts-wrapper">
                    @include('elements.feed.posts-wrapper',[
                        'posts' => $posts,
                        'emptyStateView' => 'elements.search.search-empty-state',
                        'emptyStateData' => $searchEmptyState,
                    ])
                </div>
                @include('elements.feed.posts-loading-spinner')
            @endif

            @if(isset($users))
                <div class="users-box mt-4 users-wrapper">
                    @include('elements.search.users-wrapper',[
                        'users' => $users,
                        'emptyStateView' => 'elements.search.search-empty-state',
                        'emptyStateData' => $searchEmptyState,
                    ])
                </div>
                @include('elements.feed.posts-loading-spinner')
            @endif

            @if(isset($streams))
                <div class="streams-box mt-4 streams-wrapper">
                    @include('elements.search.streams-wrapper',[
                        'streams' => $streams,
                        'emptyStateView' => 'elements.search.search-empty-state',
                        'emptyStateData' => $searchEmptyState,
                    ])
                </div>
                @include('elements.feed.posts-loading-spinner')
            @endif

        </div>
        <div class="col-12 col-sm-12 col-md-5 col-lg-4 first border-start order-0 px-3 pt-3 pb-5 min-vh-100 suggestions-wrapper d-none d-md-block">
            <div class="search-widgets">
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
                @if(!getSetting('feed.expired_subs_widget_hide') && Auth::check())
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
                @if(getSetting('site.ads_sidebar_spot'))
                    <div class="mt-3">
                        {!! getSetting('site.ads_sidebar_spot') !!}
                    </div>
                @endif

                @if(getSetting('feed.enable_hashtags') && !getSetting('feed.popular_hashtags_widget_disable'))
                    @include('elements.feed.hashtags-box')
                @endif

                @include('template.footer-feed')
            </div>
        </div>
    </div>
    @include('elements.checkout.checkout-box')
@stop
