<div class="modal fade" tabindex="-1" role="dialog" id="stream-update-dialog" aria-labelledby="stream-update-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="stream-update-title"><span class="create-label d-none">{{__('Start a new stream')}}</span> <span class="edit-label d-none">{{__('Edit stream details')}}</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{__('Close')}}"></button>
            </div>
            <div class="modal-body">
                @include('elements.creator-access-notice')
                @if($activeStream && !GenericHelper::canPublishContent())
                    <p class="small text-muted">{{ __('You can update this stream\'s details. New streams, poster uploads, and broadcasting require publishing access.') }}</p>
                @endif
                <div class="mb-3">

                    <div class="d-flex justify-content-between">
                        <label class="form-label" for="stream-name">{{__('Stream name')}}</label>
                        <div>
                            @if(getSetting('ai.text_enabled'))
                                <a href="javascript:void(0)"
                                   class="ai-suggest-link"
                                   data-ai-type="stream"
                                   data-loading-text="{{ __('Generating') }}">
                                    {{ __('AI Suggestion') }}
                                </a>
                            @endif
                        </div>
                    </div>

                    <input class="form-control" id="stream-name" name="stream-name" value="{{$activeStream ? $activeStream->name : ''}}">
                    <span class="invalid-feedback" role="alert">
                        <strong></strong>
                    </span>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="stream-access_price">{{__('Access price')}}</label>
                    <div class="input-group">
                        <span class="input-group-text" id="amount-label">@include('elements.icon',['icon'=>'cash-outline','variant'=>'medium'])</span>
                        <input class="form-control" id="stream-access_price" name="access_price" aria-describedby="amount-label" value="{{$activeStream ? $activeStream->price : ''}}" type="number">
                        <span class="invalid-feedback" role="alert">
                            <strong>{{$errors->first('access_price')}}</strong>
                        </span>
                    </div>

                </div>

                <div class="mb-3">
                    <div class="form-label">{{__('Stream poster')}}</div>
                    <div class="card profile-cover-bg" style="background-image: url('{{$activeStream && $activeStream->poster ? $activeStream->poster : asset('/img/live-stream-cover.svg')}}');">
                        <div class="card-img-overlay d-flex justify-content-center align-items-center">
                            <div class="actions-holder d-none">
                                <div class="d-flex">
                                    <button type="button" class="h-pill h-pill-accent pointer-cursor me-1 upload-button border-0" data-bs-toggle="tooltip" data-bs-placement="top" title="{{__('Upload stream poster')}}" aria-label="{{__('Upload stream poster')}}" {{ GenericHelper::canPublishContent() ? '' : 'disabled' }}>
                                        @include('elements.icon',['icon'=>'image','variant'=>'medium'])
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="requires_subscription" name="requires_subscription" {{$activeStream && $activeStream->requires_subscription ? 'checked' : ''}}>
                    <label class="form-check-label" for="requires_subscription">{{__("Requires a subscription")}}</label>
                </div>

                <div class="form-check form-switch mt-1">
                    <input type="checkbox" class="form-check-input" id="is_public" name="is_public" {{$activeStream ? ( $activeStream->is_public ? 'checked' : '') : 'checked'}}>
                    <label class="form-check-label" for="is_public">{{__("Is public stream")}}</label>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary stream-save-btn" onclick="Streams.updateStream();" {{ GenericHelper::canPublishContent() || $activeStream ? '' : 'disabled' }}>{{__('Save')}}</button>
            </div>
        </div>
    </div>
</div>
