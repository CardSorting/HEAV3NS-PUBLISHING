<div class="post-comment-thread" data-root-comment-id="{{$comment->id}}">
    @include('elements.feed.post-comment', [
        'comment' => $comment,
        'isFirst' => $isFirst ?? false,
        'isReply' => false,
    ])

    <div class="post-comment-thread-content">
        <div class="post-comment-replies {{$comment->replies->count() ? '' : 'd-none'}}">
            @foreach($comment->replies as $reply)
                @include('elements.feed.post-comment', [
                    'comment' => $reply,
                    'isFirst' => false,
                    'isReply' => true,
                ])
            @endforeach
        </div>

        <div class="comment-replies-control ms-2 mb-2 {{$comment->replies_count ? '' : 'd-none'}}">
            <a href="javascript:void(0)"
               class="comment-replies-toggle text-primary small"
               onclick="Post.toggleCommentReplies(this)"
               data-post-id="{{$comment->post_id}}"
               data-root-id="{{$comment->id}}"
               data-total="{{$comment->replies_count}}"
               data-offset="{{min(2, $comment->replies_count)}}"
               data-more-template="{{__('View :count more replies', ['count' => '__COUNT__'])}}"
               data-view-template="{{__('View :count replies', ['count' => '__COUNT__'])}}"
               data-hide-label="{{__('Hide replies')}}">
                @if($comment->replies_count > $comment->replies->count())
                    {{__('View :count more replies', ['count' => $comment->replies_count - $comment->replies->count()])}}
                @else
                    {{__('Hide replies')}}
                @endif
            </a>
        </div>
    </div>
</div>
