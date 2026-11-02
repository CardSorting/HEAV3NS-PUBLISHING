<div class="modal fade" tabindex="-1" role="dialog" id="stream-stop-dialog" aria-labelledby="stream-stop-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="stream-stop-title">{{__('End stream')}}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{__('Close')}}"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">{{__('Are you sure you want to end this stream?')}}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger end-stream-label" onclick="Streams.endStream();">{{__('Stop stream')}}</button>
            </div>
        </div>
    </div>
</div>
