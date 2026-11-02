<?php

namespace App\Http\Controllers;

use App\Model\Country;
use App\Providers\GenericHelperServiceProvider;
use App\Providers\ListsHelperServiceProvider;
use App\Providers\ProfileMonetizationServiceProvider;
use App\Providers\PostsHelperServiceProvider;
use App\Providers\ReelsServiceProvider;
use App\Providers\StreamsServiceProvider;
use App\Services\AbstractApiClient;
use Carbon\Carbon;
use Cookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ViewErrorBag;
use JavaScript;
use Session;

class ProfileController extends Controller
{
    protected $user;

    protected $hasSub = false;

    protected $isOwner = false;

    protected $isPublic = false;

    protected $viewerHasChatAccess = false;

    public function __construct(Request $request)
    {
        $username = $request->route('username');
        $this->user = PostsHelperServiceProvider::getUserByUsername($username);
    }

    /**
     * Renders the main profile page & first feed posts, if available.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Exception
     */
    public function index(Request $request)
    {
        // Forcing no cache, in order to be able to return from post over
        // profile w/o saving state, and be able to paginate from where we left of
        header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
        header('Pragma: no-cache'); // HTTP 1.0.
        header('Expires: 0 '); // Proxies.

        // General access rules
        $this->ensureProfileIsAccessible();
        $this->setAccessRules();

        $data['showLoginDialog'] = false;
        $errors = session()->get('errors', app(ViewErrorBag::class));
        if ($errors->getBag('default')->has('email') || $errors->getBag('default')->has('name') || $errors->getBag('default')->has('password')) {
            $data['showLoginDialog'] = true;
        }

        $postsFilter = $request->get('filter') ? $request->get('filter') : false;
        $profileCompactMediaView = $this->profileCompactMediaViewEnabled($request, $postsFilter);
        $startPage = PostsHelperServiceProvider::getFeedStartPage(PostsHelperServiceProvider::getPrevPage($request));
        $posts = PostsHelperServiceProvider::getUserPosts($this->user->id, false, $startPage, $postsFilter, $this->hasSub, $profileCompactMediaView);
        PostsHelperServiceProvider::shouldDeletePaginationCookie($request);
        $posts = $posts->appends($_GET);
        $filterTypeCounts = PostsHelperServiceProvider::getUserMediaTypesCount($this->user->id);
        $profileReelsCounts = getSetting('reels.reels_enabled')
            ? ReelsServiceProvider::profileCounts($request->user(), $this->user)
            : ['total' => 0, 'accessible' => 0, 'locked' => 0];
        $filterTypeCounts['reels'] = $profileReelsCounts['total'];
        $totalPostsCount = $postsFilter
            ? PostsHelperServiceProvider::getUserPosts($this->user->id, false, false, false, $this->hasSub)->total()
            : $posts->total();
        $offer = [];
        if ($this->user->offer && !getSetting('profiles.disable_profile_offers')) {
            $discount30 = ($this->user->profile_access_price && $this->user->offer->old_profile_access_price) ? 100 - (($this->user->profile_access_price * 100) / $this->user->offer->old_profile_access_price) : 100;
            $discount90 = 100 - (($this->user->profile_access_price_3_months * 100) / ($this->user->offer->old_profile_access_price_3_months ?: 1));
            $discount182 = 100 - (($this->user->profile_access_price_6_months * 100) / ($this->user->offer->old_profile_access_price_6_months ?: 1));
            $discount365 = 100 - (($this->user->profile_access_price_12_months * 100) / ($this->user->offer->old_profile_access_price_12_months ?: 1));
            $expiringDate = $this->user->offer->expires_at;
            $currentDate = Carbon::now();
            if ($expiringDate > $currentDate) {
                $offer = [
                    'discountAmount' => [
                        '30' => $discount30,
                        '90' => $discount90,
                        '182' => $discount182,
                        '365' => $discount365,
                    ],
                    'daysRemaining' => (int)$currentDate->diffInDays($expiringDate),
                    'expiresAt' => $expiringDate,
                ];
            }
        }

        $data = array_merge($data, [
            'user' => $this->user,
            'hasSub' => $this->hasSub,
            'posts' => $posts,
            'activeFilter' => $postsFilter,
            'filterTypeCounts' => $filterTypeCounts,
            'profileReelsCounts' => $profileReelsCounts,
            'profileContentTabs' => $this->getProfileContentTabs($totalPostsCount, $filterTypeCounts, $postsFilter),
            'profileMediaTabs' => $this->getProfileMediaTabs($filterTypeCounts, $postsFilter),
            'profileCompactMediaView' => $profileCompactMediaView,
            'showProfileCompactMediaToggle' => in_array($postsFilter, $this->profileCompactMediaFilterKeys(), true),
            'profileMediaFeedViewUrl' => $this->profileMediaViewUrl('feed', $postsFilter),
            'profileMediaCompactViewUrl' => $this->profileMediaViewUrl('compact', $postsFilter),
            'profileEmptyState' => $this->profileEmptyState($postsFilter),
            'offer'=> $offer,
            'viewerHasChatAccess'=> $this->viewerHasChatAccess,
        ]);

        $streams = null;
        if($postsFilter == 'streams'){
            $streams = StreamsServiceProvider::getPublicStreams(['userId' => $this->user->id, 'status' => 'all']);
            $data['streams'] = $streams;
        }
        $data['hasActiveStream'] = StreamsServiceProvider::getUserInProgressStream(true, $this->user->id) ? true : false;

        $data['recentMedia'] = false;
        if ($this->hasSub || (Auth::check() && Auth::user()->id == $this->user->id) || ProfileMonetizationServiceProvider::userHasOpenProfile($this->user)) {
            $data['recentMedia'] = PostsHelperServiceProvider::getLatestUserAttachments($this->user->id, 'image');
        }

        $additionalAssets = ['js' => ['/js/messenger/messenger-modal-dm.js'], 'css' => []];
        if(getSetting('profiles.allow_profile_qr_code')){
            $additionalAssets['js'][] = '/libs/easyqrcodejs/dist/easy.qrcode.min.js';
        }

        if(getSetting('stories.stories_enabled')){
            $additionalAssets['js'][] = '/js/stories/stories-player.js';
            $additionalAssets['js'][] = '/js/stories/stories-swiper.js';
            $additionalAssets['js'][] = '/js/stories/stories-profile.js';
            $additionalAssets['css'][] = '/css/stories.css';
        }

        if(getSetting('reels.reels_enabled') && $postsFilter === 'reels'){
            $additionalAssets['js'][] = '/js/reels/reels-api.js';
            $additionalAssets['js'][] = '/js/reels/reels-renderer.js';
            $additionalAssets['js'][] = '/js/reels/reels-comments.js';
            $additionalAssets['js'][] = '/js/reels/reels-player.js';
            $additionalAssets['css'][] = '/css/pages/reels.css';
        }

        $data['additionalAssets'] = $additionalAssets;

        $paginatorConfig = [
            'next_page_url' => str_replace(['?page=', '?filter='], ['/posts?page=', '/posts?filter='], $posts->nextPageUrl()),
            'prev_page_url' => str_replace(['?page=', '?filter='], ['/posts?page=', '/posts?filter='], $posts->previousPageUrl()),
            'current_page' => $posts->currentPage(),
            'total' => $posts->total(),
            'per_page' => $posts->perPage(),
            'hasMore' => $posts->hasMorePages(),
        ];

        if($streams) {
            $paginatorConfig = [
                'next_page_url' => str_replace(['?page=', '?filter='], ['/streams?page=', '/streams?filter='], $streams->nextPageUrl()),
                'prev_page_url' => str_replace(['?page=', '?filter='], ['/streams?page=', '/streams?filter='], $streams->previousPageUrl()),
                'current_page' => $streams->currentPage(),
                'total' => $streams->total(),
                'per_page' => $streams->perPage(),
                'hasMore' => $streams->hasMorePages(),
            ];
        }

        // Seo description for share urls
        $rawDescription = getSetting('profiles.allow_profile_bio_markdown') && $this->user->bio ? strip_tags(GenericHelperServiceProvider::parseProfileMarkdownBio($this->user->bio)) : $this->user->bio;
        $data['seo_description'] = $rawDescription ? str_replace(["\n", "\r"], ' ', substr($rawDescription, 0, 90)).(strlen($rawDescription) > 90 ? '...' : '') : null;

        Session::put('lastProfileUrl', route('profile', ['username'=> $this->user->username]));
        if (!Auth::check()) {
            Session::put('registrationProfileUrl', route('profile', ['username' => $this->user->username]));
        }

        JavaScript::put([
            'paginatorConfig' => $paginatorConfig,
            'messengerVars' => [
                'bootFullMessenger' => false,
            ],
            'initialPostIDs' => collect($posts->items())->pluck('id')->toArray(),
            'profileVars' => [
                'user_id' =>  $this->user->id,
                'username' => $this->user->username,
            ],
            'showLoginDialog' => $data['showLoginDialog'],
            'postsFilter' => $postsFilter,
            'storiesDeepLink' => request()->filled('story')
                ? ['story_id' => (int) request('story')]
                : null,
            'showDisabledPaywallWarning' => (Auth::check() && Auth::user()->role_id === 1 && Auth::user()->id !== $this->user->id) ? true : false,
        ]);

        // Stories
        $storiesEnabled = (bool) getSetting('stories.stories_enabled');
        $allowHighlights = (bool) getSetting('stories.allow_highlights');
        $viewer = $request->user(); // may be null
        $hasHighlights = false;
        if ($storiesEnabled) {
            // highlights check only if feature enabled
            if ($allowHighlights) {
                $hasHighlights = \App\Providers\StoriesServiceProvider::hasHighlightsForViewer($viewer, $this->user);
            }
        }

        $data = array_merge($data, [
            'storiesEnabled' => $storiesEnabled,
            'allowHighlights' => $allowHighlights,
            'hasHighlights' => $hasHighlights,
        ]);

        // Social links
        $data['profileSocialLinks'] = [];
        if(getSetting('profiles.social_links_enabled')) {
            $profileSocialLinks = GenericHelperServiceProvider::getProfileSocialLinkItems($this->user);
            $data['profileSocialLinks'] = $profileSocialLinks;
        }

        // Spotify widget
        if(getSetting('profiles.spotify_enabled')){
            $spotifyAccount = \App\Model\UserSpotifyAccount::where('user_id', $this->user->id)->first();
            $spotifyAnthem = null;
            if ($spotifyAccount && $spotifyAccount->anthem_track_id) {
                try {
                    $spotify = app(\App\Services\SpotifyService::class);
                    $track = $spotify->apiGet($spotifyAccount, '/tracks/'.$spotifyAccount->anthem_track_id);

                    $spotifyAnthem = [
                        'id' => $track['id'] ?? null,
                        'name' => $track['name'] ?? null,
                        'artist' => data_get($track, 'artists.0.name'),
                        'image' => data_get($track, 'album.images.0.url'),
                        'url' => data_get($track, 'external_urls.spotify'),
                    ];
                } catch (\Exception $e) {
                    $spotifyAnthem = null;
                }
            }
            $data['spotifyAnthem'] = $spotifyAnthem;
            $data['spotifyAccount'] = $spotifyAccount;
        }

        return view('pages.profile', $data);
    }

    /**
     * Fetches user posts, to be paginated into the profile page.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserPosts(Request $request)
    {
        $this->ensureProfileIsAccessible();
        $this->setAccessRules();
        $postsFilter = $request->get('filter') ? $request->get('filter') : false;
        $profileCompactMediaView = $this->profileCompactMediaViewEnabled($request, $postsFilter);

        return response()->json([
            'success' => true,
            'data' => PostsHelperServiceProvider::getUserPosts($this->user->id, true, false, $postsFilter, $this->hasSub, $profileCompactMediaView),
        ]);
    }

    /**
     * Fetches paginated user (public) streams.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserStreams(Request $request) {
        $this->ensureProfileIsAccessible();
        $this->setAccessRules();
        return response()->json([
            'success'=>true,
            'data'=>StreamsServiceProvider::getPublicStreams(['encodePostsToHtml'=>true, 'status' => 'all', 'showUsername'=>false]),
        ]);
    }

    public function getUserReels(Request $request)
    {
        if (!getSetting('reels.reels_enabled')) abort(404);

        $this->ensureProfileIsAccessible();
        $this->setAccessRules();

        $limit = (int) ($request->get('limit') ?: 10);
        $limit = max(1, min($limit ?: 10, 30));
        $offset = max(0, (int) $request->get('offset', 0));

        $reels = ReelsServiceProvider::forProfile($request->user(), $this->user, $limit + 1, $offset);
        $hasMore = $reels->count() > $limit;
        $reels = $reels->take($limit)->values();

        return response()->json(array_merge(
            ReelsServiceProvider::toFrontendPayload($reels, $request->user()),
            [
                'has_more' => $hasMore,
                'next_offset' => $offset + $reels->count(),
            ]
        ));
    }

    protected function getProfileContentTabs(int $totalPostsCount, array $filterTypeCounts, $activeFilter): array
    {
        $mediaCount = (int) ($filterTypeCounts['media'] ?? 0);
        $streamsCount = (int) ($filterTypeCounts['streams'] ?? 0);
        $reelsCount = (int) ($filterTypeCounts['reels'] ?? 0);

        return [
            [
                'key' => 'posts',
                'label' => __('Posts'),
                'count' => $totalPostsCount,
                'url' => $this->profileFilterUrl(),
                'active' => !$activeFilter,
                'visible' => true,
            ],
            [
                'key' => 'media',
                'label' => __('Media'),
                'count' => $mediaCount,
                'url' => $this->profileMediaUrl($filterTypeCounts),
                'active' => in_array($activeFilter, $this->profileMediaFilterKeys()),
                'visible' => $mediaCount > 0,
            ],
            [
                'key' => 'streams',
                'label' => __('Streams'),
                'count' => $streamsCount,
                'url' => $this->profileFilterUrl('streams'),
                'active' => $activeFilter === 'streams',
                'visible' => $this->streamsProfileFilterEnabled() && $streamsCount > 0,
            ],
            [
                'key' => 'reels',
                'label' => __('Reels'),
                'count' => $reelsCount,
                'url' => $this->profileFilterUrl('reels'),
                'active' => $activeFilter === 'reels',
                'visible' => $this->reelsProfileFilterVisible($reelsCount),
            ],
        ];
    }

    protected function getProfileMediaTabs(array $filterTypeCounts, $activeFilter): array
    {
        $mediaCount = (int) ($filterTypeCounts['media'] ?? 0);
        return [
            [
                'key' => 'media',
                'label' => __('All'),
                'count' => $mediaCount,
                'url' => $this->profileFilterUrl('media'),
                'active' => $activeFilter === 'media',
                'visible' => $mediaCount > 0,
            ],
            [
                'key' => 'image',
                'label' => __('Photos'),
                'count' => (int) ($filterTypeCounts['image'] ?? 0),
                'url' => $this->profileFilterUrl('image'),
                'active' => $activeFilter === 'image',
                'visible' => (int) ($filterTypeCounts['image'] ?? 0) > 0,
            ],
            [
                'key' => 'video',
                'label' => __('Videos'),
                'count' => (int) ($filterTypeCounts['video'] ?? 0),
                'url' => $this->profileFilterUrl('video'),
                'active' => $activeFilter === 'video',
                'visible' => (int) ($filterTypeCounts['video'] ?? 0) > 0,
            ],
            [
                'key' => 'audio',
                'label' => __('Audio'),
                'count' => (int) ($filterTypeCounts['audio'] ?? 0),
                'url' => $this->profileFilterUrl('audio'),
                'active' => $activeFilter === 'audio',
                'visible' => (int) ($filterTypeCounts['audio'] ?? 0) > 0,
            ],
        ];
    }

    protected function profileMediaUrl(array $filterTypeCounts): string
    {
        foreach ($this->getProfileMediaTabs($filterTypeCounts, 'media') as $tab) {
            if ($tab['visible']) {
                return $tab['url'];
            }
        }

        return $this->profileFilterUrl('media');
    }

    protected function profileFilterUrl($filter = false): string
    {
        $url = route('profile', ['username' => $this->user->username]);

        return $filter ? $url.'?filter='.$filter : $url;
    }

    protected function profileMediaViewUrl(string $view, $filter): string
    {
        return $this->profileFilterUrl($filter ?: 'media').'&view='.$view;
    }

    protected function profileMediaFilterKeys(): array
    {
        return ['media', 'image', 'video', 'audio'];
    }

    protected function profileEmptyState($activeFilter): array
    {
        $title = match ($activeFilter) {
            'media' => __('No media yet'),
            'image' => __('No photos yet'),
            'video' => __('No videos yet'),
            'audio' => __('No audio yet'),
            'reels' => __('No reels yet'),
            default => __('No posts yet'),
        };

        $copy = match ($activeFilter) {
            'media' => $this->isOwner ? __('Media from your posts will appear here.') : __("This creator hasn't shared any media yet."),
            'image' => $this->isOwner ? __('Photos from your posts will appear here.') : __("This creator hasn't shared any photos yet."),
            'video' => $this->isOwner ? __('Videos from your posts will appear here.') : __("This creator hasn't shared any videos yet."),
            'audio' => $this->isOwner ? __('Audio from your posts will appear here.') : __("This creator hasn't shared any audio yet."),
            'reels' => $this->isOwner ? __('Share your first reel with your audience.') : __("This creator hasn't shared any reels yet."),
            default => $this->isOwner ? __('Share your first post with your audience.') : __("This creator hasn't shared any posts yet."),
        };

        return [
            'title' => $title,
            'copy' => $copy,
            'showCreateAction' => $this->isOwner
                && ($activeFilter !== 'reels' || getSetting('reels.reels_enabled'))
                && GenericHelperServiceProvider::isEmailEnforcedAndValidated(),
        ];
    }

    protected function profileCompactMediaFilterKeys(): array
    {
        return ['media', 'image', 'video'];
    }

    protected function streamsProfileFilterEnabled(): bool
    {
        return getSetting('streams.streaming_driver') !== 'none';
    }

    protected function reelsProfileFilterVisible(int $reelsCount): bool
    {
        return getSetting('reels.reels_enabled')
            && ($reelsCount > 0 || (Auth::check() && Auth::user()->id === $this->user->id));
    }

    protected function profileCompactMediaViewEnabled(Request $request, $activeFilter): bool
    {
        if (!in_array($activeFilter, $this->profileCompactMediaFilterKeys(), true)) {
            return false;
        }

        $requestedView = $request->get('view');
        if (in_array($requestedView, ['feed', 'compact'], true)) {
            Cookie::queue('app_profile_media_view', $requestedView, 60 * 24 * 365);
            Cookie::queue(Cookie::forget('app_prev_post'));
            Cookie::queue(Cookie::forget('app_feed_prev_page'));

            return $requestedView === 'compact';
        }

        return Cookie::get('app_profile_media_view') === 'compact';
    }

    protected function ensureProfileIsAccessible(): void
    {
        if (!$this->user) {
            abort(404);
        }

        self::ensureUserProfileIsAccessible($this->user);
    }

    public static function ensureUserProfileIsAccessible(\App\Model\User $user): void
    {
        if (!$user->public_profile && !Auth::check()) {
            abort(403, __('Profile access is denied.'));
        }

        if (self::isUserGeoLocationBlocked($user)) {
            abort(403, __('Profile access is denied.'));
        }
    }

    /**
     * Checks if current logged user (if any) has rights to view the profile media.
     */
    protected function setAccessRules()
    {
        $viewerUser = null;
        if (Auth::check()) {
            $viewerUser = Auth::user();
        }
        if ($viewerUser) {
            $this->hasSub = PostsHelperServiceProvider::hasActiveSub($viewerUser->id, $this->user->id);
            if ($viewerUser->id === $this->user->id) {
                $this->hasSub = true;
                $this->isOwner = true;
                $this->viewerHasChatAccess = true;
            }
            if(!ProfileMonetizationServiceProvider::userHasPaidProfile($this->user) && ListsHelperServiceProvider::loggedUserIsFollowingUser($this->user->id)){
                $this->hasSub = true;
                $this->viewerHasChatAccess = true;
            }
            if(ProfileMonetizationServiceProvider::userHasOpenProfile($this->user) && ListsHelperServiceProvider::loggedUserIsFollowingUser($this->user->id)){
                $this->hasSub = true;
                $this->viewerHasChatAccess = true;
            }
            if($viewerUser->role_id === 1){
                // These only act upon profile page itself, not feed items, so disabling them allowing admins to sub as well if they want
                // It was creating confusion before
//                $this->hasSub = true;
//                $this->isOwner = true;
                $this->viewerHasChatAccess = true;
            }
        }
    }

    protected function isGeoLocationBlocked() {
        return self::isUserGeoLocationBlocked($this->user);
    }

    protected static function isUserGeoLocationBlocked(\App\Model\User $user): bool {
        if(Auth::check() && Auth::user()->role_id === 1){
            return false;
        }
        if(getSetting('security.allow_geo_blocking') && getSetting('security.abstract_api_key')){
            if($user->enable_geoblocking){
                if(isset($user->settings['geoblocked_countries'])){
                    $countries = json_decode($user->settings['geoblocked_countries']);
                    $blockedCountries = Country::whereIn('name', $countries)->get();
                    $countryCode = app(AbstractApiClient::class)->ipCountry(
                        (string) getSetting('security.abstract_api_key'),
                        (string) request()->ip(),
                    );

                    if (!$countryCode) {
                        return false;
                    }

                    foreach($blockedCountries as $country){
                        if($country->country_code == $countryCode){
                            if(!(Auth::check() && Auth::user()->id === $user->id)){
                                return true;
                            }
                        }
                    }

                }
            }
        }
        return false;
    }
}
