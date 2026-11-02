<div class="modal fade" tabindex="-1" role="dialog" id="message-set-price-dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{__('Set message price')}}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>{{__('The PPV content is locked for subscribers as well.')}} @if(getSetting('feed.disable_posts_text_preview')) {{__("The text content will be hidden")}} @endif</p>
                <div class="input-group has-validation">
                    <span class="input-group-text" id="amount-label">@include('elements.icon',['icon'=>'cash-outline','variant'=>'medium'])</span>
                    <input id="message-price" type="number" class="form-control" name="text" required  placeholder="{{__('Post price')}}" value="{{(int)getSetting('payments.min_ppv_post_price')}}" min="1" max="100">
                    <span class="invalid-feedback" role="alert">
                        <strong>{{__('The price must be between :min and :max.',['min' => getSetting('payments.min_ppv_message_price') ?? 1, 'max' => getSetting('payments.max_ppv_message_price') ?? 500])}}</strong>
                    </span>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-white" onclick="messenger.clearMessagePrice()">{{__('Clear')}}</button>
                <button type="button" class="btn btn-primary" onclick="messenger.saveMessagePrice()">{{__('Save')}}</button>
            </div>
        </div>
    </div>
</div>
