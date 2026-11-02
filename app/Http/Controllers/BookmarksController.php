<?php

namespace App\Http\Controllers;

use App\Providers\AttachmentServiceProvider;
use App\Providers\PostsHelperServiceProvider;
use App\Providers\ReelsServiceProvider;
use Cookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use JavaScript;

class BookmarksController extends Controller
{
    /**
     * Displays the default bookmarks view & available filters.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\JsonResponse|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Avoid (browser) page caching when hitting back button
        header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
        header('Pragma: no-cache'); // HTTP 1.0.
        header('Expires: 0 '); // Proxies.

        $activeTab = $request->route('type') ?: 'all';
        $bookmarkTypes = $this->bookmarkTypes;
        if (!getSetting('reels.reels_enabled')) {
            unset($bookmarkTypes['reels']);
        }

        if ($activeTab === 'reels') {
            if (!getSetting('reels.reels_enabled')) abort(404);

            if ($request->ajax()) {
                return response()->json($this->reelsPayload($request));
            }

            return view('pages.bookmarks', [
                'posts' => null,
                'bookmarkTypes' => $bookmarkTypes,
                'activeTab' => $activeTab,
                'isReelsTab' => true,
                'bookmarkEmptyState' => $this->bookmarkEmptyState($activeTab),
            ]);
        }

        $type = AttachmentServiceProvider::getActualTypeByBookmarkCategory($activeTab);

        $startPage = PostsHelperServiceProvider::getFeedStartPage(PostsHelperServiceProvider::getPrevPage($request));
        $posts = PostsHelperServiceProvider::getUserBookmarks(Auth::user()->id, false, $startPage, $type);
        PostsHelperServiceProvider::shouldDeletePaginationCookie($request);

        if ($request->method() == 'GET') {
            JavaScript::put([
                'paginatorConfig' => [
                    'next_page_url' =>$posts->nextPageUrl(),
                    'prev_page_url' => $posts->previousPageUrl(),
                    'current_page' => $posts->currentPage(),
                    'total' => $posts->total(),
                    'per_page' => $posts->perPage(),
                    'hasMore' => $posts->hasMorePages(),
                ],
                'initialPostIDs' => collect($posts->items())->pluck('id')->toArray(),
            ]);

            return view('pages.bookmarks', [
                'posts' => $posts,
                'bookmarkTypes' => $bookmarkTypes,
                'activeTab' => $activeTab,
                'isReelsTab' => false,
                'bookmarkEmptyState' => $this->bookmarkEmptyState($activeTab),
            ]);
        } else {
            return response()->json([
                'success'=>true,
                'data'=>PostsHelperServiceProvider::getUserBookmarks(Auth::user()->id, true, false, $type),
            ]);
        }
    }

    /**
     * Available bookmark types.
     * @var array
     */
    public $bookmarkTypes = [
        'all' => ['heading' => 'All Bookmarks', 'icon' => 'bookmarks'],
        'photos' => ['heading' => 'Photos', 'icon' => 'image'],
        'videos' => ['heading' => 'Videos', 'icon' => 'videocam'],
        'audio' => ['heading' => 'Audio', 'icon' => 'musical-notes'],
//        'other' => ['heading' => 'Other', 'icon' => 'person'],
        'reels' => ['heading' => 'Reels', 'icon' => 'film'],
        'locked' => ['heading' => 'Locked', 'icon' => 'lock-closed'],
    ];

    protected function reelsPayload(Request $request): array
    {
        $limit = (int) ($request->get('limit') ?: 10);
        $limit = max(1, min($limit ?: 10, 30));
        $offset = max(0, (int) $request->get('offset', 0));

        $reels = ReelsServiceProvider::forBookmarks($request->user(), $limit + 1, $offset);
        $hasMore = $reels->count() > $limit;
        $reels = $reels->take($limit)->values();

        return array_merge(
            ReelsServiceProvider::toFrontendPayload($reels, $request->user()),
            [
                'has_more' => $hasMore,
                'next_offset' => $offset + $reels->count(),
            ]
        );
    }

    protected function bookmarkEmptyState(string $activeTab): array
    {
        $title = match ($activeTab) {
            'photos' => __('No bookmarked photos yet'),
            'videos' => __('No bookmarked videos yet'),
            'audio' => __('No bookmarked audio yet'),
            'reels' => __('No bookmarked reels yet'),
            'locked' => __('No locked bookmarks yet'),
            default => __('No bookmarked posts yet'),
        };

        $copy = match ($activeTab) {
            'photos' => __('Photos you bookmark will appear here.'),
            'videos' => __('Videos you bookmark will appear here.'),
            'audio' => __('Audio you bookmark will appear here.'),
            'reels' => __('Reels you bookmark will appear here.'),
            'locked' => __('Locked posts you bookmark will appear here.'),
            default => __('Posts you bookmark will appear here.'),
        };

        $exploreFilter = match ($activeTab) {
            'photos' => 'photos',
            'videos' => 'videos',
            default => 'top',
        };

        return [
            'title' => $title,
            'copy' => $copy,
            'exploreUrl' => $activeTab === 'reels' ? null : route('search.get', ['filter' => $exploreFilter]),
        ];
    }
}
