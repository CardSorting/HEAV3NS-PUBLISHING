@extends('layouts.user-no-nav')
@section('page_title', __('Media Vault'))
@section('styles')
    {!! Minify::stylesheet(['/css/posts/post.css', '/css/components/file-attachments.css', '/libs/photoswipe/dist/photoswipe.css', '/libs/photoswipe/dist/default-skin/default-skin.css', '/css/components/vault.css'])->withFullUrl() !!}
@stop
@section('scripts')
    {!! Minify::javascript(['/libs/dropzone/dist/dropzone.js', '/js/FileUpload.js', '/js/vault-upload.js', '/js/plugins/media/photoswipe.js', '/libs/photoswipe/dist/photoswipe-ui-default.min.js', '/js/media-vault.js'])->withFullUrl() !!}
@stop
@section('content')
    <div id="media-vault-page">
        <header class="vault-page-header border-bottom p-3 p-md-4">
            <div class="vault-heading-row">
                <h5 class="fw-bold mb-0">{{ __('Media Vault') }}</h5>
                <button type="button" class="btn btn-primary btn-sm file-upload-button vault-upload-button mb-0">
                    @include('elements.icon', ['icon' => 'add-outline', 'variant' => 'medium'])
                    <span>{{ __('Upload media') }}</span>
                </button>
            </div>
        </header>
        <div class="p-3 px-md-4 pb-md-4 vault-page-body">
            <div class="vault-upload-zone">@include('elements.vault.upload-queue')</div>
            <div data-vault-browser data-vault-mode="library">
                @include('elements.vault.filters', ['archiveFilter' => true])
                <div class="vault-message text-muted d-none" role="status" aria-live="polite"></div>
                <button type="button" class="btn btn-sm btn-outline-primary vault-reload d-none">{{ __('Retry') }}</button>
                @include('elements.vault.empty-state')
                <div class="vault-grid"></div>
                @include('elements.vault.pagination')
            </div>
        </div>
    </div>
    @include('elements.vault.usage-dialog')
    @include('elements.vault.action-dialogs')
    @include('elements.photoswipe-container')
@stop
