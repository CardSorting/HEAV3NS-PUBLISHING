<?php

namespace App\Providers;

use App\Model\Attachment;
use App\Model\Reaction;
use App\Model\Reel;
use App\Model\ReelComment;
use App\Model\ReelView;
use App\Model\User;
use App\Model\UserBookmark;
use App\Services\UserStaffBadge;
use App\Services\Moderation\PublicationReviewService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ReelsServiceProvider
{
    private const MAX_EXCLUDED_FEED_REEL_IDS = 100;

    public static function forFeed(?User $viewer, int $limit = 10, int $offset = 0, ?int $randomSeed = null, bool $prioritizeUnseen = false, array $excludeIds = []): Collection
    {
        $viewerId = $viewer ? (int) $viewer->id : null;
        $isAdmin = $viewer && (int) ($viewer->role_id ?? 0) === 1;
        $authorIds = $viewer
            ? StoriesServiceProvider::allowedStoryAuthorIds($viewer)
                ->map(fn ($id) => (int) $id)
                ->values()
            : collect();

        $query = Reel::query()
            ->where('status', Reel::APPROVED_STATUS)
            ->whereHas('video')
            ->when(!$isAdmin, function ($query) use ($authorIds, $viewerId) {
                $query->where(function ($query) use ($authorIds, $viewerId) {
                    $query->where('is_public', 1);

                    if ($authorIds->isNotEmpty()) {
                        $query->orWhereIn('user_id', $authorIds->all());
                    } elseif ($viewerId) {
                        $query->orWhere('user_id', $viewerId);
                    }
                });
            })
            ->with(self::defaultRelations())
            ->withCount(['views', 'comments', 'reactions', 'bookmarks']);

        $excludeIds = collect($excludeIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->take(self::MAX_EXCLUDED_FEED_REEL_IDS)
            ->values();

        if ($excludeIds->isNotEmpty()) {
            $query->whereNotIn('reels.id', $excludeIds->all());
        }

        if ($prioritizeUnseen && $viewer) {
            // Widget-specific ranking: unseen first, then optionally shuffle only the unseen bucket.
            // Seen reels remain newest-first so an all-seen feed has predictable ordering.
            $seenOrderSql = 'CASE WHEN EXISTS (SELECT 1 FROM reel_views WHERE reel_views.reel_id = reels.id AND reel_views.user_id = ?) THEN 1 ELSE 0 END ASC';
            $query->orderByRaw($seenOrderSql, [(int) $viewer->id]);

            if ($randomSeed) {
                $unseenRandomSql = 'CASE WHEN NOT EXISTS (SELECT 1 FROM reel_views WHERE reel_views.reel_id = reels.id AND reel_views.user_id = ?) THEN RAND(?) ELSE 0 END ASC';
                $query->orderByRaw($unseenRandomSql, [(int) $viewer->id, $randomSeed]);
            }

            $query->orderByDesc('created_at');
        } elseif ($randomSeed) {
            $query->inRandomOrder($randomSeed);
        } else {
            $query->orderByDesc('created_at');
        }

        return $query
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->values();
    }

    public static function forProfile(?User $viewer, User $profileUser, int $limit = 10, int $offset = 0): Collection
    {
        $canViewPrivate = self::canViewPrivateReelsFromUser($viewer, (int) $profileUser->id);

        return Reel::query()
            ->where('user_id', (int) $profileUser->id)
            ->when(!PublicationReviewService::canPreview((new Reel)->forceFill(['user_id' => $profileUser->id]), $viewer), fn ($query) => $query->where('status', Reel::APPROVED_STATUS))
            ->whereHas('video')
            ->when(!$canViewPrivate, fn ($query) => $query->where('is_public', 1))
            ->with(self::defaultRelations())
            ->withCount(['views', 'comments', 'reactions', 'bookmarks'])
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->filter(fn (Reel $reel) => self::canViewReel($viewer, $reel))
            ->values();
    }

    public static function forBookmarks(User $viewer, int $limit = 10, int $offset = 0): Collection
    {
        return Reel::query()
            ->where('reels.status', Reel::APPROVED_STATUS)
            ->select('reels.*')
            ->join('user_bookmarks', function ($join) use ($viewer) {
                $join->on('user_bookmarks.reel_id', '=', 'reels.id')
                    ->where('user_bookmarks.user_id', '=', (int) $viewer->id);
            })
            ->whereHas('video')
            ->with(self::defaultRelations())
            ->withCount(['views', 'comments', 'reactions', 'bookmarks'])
            ->orderByDesc('user_bookmarks.created_at')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->filter(fn (Reel $reel) => self::canViewReel($viewer, $reel))
            ->values();
    }

    public static function profileCount(?User $viewer, User $profileUser): int
    {
        return self::profileCounts($viewer, $profileUser)['accessible'];
    }

    /** @return array{total: int, accessible: int, locked: int} */
    public static function profileCounts(?User $viewer, User $profileUser): array
    {
        $counts = Reel::query()
            ->where('user_id', (int) $profileUser->id)
            ->when(!PublicationReviewService::canPreview((new Reel)->forceFill(['user_id' => $profileUser->id]), $viewer), fn ($query) => $query->where('status', Reel::APPROVED_STATUS))
            ->whereHas('video')
            ->selectRaw('COUNT(*) AS total_count')
            ->selectRaw('SUM(CASE WHEN is_public = 1 THEN 1 ELSE 0 END) AS public_count')
            ->first();

        $total = (int) $counts?->getAttribute('total_count');
        $accessible = self::canViewPrivateReelsFromUser($viewer, (int) $profileUser->id)
            ? $total
            : (int) $counts?->getAttribute('public_count');

        return ['total' => $total, 'accessible' => $accessible, 'locked' => $total - $accessible];
    }

    public static function canViewReel(?User $viewer, Reel $reel): bool
    {
        if (!PublicationReviewService::isApproved($reel)) return PublicationReviewService::canPreview($reel, $viewer);

        if ((bool) $reel->is_public) {
            return true;
        }

        return self::canViewPrivateReelsFromUser($viewer, (int) $reel->user_id);
    }

    public static function canViewPrivateReelsFromUser(?User $viewer, int $profileUserId): bool
    {
        if (!$viewer) {
            return false;
        }

        if ((int) $viewer->id === $profileUserId) {
            return true;
        }

        if ((int) ($viewer->role_id ?? 0) === 1) {
            return true;
        }

        return StoriesServiceProvider::allowedStoryAuthorIds($viewer)
            ->contains($profileUserId);
    }

    public static function toFrontendPayload($reels, ?User $viewer = null): array
    {
        $viewer = $viewer ?: Auth::user();
        $reels = collect($reels)->filter(fn (Reel $reel) => PublicationReviewService::isApproved($reel) || PublicationReviewService::canPreview($reel, $viewer));
        $viewerId = $viewer ? (int) $viewer->id : null;
        $reelIds = collect($reels)->pluck('id')->map(fn ($id) => (int) $id)->values();
        $staffBadges = app(UserStaffBadge::class);
        $staffBadges->preload(collect($reels)->pluck('user'));

        $reactedIds = [];
        $bookmarkedIds = [];
        $seenIds = [];

        if ($viewerId && $reelIds->isNotEmpty()) {
            $reactedIds = Reaction::query()
                ->where('user_id', $viewerId)
                ->whereIn('reel_id', $reelIds)
                ->pluck('reel_id')
                ->map(fn ($id) => (int) $id)
                ->flip()
                ->all();

            $bookmarkedIds = UserBookmark::query()
                ->where('user_id', $viewerId)
                ->whereIn('reel_id', $reelIds)
                ->pluck('reel_id')
                ->map(fn ($id) => (int) $id)
                ->flip()
                ->all();

            $seenIds = ReelView::query()
                ->where('user_id', $viewerId)
                ->whereIn('reel_id', $reelIds)
                ->pluck('reel_id')
                ->map(fn ($id) => (int) $id)
                ->flip()
                ->all();
        }

        return [
            'reels' => collect($reels)->map(function (Reel $reel) use ($viewer, $viewerId, $reactedIds, $bookmarkedIds, $seenIds, $staffBadges) {
                $video = self::firstAttachmentOfType($reel->attachments, 'video');
                $cover = self::firstAttachmentOfType($reel->attachments, 'image');
                $coverSrc = $cover
                    ? $cover->path
                    : (($video && $video->has_thumbnail && !empty($video->thumbnail)) ? $video->thumbnail : null);
                $user = $reel->user;

                return [
                    'id' => (int) $reel->id,
                    'caption' => $reel->caption,
                    'is_public' => (bool) $reel->is_public,
                    'overlay' => $reel->overlay,
                    'publication_review' => PublicationReviewService::canPreview($reel, $viewer) ? PublicationReviewService::presentation($reel) : null,
                    'duration' => $video && $video->length ? (int) $video->length : null,
                    'src' => $video ? $video->path : null,
                    'cover' => $coverSrc,
                    'time' => optional($reel->created_at)->timestamp ?? now()->timestamp,
                    'views' => (int) ($reel->views_count ?? 0),
                    'comments' => (int) ($reel->comments_count ?? 0),
                    'reactions' => (int) ($reel->reactions_count ?? 0),
                    'bookmarks' => (int) ($reel->bookmarks_count ?? 0),
                    'reacted' => isset($reactedIds[(int) $reel->id]),
                    'bookmarked' => isset($bookmarkedIds[(int) $reel->id]),
                    'seen' => isset($seenIds[(int) $reel->id]),
                    'owner' => $viewerId && ((int) $reel->user_id === $viewerId),
                    'url' => route('reels.get', ['reel_id' => (int) $reel->id]),
                    'user' => [
                        'id' => (int) $user->id,
                        'name' => $user->name,
                        'username' => $user->username,
                        'photo' => $user->avatar,
                        'verified' => GenericHelperServiceProvider::isUserVerified($user),
                        'staff_badge' => $staffBadges->forUser($user),
                        'url' => route('profile', ['username' => $user->username]),
                    ],
                    'sound' => self::mapSound($reel->sound),
                ];
            })->values(),
        ];
    }

    public static function commentsPayload(Reel $reel, ?User $viewer = null): array
    {
        $viewer = $viewer ?: Auth::user();

        $comments = ReelComment::query()
            ->where('reel_id', (int) $reel->id)
            ->whereNull('parent_id')
            ->with([
                'user:id,name,username,avatar,birthdate,email_verified_at,role_id',
                'user.verification:id,user_id,status',
                'replyTarget.user:id,username',
            ])
            ->withCount(['reactions', 'replies'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return ['comments' => self::mapComments($comments, $viewer, $reel)];
    }

    public static function repliesPayload(Reel $reel, ReelComment $root, ?User $viewer = null, int $offset = 0): array
    {
        $query = ReelComment::query()
            ->where('reel_id', (int) $reel->id)
            ->where('parent_id', (int) $root->id);
        $total = (clone $query)->count();
        $offset = max(0, $offset);
        $replies = $query
            ->with([
                'user:id,name,username,avatar,birthdate,email_verified_at,role_id',
                'user.verification:id,user_id,status',
                'replyTarget.user:id,username',
            ])
            ->withCount(['reactions', 'replies'])
            ->oldest('created_at')
            ->oldest('id')
            ->offset($offset)
            ->limit(5)
            ->get();

        return [
            'success' => true,
            'replies' => self::mapComments($replies, $viewer, $reel),
            'total' => $total,
            'next_offset' => $offset + $replies->count(),
        ];
    }

    protected static function mapComments(Collection $comments, ?User $viewer, Reel $reel): Collection
    {
        $viewerId = $viewer ? (int) $viewer->id : null;

        app(UserStaffBadge::class)->preload($comments->pluck('user'));

        $commentIds = $comments->pluck('id')->map(fn ($id) => (int) $id)->values();
        $reactedIds = [];

        if ($viewerId && $commentIds->isNotEmpty()) {
            $reactedIds = Reaction::query()
                ->where('user_id', $viewerId)
                ->whereIn('reel_comment_id', $commentIds)
                ->pluck('reel_comment_id')
                ->map(fn ($id) => (int) $id)
                ->flip()
                ->all();
        }

        return $comments->map(fn (ReelComment $comment) => self::commentPayload($comment, $viewer, $reel, $reactedIds))->values();
    }

    public static function commentPayload(ReelComment $comment, ?User $viewer, Reel $reel, array $reactedIds = []): array
    {
        $viewerId = $viewer ? (int) $viewer->id : null;
        $comment->loadMissing([
            'user:id,name,username,avatar,birthdate,email_verified_at,role_id',
            'user.verification:id,user_id,status',
            'replyTarget.user:id,username',
        ]);
        $replyUser = $comment->replyTarget?->user;

        return [
            'id' => (int) $comment->id,
            'parent_id' => $comment->parent_id ? (int) $comment->parent_id : null,
            'reply_to_id' => $comment->reply_to_id ? (int) $comment->reply_to_id : null,
            'reply_to' => $replyUser ? ['id' => (int) $replyUser->id, 'username' => $replyUser->username] : null,
            'replies_count' => (int) ($comment->replies_count ?? 0),
            'can_reply' => $viewerId !== null,
            'message' => $comment->message,
            'gif' => app(\App\Services\GifService::class)->normalize($comment->gif),
            'time' => optional($comment->created_at)->timestamp ?? now()->timestamp,
            'reactions' => (int) ($comment->reactions_count ?? 0),
            'reacted' => isset($reactedIds[(int) $comment->id]),
            'owner' => $viewerId && ((int) $comment->user_id === $viewerId),
            'can_delete' => $viewerId && (
                (int) $comment->user_id === $viewerId
                || (int) $reel->user_id === $viewerId
            ),
            'user' => [
                'id' => (int) $comment->user->id,
                'name' => $comment->user->name,
                'username' => $comment->user->username,
                'photo' => $comment->user->avatar,
                'verified' => GenericHelperServiceProvider::isUserVerified($comment->user),
                'staff_badge' => app(UserStaffBadge::class)->forUser($comment->user),
                'url' => route('profile', ['username' => $comment->user->username]),
            ],
        ];
    }

    public static function firstAttachmentOfType($attachments, string $type): ?Attachment
    {
        return collect($attachments)->first(function (Attachment $attachment) use ($type) {
            return AttachmentServiceProvider::getAttachmentType($attachment->type) === $type;
        });
    }

    public static function defaultRelations(): array
    {
        return [
            'contentReview:id,content_reviews.source_id,status,mode,attention_reason,rejection_reason,policy,created_at',
            'user' => function ($q) {
                $q->select('id', 'name', 'username', 'avatar', 'birthdate', 'email_verified_at', 'role_id')
                    ->with(['verification' => function ($v) {
                        $v->select('id', 'user_id', 'status');
                    }]);
            },
            'attachments' => function ($q) {
                $q->orderBy('created_at', 'asc');
            },
            'sound.attachments',
        ];
    }

    protected static function mapSound($sound): ?array
    {
        if (!$sound) {
            return null;
        }

        return [
            'id' => (int) $sound->id,
            'title' => $sound->title,
            'artist' => $sound->artist,
            'description' => $sound->description,
            'cover' => optional($sound->coverAttachment)->path,
            'audio_src' => optional($sound->audioAttachment)->path,
        ];
    }
}
