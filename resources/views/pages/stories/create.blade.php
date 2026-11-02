@extends('layouts.user-no-nav')
@section('page_title', __('New story'))

@section('styles')
    {!!
        Minify::stylesheet([
            '/libs/dropzone/dist/dropzone.css',
            '/libs/@selectize/selectize/dist/css/selectize.css',
            '/libs/photoswipe/dist/photoswipe.css',
            '/libs/photoswipe/dist/default-skin/default-skin.css',
            '/css/components/sound-select.css',
            '/css/pages/stories-create.css',
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
            '/js/stories/stories-creator.js',
            '/js/AISuggestions.js',
         ])->withFullUrl()
    !!}
@stop

@section('content')
    <div class="d-flex flex-wrap">
        <div class="col-12 px-0">
            @include('elements.uploaded-file-preview-template')
            @include('elements.vault.picker')
            @include('elements.attachments-uploading-dialog')

            <div class="d-flex justify-content-between pt-4 pb-3 px-3 border-bottom">
                <h5 class="text-truncate fw-bold text-dark-r">
                    {{ __("Create a story") }}
                </h5>
            </div>

            <div class="ps-3 pe-3 pt-3 pb-4">
                @include('elements.creator-access-notice')

                <div class="row">
                    {{-- LEFT: live preview --}}
                    <div class="col-md-6 mb-4">
                        <div class="story-preview-wrapper card border-0">
                            <div class="card-body p-3 story-preview-stage">

                                <div id="story-preview" class="story-canvas is-empty">

                                    {{-- top chrome --}}
                                    <div class="story-chrome story-chrome--top">
                                        <div class="story-progress">
                                            <span class="story-progress__seg is-active"></span>
                                            <span class="story-progress__seg"></span>
                                            <span class="story-progress__seg"></span>
                                        </div>

                                        <div class="story-header">
                                            <div class="story-user">
                                                <div class="story-avatar" aria-hidden="true"></div>
                                                <div class="story-user-meta">
                                                    <div class="story-user-name">{{ __("You") }}</div>
                                                    <div class="story-user-time">{{ __("now") }}</div>
                                                </div>
                                            </div>

                                            <div class="story-header-actions" aria-hidden="true">
                                                <button type="button" class="story-preview-expand" tabindex="-1" aria-hidden="true">
                                                    <ion-icon name="ellipsis-horizontal-outline"></ion-icon>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- legibility overlays --}}
                                    <div class="story-legibility story-legibility--top" aria-hidden="true"></div>
                                    <div class="story-legibility story-legibility--bottom" aria-hidden="true"></div>

                                    {{-- overlay text (used in both modes) --}}
                                    <div id="story-preview-text" class="story-text-layer story-text-draggable"></div>

                                    {{-- bottom chrome --}}
                                    <div class="story-chrome story-chrome--bottom" aria-hidden="true">
                                        <div class="story-reply">
                                            <div class="story-reply-pill">{{ __("Send message…") }}</div>
                                            <div class="story-reply-btn">➤</div>
                                        </div>
                                    </div>

                                    {{-- empty hint (nice before upload) --}}
                                    <div class="story-empty-hint" aria-hidden="true">
                                        <div class="story-empty-hint__title">{{ __("Your story preview") }}</div>
                                        <div class="story-empty-hint__sub">{{ __("Upload media or write text to see it here.") }}</div>
                                    </div>

                                </div>

                            </div>

                            <div class="card-footer bg-transparent border-0 pt-0 text-center pt-1">
                                <small class="text-muted">{{ __("Preview of how your story will appear.") }}</small>
                            </div>
                        </div>
                    </div>

                    {{-- RIGHT: controls --}}
                    <div class="col-md-6 mb-4">
                        <div class="story-panel card border">
                            <div class="card-body">

                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0 text-muted text-uppercase story-panel-label">
                                        {{ __("Story type") }}
                                    </h6>
                                </div>

                                <ul class="nav nav-pills story-tabs mb-3" id="story-type-tabs">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-bs-toggle="pill" href="#story-tab-media">
                                            {{ __("Photo / Video") }}
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="pill" href="#story-tab-text">
                                            {{ __("Text story") }}
                                        </a>
                                    </li>
                                </ul>

                                <div class="tab-content">
                                    {{-- MEDIA STORY TAB --}}
                                    <div class="tab-pane fade show active" id="story-tab-media">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <label class="mb-0">{{ __("Story media") }}</label>
                                            @if(GenericHelper::canPublishContent())
                                                @include('elements.vault.button', ['vaultTextOnly' => true, 'vaultButtonLabel' => 'Add from vault'])
                                            @endif
                                        </div>

                                        <div class="dropzone-previews dropzone w-100 ppl-0 pe-0 pt-1 pb-1 border rounded story-upload-zone file-upload-button {{!GenericHelper::canPublishContent() ? 'is-disabled disabled' : ''}}">
                                            @if(!GenericHelper::canPublishContent())
                                                <div class="upload-zone-disabled-message">{{ __('Drop files here to upload') }}</div>
                                            @endif
                                        </div>

                                        <div class="mt-1">
                                            <p class="mb-2"><small class="form-text text-muted">{{ __("Max size") }}: 4 {{ __("MB") }}.</small></p>
                                        </div>

                                        <div class="mt-3">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <label for="story-text-overlay" class="mb-0">{{ __("Story text") }}</label>

                                                @if(getSetting('ai.text_enabled'))
                                                    <button type="button"
                                                            class="form-action-link ai-suggest-link"
                                                            data-ai-type="story"
                                                            data-ai-target="#story-text-overlay"
                                                            data-loading-text="{{ __('Generating') }}">
                                                        {{ __('AI Suggestion') }}
                                                    </button>
                                                @endif
                                            </div>
                                            <textarea id="story-text-overlay"
                                                      class="form-control"
                                                      rows="4"
                                                      placeholder="{{ __('Add a short message...') }}"
                                                      maxlength="{{ (int) getSetting('stories.max_text_length') ?: 2000 }}"
                                            ></textarea>
                                            <div class="mt-1">
                                                <p class="mb-2"><small class="form-text text-muted">{{__("Drag to reposition the text")}}.</small></p>
                                            </div>
                                        </div>

                                        @if(getSetting('stories.allow_cta_links'))
                                            <div class="mt-3">
                                                <label class="mb-1">{{ __("Story link") }}</label>

                                                <input type="url"
                                                       id="media-story-link-url"
                                                       class="form-control mb-2"
                                                       placeholder="https://example.com">

                                                <input type="text"
                                                       id="media-story-link-text"
                                                       class="form-control"
                                                       maxlength="80"
                                                       placeholder="{{ __('Link label (e.g. Learn more)') }}">

                                            </div>
                                        @endif

                                        @if(getSetting('stories.allow_sounds'))
                                            @include('elements.stories.story-sound-selector', [
                                                'idPrefix' => 'media',
                                                'label' => __('Sound')
                                            ])
                                        @endif

                                    </div>

                                    {{-- TEXT-ONLY STORY TAB --}}
                                    <div class="tab-pane fade" id="story-tab-text">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <label for="story-text-only" class="mb-0">{{ __("Story text") }}</label>
                                            @if(getSetting('ai.text_enabled'))
                                                <button type="button"
                                                        class="form-action-link ai-suggest-link"
                                                        data-ai-target="#story-text-only"
                                                        data-ai-type="story"
                                                        data-loading-text="{{ __('Generating') }}">
                                                    {{ __('AI Suggestion') }}
                                                </button>
                                            @endif
                                        </div>

                                        <textarea id="story-text-only"
                                                  class="form-control"
                                                  rows="2"
                                                  placeholder="{{ __('Add a short message...') }}"
                                                  maxlength="{{ (int) getSetting('stories.max_text_length') ?: 2000 }}"
                                        ></textarea>
                                        <div class="mt-1">
                                            <p class="mb-2"><small class="form-text text-muted">{{__("Drag to reposition the text")}}.</small></p>
                                        </div>
                                        <div class="mt-3">
                                            <div class="mb-2">{{ __("Background color") }}</div>
                                            <div class="d-flex flex-wrap" id="story-bg-picker">


                                                <button type="button"
                                                        class="btn btn-sm rounded-circle me-2 mb-2 story-bg-choice story-bg-choice--default"
                                                        data-preset="grad_default"></button>

                                                <button type="button"
                                                        class="btn btn-sm rounded-circle me-2 mb-2 story-bg-choice story-bg-choice--black"
                                                        data-preset="solid_black"
                                                        data-color="#000000"></button>

                                                <button type="button"
                                                        class="btn btn-sm rounded-circle me-2 mb-2 story-bg-choice story-bg-choice--orange"
                                                        data-preset="grad_orange"
                                                        data-color="#ff5722"></button>

                                                <button type="button"
                                                        class="btn btn-sm rounded-circle me-2 mb-2 story-bg-choice story-bg-choice--pink"
                                                        data-preset="grad_pink"
                                                        data-color="#ff2d8d"></button>

                                                <button type="button"
                                                        class="btn btn-sm rounded-circle me-2 mb-2 story-bg-choice story-bg-choice--red"
                                                        data-preset="grad_red"
                                                        data-color="#e53935"></button>

                                                <button type="button"
                                                        class="btn btn-sm rounded-circle me-2 mb-2 story-bg-choice story-bg-choice--purple"
                                                        data-preset="grad_purple"
                                                        data-color="#9b3dff"></button>

                                                <button type="button"
                                                        class="btn btn-sm rounded-circle me-2 mb-2 story-bg-choice story-bg-choice--indigo"
                                                        data-preset="grad_indigo"
                                                        data-color="#3f51b5"></button>

                                                <button type="button"
                                                        class="btn btn-sm rounded-circle me-2 mb-2 story-bg-choice story-bg-choice--blue"
                                                        data-preset="grad_blue"
                                                        data-color="#2196f3"></button>

                                                <button type="button"
                                                        class="btn btn-sm rounded-circle me-2 mb-2 story-bg-choice story-bg-choice--teal"
                                                        data-preset="grad_teal"
                                                        data-color="#00bcd4"></button>

                                                <button type="button"
                                                        class="btn btn-sm rounded-circle me-2 mb-2 story-bg-choice story-bg-choice--green"
                                                        data-preset="grad_green"
                                                        data-color="#4caf50"></button>

                                                <button type="button"
                                                        class="btn btn-sm rounded-circle me-2 mb-2 story-bg-choice story-bg-choice--yellow"
                                                        data-preset="grad_yellow"
                                                        data-color="#ffca28"></button>

                                                <button type="button"
                                                        class="btn btn-sm rounded-circle me-2 mb-2 story-bg-choice story-bg-choice--brown"
                                                        data-preset="grad_brown"
                                                        data-color="#6d4c41"></button>

                                            </div>

                                        </div>

                                        @if(getSetting('stories.allow_cta_links'))
                                            <div class="mt-3">
                                                <label class="mb-1">{{ __("Story link") }}</label>

                                                <input type="url"
                                                       id="text-story-link-url"
                                                       class="form-control mb-2"
                                                       placeholder="https://example.com">

                                                <input type="text"
                                                       id="text-story-link-text"
                                                       class="form-control"
                                                       maxlength="80"
                                                       placeholder="{{ __('Link label (e.g. Learn more)') }}">

                                            </div>
                                        @endif

                                        @if(getSetting('stories.allow_sounds'))
                                            @include('elements.stories.story-sound-selector', [
                                                'idPrefix' => 'text',
                                                'label' => __('Sound')
                                            ])
                                        @endif

                                    </div>
                                </div>

                                {{-- Visibility + Share button --}}
                                <div class="story-footer creator-form-footer d-flex flex-wrap justify-content-between align-items-center mt-4">
                                    <div class="d-flex align-items-center">
                                        @if(getSetting('stories.allow_public_stories'))

                                            <div class="form-check form-switch">
                                                <input type="checkbox" class="form-check-input" id="is_public" name="is_public">
                                                <label class="form-check-label" for="is_public" tabindex="0"
                                                       data-bs-toggle="tooltip" data-bs-trigger="hover focus" data-tooltip-touch="true"
                                                       title="{{ __('On: anyone can view. Off: follower or subscription access is required.') }}">
                                                    {{ __("Is public") }}
                                                </label>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="creator-form-actions d-flex flex-wrap justify-content-end align-items-center">
                                        <button type="button" id="btn-story-clear-draft" class="form-action-link">
                                            {{ __('Clear draft') }}
                                        </button>
                                        <button type="button"
                                                id="btn-story-share"
                                                class="btn btn-primary creator-submit-button mb-0 {{!GenericHelper::canPublishContent() ? 'disabled' : ''}}"
                                                {{!GenericHelper::canPublishContent() ? 'disabled' : ''}}>
                                            {{ __("Publish story") }}
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
@stop
