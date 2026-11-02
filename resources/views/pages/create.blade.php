@extends('layouts.user-no-nav')
@section('page_title', __('New post'))

@section('styles')
    {!!
        Minify::stylesheet([
            '/css/posts/post.css',
            '/css/components/file-attachments.css',
            '/libs/photoswipe/dist/photoswipe.css',
            '/libs/photoswipe/dist/default-skin/default-skin.css',
            '/css/components/vault.css',
            '/libs/dropzone/dist/dropzone.css',
            '/css/posts/composer.css',
         ])->withFullUrl()
    !!}
@stop

@section('scripts')
    {!!
        Minify::javascript([
            '/js/Post.js',
            '/js/posts/create-helper.js',
            '/js/AISuggestions.js',
            '/js/MentionSuggestions.js',
            '/js/TextareaHighlighter.js',
            (Route::currentRouteName() =='posts.create' ? '/js/posts/create.js' : '/js/posts/edit.js'),
            '/libs/dropzone/dist/dropzone.js',
            '/js/FileUpload.js',
            '/js/plugins/media/photoswipe.js',
            '/libs/photoswipe/dist/photoswipe-ui-default.min.js',
            '/js/media-vault.js',
            '/js/posts/youtube-embed.js',
            '/libs/sortablejs/Sortable.min.js',
            '/js/posts/composer.js',
         ])->withFullUrl()
    !!}
@stop

@section('content')
    <div class="d-flex flex-wrap">
        <div class="col-12 px-0">
            @include('elements.uploaded-file-preview-template', ['reorderable' => true])
            @include('elements.vault.picker')
            @include('elements.post-price-setup',['postPrice'=>(isset($post) ? $post->price : 0)])
            @include('elements.post-poll-setup',['postPrice'=>(isset($post) ? $post->price : 0)])
            @include('elements.attachments-uploading-dialog')
            @include('elements.messenger.locked-message-no-attachments-dialog',['type' => trans_choice('posts',2,['number' => ''])])
            @include('elements.post-schedule-setup', isset($post) ? ['release_date' => $post->release_date,'expire_date' => $post->expire_date, 'create_date' => $post->created_at] : [])
            <div class="d-flex justify-content-between pt-4 pb-3 px-3 border-bottom">
                <h5 class="text-truncate fw-bold text-dark-r">{{Route::currentRouteName() == 'posts.create' ? __('New post') : __('Edit post')}}</h5>
            </div>
            @if(!PostsHelper::getDefaultPostStatus(Auth::user()->id, $post ?? null))
                <div class="ps-3 pe-3 pt-3">
                    @include('elements.pending-posts-warning-box')
                </div>
            @endif
            <div class="ps-3 pe-3 pt-2">
                @unless(GenericHelper::canComposePost($post ?? null))
                    @include('elements.creator-access-notice')
                @endunless
                @include('elements.posts.composer', [
                    'canCompose' => GenericHelper::canComposePost($post ?? null),
                    'canUploadMedia' => GenericHelper::canPublishContent(),
                    'isEditing' => isset($post),
                ])
            </div>

        </div>
    </div>

@stop
