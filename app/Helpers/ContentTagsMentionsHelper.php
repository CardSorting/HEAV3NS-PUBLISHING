<?php

namespace App\Helpers;

use App\Model\Hashtag;
use App\Model\HashtagLink;
use App\Model\Mention;
use App\Model\Notification;
use App\Model\Post;
use App\Model\PostComment;
use App\Model\User;
use App\Providers\NotificationServiceProvider;
use App\Services\Moderation\PublicationReviewService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Content parser and linkify helper for mentions and hashtags.
 */
class ContentTagsMentionsHelper
{
    private const TAG_RE = '/(^|[\s(])#([A-Za-z0-9_]{1,64})(?![A-Za-z0-9_])/u';
    private const MENTION_RE = '/(^|[\s(])@([A-Za-z0-9_-]{1,255})(?![A-Za-z0-9_-])/u';

    public function syncForPost(Post $post, bool $sendMentionNotifications = true): void
    {
        $this->sync(
            text: (string) $post->text,
            mentionedByUserId: (int) $post->user_id,
            postId: (int) $post->id,
            postCommentId: null,
            sendMentionNotifications: $sendMentionNotifications,
        );
    }

    /** Publish held mentions once the current content is actually visible. */
    public function publishPostMentions(Post $post): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => $this->publishPostMentions($post));
            return;
        }
        DB::transaction(function () use ($post): void {
            $current = Post::query()->lockForUpdate()->find($post->id);
            if (!$current || !PublicationReviewService::isApproved($current) || $current->is_scheduled
                || ($current->expire_date && $current->is_expired) || $current->user?->isBanned()) return;

            $ids = Mention::query()->where('post_id', $current->id)->pluck('mentioned_user_id');
            $alreadyNotified = Notification::query()->where('post_id', $current->id)
                ->where('type', Notification::MENTION)->whereNull('post_comment_id')->pluck('to_user_id');
            foreach (User::query()->whereIn('id', $ids->diff($alreadyNotified))->get() as $recipient) {
                NotificationServiceProvider::createMentionNotification($recipient, $current, null, (int) $current->user_id);
            }
        }, 3);
    }

    /**
     * @return list<int>
     */
    public function syncForComment(PostComment $comment, bool $sendMentionNotifications = true): array
    {
        return $this->sync(
            text: (string) $comment->message,
            mentionedByUserId: (int) $comment->user_id,
            postId: null,
            postCommentId: (int) $comment->id,
            sendMentionNotifications: $sendMentionNotifications,
        );
    }

    /**
     * @return list<int>
     */
    private function sync(
        string $text,
        int $mentionedByUserId,
        ?int $postId,
        ?int $postCommentId,
        bool $sendMentionNotifications
    ): array
    {
        $enableHashtags = (bool) getSetting('feed.enable_hashtags');
        $enableMentions = (bool) getSetting('feed.enable_mentions');

        // If both are disabled, do nothing at all
        if (!$enableHashtags && !$enableMentions) {
            return [];
        }

        $tags = $enableHashtags ? $this->extractHashtags($text) : [];
        $usernames = $enableMentions ? $this->extractMentions($text) : [];

        if ($enableHashtags) {
            $this->syncHashtags($tags, $postId, $postCommentId);
        }

        return $enableMentions
            ? $this->syncMentions(
                $usernames,
                $mentionedByUserId,
                $postId,
                $postCommentId,
                $sendMentionNotifications
            )
            : [];
    }

    private function extractHashtags(string $text): array
    {
        preg_match_all(self::TAG_RE, $text, $m);
        $tags = array_map(fn ($t) => Str::lower($t), $m[2]);
        $tags = array_values(array_unique($tags));
        return array_slice($tags, 0, getSetting('feed.max_hashtags'));
    }

    private function extractMentions(string $text): array
    {
        preg_match_all(self::MENTION_RE, $text, $m);
        $names = $m[2];
        $names = array_map(fn ($t) => Str::lower($t), $names);
        $names = array_values(array_unique($names));
        return array_slice($names, 0, getSetting('feed.max_mentions'));
    }

    private function syncHashtags(array $tags, ?int $postId, ?int $postCommentId): void
    {
        $q = HashtagLink::query();
        if ($postId) $q->where('post_id', $postId);
        if ($postCommentId) $q->where('post_comment_id', $postCommentId);
        $q->delete();

        if (empty($tags)) return;

        $now = now();
        $rows = array_map(fn ($t) => ['tag' => $t, 'created_at' => $now, 'updated_at' => $now], $tags);
        Hashtag::query()->upsert($rows, ['tag'], ['updated_at']);

        $hashtags = Hashtag::query()->whereIn('tag', $tags)->get()->keyBy('tag');

        $links = [];
        foreach ($tags as $tag) {
            $h = $hashtags->get($tag);
            if (!$h) continue;

            $links[] = [
                'hashtag_id' => $h->id,
                'post_id' => $postId,
                'post_comment_id' => $postCommentId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($links)) {
            HashtagLink::query()->insert($links);
        }
    }

    /**
     * @return list<int>
     */
    private function syncMentions(
        array $usernames,
        int $mentionedByUserId,
        ?int $postId,
        ?int $postCommentId,
        bool $sendMentionNotifications
    ): array
    {
        $existingMentionedIds = Mention::query()
            ->when($postId, fn ($q) => $q->where('post_id', $postId))
            ->when($postCommentId, fn ($q) => $q->where('post_comment_id', $postCommentId))
            ->pluck('mentioned_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        Mention::query()
            ->when($postId, fn ($q) => $q->where('post_id', $postId))
            ->when($postCommentId, fn ($q) => $q->where('post_comment_id', $postCommentId))
            ->delete();

        if (empty($usernames)) {
            return [];
        }

        $users = User::query()
            ->whereIn(DB::raw('LOWER(username)'), $usernames)
            ->get(['id', 'username'])
            ->keyBy(fn ($u) => Str::lower($u->username));

        $now = now();
        $rows = [];
        $newMentionedIds = [];

        foreach ($usernames as $u) {
            $user = $users->get($u);
            if (!$user) continue;

            if ((int) $user->id === $mentionedByUserId) continue;

            $mentionedId = (int) $user->id;

            $rows[] = [
                'mentioned_user_id' => $mentionedId,
                'mentioned_by_user_id' => $mentionedByUserId,
                'post_id' => $postId,
                'post_comment_id' => $postCommentId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $newMentionedIds[] = $mentionedId;
        }

        if (!empty($rows)) {
            Mention::query()->insert($rows);
        }

        $existing = array_values(array_unique($existingMentionedIds));
        $new = array_values(array_unique($newMentionedIds));
        $toNotify = array_values(array_diff($new, $existing));

        if (empty($toNotify)) {
            return [];
        }

        if ($sendMentionNotifications) {
            $post = $postId ? Post::query()->select(['id', 'user_id'])->find($postId) : null;
            $comment = $postCommentId ? PostComment::query()->select(['id', 'post_id', 'user_id'])->find($postCommentId) : null;

            $mentionedUsers = User::query()->whereIn('id', $toNotify)->get();
            foreach ($mentionedUsers as $toUser) {
                NotificationServiceProvider::createMentionNotification($toUser, $post, $comment, $mentionedByUserId);
            }
        }

        return $toNotify;
    }
}
