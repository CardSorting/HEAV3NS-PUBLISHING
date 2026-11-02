<div class="modal fade" tabindex="-1" role="dialog" id="post-delete-dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{__('Delete post')}}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>{{__('Are you sure you want to delete this post?')}}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="Post.removePost();">{{__('Delete')}}</button>
            </div>
        </div>
    </div>
</div>
