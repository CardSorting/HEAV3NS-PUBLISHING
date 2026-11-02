@extends('layouts.user-no-nav')

@section('page_title', __('Bookmarks'))

@section('styles')
    {!! Minify::stylesheet(['/css/components/gif-media.css', '/css/components/gif-picker.css'])->withFullUrl() !!}
    {!!
        Minify::stylesheet(array_merge([
            '/libs/photoswipe/dist/photoswipe.css',
            '/libs/photoswipe/dist/default-skin/default-skin.css',
            '/css/pages/bookmarks.css',
            '/css/components/account-sidebar.css',
            '/css/posts/post.css',
            '/css/components/file-attachments.css',
            '/css/pages/checkout.css',
            '/libs/@selectize/selectize/dist/css/selectize.bootstrap5.css'
         ], ($isReelsTab ?? false) ? [
            '/css/pages/reels.css',
         ] : []))->withFullUrl()
    !!}
    {{-- This one breakes when minified by our tools --}}
    <link rel="stylesheet" href="{{asset('/libs/swiper/swiper-bundle.min.css')}}">
    @if(getSetting('feed.post_box_max_height'))
        @include('elements.feed.fixed-height-feed-posts', ['height' => getSetting('feed.post_box_max_height')])
    @endif
@stop

@section('scripts')
    @include('elements.gif.assets', ['surfaces' => ['post_comments']])
    {!!
        Minify::javascript(($isReelsTab ?? false) ? [
            '/js/pages/lists.js',
            '/js/reels/reels-api.js',
            '/js/reels/reels-renderer.js',
            '/js/reels/reels-comments.js',
            '/js/reels/reels-player.js',
        ] : [
            '/js/pages/checkout.js',
            '/js/PostsPaginator.js',
            '/js/CommentsPaginator.js',
            '/js/Post.js',
            '/js/pages/lists.js',
            '/js/pages/bookmarks.js',
            '/libs/swiper/swiper-bundle.min.js',
            '/js/plugins/media/photoswipe.js',
            '/libs/photoswipe/dist/photoswipe-ui-default.min.js',
            '/js/plugins/media/mediaswipe.js',
            '/js/plugins/media/mediaswipe-loader.js',
            '/libs/autolinker/dist/autolinker.min.js',
            '/js/TextareaHighlighter.js',
            '/js/MentionSuggestions.js',
            '/libs/@selectize/selectize/dist/js/selectize.min.js'
         ])->withFullUrl()
    !!}
    @unless($isReelsTab ?? false)
        @include('elements.feed.video-autoplay-assets')
    @endunless
@stop

@section('content')
    <div class="d-flex flex-wrap">
        <div class="col-12 col-md-6 col-lg-3 mb-3 settings-menu pe-0 px-0">
            <div class="bookmarks-menu-wrapper">
                <div class="mt-3 ms-3">
                    <h5 class="fw-bold text-dark-r">{{__('Bookmarks')}}</h5>
                </div>
                <hr class="mb-0">
                <div class="d-lg-block bookmarks-nav">
                    <div class="d-none d-md-block">
                        @include('elements.bookmarks.bookmarks-menu',['variant' => 'desktop'])
                    </div>
                    <div class="bookmarks-menu-mobile d-block d-md-none mt-3">
                        @include('elements.bookmarks.bookmarks-menu',['variant' => 'mobile'])
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-9 mb-5 mb-lg-0 min-vh-100 border-start border-end settings-content ps-md-0 pe-md-0 px-0">
            <div class="px-2 px-md-3">
                @if(isset($filterType))
                    {{$filterType}}
                @endif
            </div>
            @if($isReelsTab ?? false)
                @include('elements.verified-svg-store')

                <div class="reels-topbar border-bottom">
                    <h5 class="mb-0 fw-bold text-dark-r">
                        {{ __('Bookmarked') }} {{ __('Reels') }}
                    </h5>
                    <a href="{{ route('reels.index') }}" class="btn btn-sm btn-outline-primary mb-0">{{ __('Explore') }}</a>
                </div>
                <template id="bookmarked-reels-empty-state-template">
                    @include('elements.bookmarks.bookmarks-empty-state', $bookmarkEmptyState)
                </template>
                <div id="reels-feed"
                     class="reels-feed profile-reels-feed"
                     data-feed-url="{{ route('my.bookmarks', ['type' => 'reels']) }}"
                     data-base-url="{{ route('my.bookmarks', ['type' => 'reels']) }}"
                     data-empty-state-template="#bookmarked-reels-empty-state-template"
                     data-allow-progress-scrubbing="{{ getSetting('reels.allow_progress_scrubbing') ? 1 : 0 }}"
                     data-permalink-template="{{ route('reels.get', ['reel_id' => '__REEL_ID__']) }}">
                    <div class="reels-empty-state">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                </div>
            @else
                @include('elements.feed.posts-load-more')
                <div class="feed-box mt-0  pt-4 posts-wrapper">
                    @include('elements.feed.posts-wrapper',[
                        'posts' => $posts,
                        'emptyStateView' => 'elements.bookmarks.bookmarks-empty-state',
                        'emptyStateData' => $bookmarkEmptyState,
                    ])
                </div>
                @include('elements.feed.posts-loading-spinner')
            @endif
        </div>
    </div>

    <div class="d-none">
        <ion-icon name="heart"></ion-icon>
        <ion-icon name="heart-outline"></ion-icon>
    </div>

    @include('elements.checkout.checkout-box')

    @if($isReelsTab ?? false)
        @include('elements.report-user-or-post',['reportStatuses' => ListsHelper::getReportTypes()])
        @include('elements.reels.icon-preload')
        @include('elements.reels.delete-dialogs')
    @endif

    @unless($isReelsTab ?? false)
        @include('elements.standard-dialog',[
            'dialogName' => 'comment-delete-dialog',
            'title' => __('Delete comment'),
            'content' => __('Are you sure you want to delete this comment?'),
            'actionLabel' => __('Delete'),
            'actionFunction' => 'Post.deleteComment();',
        ])
    @endunless

@stop
