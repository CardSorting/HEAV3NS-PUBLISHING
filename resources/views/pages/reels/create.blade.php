@extends('layouts.user-no-nav')
@section('page_title', __('New reel'))

@section('styles')
    {!!
        Minify::stylesheet([
            '/libs/dropzone/dist/dropzone.css',
            '/libs/@selectize/selectize/dist/css/selectize.css',
            '/libs/photoswipe/dist/photoswipe.css',
            '/libs/photoswipe/dist/default-skin/default-skin.css',
            '/css/components/sound-select.css',
            '/css/pages/reels.css',
            '/css/components/vault.css',
         ])->withFullUrl()
    !!}
@stop

@section('scripts')
    {!!
        Minify::javascript([
            '/libs/dropzone/dist/dropzone.js',
            '/libs/@selectize/selectize/dist/js/selectize.min.js',
            '/js/FileUpload.js',
            '/js/plugins/media/photoswipe.js',
            '/libs/photoswipe/dist/photoswipe-ui-default.min.js',
            '/js/media-vault.js',
            '/js/stories/sound-select.js',
            '/js/AISuggestions.js',
            '/js/reels/reels-creator.js',
        ])->withFullUrl()
    !!}
@stop

@section('content')
    <div class="d-flex flex-wrap reel-create-page">
        <div class="col-12 px-0">
            @include('elements.uploaded-file-preview-template')
            @include('elements.vault.picker')
            @include('elements.attachments-uploading-dialog')

            <div class="d-flex justify-content-between align-items-center pt-4 pb-3 px-3 border-bottom">
                <h5 class="text-truncate fw-bold text-dark-r mb-0">
                    {{ __('Create a reel') }}
                </h5>
                <a href="{{ route('reels.index') }}" class="btn btn-sm btn-outline-primary">{{ __('View reels') }}</a>
            </div>

            <div class="px-3 py-3">
                @include('elements.creator-access-notice')

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="reel-preview-wrapper card border-0">
                            <div class="card-body p-3 reel-preview-stage">
                                <div id="reel-preview" class="reel-canvas is-empty is-no-media">
                                    <div class="reel-preview-top-controls" aria-hidden="true">
                                        <span class="reel-preview-control">
                                            <ion-icon name="pause-outline"></ion-icon>
                                        </span>
                                        <span class="reel-preview-control">
                                            <ion-icon name="volume-mute-outline"></ion-icon>
                                        </span>
                                    </div>

                                    <div class="reel-legibility reel-legibility--top" aria-hidden="true"></div>
                                    <div class="reel-legibility reel-legibility--bottom" aria-hidden="true"></div>

                                    <video id="reel-preview-video" class="d-none" autoplay muted loop playsinline></video>
                                    <img id="reel-preview-cover" class="d-none" alt="">

                                    <div class="reel-preview-actions" aria-hidden="true">
                                        <span class="reel-preview-action">
                                            <ion-icon name="heart-outline"></ion-icon>
                                            <small>0</small>
                                        </span>
                                        <span class="reel-preview-action">
                                            <ion-icon name="chatbubble-outline"></ion-icon>
                                            <small>0</small>
                                        </span>
                                        <span class="reel-preview-action">
                                            <ion-icon name="bookmark-outline"></ion-icon>
                                            <small>0</small>
                                        </span>
                                        <span class="reel-preview-action">
                                            <ion-icon name="share-social-outline"></ion-icon>
                                        </span>
                                        <span class="reel-preview-action">
                                            <ion-icon name="eye-outline"></ion-icon>
                                            <small>0</small>
                                        </span>
                                    </div>

                                    <div class="reel-preview-overlay" aria-hidden="true">
                                        <div class="reel-preview-user-row">
                                            <span class="reel-preview-avatar">
                                                <img src="{{ Auth::user()->avatar }}" alt="">
                                            </span>
                                            <span class="reel-preview-username">{{ '@'.Auth::user()->username }}</span>
                                        </div>
                                        <div class="reel-preview-caption d-none"></div>
                                        <div class="reel-preview-sound d-none">
                                            <ion-icon name="musical-notes-outline"></ion-icon>
                                            <span></span>
                                        </div>
                                    </div>

                                    <div class="reel-preview-progress" aria-hidden="true">
                                        <span></span>
                                    </div>

                                    <div class="reel-preview-empty" aria-hidden="true">
                                        <div class="reel-preview-empty__title">{{ __('Your reel preview') }}</div>
                                        <div class="reel-preview-empty__sub">{{ __('Upload a vertical video to see it here.') }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent border-0 pt-0 text-center pt-1">
                                <small class="text-muted">{{ __('Preview of how your reel will appear.') }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-4">
                        <div class="reel-create-panel">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="mb-0">{{ __('Reel media') }}</label>
                                @if(GenericHelper::canPublishContent())
                                    @include('elements.vault.button', ['vaultTextOnly' => true, 'vaultButtonLabel' => 'Add from vault'])
                                @endif
                            </div>
                            <div class="dropzone-previews dropzone w-100 ppl-0 pe-0 pt-1 pb-1 border rounded reel-upload-zone file-upload-button {{!GenericHelper::canPublishContent() ? 'is-disabled disabled' : ''}}">
                                @if(!GenericHelper::canPublishContent())
                                    <div class="upload-zone-disabled-message">{{ __('Drop files here to upload') }}</div>
                                @endif
                            </div>
                            <p class="mb-0 mt-2">
                                <small class="form-text text-muted">
                                    {{ __('Upload one video and an optional cover image.') }}
                                </small>
                            </p>
                            <div id="reel-orientation-warning" class="reel-orientation-warning d-none mt-2">
                                <div class="reel-orientation-warning-icon">
                                    <ion-icon name="phone-portrait-outline"></ion-icon>
                                </div>
                                <div>
                                    <div class="fw-bold">{{ __('Vertical videos work best for Reels.') }}</div>
                                    <div class="small">{{ __('Landscape or square videos may be cropped in Explore and fullscreen playback.') }}</div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <label for="reel-caption" class="mb-0">{{ __('Caption') }}</label>

                                    @if(getSetting('ai.text_enabled'))
                                        <button type="button"
                                                class="form-action-link ai-suggest-link"
                                                data-ai-type="reel"
                                                data-ai-target="#reel-caption"
                                                data-loading-text="{{ __('Generating') }}">
                                            {{ __('AI Suggestion') }}
                                        </button>
                                    @endif
                                </div>
                                <textarea id="reel-caption"
                                          class="form-control"
                                          rows="4"
                                          maxlength="2200"
                                          placeholder="{{ __('Write a caption...') }}"></textarea>
                            </div>

                            @if(getSetting('reels.allow_sounds'))
                                @include('elements.stories.story-sound-selector', [
                                    'idPrefix' => 'reel',
                                    'label' => __('Sound'),
                                    'helpText' => __('Start typing to search. Select a sound to attach it to this reel.'),
                                    'videoUnavailableText' => null
                                ])
                            @endif

                            <div class="creator-form-footer d-flex flex-wrap justify-content-between align-items-center mt-4">
                                <div class="d-flex align-items-center">
                                    @if(getSetting('reels.allow_public_reels'))
                                        <div class="form-check form-switch">
                                            <input type="checkbox" class="form-check-input" id="reel-is-public" name="is_public">
                                            <label class="form-check-label" for="reel-is-public" tabindex="0"
                                                   data-bs-toggle="tooltip" data-bs-trigger="hover focus" data-tooltip-touch="true"
                                                   title="{{ __('On: anyone can view. Off: follower or subscription access is required.') }}">
                                                {{ __('Is public') }}
                                            </label>
                                        </div>
                                    @endif
                                </div>

                                <div class="creator-form-actions d-flex flex-wrap justify-content-end align-items-center">
                                    <button type="button" id="btn-reel-clear-draft" class="form-action-link">
                                        {{ __('Clear draft') }}
                                    </button>
                                    <button type="button"
                                            id="btn-reel-share"
                                            class="btn btn-primary creator-submit-button mb-0 {{!GenericHelper::canPublishContent() ? 'disabled' : ''}}"
                                            {{!GenericHelper::canPublishContent() ? 'disabled' : ''}}>
                                        {{ __('Publish reel') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
