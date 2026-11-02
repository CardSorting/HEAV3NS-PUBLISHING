<div class="px-3 new-post-comment-area">
    <div class="comment-reply-context d-none align-items-center justify-content-between mb-2 ms-3">
        <div class="small text-muted">
            {{__('Replying to')}} <span class="comment-reply-username fw-bold"></span>
        </div>
        <a href="javascript:void(0)" class="small text-muted" onclick="Post.cancelCommentReply({{$post->id}})">{{__('Cancel')}}</a>
    </div>
    <input type="hidden" class="comment-reply-to-id" value="">
    <div data-gif-composer data-gif-surface="post_comments">
        <div class="ms-5" data-gif-preview-slot></div>
        <div class="d-flex align-items-start">
            <img class="rounded-circle flex-shrink-0 me-2" src="{{Auth::user()->avatar}}" alt="{{Auth::user()->username}}">
            <div class="post-comment-composer flex-grow-1">
                <div class="d-flex align-items-center">
                    <div class="input-group">
                        <div class="hl-textarea-wrap w-100">
                            <div class="hl-backdrop" aria-hidden="true">
                                <div class="hl-highlights"></div>
                            </div>

                            <textarea name="message"
                                      class="form-control comment-textarea comment-text new-comment-textarea hl-textarea pe-5"
                                      placeholder="{{__('Write a message..')}}"
                                      onkeyup="textAreaAdjust(this)"></textarea>
                            <div class="position-absolute top-0 end-0 h-100 d-flex align-items-center pe-2" data-gif-toolbar-slot></div>
                        </div>

                        <span class="invalid-feedback ps-4 fw-bold" role="alert"></span>
                    </div>
                    <div class="ps-2">
                        <button type="button" class="btn btn-outline-primary btn-rounded-icon btn-sm post-comment-submit" onclick="Post.addComment({{$post->id}})">
                            <div class="d-flex justify-content-center align-items-center">
                                @include('elements.icon',['icon'=>'paper-plane','size'=>'medium'])
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
