@extends('layouts.user-no-nav')
@section('page_title', 'Broadcast Your Stream')

@section('styles')
    {!!
        Minify::stylesheet([
            '/css/pages/broadcast.css',
            '/css/pages/stream.css',
         ])->withFullUrl()
    !!}
@stop

@section('scripts')
    {!!
        Minify::javascript([
            '/libs/dropzone/dist/dropzone.js',
            '/js/FileUpload.js',
            '/js/pages/broadcast.js',
            '/js/pages/streams.js',
            '/js/pages/stream.js',
            '/js/AISuggestions.js',
         ])->withFullUrl()
    !!}
@stop

@section('content')
    <div class="d-flex flex-wrap">
        <div class="col-12 px-0">

            <div class="pt-4 d-flex justify-content-between align-items-center px-3 pb-3 border-bottom">
                <h5 class="text-truncate fw-bold text-dark-r mb-0 active-stream-name">{{$stream->name}}</h5>
                <button type="button" class="btn btn-outline-danger btn-sm px-3 mb-0 d-flex align-items-center">
                    <div class="me-1">{{__("Streaming")}}</div>
                    <div><div class="blob red"></div></div>
                </button>
            </div>

            <div class="px-3 pt-3">
                <div class="position-relative">
                    <div id="local-video"></div>
                    <div class="d-flex align-items-center justify-content-center">
                        <img id="placeholder" src="{{ asset('/img/live-stream-locked.svg') }}" alt="No live stream available" style="width:70%; height: 50vh;">
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-3">

                    <div class="d-flex align-items-center ">
                        <div class="d-none">
                            <button type="button" id="startStream" class="btn btn-primary mb-0 me-2">{{__('Start Streaming')}}</button>
                            <button type="button" id="stopStream" class="btn btn-danger d-none mb-0 me-2">{{__('Stop Streaming')}}</button>
                        </div>

                        <div id="broadcaster-controls" class="d-flex align-items-center ">

                            <!-- Mute/Unmute Audio -->
                            <button type="button" class="p-pill p-0 ms-2 pointer-cursor to-tooltip" title="{{__('End stream')}}" aria-label="{{__('End stream')}}" onclick="Streams.showStreamStopDialog()">
                                <ion-icon name="stop-circle-outline"></ion-icon>
                            </button>

                            <!-- Mute/Unmute Audio -->
                            <button type="button" id="muteAudioButton" class="p-pill p-0 ms-2 pointer-cursor to-tooltip" title="{{__('Mute Audio')}}" aria-label="{{__('Mute Audio')}}">
                                <ion-icon name="mic-outline"></ion-icon>
                            </button>

                            <!-- Mute/Unmute Video -->
                            <button type="button" id="muteVideoButton" class="p-pill p-0 ms-2 pointer-cursor to-tooltip" title="{{__('Mute Video')}}" aria-label="{{__('Mute Video')}}">
                                <ion-icon name="videocam-outline"></ion-icon>
                            </button>

                            <!-- Toggle Mirror -->
                            <button type="button" id="toggleMirrorButton" class="p-pill p-0 ms-2 pointer-cursor to-tooltip" title="{{__('Toggle Mirror')}}" aria-label="{{__('Toggle Mirror')}}">
                                <ion-icon name="swap-horizontal-outline"></ion-icon>
                            </button>
                        </div>
                    </div>

                    <div class="d-flex">
                        @if($stream->user->id === Auth::user()->id)
                            <div class="">
                                <button type="button" class="p-pill p-0 ms-2 pointer-cursor to-tooltip" onclick="Streams.showStreamEditDialog('edit',{{$stream->id}});" title="{{__("Edit stream")}}" aria-label="{{__("Edit stream")}}">
                                    <ion-icon name="create-outline"></ion-icon>
                                </button>
                            </div>
                            <div class="">
                                <button type="button" class="p-pill p-0 ms-2 pointer-cursor to-tooltip" title="{{__("Camera settings")}}" aria-label="{{__("Camera settings")}}" onclick="Broadcast.openCameraSettingsDialog()">
                                    @include('elements.icon',['icon'=>'settings-outline'])
                                </button>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card pb-3 my-3">
                    <div class="pt-3 px-3">
                        @include('elements.streams.stream-details-banner')
                    </div>
                    @include('elements.streams.stream-chat')
                </div>

            </div>
        </div>
    </div>

    @include('elements.streams.broadcaster-camera-settings-dialog')
    @include('elements.streams.stream-create-update-dialog', ['activeStream' => $stream])
    @include('elements.streams.stream-stop-dialog')
    @include('elements.standard-dialog',[
        'dialogName' => 'broadcast-leave-dialog',
        'title' => __('Leave broadcast page?'),
        'content' => __('Leaving this page will interrupt your live stream.'),
        'actionLabel' => __('Leave page'),
        'actionFunction' => 'Broadcast.confirmLeavePage();',
    ])
    @include('elements.dropzone-dummy-element')

@endsection
