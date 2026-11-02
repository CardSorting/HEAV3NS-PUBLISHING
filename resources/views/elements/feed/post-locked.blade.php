@php
    $preview = AttachmentHelper::getPostPreviewData($post);
@endphp

<div class="px-0">
    <div class="d-flex justify-content-center align-items-center">
        <div class="w-100">
            <div class="w-100 locked-media position-relative {{ $preview['backgroundClass'] }}" style="background-image: url('{{ $preview['backgroundImage'] }}');">
                @if($preview['attachmentExists'] && $preview['hasBlurred'] && $post->price > 0)
                    @include('elements.feed.locked-post-actions')
                @endif
            </div>

            <div class="non-blur-locked-actions">
                @if((!$preview['hasBlurred'] && $preview['attachmentExists'] && $post->price > 0) || (!$preview['attachmentExists'] && $post->price > 0))
                    @include('elements.feed.locked-post-actions')
                @endif
            </div>
        </div>
    </div>
</div>
