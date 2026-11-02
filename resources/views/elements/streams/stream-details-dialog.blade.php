<div class="modal fade" tabindex="-1" role="dialog" id="stream-details-dialog" aria-labelledby="stream-details-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="stream-details-title">{{__('How to stream')}}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{__('Close')}}"></button>
            </div>
            <div class="modal-body">

                <p>{{__('Your stream server is online. In order to get going, follow the steps below:')}}</p>


                <div class="mt-3 inline-border-tabs">
                    <nav class="nav nav-pills nav-justified" role="tablist">
                        <button class="nav-link active" id="stream-desktop-tab" data-bs-toggle="tab" data-bs-target="#nav-desktop" type="button" role="tab" aria-controls="nav-desktop" aria-selected="true">
                            <div class="d-flex align-items-center justify-content-center">
                                @include('elements.icon',['icon'=>'laptop-outline','variant'=>'small','classes'=>'me-2'])
                                {{__("Desktop")}}
                            </div>
                        </button>
                        <button class="nav-link" id="stream-mobile-tab" data-bs-toggle="tab" data-bs-target="#nav-mobile" type="button" role="tab" aria-controls="nav-mobile" aria-selected="false">
                            <div class="d-flex align-items-center justify-content-center">
                                @include('elements.icon',['icon'=>'phone-portrait-outline','variant'=>'small','classes'=>'me-2'])
                                {{__("Mobile")}}
                            </div>
                        </button>
                    </nav>
                </div>
                <div class="tab-content" id="nav-tabContent">
                    <div class="tab-pane fade show active" id="nav-desktop" role="tabpanel" aria-labelledby="stream-desktop-tab" tabindex="0">
                        <div class="mt-2">
                            <ol class="py-3">
                                <li class="mb-1">{{__('Download')}} <a href="https://obsproject.com/download" target="_blank" rel="noopener">OBS</a> {{__('for desktop or mobile alternatives.')}}</li>
                                <li class="mb-1">{{__('Go to')}} <code>{{__("Settings > Stream")}}</code>. {{__('For')}} <code>{{__("Service")}}</code>, {{__('select')}} <code>{{__("Custom")}}</code>.</li>
                                <li class="mb-1">{{ucfirst(__('for the'))}} <code>{{__("Server & Stream key")}}</code>, {{__('use the values below.')}}</li>
                            </ol>
                            <div class="row g-2 align-items-center mb-3">
                                <label for="stream-url" class="col-sm-3 col-form-label">{{__('Stream url')}}</label>
                                <div class="col-sm-7">
                                    <input type="text" class="form-control" id="stream-url" placeholder="{{__('Stream url')}}">
                                </div>
                                <div class="col-sm-auto d-flex align-items-center justify-content-center">
                                    <button type="button" class="h-pill h-pill-accent rounded me-2 border-0" onclick="Streams.copyStreamData('url')" title="{{__('Copy stream url')}}" aria-label="{{__('Copy stream url')}}">
                                        @include('elements.icon',['icon'=>'copy-outline'])
                                    </button>
                                </div>
                            </div>
                            <div class="row g-2 align-items-center">
                                <label for="stream-key" class="col-sm-3 col-form-label">{{__('Stream key')}}</label>
                                <div class="col-sm-7">
                                    <input type="text" class="form-control" id="stream-key" placeholder="{{__('Stream key')}}">
                                </div>
                                <div class="col-sm-auto d-flex align-items-center justify-content-center">
                                    <button type="button" class="h-pill h-pill-accent rounded me-2 border-0" onclick="Streams.copyStreamData('key');" title="{{__('Copy stream key')}}" aria-label="{{__('Copy stream key')}}">
                                        @include('elements.icon',['icon'=>'copy-outline'])
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="nav-mobile" role="tabpanel" aria-labelledby="stream-mobile-tab" tabindex="0">
                        <div class="mt-2">
                            <ol class="py-3">
                                <li class="mb-1">{{__('Download')}} {{__("Larix for")}} <a href="https://apps.apple.com/us/app/larix-broadcaster/id1042474385" target="_blank" rel="noopener">iOS</a> {{__("or")}} {{__("IRL Pro for")}} <a href="https://play.google.com/store/apps/details?id=app.irlpro.android" target="_blank" rel="noopener">Android</a>.</li>
                                <li class="mb-1">{{__('Go to')}} <code>{{__("Settings > Connection > New connection")}}</code>.</li>
                                <li class="mb-1">{{ucfirst(__('for the'))}} <code>URL</code>, {{__("use the following value")}}.</li>
                            </ol>
                            <div class="row g-2 align-items-center">
                                <label for="stream-url-larix" class="col-sm-3 col-form-label">{{__('Stream url')}}</label>
                                <div class="col-sm-7">
                                    <input type="text" class="form-control" id="stream-url-larix" placeholder="{{__('Stream url')}}">
                                </div>
                                <div class="col-sm-auto d-flex align-items-center justify-content-center">
                                    <button type="button" class="h-pill h-pill-accent rounded me-2 border-0" onclick="Streams.copyStreamData('mobile-url')" title="{{__('Copy stream url')}}" aria-label="{{__('Copy stream url')}}">
                                        @include('elements.icon',['icon'=>'copy-outline'])
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">{{__('Got it')}}</button>
            </div>
        </div>
    </div>
</div>
