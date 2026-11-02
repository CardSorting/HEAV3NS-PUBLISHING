@extends('layouts.user-no-nav')
@section('page_title', $stream->name)

@section('styles')
    @if(!$stream->isLivekitDriver())
        {{-- RTMP (Pushr) specific styles --}}
        <link rel="stylesheet" href="{{asset('/libs/video.js/dist/video-js.min.css')}}">
        <link rel="stylesheet" href="{{asset('/css/player-theme.css')}}">
    @endif
    {!! Minify::stylesheet([
            '/libs/dropzone/dist/dropzone.css',
            '/css/pages/checkout.css',
            '/css/pages/stream.css',
            '/libs/@selectize/selectize/dist/css/selectize.bootstrap5.css',
        ])->withFullUrl()
     !!}
@stop

    @section('scripts')
    @if(!$stream->isLivekitDriver())
        <script type="text/javascript" src="{{asset('/libs/video.js/dist/video.min.js')}}"></script>
        <script type="text/javascript" src="{{asset('/libs/videojs-contrib-quality-levels/dist/videojs-contrib-quality-levels.min.js')}}"></script>
        <script type="text/javascript" src="{{asset('/libs/videojs-contrib-quality-menu/dist/videojs-contrib-quality-menu.min.js')}}"></script>
    @endif
    {!! Minify::javascript([
        '/libs/dropzone/dist/dropzone.js',
        '/libs/pusher-js-auth/lib/pusher-auth.js',
        '/js/FileUpload.js',
        '/js/pages/lists.js',
        '/js/pages/checkout.js',
        '/js/RTMPStream.js',
        '/js/RTCStream.js',
        '/js/pages/stream.js',
        '/libs/@selectize/selectize/dist/js/selectize.min.js'
    ])->withFullUrl() !!}
    <script>
        $(document).ready(function () {
            Stream.init("{{ $stream->getDriverSlug() }}");

            $('#startStreamBtn').on('click', function () {
                const overlay = document.getElementById('stream-start-overlay');
                overlay?.classList.add('hidden');

                if (typeof RTCStream !== 'undefined' && typeof RTCStream.resumeMedia === 'function') {
                    RTCStream.resumeMedia().then(() => {
                        RTCStream.hasUserInteracted = true;
                        RTCStream.tryResumePlayback();
                    }).catch(err => {
                        console.warn("Stream resumeMedia error:", err);
                    });
                }
            });
        });
    </script>
@stop

@section('content')
    <div class="d-flex flex-wrap">
        <div class="col-12 px-0">
            <div class="pt-4 d-flex justify-content-between align-items-center px-3 pb-3 border-bottom">
                <h5 class="text-truncate fw-bold text-dark-r mb-0">{{$stream->name}}</h5>
                @if(!isset($streamEnded))
                    @if(StreamsHelper::getUserInProgressStream())
                        <button type="button" class="btn btn-outline-danger btn-sm px-3 mb-0 d-flex align-items-center" onclick="Streams.showStreamEditDialog('create')">
                            <div class="me-1">{{__("Streaming")}}</div>
                            <div><div class="blob red"></div></div>
                        </button>
                    @endif
                @endif
            </div>
            <div class="px-3 pt-3">
                @include('elements.message-alert',['classes'=>'pb-3'])
                <div class="stream-wrapper d-flex flex-wrap">
                    <div class="stream-video col-12 px-0">
                        @if($stream->canWatchStream)
                            @if($stream->isLivekitDriver())
                                <div id="stream-start-overlay-wrapper">
                                    <div id="remote-videos" class="">
                                        <div id="placeholder" class="d-flex align-items-center justify-content-center">
                                            <img src="{{ asset('/img/live-stream-locked.svg') }}" alt="No live stream available" style="width:70%; height: 50vh;">
                                        </div>
                                    </div>
                                    <div id="stream-start-overlay">
                                        <button type="button" id="startStreamBtn" class="btn btn-lg btn-primary">{{ __("Click to start stream") }}</button>
                                    </div>
                                </div>
                                <div id="remote-audios"></div>
                            @else
                                <video
                                    id="my_video_1"
                                    class="video-js vjs-fluid vjs-theme-forest"
                                    controls
                                    preload="auto"
                                    autoplay
                                    muted
                                    poster="{{ $stream->poster ?? asset('/img/live-stream-locked.svg') }}"
                                >
                                    <source src="{{ isset($streamEnded) ? 'https://' . $stream->vod_link : $stream->hls_link }}" type="application/vnd.apple.mpegurl">
                                </video>
                            @endif
                        @else
                            <div class="d-flex flex-wrap justify-content-center align-items-center">
                                <div class="col-12 px-0">
                                    <div class="card p-5">
                                        <div class="p-4 p-md-5">
                                            <img src="{{asset('/img/live-stream-locked.svg')}}" class="stream-locked" alt="{{__('Live stream is locked')}}">
                                        </div>
                                        <div class="d-flex align-items-center justify-content-center">
                                            <span>
                                                🔒 {{__("Live stream requires a")}}
                                                @if(isset($subLocked))
                                                    {{__("valid")}}
                                                    <a href="javascript:void(0);" class="stream-subscribe-label to-tooltip"
                                                       @if(!GenericHelper::creatorCanEarnMoney($stream->user))
                                                           data-bs-placement="top"
                                                       title="{{__('This creator cannot earn money yet')}}"
                                                       @endif>
                                                       {{__("user subscription")}}
                                                    </a>
                                                @endif
                                                @if(isset($priceLocked))
                                                    @if(isset($subLocked)){{__("and an")}}@endif
                                                    <a href="javascript:void(0);" class="stream-unlock-label to-tooltip"
                                                       @if(!GenericHelper::creatorCanEarnMoney($stream->user))
                                                           data-bs-placement="top"
                                                       title="{{__('This creator cannot earn money yet')}}"
                                                       @endif>
                                                       {{__("one time fee")}}
                                                    </a>
                                                @endif.
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card pb-3 my-3 stream-details-wrapper">
                    <div class="pt-3 px-3">
                        @include('elements.streams.stream-details-banner')
                    </div>
                    @include('elements.streams.stream-chat')
                </div>
            </div>
        </div>
    </div>

    @include('elements.checkout.checkout-box')
    @include('elements.report-user-or-post', ['reportStatuses' => ListsHelper::getReportTypes()])
@stop
