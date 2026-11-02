<div class="mt-2 inline-border-tabs profile-content-tabs">
    <nav class="nav nav-pills nav-justified fw-bold profile-content-tabs-nav">
        @foreach($profileContentTabs as $tab)
            @if($tab['visible'])
                <a class="nav-item nav-link {{$tab['active'] ? 'active' : ''}}" href="{{$tab['url']}}">
                    <span class="profile-content-tab-count">{{short_number($tab['count'])}}</span>
                    <span class="profile-content-tab-label">{{$tab['label']}}</span>
                </a>
            @endif
        @endforeach
    </nav>
</div>
@if(in_array($activeFilter, ['media', 'image', 'video', 'audio']))
    <div class="profile-media-filter-section px-3">
        <div class="d-flex align-items-center justify-content-between mt-3 mb-2">
            <span class="profile-media-filter-title fw-bold text-uppercase">{{__('Recent')}}</span>
            @if($showProfileCompactMediaToggle)
                <div class="profile-media-view-toggle d-flex align-items-center">
                    <a href="{{$profileMediaFeedViewUrl}}"
                       class="profile-media-view-button {{$profileCompactMediaView ? '' : 'active'}}"
                       data-bs-toggle="tooltip"
                       data-bs-placement="top"
                       title="{{__('Feed view')}}">
                        @include('elements.icon',['icon'=>'list-outline', 'variant'=>'small'])
                    </a>
                    <a href="{{$profileMediaCompactViewUrl}}"
                       class="profile-media-view-button {{$profileCompactMediaView ? 'active' : ''}}"
                       data-bs-toggle="tooltip"
                       data-bs-placement="top"
                       title="{{__('Compact view')}}">
                        @include('elements.icon',['icon'=>'grid-outline', 'variant'=>'small'])
                    </a>
                </div>
            @endif
        </div>
        <div class="profile-media-filter-pills filter-pills">
            @foreach($profileMediaTabs as $tab)
                @if($tab['visible'])
                    <a class="filter-pill {{$tab['active'] ? 'active' : ''}}" href="{{$tab['url']}}">
                        <span>{{$tab['label']}}</span>
                        <span class="filter-pill-count">{{short_number($tab['count'])}}</span>
                    </a>
                @endif
            @endforeach
        </div>
    </div>
@endif
<div class="justify-content-center align-items-center {{(Cookie::get('app_feed_prev_page') && PostsHelper::isComingFromPostPage(request()->session()->get('_previous'))) ? 'mt-3' : 'mt-4'}}">
    @if($activeFilter === 'reels')
        @if($profileReelsCounts['accessible'] > 0 || $profileReelsCounts['locked'] === 0)
            <template id="profile-reels-empty-state-template">
                @if($profileReelsCounts['locked'] > 0)
                    @include('elements.profile.profile-reels-locked', ['lockedEmptyState' => true])
                @else
                    @include('elements.profile.profile-reels-empty-state', $profileEmptyState)
                @endif
            </template>
            @if($profileReelsCounts['locked'] > 0)
                <template id="profile-reels-locked-template">
                    @include('elements.profile.profile-reels-locked')
                </template>
            @endif
            <div id="reels-feed"
                 class="reels-feed profile-reels-feed"
                 data-feed-url="{{ route('profile.reels', ['username' => $user->username]) }}"
                 data-base-url="{{ route('profile', ['username' => $user->username]) . '?filter=reels' }}"
                 data-empty-state-template="#profile-reels-empty-state-template"
                 @if($profileReelsCounts['locked'] > 0)
                     data-locked-state-template="#profile-reels-locked-template"
                 @endif
                 data-allow-progress-scrubbing="{{ getSetting('reels.allow_progress_scrubbing') ? 1 : 0 }}"
                 data-permalink-template="{{ route('reels.get', ['reel_id' => '__REEL_ID__']) }}">
                <div class="reels-empty-state">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
        @else
            <div class="reels-feed profile-reels-feed">
                @include('elements.profile.profile-reels-locked', ['lockedEmptyState' => true])
            </div>
        @endif
    @elseif($activeFilter !== 'streams')
        @include('elements.feed.posts-load-more', ['classes' => 'mb-2'])
        <div class="feed-box mt-0 posts-wrapper {{$profileCompactMediaView ? 'profile-compact-posts-wrapper profile-compact-posts-theme-'.GenericHelper::getSiteTheme() : ''}}">
            @include($profileCompactMediaView ? 'elements.feed.posts-compact-wrapper' : 'elements.feed.posts-wrapper',[
                'posts' => $posts,
                'compactMediaType' => $activeFilter,
                'emptyStateView' => 'elements.profile.profile-posts-empty-state',
                'emptyStateData' => $profileEmptyState,
            ])
        </div>
    @else
        <div class="streams-box mt-4 streams-wrapper mb-4">
            @include('elements.search.streams-wrapper',['streams'=>$streams,'showLiveIndicators'=>true, 'showUsername' => false])
        </div>
    @endif
    @include('elements.feed.posts-loading-spinner')
</div>
