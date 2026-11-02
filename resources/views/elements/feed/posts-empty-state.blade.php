<div data-posts-empty-state class="{{ ($compactPostsEmptyState ?? false) ? 'profile-compact-posts-empty' : '' }} {{ count($posts) ? 'd-none' : '' }}">
    @if(isset($emptyStateView))
        @include($emptyStateView, $emptyStateData ?? [])
    @else
        <div class="d-flex justify-content-center align-items-center">
            <div class="col-10">
                <img src="{{asset('/img/no-content-available.svg')}}" alt="">
            </div>
        </div>
        <div class="d-flex justify-content-center align-items-center">
            <h5 class="text-center mb-2 mt-2">{{__('No posts available')}}</h5>
        </div>
    @endif
</div>
<div data-posts-load-error class="{{ ($compactPostsEmptyState ?? false) ? 'profile-compact-posts-empty' : '' }} d-none px-3 py-4" role="alert">
    <p class="text-muted mb-2">{{__('Something went wrong, please try again')}}</p>
    <button type="button" class="btn btn-outline-primary btn-sm" onclick="PostsPaginator.retryLoading()">{{__('Try again')}}</button>
</div>
