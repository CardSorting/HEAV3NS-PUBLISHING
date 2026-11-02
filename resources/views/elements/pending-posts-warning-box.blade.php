<div class="alert alert-primary alert-dismissible text-white unverified-email-box mb-0" role="alert">
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="{{__('Close')}}"></button>
    <p class="m-0">
        {{PostsHelper::getPostReviewWarning(Auth::user()->id, $post ?? null)}}
    </p>
</div>
