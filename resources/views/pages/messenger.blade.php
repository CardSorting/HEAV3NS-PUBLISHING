@extends('layouts.user-no-nav')

@section('page_title', __('Messenger'))

@section('styles')
    {!!
        Minify::stylesheet(array_merge([
            '/libs/@selectize/selectize/dist/css/selectize.bootstrap5.css',
            '/libs/dropzone/dist/dropzone.css',
            '/libs/photoswipe/dist/photoswipe.css',
            '/libs/photoswipe/dist/default-skin/default-skin.css',
            '/css/pages/messenger.css',
            '/css/components/file-attachments.css',
            '/css/components/vault.css',
            '/css/components/gif-media.css',
            '/css/components/gif-picker.css',
            '/css/pages/checkout.css'
         ],$additionalAssets['css']))->withFullUrl()
    !!}
    @if(getSetting('stories.stories_enabled'))
        <link rel="stylesheet" href="{{asset('/libs/swiper/swiper-bundle.min.css')}}">
    @endif
@stop

@section('scripts')
    @include('elements.gif.assets', ['surfaces' => ['messenger']])
    {!!
        Minify::javascript(array_merge([
            '/js/messenger/messenger.js',
            '/js/messenger/elements.js',
            '/js/messenger/message-pins.js',
            '/libs/@selectize/selectize/dist/js/selectize.min.js',
            '/libs/dropzone/dist/dropzone.js',
            '/js/FileUpload.js',
            '/js/media-vault.js',
            '/js/plugins/media/photoswipe.js',
            '/libs/photoswipe/dist/photoswipe-ui-default.min.js',
            '/js/plugins/media/mediaswipe.js',
            '/js/plugins/media/mediaswipe-loader.js',
            '/js/pages/lists.js',
            '/js/pages/checkout.js',
            '/libs/autolinker/dist/autolinker.min.js'
         ],$additionalAssets['js']))->withFullUrl()
    !!}
@stop

@section('content')
    @include('elements.uploaded-file-preview-template')
    @include('elements.vault.picker')
    @include('elements.photoswipe-container')
    @include('elements.report-user-or-post',['reportStatuses' => ListsHelper::getReportTypes()])
    @include('elements.feed.post-delete-dialog')
    @include('elements.feed.post-list-management')
    @include('elements.messenger.message-price-dialog')
    @include('elements.checkout.checkout-box')
    @include('elements.attachments-uploading-dialog')
    @include('elements.messenger.locked-message-no-attachments-dialog', ['type' => lcfirst(__('Messages'))])
    @include('elements.messenger.empty-states')
    @include('elements.messenger.pinned-messages-dialog')
    <div class="d-flex flex-wrap">
        <div class="col-12 px-0">
            <div class="container-fluid messenger px-0">
                <div class="messenger-shell {{ GenericHelper::isDarkMode() ? 'messenger-dark' : 'messenger-light' }} border border-start-0 border-end-0 rounded-0 d-flex flex-column flex-lg-row overflow-hidden"
                     data-theme-light-class="messenger-light"
                     data-theme-dark-class="messenger-dark">
                    <aside class="messenger-sidebar conversations-wrapper d-flex flex-column border-end">
                        <div class="d-flex align-items-center justify-content-between px-3 py-3 border-bottom">
                            <div class="pe-3 overflow-hidden">
                                <h5 class="mb-0 text-truncate fw-bold text-dark-r">{{__('Messages')}}</h5>
                            </div>
	                            <div class="d-flex align-items-center flex-shrink-0">
	                                <a title="{{__('Search conversations')}}" class="h-pill h-pill-primary pointer-cursor messenger-contacts-search-toggle rounded-circle d-flex align-items-center justify-content-center mb-0 me-2" data-bs-toggle="tooltip" data-bs-placement="bottom">
	                                    @include('elements.icon',['icon'=>'search-outline','variant'=>'mediumish'])
	                                </a>
	                                <a title="{{__('Message automations')}}" class="h-pill h-pill-primary pointer-cursor messenger-templates-toggle rounded-circle d-flex align-items-center justify-content-center mb-0 me-2" data-bs-toggle="tooltip" data-bs-placement="bottom">
	                                    @include('elements.icon',['icon'=>'chatbubble-ellipses-outline','variant'=>'mediumish'])
	                                </a>
	                                <span data-bs-toggle="tooltip" class="pointer-cursor flex-shrink-0"
	                                      @if(!count($availableContacts))
	                                          title="{{trans_choice('Before sending a new message, please subscribe to a creator a follow a free profile.',['user' => 0])}}"
                                      @else
                                          title="{{trans_choice('Send a new message',['user' => 0])}}"
                                      @endif
                                >
                                    <a class="h-pill h-pill-primary pointer-cursor new-conversation-toggle rounded-circle d-flex align-items-center justify-content-center mb-0">
                                        @include('elements.icon',['icon'=>'create-outline','variant'=>'mediumish'])
                                    </a>
                                </span>
                            </div>
                        </div>
                        <div class="conversation-search d-none px-3 py-2 border-bottom">
                            <div class="messenger-search-field d-flex align-items-center">
                                <span class="messenger-search-icon text-muted d-flex align-items-center">
                                    @include('elements.icon',['icon'=>'search-outline','variant'=>'small'])
                                </span>
                                <input type="search" class="form-control messenger-contacts-search border-0 shadow-none" placeholder="{{__('Search conversations')}}" autocomplete="off">
                                <button type="button" class="btn btn-link messenger-contacts-search-clear d-none p-0 text-muted mb-0" aria-label="{{__('Clear search')}}">
                                    @include('elements.icon',['icon'=>'close-outline','variant'=>'small'])
                                </button>
                            </div>
                        </div>
                        <div class="conversations-list flex-fill">
                            @if($lastContactID == false)
                                @include('elements.empty-state', [
                                    'title' => __('No conversations yet'),
                                    'copy' => __('Start a new message using the compose button.'),
                                    'classes' => 'messenger-empty-state messenger-empty-state-compact h-100',
                                ])
                            @else
                                @include('elements.preloading.messenger-contact-box', ['limit'=>5])
                            @endif
                        </div>
                        <div class="contacts-pagination d-none border-top px-3 py-2">
                            <div class="d-flex align-items-center justify-content-center messenger-mobile-back">
                                <div class="py-2 spinner d-none messenger-contacts-spinner">
                                    <div class="spinner-border text-primary messenger-pagination-spinner" role="status">
                                        <span class="visually-hidden">{{__('Loading...')}}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </aside>
                    <section class="conversation-wrapper d-flex flex-column flex-fill border-end">
                        @include('elements.message-alert')
                        @include('elements.messenger.messenger-conversation-header')
                        @include('elements.messenger.messenger-new-conversation-header')
                        @include('elements.preloading.messenger-conversation-header-box')
                        @include('elements.messenger.pinned-messages-bar')
                        <div class="conversation-message-search d-none px-3 py-2 border-bottom">
                            <div class="messenger-search-field d-flex align-items-center">
                                <span class="messenger-search-icon text-muted d-flex align-items-center">
                                    @include('elements.icon',['icon'=>'search-outline','variant'=>'small'])
                                </span>
                                <input type="search" class="form-control conversation-message-search-input border-0 shadow-none" placeholder="{{__('Search messages')}}" autocomplete="off">
                                <span class="conversation-message-search-count small text-muted d-none me-2"></span>
                                <button type="button" class="btn btn-link conversation-message-search-clear d-none p-0 text-muted mb-0" aria-label="{{__('Clear search')}}">
                                    @include('elements.icon',['icon'=>'close-outline','variant'=>'small'])
                                </button>
                            </div>
                        </div>
	                        <div class="conversation-history-control d-none px-3 pt-3">
	                            <div class="d-flex align-items-center justify-content-center posts-loading-indicator">
	                                <div class="py-2 spinner d-none messenger-messages-spinner">
	                                    <div class="spinner-border text-primary messenger-pagination-spinner" role="status">
	                                        <span class="visually-hidden">{{__('Loading...')}}</span>
                                    </div>
	                                </div>
	                            </div>
	                        </div>
	                        <div class="message-templates-panel d-none flex-fill overflow-auto">
	                            <div class="d-flex align-items-center justify-content-between px-3 py-3 border-bottom">
	                                <div class="pe-3">
	                                    <h5 class="mb-0 fw-bold">{{__('Message automations')}}</h5>
	                                </div>
	                                <a class="h-pill h-pill-primary pointer-cursor messenger-templates-close rounded-circle d-flex align-items-center justify-content-center mb-0" data-bs-toggle="tooltip" data-bs-placement="bottom" title="{{__('Close')}}">
	                                    @include('elements.icon',['icon'=>'close-outline','variant'=>'mediumish'])
	                                </a>
	                            </div>
	                            <div class="px-3 py-3">
	                            <div class="message-template-trigger-list inline-border-tabs mb-3">
	                                <nav class="nav nav-pills nav-justified">
	                                    <a href="javascript:void(0)" class="nav-item nav-link message-template-trigger active" data-trigger="follower_created">
	                                        <div class="d-flex align-items-center justify-content-center">
	                                            @include('elements.icon',['icon'=>'people','variant'=>'medium','classes'=>'me-2'])
	                                            {{__('New followers')}}
	                                        </div>
	                                    </a>
	                                    <a href="javascript:void(0)" class="nav-item nav-link message-template-trigger" data-trigger="subscription_created">
	                                        <div class="d-flex align-items-center justify-content-center">
	                                            @include('elements.icon',['icon'=>'logo-usd','variant'=>'medium','classes'=>'me-2'])
	                                            {{__('New subscribers')}}
	                                        </div>
	                                    </a>
	                                </nav>
	                            </div>
	                            <div class="card shadow-none border message-template-card">
	                                <div class="card-body">
	                                    <div class="d-flex align-items-start justify-content-between">
	                                        <div class="pe-3">
	                                            <h6 class="message-template-title fw-bold mb-1">{{__('New followers')}}</h6>
	                                            <div class="message-template-description text-muted small">{{__('Sent once when someone follows you.')}}</div>
	                                        </div>
	                                        <div class="form-check form-switch">
	                                            <input type="checkbox" class="form-check-input message-template-enabled" id="message-template-enabled">
	                                            <label class="form-check-label" for="message-template-enabled">{{__('Enabled')}}</label>
	                                        </div>
	                                    </div>
	                                    <div class="mt-3 small text-muted">
	                                        {{__('Use the composer to write the template, attach media, and optionally set PPV.')}}
	                                    </div>
	                                </div>
	                            </div>
	                            </div>
	                        </div>
	                        @include('elements.preloading.messenger-conversation-box')
	                        <div class="conversation-content px-3 pb-3 flex-fill">
	                            @if($lastContactID == false)
	                                @include('elements.empty-state', [
	                                    'illustration' => asset('/img/no-content-available.svg'),
	                                    'illustrationClasses' => 'messenger-empty-state-illustration',
	                                    'title' => __('Start a conversation'),
	                                    'copy' => __('Choose a conversation or send a new message.'),
	                                    'classes' => 'messenger-empty-state messenger-empty-state-main h-100',
	                                ])
	                            @endif
	                        </div>
                        <div class="dropzone-previews dropzone w-100 px-3 py-2"></div>
                        <div id="messenger-gif-composer" class="{{!$lastContactID ? 'hidden' : ''}}" data-gif-composer data-gif-surface="messenger">
                            <div class="px-3" data-gif-preview-slot></div>
                            <div class="conversation-writeup d-flex align-items-center {{!$lastContactID ? 'hidden' : ''}}">
                                {{-- Left side--}}
                                <div class="messenger-buttons-wrapper d-flex align-items-center">
                                    @include('elements.messenger.attachment-menu')
                                </div>
                                <div class="messenger-voice-recorder d-none align-items-center flex-fill">
                                    <div class="messenger-voice-recording-dot"></div>
                                    <span class="messenger-voice-recording-label">{{__('Recording')}}</span>
                                    <canvas class="messenger-voice-waveform" height="28" aria-hidden="true"></canvas>
                                    <span class="messenger-voice-recording-time">0:00</span>
                                    <button type="button" class="btn btn-outline-secondary btn-rounded-icon messenger-button voice-record-cancel to-tooltip" data-bs-placement="top" title="{{__('Cancel')}}">
                                        <div class="d-flex justify-content-center align-items-center">
                                            @include('elements.icon',['icon'=>'close-outline','variant'=>''])
                                        </div>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-rounded-icon messenger-button voice-record-stop to-tooltip" data-bs-placement="top" title="{{__('Stop recording')}}">
                                        <div class="d-flex justify-content-center align-items-center">
                                            @include('elements.icon',['icon'=>'stop-outline','variant'=>''])
                                        </div>
                                    </button>
                                </div>
                                {{-- Input --}}
                                <form class="message-form flex-fill">
                                    <div class="messageBoxInput-wrapper position-relative">
                                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                        <input type="hidden" name="receiverID" id="receiverID" value="">
                                        <textarea name="message" class="form-control messageBoxInput dropzone pe-5" placeholder="{{__('Write a message..')}}" onkeyup="messenger.textAreaAdjust(this)"></textarea>
                                        <div class="position-absolute top-0 end-0 h-100 d-flex align-items-center pe-2" data-gif-toolbar-slot></div>
                                    </div>
                                </form>
                                {{-- Right side --}}
                                <div class="messenger-buttons-wrapper d-flex align-items-center">
                                    @if((GenericHelper::creatorCanEarnMoney(Auth::user()) && !(!GenericHelper::isUserVerified() && getSetting('site.enforce_user_identity_checks'))) /*|| Auth::user()->role_id === 1*/)
                                        <button class="btn btn-outline-primary btn-rounded-icon messenger-button mx-0 mx-md-1 to-tooltip" data-bs-placement="top" title="{{__('Message price')}}" onClick="messenger.showSetPriceDialog()">
                                            <div class="d-flex justify-content-center align-items-center">
                                                <span class="message-price-lock">@include('elements.icon',['icon'=>'lock-open','variant'=>''])</span>
                                                <span class="message-price-close d-none">@include('elements.icon',['icon'=>'lock-closed','variant'=>''])</span>
                                            </div>
                                        </button>
                                    @endif
                                    <button class="btn btn-outline-primary btn-rounded-icon messenger-button send-message mx-0 mx-md-1 to-tooltip" onClick="messenger.sendMessage()" data-bs-placement="top" title="{{__('Send message')}}">
                                        <div class="d-flex justify-content-center align-items-center">
                                            @include('elements.icon',['icon'=>'paper-plane','variant'=>''])
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
    @include('elements.standard-dialog',[
    'dialogName' => 'message-delete-dialog',
    'title' => __('Delete message'),
    'content' => __('Are you sure you want to delete this message?'),
    'actionLabel' => __('Delete'),
    'actionFunction' => 'messenger.deleteMessage();',
])
    @if(getSetting('stories.stories_enabled'))
        @include('elements.stories.delete-dialog')
    @endif
@stop
