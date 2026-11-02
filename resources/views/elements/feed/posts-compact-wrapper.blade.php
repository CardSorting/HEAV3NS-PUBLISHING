@if(count($posts))
    @foreach($posts as $post)
        @include('elements.feed.post-compact-tile', [
            'post' => $post,
            'compactPostPreview' => PostsHelper::getPostCompactMediaPreviewData($post, $compactMediaType ?? null),
        ])
    @endforeach
@endif
@include('elements.feed.posts-empty-state', ['compactPostsEmptyState' => true])
