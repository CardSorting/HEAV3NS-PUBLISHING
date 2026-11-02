@extends('layouts.user-no-nav')
@section('page_title', __('Your live streams'))

@section('styles')
    {!!
        Minify::stylesheet([
            '/libs/dropzone/dist/dropzone.css',
            '/css/pages/stream.css',
         ])->withFullUrl()
    !!}
@stop

@section('scripts')
    {!!
        Minify::javascript([
            '/libs/dropzone/dist/dropzone.js',
            '/js/FileUpload.js',
            '/js/pages/streams.js',
            '/js/AISuggestions.js',
         ])->withFullUrl()
    !!}
@stop

@section('content')

    <div class="d-flex flex-wrap" data-stream-creation data-can-publish="{{ GenericHelper::canPublishContent() ? 'true' : 'false' }}">
        <div class="col-12 px-0">
            <div class="pt-4 d-flex justify-content-between align-items-center px-3 pb-3 border-bottom">
                <div>
                    <h5 class="text-truncate fw-bold text-dark-r mb-0">{{__('Streams')}}</h5>
                </div>
                <div class="d-flex">
                    <div class="stream-on-label w-100 {{StreamsHelper::getUserInProgressStream() ? '' : 'd-none'}}">
                        <button type="button" class="btn btn-outline-danger btn-sm px-3 mb-0 d-flex align-items-center">
                            <div class="me-1">{{__("Streaming")}}</div>
                            <div><div class="blob red"></div></div>
                        </button>
                    </div>

                    @if(GenericHelper::canViewCreationPage())
                        <div class="stream-off-label w-100 {{StreamsHelper::getUserInProgressStream() ? 'd-none' : ''}}">
                            <button type="button" class="btn btn-outline-danger btn-sm px-3 mb-0 d-flex align-items-center"
                                    onclick="Streams.showStreamEditDialog('create')" data-bs-toggle="tooltip" data-bs-placement="top"
                                    title="{{__('Go live')}}">
                                <div class="me-1">{{__("New stream")}}</div>
                                <div> @include('elements.icon',['icon'=>'ellipse','variant'=>'','classes'=>'flex-shrink-0 text-danger'])</div>
                            </button>
                        </div>
                    @elseif(GenericHelper::canAccessCreatorOnboarding())
                        <a class="btn btn-outline-primary btn-sm mb-0" href="{{ GenericHelper::getCreatorOnboardingUrl() }}">{{ GenericHelper::getCreatorOnboardingLabel() }}</a>
                    @endif

                </div>
            </div>

            <div class="px-3 pt-3">
                @include('elements.creator-access-notice')
                <div class="card py-3 px-3">
                    <p class="h6 fw-bolder mb-2 text-bold-600">{{__("Active streams")}}</p>
                    <div class="lists-wrapper mt-{{StreamsHelper::getUserInProgressStream() ? '2' : '0'}} active-stream-container">
                        @if(StreamsHelper::getUserInProgressStream())
                            @include('elements.streams.stream-element',['stream'=>$activeStream, 'isLive' => true])
                        @else
                            <span>{{ GenericHelper::canViewCreationPage() ? __("There are no active streams. Click the button above to start a new one.") : __("There are no active streams.") }}</span>
                        @endif
                    </div>
                </div>
                <div class="card py-3 px-3 my-4">
                    <p class="h6 fw-bolder mb-2 text-bold-600">{{__("Previous streams")}}</p>
                    @if($previousStreams->count())
                        <div class="lists-wrapper mt-2">
                            @foreach($previousStreams as $stream)
                                @include('elements.streams.stream-element',['stream'=>$stream, 'isLive' => false])
                            @endforeach
                            @if($previousStreams->total() > 6)
                                <div class="d-flex flex-row-reverse mt-3">
                                    {{ $previousStreams->onEachSide(1)->links() }}
                                </div>
                            @endif
                        </div>
                    @else
                        <span>{{__("There are no previous streams.")}}</span>
                    @endif
                </div>
            </div>
        </div>

    </div>
    @if(GenericHelper::canViewCreationPage() || $activeStream)
        @include('elements.streams.stream-create-update-dialog')
    @endif
    @include('elements.streams.stream-stop-dialog')
    @include('elements.streams.stream-delete-dialog')
    @if(GenericHelper::canPublishContent())
        @include('elements.streams.stream-details-dialog')
    @endif
    @include('elements.dropzone-dummy-element')
@stop
