<a href="{{route('posts.get',['post_id'=>$post->id,'username'=>$post->user->username])}}"
   class="profile-compact-post-tile {{$compactPostPreview['isLocked'] ? 'profile-compact-post-tile-locked' : ''}}"
   data-postID="{{$post->id}}"
   aria-label="{{__('View post')}}"
   onclick="PostsPaginator.goToPostPageKeepingNav({{$post->id}},{{$post->postPage}},'{{route('posts.get',['post_id'=>$post->id,'username'=>$post->user->username])}}'); return false;">
    <span class="profile-compact-post-media {{$compactPostPreview['backgroundClass']}}">
        @if($compactPostPreview['image'])
            <img src="{{$compactPostPreview['image']}}" draggable="false" alt="">
        @elseif(!$compactPostPreview['isLocked'] && $compactPostPreview['attachmentType'] === 'video')
            <span class="profile-compact-post-placeholder profile-compact-post-placeholder-video">
                @include('elements.icon',['icon'=>'play-outline', 'variant'=>'medium'])
                <span class="profile-compact-post-placeholder-label">{{__('No preview')}}</span>
            </span>
        @else
            <span class="profile-compact-post-placeholder-icon">
                @include('elements.icon',['icon'=>$compactPostPreview['icon'], 'variant'=>'medium'])
            </span>
        @endif
    </span>

    <span class="profile-compact-post-overlay">
        @if($compactPostPreview['isLocked'] || ($compactPostPreview['image'] && in_array($compactPostPreview['attachmentType'], ['video', 'audio', 'document'])))
            <span class="profile-compact-post-overlay-icon">
                @include('elements.icon',['icon'=>$compactPostPreview['icon'], 'variant'=>'small'])
            </span>
        @endif
        @if($compactPostPreview['isMultiple'])
            <span class="profile-compact-post-overlay-icon">
                @include('elements.icon',['icon'=>'images', 'variant'=>'small'])
            </span>
        @endif
    </span>
</a>
