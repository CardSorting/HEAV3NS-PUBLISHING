@extends('layouts.user-no-nav')
@section('page_title', __('Reels'))

@section('styles')
    {!! Minify::stylesheet(['/css/components/gif-media.css', '/css/components/gif-picker.css'])->withFullUrl() !!}
    {!!
        Minify::stylesheet([
            '/css/pages/reels.css',
         ])->withFullUrl()
    !!}
@stop

@section('scripts')
    @include('elements.gif.assets', ['surfaces' => ['reel_comments']])
    {!!
        Minify::javascript([
            '/js/pages/lists.js',
            '/js/reels/reels-api.js',
            '/js/reels/reels-renderer.js',
            '/js/reels/reels-comments.js',
            '/js/reels/reels-player.js',
         ])->withFullUrl()
    !!}
@stop

@section('content')
    <div class="reels-page">
        @include('elements.verified-svg-store')

        <div class="reels-topbar border-bottom  pt-4 pb-3">
            <h5 class="mb-0 fw-bold text-dark-r">
                {{ __('Explore') }} {{ __('Reels') }}
            </h5>
            @if(GenericHelper::canViewCreationPage() || GenericHelper::canAccessCreatorOnboarding())
                <a href="{{ GenericHelper::canViewCreationPage() ? route('reels.create') : GenericHelper::getCreatorOnboardingUrl() }}" class="btn btn-sm btn-outline-primary mb-0">{{ GenericHelper::canViewCreationPage() ? __('Create') : GenericHelper::getCreatorOnboardingLabel() }}</a>
            @endif
        </div>

        <template id="reels-empty-state-template">
            @include('elements.empty-state', [
                'illustration' => asset('/img/no-content-available.svg'),
                'title' => __('No reels yet'),
                'copy' => __('New reels from the community will appear here.'),
            ])
        </template>

        <div id="reels-feed"
             class="reels-feed"
             data-initial-reel="{{ $initialReelId ?? '' }}"
             data-initial-unavailable="{{ !empty($initialReelUnavailable) ? 1 : 0 }}"
             data-allow-progress-scrubbing="{{ getSetting('reels.allow_progress_scrubbing') ? 1 : 0 }}"
             data-base-url="{{ route('reels.index') }}"
             data-empty-state-template="#reels-empty-state-template"
             data-permalink-template="{{ route('reels.get', ['reel_id' => '__REEL_ID__']) }}">
            <div class="reels-empty-state">
                <div class="spinner-border text-primary" role="status"></div>
            </div>
        </div>
    </div>

    @include('elements.report-user-or-post',['reportStatuses' => ListsHelper::getReportTypes()])
    @include('elements.reels.icon-preload')
    @include('elements.reels.delete-dialogs')
@stop
