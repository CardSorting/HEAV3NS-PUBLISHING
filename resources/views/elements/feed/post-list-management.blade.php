<div class="modal fade" tabindex="-1" role="dialog" id="post-lists-management-dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <span class="block-user-label">{{__('Block user')}}</span>
                    <span class="unfollow-user-label">{{__('Unfollow user')}}</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="block-user-label">{{__('Are you sure you want to block this user? Your posts will be mutually hidden and chats disabled.')}}</p>
                <p class="unfollow-user-label">{{__('Are you sure you want to unfollow this user? His posts will be hidden from your feed.')}}</p>
                <p class="unfollow-user-label">{{__('You can follow back any time later from the lists module.')}}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger post-list-management-btn">{{__('Confirm')}}</button>
            </div>
        </div>
    </div>
</div>
