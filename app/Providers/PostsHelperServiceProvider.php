<?php

namespace App\Providers;

use App\Model\Attachment;
use App\Model\Hashtag;
use App\Model\HashtagLink;
use App\Model\Poll;
use App\Model\PollAnswer;
use App\Model\PollUserAnswer;
use App\Model\Post;
use App\Model\PostComment;
use App\Model\Stream;
use App\Model\Subscription;
use App\Model\Transaction;
use App\Model\UserList;
use App\Model\User;
use App\Services\YouTubeEmbed;
use App\Services\Moderation\PublicationReviewService;
use App\Services\Media\FileAttachmentService;
use App\Services\Media\FileAttachmentAccess;
use Carbon\Carbon;
use Cookie;
use DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use View;

class PostsHelperServiceProvider extends ServiceProvider
{
    public static function getPublicationReviewData(Post $content): ?array
    {
        return PublicationReviewService::canPreview($content, Auth::user()) ? PublicationReviewService::presentation($content) : null;
    }

    /**
     * Get latest user attachments.
     *
     * @param int|false $userID
     * @param string|false $type
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     * @throws \Exception
     */
    public static function getLatestUserAttachments($userID = false, $type = false)
    {
        if (!$userID) {
            if (Auth::check()) {
                $userID = Auth::user()->id;
            } else {
                throw new \Exception(__('Can not fetch latest post attachments for this profile.'));
            }
        }
        $attachments = Attachment::query()
            ->select('attachments.*')
            ->join('attachment_placements as placement', 'placement.attachment_id', '=', 'attachments.id')
            ->join('posts', 'posts.id', '=', 'placement.post_id')
            ->where('attachments.user_id', $userID);

        $attachments->whereIn('attachments.type', array_merge(
            AttachmentServiceProvider::getTypeByExtension('images'),
            ['gif'],
            AttachmentServiceProvider::getTypeByExtension('video'),
            AttachmentServiceProvider::getTypeByExtension('audio')
        ));

        if ($type) {
            $extensions = AttachmentServiceProvider::getTypeByExtension('image');
            $attachments->whereIn('attachments.type', $extensions);
        }
        // validate access for paid posts attachments
        if (!Auth::check() || ((int) Auth::user()->role_id !== 1 && (int) Auth::id() !== (int) $userID)) {
            $attachments->where(function ($query) {
                    $query->where('posts.price', '=', floatval(0))
                        ->when(Auth::check(), fn ($query) => $query->orWhereExists(function ($query) {
                            $query->selectRaw('1')->from('transactions')
                                ->whereColumn('transactions.post_id', 'posts.id')
                                ->where('transactions.type', '=', Transaction::POST_UNLOCK)
                                ->where('transactions.status', '=', Transaction::APPROVED_STATUS)
                                ->where('transactions.sender_user_id', '=', Auth::id());
                        }));
                })
                ->where(function ($query) {
                    $query->where('posts.expire_date', '>', Carbon::now());
                    $query->orWhere('posts.expire_date', null);
                })
                ->where(function ($query) {
                    $query->where('posts.release_date', '<', Carbon::now());
                    $query->orWhere('posts.release_date', null);
                })
                ->where('posts.status', Post::APPROVED_STATUS);
        }
        $attachments = $attachments->limit(3)
            ->orderByDesc('posts.created_at')->orderByDesc('posts.id')
            ->orderBy('placement.position')->orderBy('placement.id')->get();

        return $attachments;
    }

    /**
     * Get user by it's username.
     *
     * @param $username
     * @return mixed
     */
    public static function getUserByUsername($username)
    {
        return User::where('username', $username)->first();
    }

    /**
     * Get user's all active subs.
     *
     * @param $userID
     * @return mixed
     */
    public static function getUserActiveSubs($userID)
    {
        $activeSubs = Subscription::where('sender_user_id', $userID)
            ->whereIn('status', [Subscription::ACTIVE_STATUS, Subscription::CANCELED_STATUS])
            ->where('expires_at', '>', Carbon::now()->toDateTimeString())
            ->pluck('recipient_user_id')->toArray();

        return $activeSubs;
    }

    /**
     * Get following users with free profiles.
     * @param $userId
     * @return mixed
     */
    public static function getFreeFollowingProfiles($userId) {
        $followingList = UserList::where('user_id', $userId)->where('type', 'following')->with(['members', 'members.user'])->first();
        $followingUserIds = [];
        foreach($followingList->members as $member){
            if(ProfileMonetizationServiceProvider::userHasFreeProfile($member->user)){
                $followingUserIds[] = $member->user->id;
            }
        }
        return $followingUserIds;
    }

    /**
     * Check if user has active sub to another.
     *
     * @param $sender_id
     * @param $recipient_id
     * @return bool
     */
    public static function hasActiveSub($sender_id, $recipient_id)
    {
        $hasSub = Subscription::where('sender_user_id', $sender_id)
            ->where('recipient_user_id', $recipient_id)
            ->whereIn('status', [Subscription::ACTIVE_STATUS, Subscription::CANCELED_STATUS])
            ->where('expires_at', '>', Carbon::now()->toDateTimeString())
            ->count();
        if ($hasSub > 0) {
            return true;
        }

        return false;
    }

    /**
     * Gets list of posts for feed.
     * @param $userID
     * @param bool $encodePostsToHtml
     * @param int|false $pageNumber
     * @param string|false $mediaType
     * @return array|LengthAwarePaginator
     * @phpstan-return ($encodePostsToHtml is true ? array<string, mixed> : LengthAwarePaginator)
     */
    public static function getFeedPosts($userID, $encodePostsToHtml = false, $pageNumber = false, $mediaType = false, $sortOrder = false, $searchTerm = '', $searchMode = null)
    {
        return self::getFilteredPosts($userID, $encodePostsToHtml, $pageNumber, $mediaType, false, false, false, $sortOrder, $searchTerm, $searchMode);
    }

    /**
     * Gets list of posts for profile.
     * @param $userID
     * @param bool $encodePostsToHtml
     * @param int|false $pageNumber
     * @param string|false $mediaType
     * @return array|LengthAwarePaginator
     * @phpstan-return ($encodePostsToHtml is true ? array<string, mixed> : LengthAwarePaginator)
     */
    public static function getUserPosts($userID, $encodePostsToHtml = false, $pageNumber = false, $mediaType = false, $hasSub = false, $compactView = false)
    {
        return self::getFilteredPosts($userID, $encodePostsToHtml, $pageNumber, $mediaType, true, $hasSub, false, false, '', '', $compactView);
    }

    /**
     * Gets list of posts for the bookmarks page.
     * @param $userID
     * @param bool $encodePostsToHtml
     * @param int|false $pageNumber
     * @param string|false $mediaType
     * @return array|LengthAwarePaginator
     * @phpstan-return ($encodePostsToHtml is true ? array<string, mixed> : LengthAwarePaginator)
     */
    public static function getUserBookmarks($userID, $encodePostsToHtml = false, $pageNumber = false, $mediaType = false, $hasSub = false)
    {
        return self::getFilteredPosts($userID, $encodePostsToHtml, $pageNumber, $mediaType, false, $hasSub, true);
    }

    /**
     * Returns lists of posts, conditioned by different filters.
     * TODO: This one should get refactored a little bit - eg: remove all un-necessary params to differ between these feed pages:
     * feed - profile (logged in/not logged in) - search - bookmarks.
     * @param $userID
     * @param bool $encodePostsToHtml
     * @param int|false $pageNumber
     * @param string|false $mediaType
     * @return array|LengthAwarePaginator
     * @phpstan-return ($encodePostsToHtml is true ? array<string, mixed> : LengthAwarePaginator)
     */
    public static function getFilteredPosts($userID, $encodePostsToHtml, $pageNumber, $mediaType, $ownPosts, $hasSub, $bookMarksOnly, $sortOrder = false, $searchTerm = '', $searchMode = '', $compactView = false)
    {
        $relations = ['user', 'reactions', 'attachments', 'bookmarks', 'postPurchases', 'mentions.mentionedUser', 'contentReview:id,content_reviews.source_id,status,mode,attention_reason,rejection_reason,policy,created_at'];

        // Fetching basic posts information
        $posts = Post::withCount('tips')
            ->with($relations);

        // For profile page
        if ($ownPosts) {
            $posts->where('user_id', $userID);
            // Registered
            if(Auth::check() && Auth::user()->id !== $userID) {
                $posts = self::filterPosts($posts, $userID, 'scheduled');
                $posts = self::filterPosts($posts, $userID, 'approvedPostsOnly');
            }
            // Un-registered
            elseif (!Auth::check()){
                $posts = self::filterPosts($posts, $userID, 'scheduled');
                $posts = self::filterPosts($posts, $userID, 'approvedPostsOnly');
            }
            $posts = self::filterPosts($posts, $userID, 'pinned');
        }
        // For bookmarks page
        elseif ($bookMarksOnly) {
            $posts = self::filterPosts($posts, $userID, 'bookmarks');
            $posts = self::filterPosts($posts, $userID, 'blocked');
        }
        // For feed (& Default search) page
        else {
            if ($searchMode === 'public') {
                // public discover: always show all posts (but still exclude blocked if logged in)
                $posts = self::filterPosts($posts, $userID, 'blocked');
            } elseif ($searchMode === 'paywall' && $searchTerm) {
                if (!$userID) {
                    $posts->whereRaw('1=0');
                } else {
                    $posts = self::filterPosts($posts, $userID, 'all');
                    $posts = self::filterPosts($posts, $userID, 'blocked');
                }
            } else {
                // feed default / non-explore calls
                $posts = self::filterPosts($posts, $userID, 'all');
            }
        }

        if (!$ownPosts) { // More feed/bookmarks/search rules
            $posts = self::filterPosts($posts, $userID, 'scheduled');
            $posts = self::filterPosts($posts, $userID, 'approvedPostsOnly');
        }

        // Media type filters
        if ($mediaType) {
            $posts = self::filterPosts($posts, $userID, 'media', $mediaType);
        }

        // Filtering the search term
        if($searchTerm){
            $posts = self::filterPosts($posts, $userID, 'search', false, false, $searchTerm);
        }

        // Processing sorting
        $posts = self::filterPosts($posts, $userID, 'order', false, $sortOrder);

        $perPage = max(1, (int) getSetting('feed.feed_posts_per_page'));
        if ($compactView && $ownPosts && in_array($mediaType, ['media', 'image', 'video'], true)) {
            $perPage *= 3;
        }

        if ($pageNumber) {
            $posts = $posts->paginate($perPage, ['*'], 'page', $pageNumber)->appends(request()->query());
        } else {
            $posts = $posts->paginate($perPage)->appends(request()->query());
        }

        app(\App\Services\UserStaffBadge::class)->preload($posts->getCollection()->pluck('user'));

        if(Auth::check() && Auth::user()->role_id === 1){
            $hasSub = true;
        }

        // Precomputes for splitting between paywall or public search; granting proper $hasSub
        $viewerIsAdmin = Auth::check() && Auth::user()->role_id === 1;

        $allowedAuthorIds = [];
        if ($searchMode === 'public' && $userID) {
            $allowedAuthorIds = array_values(array_unique(array_merge(
                self::getUserActiveSubs($userID),
                self::getFreeFollowingProfiles($userID),
                [$userID]
            )));
        }

        // Shared access resolver for both mappers
        $resolveIsSubbed = function ($post) use ($viewerIsAdmin, $ownPosts, $hasSub, $searchMode, $userID, $allowedAuthorIds) {
            // Admin sees everything
            if ($viewerIsAdmin) {
                return true;
            }
            // Profile page logic (single creator page)
            if ($ownPosts) {
                return (bool) $hasSub;
            }
            // Public discover search: show all posts, but only unlock if open or accessible
            if ($searchMode === 'public') {
                $isOpen = (bool) ($post->user->is_open ?? false);
                $hasAccess = $userID ? in_array($post->user_id, $allowedAuthorIds, true) : false;
                return $isOpen || $hasAccess;
            }

            // Paywall search: never unlocked for guests
            if ($searchMode === 'paywall' && !$userID) {
                return false;
            }

            // Feed/bookmarks/paywall-search (authed): preserve existing behavior
            return true;
        };

        if ($encodePostsToHtml) {
            // JS rendered feed
            $data = [
                'total' => $posts->total(),
                'currentPage' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'prev_page_url' => $posts->previousPageUrl(),
                'next_page_url' => $posts->nextPageUrl(),
                'first_page_url' => $posts->nextPageUrl(),
                'hasMore' => $posts->hasMorePages(),
            ];
            $postsData = $posts->map(function ($post) use ($resolveIsSubbed, $ownPosts, $hasSub, $data, $compactView, $mediaType) {
                if ($ownPosts) {
                    $post->setAttribute('isSubbed', (bool) $hasSub);
                } else {
                    $post->setAttribute('isSubbed', (bool) $resolveIsSubbed($post));
                }
                $post->setAttribute('postPage', $data['currentPage']);
                $postView = View::make($compactView ? 'elements.feed.post-compact-tile' : 'elements.feed.post-box')
                    ->with('post', $post);
                if ($compactView) {
                    $postView->with('compactPostPreview', self::getPostCompactMediaPreviewData($post, $mediaType));
                }
                return [
                    'id' => $post->id,
                    'html' => $postView->render(),
                ];
            });
            $data['posts'] = $postsData;
        } else {
            // Server side rendered feed
            $postsCurrentPage = $posts->currentPage();
            $posts->map(function ($post) use ($resolveIsSubbed, $ownPosts, $hasSub, $postsCurrentPage) {
                if ($ownPosts) {
                    $post->hasSub = $hasSub;
                }
                $post->setAttribute('isSubbed', (bool) $resolveIsSubbed($post));
                $post->setAttribute('postPage', $postsCurrentPage);
                return $post;
            });
            $data = $posts;
        }
        return $data;
    }

    /**
     * Filters out posts using fast, join based queries.
     * @param $posts
     * @param $userID
     * @param $filterType
     * @param string|false $mediaType
     * @return mixed
     */
    public static function filterPosts($posts, $userID, $filterType, $mediaType = false, $sortOrder = false, $searchTerm = '')
    {
        if ($filterType == 'following' || $filterType == 'all') {
            // Followers only
            if($userID){
                $posts->join('user_list_members as following', function ($join) {
                    $join->on('following.user_id', '=', 'posts.user_id');
                    $join->on('following.list_id', '=', DB::raw(Auth::user()->lists->firstWhere('type', 'following')->id));
                });
            }
        }

        if ($filterType == 'blocked' || $filterType == 'all') {
            // Blocked users
            if($userID){
                $blockedUsers = ListsHelperServiceProvider::getListMembers(Auth::user()->lists->firstWhere('type', 'blocked')->id);
                $posts->whereNotIn('posts.user_id', $blockedUsers);
            }
        }

        if ($filterType == 'subs' || $filterType == 'all') {
            if($userID){
                if($filterType == 'all'){
                    $userIds = array_merge(self::getUserActiveSubs($userID), self::getFreeFollowingProfiles($userID));
                    $posts->whereIn('posts.user_id', $userIds);
                } else {
                    // Subs only
                    $activeSubs = self::getUserActiveSubs($userID);
                    $posts->whereIn('posts.user_id', $activeSubs);
                }
            }
            // Else: Don't filter anything, return all posts, for search page when un-auth (and site settings set so)
        }

        if ($filterType == 'bookmarks') {
            $posts->join('user_bookmarks', function ($join) use ($userID) {
                $join->on('user_bookmarks.post_id', '=', 'posts.id');
                $join->on('user_bookmarks.user_id', '=', DB::raw($userID));
            });
            $posts->orderBy('user_bookmarks.created_at', 'DESC');
            // Filtering allowed userIDs only for active bookmarks
            $userIds = array_merge(self::getUserActiveSubs($userID), self::getFreeFollowingProfiles($userID), [$userID]);
            $posts->whereIn('posts.user_id', $userIds);
        }

        if ($filterType == 'media') {
            // This guy is not really that optimal but neither bookmarks is heavy accessed
            if ($mediaType == 'media') {
                $mediaTypes = array_merge(AttachmentServiceProvider::getTypeByExtension('images'), ['gif'], AttachmentServiceProvider::getTypeByExtension('video'), AttachmentServiceProvider::getTypeByExtension('audio'));
                $posts->whereHas('attachments', fn ($query) => $query->whereIn('attachments.type', $mediaTypes));
            } else {
                $mediaTypes = AttachmentServiceProvider::getTypeByExtension($mediaType);
                $posts->whereHas('attachments', function ($query) use ($mediaTypes) {
                    $query->whereIn('type', $mediaTypes);
                });
            }
        }

        if ($filterType == 'search'){
            $posts->where(
                function ($query) use ($searchTerm) {
                    $query->where('text', 'like', '%'.$searchTerm.'%')
                        ->orWhereHas('user', function ($q) use ($searchTerm) {
                            $q->where('username', 'like', '%'.$searchTerm.'%');
                            $q->orWhere('name', 'like', '%'.$searchTerm.'%');
                        });
                }
            );
        }

        if ($filterType == 'pinned'){
            $posts->orderBy('is_pinned', 'DESC');
        }

        if ($filterType == 'order'){
            if($sortOrder){
                if($sortOrder == 'top'){
                    $relationsCount = ['reactions', 'comments'];
                    $posts->withCount($relationsCount);
                    $posts->orderBy('comments_count', 'DESC');
                    $posts->orderBy('reactions_count', 'DESC');
                }
                elseif($sortOrder == 'latest'){
                    $posts->orderBy('release_date', 'DESC');
                    $posts->orderBy('created_at', 'DESC');
                }
            }
            else{
                $posts->orderBy('release_date', 'DESC');
                $posts->orderBy('created_at', 'DESC');
            }
        }

        if ($filterType == 'scheduled') {
            $posts->notExpiredAndReleased();
        }

        if ($filterType == 'approvedPostsOnly') {
            if (!(Auth::check() && (Auth::user()->role_id === 1))) { // Admin can preview all  types of posts
                $posts->where('status', Post::APPROVED_STATUS);
            }
        }

        return $posts;
    }

    /**
     * Returns all comments for a post.
     * @param $post_id
     * @param int $limit
     * @param string $order
     * @param bool $encodePostsToHtml
     * @param int|null $targetCommentId
     * @return array|LengthAwarePaginator
     * @phpstan-return ($encodePostsToHtml is true ? array<string, mixed> : LengthAwarePaginator)
     */
    public static function getPostComments(
        $post_id,
        $limit = 9,
        $order = 'DESC',
        $encodePostsToHtml = false,
        ?int $targetCommentId = null
    )
    {
        $commentRelations = [
            'author',
            'post.user',
            'reactions',
            'mentions.mentionedUser',
        ];

        $targetComment = $targetCommentId
            ? PostComment::query()->where('post_id', $post_id)->find($targetCommentId)
            : null;
        $targetRootId = $targetComment
            ? ($targetComment->parent_id ?: $targetComment->id)
            : null;
        $targetPage = null;

        if ($targetRootId) {
            $targetRoot = PostComment::query()->where('post_id', $post_id)->find($targetRootId);
            if ($targetRoot) {
                $newerRootCount = PostComment::query()
                    ->where('post_id', $post_id)
                    ->whereNull('parent_id')
                    ->where(function ($query) use ($targetRoot) {
                        $query->where('created_at', '>', $targetRoot->created_at)
                            ->orWhere(function ($query) use ($targetRoot) {
                                $query->where('created_at', $targetRoot->created_at)
                                    ->where('id', '>', $targetRoot->id);
                            });
                    })
                    ->count();
                $targetPage = intdiv($newerRootCount, max(1, (int) $limit)) + 1;
            }
        }

        $comments = PostComment::with($commentRelations)
            ->withCount('replies')
            ->where('post_id', $post_id)
            ->whereNull('parent_id')
            ->orderBy('created_at', $order)
            ->orderBy('id', $order)
            ->paginate($limit, ['*'], 'page', $targetPage);

        if ($encodePostsToHtml) {
            $firstCommentId = PostComment::query()
                ->where('post_id', $post_id)
                ->whereNull('parent_id')
                ->oldest('created_at')
                ->oldest('id')
                ->value('id');

            $rootComments = $comments->getCollection();
            $replyIds = collect();

            foreach ($rootComments as $comment) {
                $replyIds = $replyIds->merge(PostComment::query()
                    ->where('parent_id', $comment->id)
                    ->oldest('created_at')
                    ->oldest('id')
                    ->limit(2)
                    ->pluck('id'));
            }

            if (
                $targetComment
                && $targetComment->parent_id
                && $rootComments->contains('id', $targetComment->parent_id)
            ) {
                $replyIds->push($targetComment->id);
            }

            $repliesByRoot = collect();
            if ($replyIds->isNotEmpty()) {
                $repliesByRoot = PostComment::with(array_merge($commentRelations, ['replyTarget.author']))
                    ->whereIn('id', $replyIds->unique()->values())
                    ->oldest('created_at')
                    ->oldest('id')
                    ->get()
                    ->groupBy('parent_id');
            }

            foreach ($rootComments as $comment) {
                $comment->setRelation(
                    'replies',
                    $repliesByRoot->get($comment->id, collect())->values()
                );
            }

            app(\App\Services\UserStaffBadge::class)->preload(
                $rootComments->pluck('author')->merge(
                    $repliesByRoot->flatMap(fn ($replies) => $replies->pluck('author'))
                )
            );

            $data = [
                'total' => $comments->total(),
                'currentPage' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'prev_page_url' => $comments->previousPageUrl(),
                'next_page_url' => $comments->nextPageUrl(),
                'first_page_url' => $comments->nextPageUrl(),
                'hasMore' => $comments->hasMorePages(),
            ];
            $commentsData = $comments->map(function ($comment) use ($firstCommentId) {
                $post = [
                    'id' => $comment->id,
                    'post_id' => $comment->post_id,
                    'html' => View::make('elements.feed.post-comment-thread')
                        ->with('comment', $comment)
                        ->with('isFirst', (int) $comment->id === (int) $firstCommentId)
                        ->render(),
                ];
                return $post;
            });
            $data['comments'] = $commentsData;
        } else {
            $data = $comments;
        }

        return $data;
    }

    /**
     * Check if user has unlocked a post.
     * @param $transactions
     * @return bool
     */
    public static function hasUserUnlockedPost($transactions)
    {
        if (Auth::check()) {
            if(Auth::user()->role_id === 1) {
                return true;
            }

            foreach ($transactions as $transaction) {
                if (Auth::user()->id == $transaction->sender_user_id
                    && $transaction->status === Transaction::APPROVED_STATUS
                    && $transaction->type === Transaction::POST_UNLOCK) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function isPostPPVLocked($post): bool
    {
        if ((float) $post->price <= 0) {
            return false;
        }

        if (!Auth::check()) {
            return true;
        }

        if (Auth::id() === (int) $post->user_id) {
            return false;
        }

        return !self::hasUserUnlockedPost($post->postPurchases);
    }

    public static function isPostMediaUnlocked($post): bool
    {
        return FileAttachmentAccess::canViewPostPublication($post, Auth::user())
            && self::isPostSubscriptionUnlocked($post) && !self::isPostPPVLocked($post);
    }

    /** @return array{youtubeVideoIds: list<string>, postMediaCount: int, mediaAttachments: \Illuminate\Support\Collection, fileAttachments: array, fileCount: int, filesUnlocked: bool} */
    public static function getPostMediaData(Post $post): array
    {
        $youtubeVideoIds = app(YouTubeEmbed::class)->forPost($post->text);
        $files = $post->attachments->filter(fn ($attachment) => FileAttachmentService::isFile($attachment));
        $media = $post->attachments->reject(fn ($attachment) => FileAttachmentService::isFile($attachment))->values();
        $unlocked = self::isPostMediaUnlocked($post);

        return [
            'youtubeVideoIds' => $youtubeVideoIds,
            'postMediaCount' => $media->count() + count($youtubeVideoIds),
            'mediaAttachments' => $media,
            'fileAttachments' => $unlocked ? $files->map(fn ($attachment) => app(FileAttachmentService::class)->payload(
                $attachment,
                route('file-attachments.post', ['post' => $post->id, 'attachment' => $attachment->id])
            ))->values()->all() : [],
            'fileCount' => $files->count(),
            'filesUnlocked' => $unlocked,
        ];
    }

    public static function getPostCompactMediaPreviewData(Post $post, $mediaType = null): array
    {
        $attachment = self::getPostCompactMediaPreviewAttachment($post, $mediaType);
        $attachmentType = $attachment ? (FileAttachmentService::isFile($attachment) ? 'file' : AttachmentServiceProvider::getAttachmentType($attachment->type)) : 'post';
        $isUnlocked = $attachment && self::isPostMediaUnlocked($post);
        $image = null;
        $backgroundClass = '';

        if ($isUnlocked && in_array($attachmentType, ['image', 'video'], true) && $attachment->has_thumbnail) {
            $image = AttachmentServiceProvider::getThumbnailPathForAttachmentByResolution($attachment, 150, 150);
        }

        if (!$isUnlocked) {
            $preview = AttachmentServiceProvider::getPostPreviewData($post);
            $image = $preview['backgroundImage'];
            $backgroundClass = $preview['hasBlurred'] ? '' : 'profile-compact-post-media-contain';
        }

        return [
            'attachmentType' => $attachmentType,
            'backgroundClass' => $backgroundClass,
            'icon' => self::getPostCompactMediaIcon($attachmentType, !$isUnlocked),
            'image' => $image,
            'isLocked' => !$isUnlocked,
            'isMultiple' => $post->attachments->count() > 1,
        ];
    }

    protected static function getPostCompactMediaPreviewAttachment(Post $post, $mediaType = null)
    {
        if (in_array($mediaType, ['image', 'video'], true)) {
            $mediaTypes = AttachmentServiceProvider::getTypeByExtension($mediaType);

            return $post->attachments->first(function ($attachment) use ($mediaTypes) {
                return $attachment instanceof Attachment
                    && in_array($attachment->type, $mediaTypes, true);
            });
        }

        return $post->attachments->first(fn ($attachment) => !FileAttachmentService::isFile($attachment)) ?? $post->attachments->first();
    }

    protected static function getPostCompactMediaIcon(string $attachmentType, bool $locked): string
    {
        if ($locked) {
            return 'lock-closed-outline';
        }

        return match ($attachmentType) {
            'video' => 'play',
            'audio' => 'musical-notes-outline',
            'document' => 'document-outline',
            'file' => 'document-outline',
            default => 'image-outline',
        };
    }

    /** Check if user reacted to a post / comment.
     * @param $reactions
     * @return bool
     */
    public static function didUserReact($reactions)
    {
        if (Auth::check()) {
            foreach ($reactions as $reaction) {
                if (Auth::user()->id == $reaction->user_id) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if post is bookmarked by current user.
     * @param $bookmarks
     * @return bool
     */
    public static function isPostBookmarked($bookmarks)
    {
        if (Auth::check()) {
            foreach ($bookmarks as $bookmark) {
                if (Auth::user()->id == $bookmark->user_id) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if user is coming back to a paginated feed post from a post page.
     * @param $page
     * @return bool
     */
    public static function isComingFromPostPage($page)
    {
        if (isset($page) && is_int(strpos($page['url'], '/posts')) && !is_int(strpos($page['url'], '/posts/create'))) {
            return true;
        }

        return false;
    }

    /**
     * Get (user session) start page of the feed pagination.
     * @param $prevPage
     * @return int
     */
    public static function getFeedStartPage($prevPage)
    {
        return Cookie::get('app_feed_prev_page') && self::isComingFromPostPage($prevPage) ? Cookie::get('app_feed_prev_page') : 1;
    }

    /**
     * Get (user session) prev page of the feed pagination.
     * @param $request
     * @return mixed
     */
    public static function getPrevPage($request)
    {
        return $request->session()->get('_previous');
    }

    /**
     * Check if the pagination cookie should be deleted when navigating.
     * @param $request
     * @return bool
     */
    public static function shouldDeletePaginationCookie($request)
    {
        if (!self::isComingFromPostPage(self::getPrevPage($request))) {
            Cookie::queue(Cookie::forget('app_feed_prev_page'));
            Cookie::queue(Cookie::forget('app_prev_post'));
            return true;
        }

        return false;
    }

    /**
     * Returns count of each attachment types for user.
     * @param $userID
     * @return array
     */
    public static function getUserMediaTypesCount($userID)
    {
        $attachments = Attachment::query()->select('attachments.*')
            ->join('attachment_placements as placement', 'placement.attachment_id', '=', 'attachments.id')
            ->join('posts', 'posts.id', '=', 'placement.post_id')
            ->where('attachments.user_id', $userID)
            ->where('posts.status', Post::APPROVED_STATUS)
            ->where(function ($query) {
                $query->where('posts.expire_date', '>', Carbon::now());
                $query->orWhere('posts.expire_date', null);
            })
            ->where(function ($query) {
                $query->where('posts.release_date', '<', Carbon::now());
                $query->orWhere('posts.release_date', null);
            })
            ->get();
        $typeCounts = [
            'video' => 0,
            'audio' => 0,
            'image' => 0,
        ];
        foreach ($attachments as $attachment) {
            if (FileAttachmentService::isFile($attachment)) continue;
            $attachmentType = AttachmentServiceProvider::getAttachmentType($attachment->type);
            if(isset($typeCounts[$attachmentType])){
                $typeCounts[$attachmentType] += 1;
            }
        }
        $typeCounts['media'] = $typeCounts['video'] + $typeCounts['audio'] + $typeCounts['image'];
        $streams = Stream::where('user_id', $userID)->where('is_public', 1)->whereIn('status', [Stream::ENDED_STATUS, Stream::IN_PROGRESS_STATUS])->count();
        $typeCounts['streams'] = $streams;
        return $typeCounts;
    }

    /**
     * Check if user paid for post.
     * @param $userId
     * @param $postId
     * @return bool
     */
    public static function userPaidForPost($userId, $postId) {
        return Transaction::query()->where(
            [
                    'post_id' => $postId,
                    'sender_user_id' => $userId,
                    'type' => Transaction::POST_UNLOCK,
                    'status' => Transaction::APPROVED_STATUS,
                ]
        )->first() != null;
    }

    /**
     * Check if user paid for stream access.
     * @param $userId
     * @param $streamId
     * @return bool
     */
    public static function userPaidForStream($userId, $streamId) {
        return Transaction::query()->where(
            [
                    'stream_id' => $streamId,
                    'sender_user_id' => $userId,
                    'type' => Transaction::STREAM_ACCESS,
                    'status' => Transaction::APPROVED_STATUS,
                ]
        )->first() != null;
    }

    /**
     * Checks if user paid access for this message.
     * @param $userId
     * @param $messageId
     * @return bool
     */
    public static function userPaidForMessage($userId, $messageId) {
        return Transaction::query()->where(
            [
                    'user_message_id' => $messageId,
                    'sender_user_id' => $userId,
                    'type' => Transaction::MESSAGE_UNLOCK,
                    'status' => Transaction::APPROVED_STATUS,
                ]
        )->first() != null;
    }

    /**
     * Returns number of approved posts.
     * @param $userID
     * @return mixed
     */
    public static function getUserApprovedPostsCount($userID) {
        return $postsCount = Post::where([
            'user_id' =>  $userID,
            'status' => Post::APPROVED_STATUS,
        ])->count();
    }

    public static function getPostsCountLeftTillAutoApprove($userID) {
        return (int)getSetting('compliance.admin_approved_posts_limit') - self::getUserApprovedPostsCount(Auth::user()->id);
    }

    public static function getPostReviewWarning($userID, ?Post $post = null): string
    {
        if (app(PublicationReviewService::class)->requiresReview($post ?? new Post(['user_id' => $userID]))) {
            return __('Your post will be reviewed before it becomes visible.');
        }

        $remaining = max(0, (int) getSetting('compliance.admin_approved_posts_limit') - self::getUserApprovedPostsCount($userID));

        return $remaining > 0
            ? trans_choice('Your next :count posts require admin approval.', $remaining, ['count' => $remaining])
            : '';
    }

    /**
     * Returns the default status for post to be created
     * If admin_approved_posts_limit is > 0, user must have had more posts than that number
     * Otherwise, post goes to pending state.
     * @return int
     */
    public static function getDefaultPostStatus($userID, ?Post $post = null) {
        if (app(PublicationReviewService::class)->requiresReview($post ?? new Post(['user_id' => $userID]))) {
            return Post::PENDING_STATUS;
        }
        $postStatus = Post::APPROVED_STATUS;
        if(getSetting('compliance.admin_approved_posts_limit')){
            $postsCount = self::getUserApprovedPostsCount($userID);
            if((int)getSetting('compliance.admin_approved_posts_limit') > $postsCount){
                $postStatus = Post::PENDING_STATUS;
            }
        }
        return $postStatus;
    }

    /**
     * Counts attachment types for a post's locked-content summary.
     * @param $attachments
     * @return array
     */
    public static function getAttachmentsTypesCount($attachments) {
        $counts = [
            'image' => 0,
            'video' => 0,
            'audio' => 0,
            'file' => 0,
        ];
        foreach($attachments as $attachment){
            $attachmentType = FileAttachmentService::isFile($attachment)
                ? 'file'
                : AttachmentServiceProvider::getAttachmentType($attachment->type);
            if(isset($counts[$attachmentType])){
                $counts[$attachmentType] += 1;
            }
        }
        return $counts;
    }

    /**
     * Determines if a post has no media attachments.
     * @param $attachments
     * @return bool
     */
    public static function hasNoMedia($attachments): bool {
        $counts = self::getAttachmentsTypesCount($attachments);
        return array_sum($counts) === 0;
    }

    /**
     * Sends post-notifications to users.
     *
     * @param Post $post
     * @return void
     */
    public static function sendPostNotifications(Post $post): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => self::sendPostNotifications($post));
            return;
        }
        // Claim delivery before contacting email providers; concurrent approvals/cron must not replay it.
        $claimed = Post::query()->whereKey($post->id)->where('status', Post::APPROVED_STATUS)
            ->where('notify_followers', true)->whereNull('notifications_sent_at')
            ->whereDoesntHave('user.activeBan')
            ->where(fn ($query) => $query->whereNull('release_date')->orWhere('release_date', '<=', now()))
            ->where(fn ($query) => $query->whereNull('expire_date')->orWhere('expire_date', '>', now()))
            ->update(['notifications_sent_at' => now()]);
        if (!$claimed) return;

        $post->loadMissing('user');
        if (!$post->user) {
            return;
        }

        $originalLocale = App::getLocale();

        // Grabbing followers
        $followers = ListsHelperServiceProvider::getUserFollowers($post->user_id);
        // Sending them email notifications, if site & user settings allows it
        foreach($followers as $follower){
            $serializedSettings = json_decode($follower['settings']);
            if(isset($serializedSettings->notification_email_new_post_created) && $serializedSettings->notification_email_new_post_created == 'true'){
                App::setLocale($serializedSettings->locale ?? $originalLocale);

                try {
                    EmailsServiceProvider::sendGenericEmail(
                        [
                            'email' => $follower['email'],
                            'subject' => __('New content from @:username', ['username' => $post->user->username]),
                            'title' => __('Hello, :name,', ['name'=>$follower['name']]),
                            'content' => __('New content from people you follow is available', ['siteName'=>getSetting('site.name')]),
                            'button' => [
                                'text' => __('View your feed'),
                                'url' => route('feed'),
                            ],
                        ]
                    );
                } finally {
                    App::setLocale($originalLocale);
                }
            }
        }
    }

    /**
     * Sends admin notifications on posts to be approved.
     * @return void
     */
    public static function sendAdminPostsApprovalNotifications()
    {
        // Sending out admin email
        $adminEmails = User::where('role_id', 1)->select(['email', 'name'])->get();
        foreach ($adminEmails as $user) {
            EmailsServiceProvider::sendGenericEmail(
                [
                    'email' => $user->email,
                    'subject' => __('Action required | New post pending approval'),
                    'title' => __('Hello, :name,', ['name' => $user->name]),
                    'content' => __('There is a new post pending your approval on :siteName.', ['siteName' => getSetting('site.name')]),
                    'button' => [
                        'text' => __('Go to admin'),
                        // TODO: Review filter
                        'url' => url()->route('filament.admin.resources.posts.index').'?tableFilters[queryBuilder][rules][O1Dc][type]=status&tableFilters[queryBuilder][rules][O1Dc][data][operator]=equals&tableFilters[queryBuilder][rules][O1Dc][data][settings][text]=0',
                    ],
                ]
            );
        }
    }

    /**
     * Creates a new poll.
     * @param $postID
     * @param $pollAnswers
     * @return true
     */
    public static function createNewPoll($postID, $pollAnswers)
    {
        $pollID = Poll::create([
            'user_id' => Auth::user()->id,
            'post_id' => $postID,
            'ends_at' => null,
        ])->id;
        foreach($pollAnswers as $pollAnswer){
            PollAnswer::create([
                'poll_id' => $pollID,
                'answer' => $pollAnswer['value'],
            ]);
        }
        return true;
    }

    /**
     * Update existing poll.
     * @param $post
     * @param $pollAnswers
     * @return true
     */
    public static function updatePoll($post, $pollAnswers)
    {
        // Get existing answers keyed by ID
        $existingAnswers = $post->poll->answers->keyBy('id');

        // Loop over answers from the request
        foreach ($pollAnswers as $answerData) {
            $answerId = $answerData['id'] ?? null;
            $answerValue = $answerData['value'];

            if ($answerId && $existingAnswers->has($answerId)) {
                // Update existing
                $existingAnswers[$answerId]->update([
                    'answer' => $answerValue,
                ]);
                // Remove from the list so it's not deleted
                $existingAnswers->forget($answerId);

            } else {
                // Create new
                PollAnswer::create([
                    'poll_id' => $post->poll->id,
                    'answer'  => $answerValue,
                ]);
            }
        }

        // Delete leftovers
        foreach ($existingAnswers as $toDelete) {
            $toDelete->delete();
        }

        return true;
    }

    /**
     * Checks if user has voted in a particular poll
     * If so, returns that poll answer id.
     * @param $pollID
     * @return int|null
     */
    public static function hasUserVotedInPoll($pollID)
    {
        if(Auth::check()) {
            $pollAnswer = PollUserAnswer::where('user_id', Auth::user()->id)->where('poll_id', $pollID)->first();
            if ($pollAnswer) {
                return $pollAnswer->answer->id;
            }
        }
        return null;
    }

    /**
     * Returns a wrap up of a posts
     * EG: Aggregates and percentages on answers.
     * @param $poll
     * @return \Illuminate\Support\Collection
     */
    public static function getPollResults($poll)
    {
        // 1) Count the total votes across all answers
        $totalVotes = $poll->answers->reduce(function ($carry, $answer) {
            return $carry + $answer->votes->count();
        }, 0);

        // 2) Map each answer to an array containing votes & percentage
        $results = $poll->answers->map(function ($answer) use ($totalVotes) {
            $votesCount = $answer->votes->count();
            return [
                'id'        => $answer->id,
                'answer'    => $answer->answer,
                'votes'     => $votesCount,
                'percentage'=> $totalVotes > 0
                    ? round(($votesCount / $totalVotes) * 100, 2)
                    : 0,
            ];
        });
        return collect([
            'totalVotes' => $totalVotes,
            'answers' => $results,
        ]);
    }

    public static function isPostAudienceUnlocked($post): bool
    {
        if (!Auth::check() && !$post->user->public_profile) return false;

        return (bool) $post->isSubbed || ProfileMonetizationServiceProvider::userHasOpenProfile($post->user);
    }

    public static function isPostSubscriptionUnlocked($post) {
        return self::isPostAudienceUnlocked($post)
            || ((float) $post->price > 0 && self::hasUserUnlockedPost($post->postPurchases));
    }

    public static function shouldHidePostText($post) {
        $isLoggedIn = Auth::check();
        $isOwner = $isLoggedIn && Auth::id() === $post->user_id;
        $isSubscriptionUnlocked = self::isPostSubscriptionUnlocked($post);
        $isPPVLocked = (!$isOwner && $post->price > 0 && (!$isLoggedIn || !self::hasUserUnlockedPost($post->postPurchases)));
        $isTextPreviewDisabled = getSetting('feed.disable_posts_text_preview');
        $shouldHideText = $isTextPreviewDisabled && ($isPPVLocked || !$isSubscriptionUnlocked);
        return $shouldHideText;
    }

    public static function renderPostText(Post $post): string
    {
        $clean = GenericHelperServiceProvider::parseSafeHTML($post->text ?? '');

        $enableHashtags = (bool) getSetting('feed.enable_hashtags');
        $enableMentions = (bool) getSetting('feed.enable_mentions');

        $valid = [];

        if ($enableMentions && $post->relationLoaded('mentions')) {
            foreach ($post->mentions as $m) {
                if ($m->mentionedUser && $m->mentionedUser->username) {
                    $username = $m->mentionedUser->username;
                    $valid[Str::lower($username)] = $username; // lowercase key => canonical username
                }
            }
        }

        return self::linkifyHashtagsAndMentions($clean, $valid, $enableHashtags, $enableMentions);
    }

    public static function renderCommentText(PostComment $comment): string
    {
        $clean = GenericHelperServiceProvider::parseSafeHTML($comment->message ?? '');

        $enableHashtags = (bool) getSetting('feed.enable_hashtags');
        $enableMentions = (bool) getSetting('feed.enable_mentions');

        $valid = [];

        if ($enableMentions && $comment->relationLoaded('mentions')) {
            foreach ($comment->mentions as $m) {
                if ($m->mentionedUser && $m->mentionedUser->username) {
                    $username = $m->mentionedUser->username;
                    $valid[Str::lower($username)] = $username; // important for matching + URL building
                }
            }
        }

        return self::linkifyHashtagsAndMentions($clean, $valid, $enableHashtags, $enableMentions);
    }

    private static function linkifyHashtagsAndMentions(
        string $html,
        array $validMentionsSet = [],
        bool $enableHashtags = true,
        bool $enableMentions = true
    ): string {
        if ($enableHashtags) {
            $html = preg_replace_callback(
                '/(^|[\s(>])#([A-Za-z0-9_]{1,64})(?![A-Za-z0-9_])/u',
                function ($m) {
                    $lead = $m[1];
                    $tag = $m[2];
                    $tagLower = Str::lower($tag);

                    $href = route('search.get', ['filter' => 'top', 'query' => '#'.$tagLower]);

                    return $lead.'<a href="'.e($href).'" class="hashtag" rel="nofollow noopener">#'.e($tag).'</a>';
                },
                $html
            );
        }

        if ($enableMentions) {
            $html = preg_replace_callback(
                '/(^|[\s(>])@([A-Za-z0-9_-]{1,255})(?![A-Za-z0-9_-])/u',
                function ($m) use ($validMentionsSet) {
                    $lead = $m[1];
                    $typed = $m[2];
                    $key = Str::lower($typed);

                    if (!isset($validMentionsSet[$key])) {
                        return $lead.'@'.e($typed);
                    }

                    $canonical = $validMentionsSet[$key];
                    $href = url('/'.rawurlencode($canonical));

                    return $lead.'<a href="'.e($href).'" class="mention" rel="nofollow noopener">@'.e($typed).'</a>';
                },
                $html
            );
        }

        return $html;
    }

    // TODO: Expose $limit as admin setting
    public static function getTopHashtags(int $limit = 10, int $cacheSeconds = 300): array
    {
        $limit = max(1, min(50, $limit));

        $mode = (string) (getSetting('site.explore_mode') ?? 'public');

        $days = (int) (getSetting('feed.popular_hashtags_days') ?? 14);
        $days = $days > 0 ? $days : null;

        $viewerId = Auth::id(); // null if guest

        // Cache key must vary by viewer for paywall mode
        $cacheKey = 'hashtags:top:'
            .$limit
            .':days:'.($days ?? 'all')
            .':mode:'.$mode
            .':viewer:'.($mode === 'paywall' ? ($viewerId ?? 'guest') : 'all')
            .':publication:'.Cache::get('publication:visibility-version', 0);

        return Cache::remember($cacheKey, $cacheSeconds, function () use ($limit, $days, $mode, $viewerId) {
            $q = HashtagLink::query()->from('hashtag_links');

            $q->whereExists(function ($query) {
                $query->selectRaw('1')->from('posts as published_post')
                    ->where('published_post.status', Post::APPROVED_STATUS)
                    ->where(fn ($q) => $q->whereNull('published_post.release_date')->orWhere('published_post.release_date', '<=', now()))
                    ->where(fn ($q) => $q->whereNull('published_post.expire_date')->orWhere('published_post.expire_date', '>', now()))
                    ->where(function ($q) {
                        $q->whereColumn('published_post.id', 'hashtag_links.post_id')
                            ->orWhereExists(fn ($comment) => $comment->selectRaw('1')->from('post_comments as linked_comment')
                                ->whereColumn('linked_comment.id', 'hashtag_links.post_comment_id')
                                ->whereColumn('linked_comment.post_id', 'published_post.id'));
                    });
            });

            // window
            if ($days !== null) {
                $q->where('hashtag_links.created_at', '>=', Carbon::now()->subDays($days));
            }

            // paywall filtering
            if ($mode === 'paywall') {
                if (!$viewerId) {
                    return []; // paywall explore: guests see no hashtag widget
                }

                $allowedAuthorIds = array_values(array_unique(array_merge(
                    self::getUserActiveSubs($viewerId),
                    self::getFreeFollowingProfiles($viewerId),
//                    [$viewerId]
                )));

                // Link can point to post OR post_comment. For comments, author is the parent post's user_id.
                $q->leftJoin('posts as p', 'p.id', '=', 'hashtag_links.post_id')
                    ->leftJoin('post_comments as pc', 'pc.id', '=', 'hashtag_links.post_comment_id')
                    ->leftJoin('posts as cp', 'cp.id', '=', 'pc.post_id');

                $q->where(function ($w) use ($allowedAuthorIds) {
                    $w->whereIn('p.user_id', $allowedAuthorIds)
                        ->orWhereIn('cp.user_id', $allowedAuthorIds);
                });
            }

            $top = $q->selectRaw('hashtag_links.hashtag_id, COUNT(*) as uses')
                ->groupBy('hashtag_links.hashtag_id')
                ->orderByDesc('uses')
                ->limit($limit)
                ->get();

            if ($top->isEmpty()) {
                return [];
            }

            $tags = Hashtag::query()
                ->whereIn('id', $top->pluck('hashtag_id')->all())
                ->get(['id', 'tag'])
                ->keyBy('id');

            $out = [];
            foreach ($top as $row) {
                $h = $tags->get($row->hashtag_id);
                if (!$h) {
                    continue;
                }

                $out[] = [
                    'tag'  => $h->tag,
                    'uses' => (int) $row->getAttribute('uses'),
                ];
            }

            return $out;
        });
    }
}
