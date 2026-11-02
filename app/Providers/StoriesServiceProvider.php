<?php

namespace App\Providers;

use App\Model\Story;
use App\Model\StoryView;
use App\Model\User;
use App\Model\Subscription;
use App\Services\UserStaffBadge;
use App\Services\Moderation\PublicationReviewService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class StoriesServiceProvider
{
    /**
     * Return a collection of Story bubbles for the feed:
     * - grouped by user (owner)
     * - active only (not expired)
     * - only from allowed authors
     */
    public static function forFeed(User $viewer)
    {
        $authorIds = self::allowedStoryAuthorIds($viewer);

        if ($authorIds->isEmpty()) {
            return collect();
        }

        // Stories (bubbles) for allowed authors, active only.
        // Eager load attachments (for thumb + viewer).
        return Story::query()
            ->active()
            ->whereIn('user_id', $authorIds->all())
            ->where(fn ($query) => $query->where('status', Story::APPROVED_STATUS)->orWhere('user_id', $viewer->id))
            ->with([
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
            ])
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn ($story) => self::canViewStory($viewer, $story))
            ->values();
    }

    public static function forProfile(?User $viewer, User $owner)
    {
        $q = self::viewableStoriesQuery($viewer, (int) $owner->id);

        return $q->with([
            'contentReview:id,content_reviews.source_id,status,mode,attention_reason,rejection_reason,policy,created_at',
            'user' => function ($q) {
                $q->select('id', 'name', 'username', 'avatar', 'birthdate', 'email_verified_at', 'role_id')
                    ->with(['verification' => function ($v) {
                        $v->select('id', 'user_id', 'status');
                    }]);
            },
            'attachments',
            'sound.attachments',
        ])
            ->orderByDesc('is_highlight')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn ($story) => self::canViewStory($viewer, $story))
            ->values();
    }

    public static function canViewProfileStories(?User $viewer, User $owner): bool
    {
        return self::viewableStoriesQuery($viewer, (int) $owner->id)->exists();
    }

    private static function canViewPrivateStoriesFrom(?User $viewer, int $ownerId): bool
    {
        if (!$viewer) {
            return false;
        }

        if ((int) $viewer->id === $ownerId) {
            return true;
        }

        return self::allowedStoryAuthorIds($viewer)->contains($ownerId);
    }

    private static function viewableStoriesQuery(?User $viewer, int $ownerId, bool $active = true)
    {
        $q = Story::query()->where('user_id', $ownerId);

        if (!PublicationReviewService::canPreview((new Story)->forceFill(['user_id' => $ownerId]), $viewer)) {
            $q->where('status', Story::APPROVED_STATUS);
        }

        if ($active) {
            $q->active();
        }

        if (!self::canViewPrivateStoriesFrom($viewer, $ownerId)) {
            $q->where('is_public', 1);
        }

        return $q;
    }

    /**
     * Who can appear in my story tray?
     * - me
     * - subscribed creators
     * - free-followed profiles
     * - optionally: public profiles (if you have that concept).
     */
    public static function allowedStoryAuthorIds(User $viewer): Collection
    {
        $viewerId = (int) $viewer->id;

        // 1) Subscribed users
        // If your Subscription model relationships are different, keep it simple and just pluck IDs.
        $subbedIds = Subscription::query()
            ->where(function ($q) use ($viewerId) {
                $q->where('sender_user_id', $viewerId)
                    ->orWhere('recipient_user_id', $viewerId);
            })
            ->whereIn('status', [Subscription::ACTIVE_STATUS, Subscription::CANCELED_STATUS])
            ->where('expires_at', '>', now())
            ->get()
            ->map(function ($sub) use ($viewerId) {
                // "other side" of the relationship (creator/subscriber)
                // adjust if your Subscription fields differ:
                $a = (int) $sub->sender_user_id;
                $b = (int) $sub->recipient_user_id;
                return $a === $viewerId ? $b : $a;
            })
            ->unique()
            ->values();

        // 2) Free-followed users (you already have helper returning IDs)
        $freeFollowIds = collect(PostsHelperServiceProvider::getFreeFollowingProfiles($viewerId))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        // 3) Always include self
        return collect([$viewerId])
            ->merge($subbedIds)
            ->merge($freeFollowIds)
            ->unique()
            ->values();
    }

    /**
     * Defense-in-depth check for a single story.
     * Keep it conservative and simple at first.
     */
    public static function canViewStory(?User $viewer, Story $story): bool
    {
        if (!PublicationReviewService::isApproved($story)) return PublicationReviewService::canPreview($story, $viewer);

        if ((bool) $story->is_public) {
            return true;
        }

        return self::canViewPrivateStoriesFrom($viewer, (int) $story->user_id);
    }

    /**
     * Convert story collection into the frontend shape for your swiper thumbs.
     * (You can evolve this as your viewer needs grow.).
     */
    public static function toFrontendPayload($stories): array
    {
        $viewer = Auth::user();
        $stories = collect($stories)->filter(fn (Story $story) => PublicationReviewService::isApproved($story) || PublicationReviewService::canPreview($story, $viewer));
        $viewerId = (int) ($viewer?->id);

        $defaultLen = (int) getSetting('stories.default_story_length_seconds');
        $maxVideoLen = (int) getSetting('stories.max_video_length_seconds');

        $storyIds = collect($stories)->pluck('id')->map(fn ($id) => (int) $id)->unique()->values();

        // Seen
        $viewsByStoryId = StoryView::query()
            ->whereIn('story_id', $storyIds)
            ->selectRaw('story_id, COUNT(*) as views')
            ->groupBy('story_id')
            ->pluck('views', 'story_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $seenStoryIds = StoryView::query()
            ->whereIn('story_id', $storyIds)
            ->where('user_id', $viewerId)
            ->pluck('story_id')
            ->map(fn ($id) => (int) $id)
            ->flip()
            ->all();

        // Sounds
        $mapSound = function ($sound) {
            if (!$sound) return null;

            // pick first attachment as audio source (customize if you have “is_primary” etc)
            $att = ($sound->attachments && $sound->attachments->count())
                ? $sound->attachments->first()
                : null;

            return [
                'id'          => (int) $sound->id,
                'title'        => $sound->title,
                'artist' => $sound->artist,
                'description' => $sound->description,
                'cover'       => optional($sound->coverAttachment)->path,
                'audio_src'   => optional($sound->audioAttachment)->path,
                'audio_id'    => $att ? (int) $att->id : null,
                'length'      => $att && $att->length ? (int)$att->length : null,
            ];
        };

        // One bubble per user
        $grouped = collect($stories)->groupBy('user_id');
        $staffBadges = app(UserStaffBadge::class);
        $staffBadges->preload(collect($stories)->pluck('user'));

        $result = $grouped->map(function ($userStories) use ($viewerId, $seenStoryIds, $viewsByStoryId, $mapSound, $defaultLen, $maxVideoLen, $staffBadges) {
            $story = $userStories->sortByDesc('created_at')->first();
            $user = $story->user;

            // Flatten items from all story rows for that user
            $items = $userStories
                ->sortBy('created_at')
                ->flatMap(function ($s) use ($viewerId, $seenStoryIds, $viewsByStoryId, $mapSound, $defaultLen, $maxVideoLen) {

                    $link = $s->link_url ? trim((string) $s->link_url) : null;

                    $linkText = $s->link_text ? trim((string) $s->link_text) : null;
                    if ($link && !$linkText) {
                        $linkText = __('Learn more');
                    }

                    $storyId = (int) $s->id;
                    $isOwnerViewingOwnStory = $viewerId && ((int) $s->user_id === (int) $viewerId);

                    if ($s->mode === 'text' || !$s->attachments || !$s->attachments->count()) {
                        return collect([[
                            'attachment_id' => null,
                            'id'            => $storyId,
                            'publication_review' => $isOwnerViewingOwnStory ? PublicationReviewService::presentation($s) : null,
                            'pinned'        => (bool) $s->is_highlight,
                            'type'          => 'text',
                            'text'          => $s->text,
                            'overlay'       => $s->overlay,
                            'bg_preset'     => $s->bg_preset,
                            'length'        => $defaultLen,
                            'src'           => null,
                            'preview'       => null,
                            'time'          => optional($s->created_at)->timestamp ?? now()->timestamp,
                            'seen'          => $isOwnerViewingOwnStory ? true : isset($seenStoryIds[$storyId]),
                            'views'         => (int) ($viewsByStoryId[$storyId] ?? 0),
                            'link'          => $link,
                            'linkText'      => $linkText,

                            // NEW: sound payload
                            'sound_id'      => $s->sound_id ? (int)$s->sound_id : null,
                            'sound'         => $mapSound($s->sound),
                        ]]);
                    }

                    // otherwise, normal media story items:
                    return $s->attachments->map(function ($att) use ($viewerId, $s, $storyId, $seenStoryIds, $viewsByStoryId, $mapSound, $defaultLen, $maxVideoLen, $link, $linkText) {
                        $type = AttachmentServiceProvider::getAttachmentType($att->type);

                        $len = $type === 'video'
                            ? (int) ($att->length ?: $defaultLen)
                            : (int) $defaultLen;

                        // optional safety cap for video
                        if ($type === 'video' && $maxVideoLen > 0) {
                            $len = min($len, $maxVideoLen);
                        }

                        $hasThumb = (bool) ($att->has_thumbnail);
                        $thumb = ($hasThumb && !empty($att->thumbnail)) ? (string) $att->thumbnail : null;

                        return [
                            'attachment_id' => (int) $att->id,
                            'id'            => $storyId,
                            'publication_review' => $viewerId && (int) $s->user_id === $viewerId ? PublicationReviewService::presentation($s) : null,
                            'pinned'        => (bool) $s->is_highlight,
                            'type'          => $type,
                            'text'          => $s->text,
                            'overlay'       => $s->overlay,
                            'bg_preset'     => $s->bg_preset,
                            'length'        => $len,
                            'src'           => $att->path,
                            'preview'       => $thumb,
                            'has_thumbnail' => $hasThumb,
                            'time'          => optional($att->created_at)->timestamp
                                            ?? optional($s->created_at)->timestamp
                                                ?? now()->timestamp,
                            'seen' => ($viewerId && ((int) $s->user_id === (int) $viewerId)) ? true : isset($seenStoryIds[$storyId]),
                            'views'         => (int) ($viewsByStoryId[$storyId] ?? 0),
                            'link'          => $link,
                            'linkText'      => $linkText,

                            'sound_id'      => $s->sound_id ? (int)$s->sound_id : null,
                            'sound'         => $mapSound($s->sound),
                        ];

                    });

                })
                ->values();

            $lastUpdated = (int) ($items->max('time') ?: (optional($story->created_at)->timestamp ?? now()->timestamp));

//            var_dump(GenericHelperServiceProvider::isUserVerified($user));
            return [
                'user_id'     => (int) $user->id,
                'name'        => $user->name,
                'username'    => $user->username,
                'verified'    => GenericHelperServiceProvider::isUserVerified($user),
                'staff_badge' => $staffBadges->forUser($user),
                'photo'       => $user->avatar,
                'lastUpdated' => $lastUpdated,
                'items'       => $items,
            ];
        })->values();

        return ['stories' => $result];
    }

    /**
     * Does $owner have any ACTIVE stories that the current viewer can view?
     * - Owners/subscribers/free followers can see public and private stories.
     * - Guests and unrelated members can only see public stories.
     *
     * Never crashes if Auth::user() is null.
     */
    public static function hasActiveStories(User $owner): bool
    {
        $ownerId = (int) $owner->id;
        $viewer = Auth::user();

        return self::viewableStoriesQuery($viewer, $ownerId)->exists();
    }

    /**
     * Returns whether the current viewer has "seen" ALL of $owner's active stories.
     *
     * - If guest: returns null (unknown; you said you still want to show stories, but can't mark seen reliably)
     * - If owner viewing self: true
     * - If owner has no active stories: true (nothing to see)
     *
     * Never crashes if Auth::user() is null.
     */
    public static function storiesSeenForViewer(User $owner): ?bool
    {
        $viewer = Auth::user();
        if (!$viewer) {
            return null; // guest => unknown
        }

        $viewerId = (int) $viewer->id;
        $ownerId = (int) $owner->id;

        if ($viewerId === $ownerId) {
            return true;
        }

        $viewableStories = self::viewableStoriesQuery($viewer, $ownerId);

        $totalActive = (clone $viewableStories)->count();

        if ($totalActive <= 0) {
            return true;
        }

        $seenActive = StoryView::query()
            ->where('user_id', $viewerId)
            ->whereIn('story_id', (clone $viewableStories)->select('stories.id'))
            ->distinct('story_id')
            ->count('story_id');

        return $seenActive >= $totalActive;
    }

    public static function hasViewableStoriesForViewer(User $owner): bool
    {
        $viewer = Auth::user();

        return self::viewableStoriesQuery($viewer, (int) $owner->id)->exists();
    }

    public static function forHighlights(?User $viewer, User $owner)
    {
        $q = self::viewableStoriesQuery($viewer, (int) $owner->id, false)
            ->where('is_highlight', 1);

        // IMPORTANT:
        // Highlights should NOT expire like normal stories,
        return $q->with([
            'contentReview:id,content_reviews.source_id,status,mode,attention_reason,rejection_reason,policy,created_at',
            'user' => function ($q) {
                $q->select('id', 'name', 'username', 'avatar', 'birthdate', 'email_verified_at', 'role_id')
                    ->with(['verification' => function ($v) {
                        $v->select('id', 'user_id', 'status');
                    }]);
            },
            'attachments',
            'sound.attachments',
        ])
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn ($story) => self::canViewStory($viewer, $story))
            ->values();
    }

    public static function hasHighlightsForViewer(?User $viewer, User $owner): bool
    {
        $q = self::viewableStoriesQuery($viewer, (int) $owner->id, false)
            ->where('is_highlight', 1);

        return $q->exists();
    }
}
