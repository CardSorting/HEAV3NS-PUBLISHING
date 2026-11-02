<div class="modal fade" tabindex="-1" role="dialog" id="list-clear-dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{__('Clear list')}}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>{{__('Are you sure you want to clear all members out of current list?')}}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="Lists.clearList();">{{__('Clear')}}</button>
            </div>
        </div>
    </div>
</div>
