<div class="modal fade" tabindex="-1" role="dialog" id="{{$dialogName}}">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{$title}}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{__('Close')}}"></button>
            </div>
            <div class="modal-body">
                <p>{{$content}}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-dialog-action onclick="{{$actionFunction}}">{{$actionLabel}}</button>
            </div>
        </div>
    </div>
</div>
