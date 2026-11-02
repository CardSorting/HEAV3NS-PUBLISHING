<div class="modal fade" tabindex="-1" role="dialog" id="subscription-cancel-dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{__('Cancel subscription')}}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{__('Close')}}"></button>
            </div>
            <div class="modal-body">
                <p>{{__('Are you sure you want to cancel this subscription?')}}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="SubscriptionsSettings.cancelSubscription();">{{__('Confirm')}}</button>
            </div>
        </div>
    </div>
</div>
