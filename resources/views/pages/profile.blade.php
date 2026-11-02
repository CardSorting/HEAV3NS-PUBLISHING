@extends('layouts.user-no-nav')

@section('page_title',  __("user_profile_title_label",['user' => $user->name]))
@section('share_url', route('profile',['username'=> $user->username]))
@section('share_title',  __("user_profile_title_label",['user' => $user->name]) . ' - ' .  getSetting('site.name'))
@section('share_description', $seo_description ?? getSetting('site.description'))
@section('share_type', 'article')
@section('share_img', $user->cover)

@if(getSetting('security.captcha_driver') !== 'none' && !Auth::check())
    @section('meta')
        <x-captcha-js />
    @stop
@endif

@section('scripts')
    @include('elements.gif.assets', ['surfaces' => ['post_comments', 'reel_comments', 'messenger']])
    {!!
        Minify::javascript(array_merge([
            '/js/PostsPaginator.js',
            '/js/CommentsPaginator.js',
            '/js/StreamsPaginator.js',
            '/js/Post.js',
            '/js/pages/profile.js',
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
            '/js/LoginModal.js',
            '/libs/@selectize/selectize/dist/js/selectize.min.js'
         ],$additionalAssets['js']))->withFullUrl()
    !!}
    @include('elements.feed.video-autoplay-assets')
@stop

@section('styles')
    {!! Minify::stylesheet(['/css/components/gif-media.css', '/css/components/gif-picker.css'])->withFullUrl() !!}
    {!!
        Minify::stylesheet(array_merge([
            '/css/pages/checkout.css',
            '/css/pages/lists.css',
            '/libs/photoswipe/dist/photoswipe.css',
            '/libs/photoswipe/dist/default-skin/default-skin.css',
            '/css/pages/profile.css',
            '/css/pages/lists.css',
            '/css/posts/post.css',
            '/css/components/file-attachments.css',
            '/libs/@selectize/selectize/dist/css/selectize.bootstrap5.css',
         ],$additionalAssets['css']))->withFullUrl()
    !!}
    {{-- This one breakes when minified by our tools --}}
    <link rel="stylesheet" href="{{asset('/libs/swiper/swiper-bundle.min.css')}}">
    @if(getSetting('feed.post_box_max_height'))
        @include('elements.feed.fixed-height-feed-posts', ['height' => getSetting('feed.post_box_max_height')])
    @endif
@stop

@section('meta')
    @if(getSetting('security.recaptcha_enabled') && !Auth::check())
        {!! NoCaptcha::renderJs() !!}
    @endif
    @if($activeFilter)
        <link rel="canonical" href="{{route('profile',['username'=> $user->username])}}" />
    @endif
@stop

@section('content')
    <div class="d-flex flex-wrap">
        @if($activeFilter === 'reels')
            @include('elements.verified-svg-store')
        @endif

        <div class="min-vh-100 col-12 col-md-8 border-end pe-md-0 px-0">
            {{-- Profile (pre) header --}}
            @include('elements.profile.profile-header')
            <div class="container pt-2 ps-0 pe-0">
                {{-- Profile details/sub-header --}}
                @include('elements.profile.profile-details')
                <div class="bg-separator border-top border-bottom"></div>
                {{-- Profile subscription/follow buttons --}}
                @include('elements.profile.profile-subscription')
                {{-- Profile feed container --}}
                @include('elements.profile.profile-feed')
            </div>
        </div>
        <div class="col-12 col-md-4 d-none d-md-block px-3 pt-3">
            @include('elements.profile.widgets')
        </div>
    </div>
    {{-- Preloading some JS icons to avoid flashes --}}
    <div class="d-none">
        <ion-icon name="heart"></ion-icon>
        <ion-icon name="heart-outline"></ion-icon>
    </div>
    @if(Auth::check())
        @include('elements.lists.list-add-user-dialog',['user_id' => $user->id, 'lists' => ListsHelper::getUserLists()])
        @include('elements.checkout.checkout-box')
        @include('elements.messenger.send-user-message',['receiver'=>$user])
    @else
        @include('elements.modal-login')
    @endif
    @include('elements.profile.qr-code-dialog')
    @if(getSetting('stories.stories_enabled'))
        @include('elements.stories.delete-dialog')
    @endif
    @if($activeFilter === 'reels')
        @include('elements.report-user-or-post',['reportStatuses' => ListsHelper::getReportTypes()])
        @include('elements.reels.icon-preload')
        @include('elements.reels.delete-dialogs')
    @endif
@stop
