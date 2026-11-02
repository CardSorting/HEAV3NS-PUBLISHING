@extends('layouts.user-no-nav')

@section('page_title', __(":username post",['username'=>$post->user->name]))

@section('styles')
    {!! Minify::stylesheet(['/css/components/gif-media.css', '/css/components/gif-picker.css'])->withFullUrl() !!}
    {!!
        Minify::stylesheet([
            '/css/posts/post.css',
            '/css/components/file-attachments.css',
            '/libs/photoswipe/dist/photoswipe.css',
            '/css/pages/checkout.css',
            '/libs/photoswipe/dist/default-skin/default-skin.css',
            '/libs/@selectize/selectize/dist/css/selectize.bootstrap5.css'
         ])->withFullUrl()
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
        Minify::javascript([
            '/libs/swiper/swiper-bundle.min.js',
            '/js/PostsPaginator.js',
            '/js/CommentsPaginator.js',
            '/js/Post.js',
            '/js/pages/lists.js',
            '/js/pages/checkout.js',
            '/js/plugins/media/photoswipe.js',
            '/libs/photoswipe/dist/photoswipe-ui-default.min.js',
            '/js/plugins/media/mediaswipe.js',
            '/js/plugins/media/mediaswipe-loader.js',
            '/libs/autolinker/dist/autolinker.min.js',
            '/js/TextareaHighlighter.js',
            '/js/MentionSuggestions.js',
            '/js/posts/view.js',
            '/libs/@selectize/selectize/dist/js/selectize.min.js'
         ])->withFullUrl()
    !!}
@stop

@section('content')
    <div class="d-flex flex-wrap">
        <div class="min-vh-100 col-12 col-md-8 border-end  pe-md-0 px-0">
            <div class="feed-box mt-0 pt-4 mb-3 posts-wrapper">
                @include('elements.message-alert',['classes'=>'pt-0 pb-4 px-2'])
                @include('elements.feed.post-box')
            </div>
        </div>
        <div class="col-12 col-md-4 d-none d-md-block px-3 pt-3">
            @include('elements.profile.widgets')
        </div>
    </div>
    @include('elements.report-user-or-post',['reportStatuses' => ListsHelper::getReportTypes()])
    @include('elements.feed.post-delete-dialog')
    @include('elements.feed.post-list-management')
    @include('elements.photoswipe-container')
    @include('elements.checkout.checkout-box')

    @include('elements.standard-dialog',[
        'dialogName' => 'comment-delete-dialog',
        'title' => __('Delete comment'),
        'content' => __('Are you sure you want to delete this comment?'),
        'actionLabel' => __('Delete'),
        'actionFunction' => 'Post.deleteComment();',
    ])

@stop
