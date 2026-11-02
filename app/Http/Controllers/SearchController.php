<?php

namespace App\Http\Controllers;

use App\Model\UserGender;
use App\Providers\MembersHelperServiceProvider;
use App\Providers\PostsHelperServiceProvider;
use App\Providers\StreamsServiceProvider;
use App\Providers\SuggestionsServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use JavaScript;

class SearchController extends Controller
{
    protected $searchPageMode = 'public'; // Or 'paywall'

    /**
     * Available search categories.
     * @var array
     */
    public $filters = [
        'live',
        'top',
        'latest',
        'people',
        'photos',
        'videos',
    ];

    public function __construct()
    {
        if (getSetting('streams.allow_streams') !== 'none') {
            unset($this->filters[2]);
        } else {
            unset($this->filters[0]);
        }

        if(!getSetting('site.explore_enabled')){
            abort(404);
        }

        $this->searchPageMode = getSetting('site.explore_mode');

    }

    /**
     * Main search page method.
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function index(Request $request)
    {

        // Avoid (browser) page caching when hitting back button
        header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
        header('Pragma: no-cache'); // HTTP 1.0.
        header('Expires: 0 '); // Proxies.

        // Redirecting to top/people based on request type on default /explore route
        if (!$request->has('filter')) {
            if ($this->searchPageMode === 'public') {
                return redirect()->to(route('search.get', ['filter' => 'top']).($request->get('query') ? '&query='.urlencode($request->get('query')) : ''));
            }

            if ($this->searchPageMode === 'paywall') {
                if(Auth::check()){
                    return redirect()->to(route('search.get', ['filter' => 'top']).($request->get('query') ? '&query='.urlencode($request->get('query')) : ''));
                }
                else{
                    return redirect()->to(route('search.get', ['filter' => 'people']).($request->get('query') ? '&query='.urlencode($request->get('query')) : ''));
                }
            }
        }

        $filters = $this->processFilterParams($request);
        if (!$filters['postsFilter'] && $this->searchPageMode === 'public') {
            $filters['postsFilter'] = 'top';
            $filters['sortOrder'] = 'top';
            $filters['mediaType'] = false;
        }

        // If search is paywall mode, disable non-people tabs
        if($this->searchPageMode == 'paywall') {
            // Redirecting to default people filter if user is not logged in buet selected custom filter
            if (!Auth::check() && $filters['postsFilter'] && $filters['postsFilter'] != 'people') {
                return redirect(route('search.get'));
            }

            // If no filter is selected & user not logged in, default UI to people search page
            if (!$filters['postsFilter'] && !Auth::check()) {
                $filters['postsFilter'] = 'people';
            }

            if (!Auth::check()) {
                $this->filters = ['people'];
            }
        }

        /*
         * People custom filter
         */
        if ($filters['postsFilter'] == 'people') {

            $searchFilters = [
                'gender' => $request->get('gender'),
                'min_age' => $request->get('min_age'),
                'max_age' => $request->get('max_age'),
                'location' => $request->get('location'),
            ];
            $users = MembersHelperServiceProvider::getSearchUsers(array_merge(['searchTerm' => $filters['searchTerm']], $searchFilters));
            $jsData = [
                'paginatorConfig' => [
                    'next_page_url' => str_replace('/search', '/search/users', $users->nextPageUrl()),
                    'prev_page_url' => str_replace('/search', '/search/users', $users->previousPageUrl()),
                    'current_page' => $users->currentPage(),
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'hasMore' => $users->hasMorePages(),
                ],
                'searchType' => 'people',
            ];
            if (
                $searchFilters['gender'] ||
                $searchFilters['min_age'] ||
                $searchFilters['max_age'] ||
                $searchFilters['location']
            ) {
                $searchFilterExpanded = true;
            } else {
                $searchFilterExpanded = false;
            }
            $viewData = [
                'users' => $users,
                'genders' => UserGender::all(),
                'searchFilters' => $searchFilters,
                'searchFilterExpanded' => $searchFilterExpanded,
            ];

        } /*
         * Live streams custom filter
         */
        elseif ($filters['postsFilter'] == 'live') {

            $streams = StreamsServiceProvider::getPublicStreams(['searchTerm' => $filters['searchTerm'], 'status' => 'live']);
            $jsData = [
                'paginatorConfig' => [
                    'next_page_url' => str_replace('/search', '/search/streams', $streams->nextPageUrl()),
                    'prev_page_url' => str_replace('/search', '/search/streams', $streams->previousPageUrl()),
                    'current_page' => $streams->currentPage(),
                    'total' => $streams->total(),
                    'per_page' => $streams->perPage(),
                    'hasMore' => $streams->hasMorePages(),
                ],
                'searchType' => 'streams',
            ];
            $viewData = [
                'streams' => $streams,
                'searchFilterExpanded' => false,
            ];
        } /*
         * Standard posts filters
         */
        else {
            $startPage = PostsHelperServiceProvider::getFeedStartPage(PostsHelperServiceProvider::getPrevPage($request));
            $userID = Auth::user()->id ?? null;
            $posts = PostsHelperServiceProvider::getFeedPosts($userID, false, $startPage, $filters['mediaType'], $filters['sortOrder'], $filters['searchTerm'], $this->searchPageMode);
            PostsHelperServiceProvider::shouldDeletePaginationCookie($request);
            $jsData = [
                'paginatorConfig' => [
                    'next_page_url' => str_replace('/search', '/search/posts', $posts->nextPageUrl()),
                    'prev_page_url' => str_replace('/search', '/search/posts', $posts->previousPageUrl()),
                    'current_page' => $posts->currentPage(),
                    'total' => $posts->total(),
                    'per_page' => $posts->perPage(),
                    'hasMore' => $posts->hasMorePages(),
                ],
                'initialPostIDs' => collect($posts->items())->pluck('id')->toArray(),
                'searchType' => 'feed',
            ];
            $viewData = ['posts' => $posts, 'searchFilterExpanded' => false];
        }
        JavaScript::put(
            array_merge(
                $jsData,
                [
                    'sliderConfig' => [
                        'suggestions' => [
                            'autoslide' => getSetting('feed.feed_suggestions_autoplay') ? true : false,
                        ],
                        'expiredSubs' => [
                            'autoslide' => getSetting('feed.expired_subs_widget_autoplay') ? true : false,
                        ],
                    ],
                ]
            )
        );

        $activeFilter = $filters['postsFilter'];
        $additionalData = [
            'searchTerm' => $filters['searchTerm'],
            'availableFilters' => $this->filters,
            'activeFilter' => $activeFilter,
            'searchEmptyState' => $this->searchEmptyState($request, $activeFilter, $filters['searchTerm']),
        ];

        if (!getSetting('feed.hide_suggestions_slider')){
            $additionalData['suggestions'] = SuggestionsServiceProvider::getSuggestedMembers();
        }
        if (!getSetting('feed.expired_subs_widget_hide') && Auth::check()){
            $additionalData['expiredSubscriptions'] = MembersHelperServiceProvider::getExpiredSubscriptions();
        }

        return view(
            'pages.search',
            array_merge($viewData, $additionalData)
        );

    }

    /**
     * Fetches AJAX paginated (feed search) content.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSearchPosts(Request $request)
    {
        $filters = $this->processFilterParams($request);
        if (!$filters['postsFilter'] && $this->searchPageMode === 'public') {
            $filters['postsFilter'] = 'top';
            $filters['sortOrder'] = 'top';
            $filters['mediaType'] = false;
        }
        $userID = Auth::user()->id ?? null;
        return response()->json([
            'success' => true,
            'data' => PostsHelperServiceProvider::getFeedPosts(
                $userID,
                true,
                false,
                $filters['mediaType'],
                $filters['sortOrder'],
                $filters['searchTerm'],
                $this->searchPageMode
            ),
        ]);
    }

    /**
     * Fetches AJAX paginated (users search) content.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUsersSearch(Request $request)
    {
        $filters = $this->processFilterParams($request);
        return response()->json(['success'=>true, 'data'=> MembersHelperServiceProvider::getSearchUsers(array_merge(
            ['encodePostsToHtml'=>true, 'searchTerm' => $filters['searchTerm']],
            [
                'gender' => $request->get('gender'),
                'min_age' => $request->get('min_age'),
                'max_age' => $request->get('max_age'),
                'location' => $request->get('location'),
            ]
        ))]);
    }

    /**
     * Gets paginated (public) streams.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStreamsSearch(Request $request)
    {
        $filters = $this->processFilterParams($request);
        return response()->json(['success'=>true, 'data'=> StreamsServiceProvider::getPublicStreams(['searchTerm' => $filters['searchTerm'], 'encodePostsToHtml'=>true, 'status' => 'live'])]);
    }

    /**
     * Filters out incoming search filters.
     *
     * @param $request
     * @return array
     */
    protected function processFilterParams($request) {
        $searchTerm = $request->get('query') ? $request->get('query') : false;
        $postsFilter = $request->get('filter') ? $request->get('filter') : false;

        $mediaType = false;
        if($postsFilter == 'videos'){
            $mediaType = 'video';
        }
        if($postsFilter == 'photos'){
            $mediaType = 'image';
        }
        $sortOrder = '';
        if($postsFilter == 'top'){
            $mediaType = false;
            $sortOrder = 'top';
        }
        if($postsFilter == 'latest'){
            $mediaType = false;
            $sortOrder = 'latest';
        }
        if($postsFilter == 'live') {
            $mediaType = false;
            $sortOrder = 'latest';
        }

        return [
            'searchTerm' => $searchTerm,
            'postsFilter' => $postsFilter,
            'mediaType' => $mediaType,
            'sortOrder' => $sortOrder,
        ];

    }

    protected function searchEmptyState(Request $request, $activeFilter, $searchTerm): array
    {
        $hasPeopleFilters = $activeFilter === 'people' && (
            ($request->filled('gender') && $request->get('gender') !== 'all')
            || $request->filled('min_age')
            || $request->filled('max_age')
            || $request->filled('location')
        );
        $hasCriteria = trim((string) $searchTerm) !== '' || $hasPeopleFilters;

        $title = $hasCriteria
            ? match ($activeFilter) {
                'people' => __('No creators found'),
                'live' => __('No live streams found'),
                'photos' => __('No photos found'),
                'videos' => __('No videos found'),
                default => __('No posts found'),
            }
            : match ($activeFilter) {
                'people' => __('No creators yet'),
                'live' => __('No one is live right now'),
                'photos' => __('No photos yet'),
                'videos' => __('No videos yet'),
                default => __('No posts yet'),
            };

        $copy = $hasCriteria
            ? __('Try a different search or clear your filters.')
            : match ($activeFilter) {
                'people' => __('Creators will appear here as they join.'),
                'live' => __('Live streams will appear here when creators go live.'),
                'photos' => __('New public photos will appear here.'),
                'videos' => __('New public videos will appear here.'),
                default => __('New public posts will appear here.'),
            };

        return [
            'title' => $title,
            'copy' => $copy,
            'resetUrl' => $hasCriteria ? route('search.get', ['filter' => $activeFilter ?: 'top']) : null,
        ];
    }
}
