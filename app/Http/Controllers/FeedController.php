<?php

namespace App\Http\Controllers;

use App\Providers\MembersHelperServiceProvider;
use App\Providers\GenericHelperServiceProvider;
use App\Providers\PostsHelperServiceProvider;
use App\Providers\SuggestionsServiceProvider;
use Cookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use JavaScript;
use View;

class FeedController extends Controller
{
    /**
     * Renders feed items.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        return view('pages.feed', $this->buildFeedData($request));
    }

    public function buildFeedData(Request $request): array
    {
        // Avoid page caching
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $startPage = PostsHelperServiceProvider::getFeedStartPage(
            PostsHelperServiceProvider::getPrevPage($request)
        );

        $posts = PostsHelperServiceProvider::getFeedPosts(
            Auth::user()->id,
            false,
            $startPage
        );

        PostsHelperServiceProvider::shouldDeletePaginationCookie($request);

        JavaScript::put([
            'paginatorConfig' => [
                'next_page_url' => str_replace('/feed?page=', '/feed/posts?page=', $posts->nextPageUrl()),
                'prev_page_url' => str_replace('/feed?page=', '/feed/posts?page=', $posts->previousPageUrl()),
                'current_page'  => $posts->currentPage(),
                'total'         => $posts->total(),
                'per_page'      => $posts->perPage(),
                'hasMore'       => $posts->hasMorePages(),
            ],
            'initialPostIDs' => collect($posts->items())->pluck('id')->toArray(),
            'sliderConfig' => [
                'suggestions' => [
                    'autoslide'=> (bool) getSetting('feed.feed_suggestions_autoplay'),
                ],
                'expiredSubs' => [
                    'autoslide'=> (bool) getSetting('feed.expired_subs_widget_autoplay'),
                ],
            ],
            'feedReelsWidgetConfig' => $this->feedReelsWidgetConfig(),
        ]);

        $data = [
            'posts' => $posts,
        ];

        if (!getSetting('feed.hide_suggestions_slider')) {
            $data['suggestions'] = SuggestionsServiceProvider::getSuggestedMembers();
        }

        if (!getSetting('feed.expired_subs_widget_hide')) {
            $data['expiredSubscriptions'] = MembersHelperServiceProvider::getExpiredSubscriptions();
        }

        $additionalAssets = ['js' => ['/js/messenger/messenger-modal-dm.js'], 'css' => []];
        if(getSetting('stories.stories_enabled')){
            $additionalAssets['js'][] = '/js/stories/stories-player.js';
            $additionalAssets['js'][] = '/js/stories/stories-swiper.js';
            $additionalAssets['css'][] = '/css/stories.css';
        }

        if($this->shouldShowReelsFeedWidget()){
            $additionalAssets['js'][] = '/js/reels/reels-api.js';
            $additionalAssets['js'][] = '/js/reels/reels-renderer.js';
            $additionalAssets['js'][] = '/js/reels/reels-comments.js';
            $additionalAssets['js'][] = '/js/reels/reels-player.js';
            $additionalAssets['js'][] = '/js/reels/feed-reels-widget.js';
            $additionalAssets['css'][] = '/css/pages/reels.css';
        }

        $data['additionalAssets'] = $additionalAssets;

        return $data;
    }

    protected function shouldShowReelsFeedWidget(): bool
    {
        $feedWidgetEnabled = getSetting('reels.feed_widget_enabled');

        return (bool) getSetting('reels.reels_enabled')
            && $feedWidgetEnabled !== false
            && (string) $feedWidgetEnabled !== '0';
    }

    protected function feedReelsWidgetConfig(): array
    {
        $cardsPerWidget = max(1, min((int) (getSetting('reels.feed_widget_cards_per_widget') ?: 12), 30));

        return [
            'enabled' => $this->shouldShowReelsFeedWidget(),
            'placementMode' => getSetting('reels.feed_widget_placement_mode') === 'repeat' ? 'repeat' : 'once',
            'firstAfterPosts' => max(0, (int) (getSetting('reels.feed_widget_first_after_posts') ?? 3)),
            'repeatEveryPosts' => max(1, (int) (getSetting('reels.feed_widget_repeat_every_posts') ?: 10)),
            'cardsPerWidget' => $cardsPerWidget,
            'maxWidgets' => getSetting('reels.feed_widget_placement_mode') === 'repeat' ? 50 : 1,
            'randomizeCards' => true,
            'prioritizeUnseen' => true,
            'avoidRepeats' => true,
            'feedUrl' => route('reels.feed'),
            'reelsUrl' => route('reels.index'),
            'createUrl' => route('reels.create'),
            'creationVisible' => GenericHelperServiceProvider::canViewCreationPage() || GenericHelperServiceProvider::canAccessCreatorOnboarding(),
            'baseUrl' => route('feed'),
            'permalinkTemplate' => route('reels.get', ['reel_id' => '__REEL_ID__']),
            'labels' => [
                'reels' => __('Reels'),
                'seeAll' => __('See all'),
                'createYourReel' => __('Create your reel'),
                'previous' => __('Previous'),
                'next' => __('Next'),
            ],
        ];
    }

    /**
     * Returns ( paginated ) feed psots.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFeedPosts(Request $request)
    {
        return response()->json(['success'=>true, 'data'=>PostsHelperServiceProvider::getFeedPosts(Auth::user()->id, true)]);
    }

    /**
     * Returns lists of suggested members.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function filterSuggestedMembers(Request $request)
    {
        return response()->json(['success'=>true, 'data'=>SuggestionsServiceProvider::getSuggestedMembers(true, $request->get('filters'))]);
    }
}
