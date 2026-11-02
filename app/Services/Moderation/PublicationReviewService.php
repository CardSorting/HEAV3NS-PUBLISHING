<?php

namespace App\Services\Moderation;

use App\Model\ContentReview;
use App\Model\Attachment;
use App\Model\Post;
use App\Model\Reel;
use App\Model\Story;
use App\Model\User;
use App\Helpers\ContentTagsMentionsHelper;
use App\Providers\PostsHelperServiceProvider;
use App\Providers\EmailsServiceProvider;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class PublicationReviewService
{
    public function requiresReview(Post|Reel|Story $content): bool
    {
        if (!self::isApproved($content) && $content->contentReview !== null) {
            return true;
        }

        return (bool) getSetting('moderation.publication_review_enabled', false)
            && in_array($content instanceof Story ? 'stories' : $this->type($content).'s', (array) getSetting('moderation.publication_review_areas', []), true);
    }

    public static function isApproved(Post|Reel|Story $content): bool
    {
        $status = $content->status ?? ($content instanceof Post ? Post::PENDING_STATUS : $content::APPROVED_STATUS);

        return (int) $status === $content::APPROVED_STATUS;
    }

    public static function canPreview(Post|Reel|Story $content, ?User $viewer): bool
    {
        return $viewer && ((int) $viewer->id === (int) $content->user_id || (int) $viewer->role_id === 1);
    }

    public static function presentation(Post|Reel|Story $content): ?array
    {
        if (self::isApproved($content)) {
            return null;
        }

        $review = $content->contentReview;
        $rejected = (int) $content->status === $content::DISAPPROVED_STATUS;
        $state = $rejected ? 'rejected' : ($review && self::attentionLabel($review) ? 'needs_attention'
            : ($review && $review->mode !== ContentReview::MODE_MANUAL && !($review->policy['checks_complete'] ?? false) ? 'checking' : 'pending'));

        return [
            'state' => $state,
            'label' => match ($state) {
                'rejected' => __('Rejected'),
                'checking' => __('Checking content'),
                'needs_attention' => __('Awaiting review'),
                default => __('Pending review'),
            },
            'rejection_reason' => $rejected ? $review?->rejection_reason : null,
        ];
    }

    public static function attentionLabel(ContentReview $review): ?string
    {
        $reason = $review->attention_reason;
        if (!$reason && $review->status === ContentReview::STATUS_PENDING && $review->mode !== ContentReview::MODE_MANUAL
            && !($review->policy['prepared'] ?? false)
            && $review->created_at?->lt(now()->subMinutes(max(5, (int) getSetting('moderation.stale_after_minutes', 15))))) {
            $reason = 'scan_interrupted';
        }

        return match ($reason) {
            null, '' => null,
            'dispatch_failed', 'scan_interrupted' => __('Checks did not finish. Retry them or review manually.'),
            'content_changed' => __('The content changed after submission. Refresh before reviewing.'),
            'provider_not_configured' => __('The AI provider is unavailable. Configure it or review manually.'),
            'unsupported_media' => __('Some media cannot be checked automatically. Review it manually.'),
            'unsupported_embed' => __('Embedded media is not scanned. Review it manually.'),
            'text_checks_disabled' => __('Text checks are disabled. Review the text manually.'),
            'additional_content_requires_review' => __('Extra text or audio needs manual review.'),
            'submission_exceeds_scan_limit' => __('This submission exceeds the scan limit. Review it manually.'),
            'no_supported_checks' => __('No supported checks were available. Review manually.'),
            'scan_incomplete' => __('Checks failed, stopped, or reached a limit. Review or retry.'),
            'flagged_content' => __('AI flagged this for review.'),
            'policy_changed' => __('Review settings changed. Review manually or retry with the current settings.'),
            default => __('This submission needs manual attention.'),
        };
    }

    /** Called after text and placements are saved, inside the publication transaction. */
    public function submit(Post|Reel|Story $content): ?ContentReview
    {
        $legacyPending = $content instanceof Post && (int) $content->status === Post::PENDING_STATUS;
        if (!$this->requiresReview($content) && !$legacyPending) {
            if ($content instanceof Story && !$content->published_at && self::isApproved($content)) {
                $content->update(['published_at' => now()]);
            }
            return null;
        }

        return DB::transaction(function () use ($content): ContentReview {
            $locked = $content->newQuery()->lockForUpdate()->whereKey($content->getKey())->firstOrFail();
            $fingerprint = $this->fingerprint($locked);
            $previous = $this->currentReview($locked);
            if ($previous && $previous->fingerprint === $fingerprint) {
                return $previous;
            }
            if ($previous?->status === ContentReview::STATUS_PENDING) {
                $previous->update(['status' => ContentReview::STATUS_SUPERSEDED]);
            }

            $selected = (bool) getSetting('moderation.publication_review_enabled', false)
                && in_array($locked instanceof Story ? 'stories' : $this->type($locked).'s', (array) getSetting('moderation.publication_review_areas', []), true);
            $mode = $selected ? (string) getSetting('moderation.publication_review_mode', ContentReview::MODE_MANUAL) : ContentReview::MODE_MANUAL;
            if (!in_array($mode, [ContentReview::MODE_ASSISTED, ContentReview::MODE_AUTOMATIC], true)) {
                $mode = ContentReview::MODE_MANUAL;
            }
            $policy = $mode === ContentReview::MODE_MANUAL ? [] : $this->scanPolicy();
            $review = ContentReview::query()->create([
                'source_type' => $this->type($locked), 'source_id' => $locked->getKey(),
                'owner_user_id' => $locked->user_id, 'status' => ContentReview::STATUS_PENDING,
                'fingerprint' => $fingerprint, 'mode' => $mode, 'policy' => $policy,
            ]);
            $this->setStatus($locked, $locked::PENDING_STATUS);
            $locked->save();
            $content->setRawAttributes($locked->getAttributes(), true);
            $content->unsetRelation('contentReview');
            DB::afterCommit(fn () => $this->invalidateVisibilityCache());
            if ($mode !== ContentReview::MODE_MANUAL) {
                DB::afterCommit(fn () => $this->dispatchScan($review));
            }

            return $review;
        });
    }

    public function normalizeLegacy(ContentReview $review): ContentReview
    {
        if (!($review->policy['legacy_pending'] ?? false)
            || $review->fingerprint !== hash('sha256', 'legacy-pending-post:'.$review->source_id)) {
            return $review;
        }

        return DB::transaction(function () use ($review): ContentReview {
            $content = $review->content();
            if (!$content) {
                return $review;
            }
            $content = $content->newQuery()->lockForUpdate()->whereKey($content->getKey())->firstOrFail();
            $review = ContentReview::query()->lockForUpdate()->findOrFail($review->id);
            if ($this->isCurrent($review, $content)
                && $review->fingerprint === hash('sha256', 'legacy-pending-post:'.$review->source_id)) {
                $review->update(['fingerprint' => $this->fingerprint($content)]);
            }

            return $review;
        });
    }

    /** A file-path change affects every reviewed placement of that file. */
    public function resubmitAttachmentUses(Attachment $attachment): void
    {
        $attachment->placements()->where(fn ($query) => $query->whereNotNull('post_id')->orWhereNotNull('reel_id')->orWhereNotNull('story_id'))
            ->with(['post', 'reel', 'story'])->chunkById(100, function ($placements): void {
                foreach ($placements as $placement) {
                    $content = $placement->post ?? $placement->reel ?? $placement->story;
                    if ($content) {
                        $this->submit($content);
                    }
                }
            });
    }

    public function approve(ContentReview $review, User $actor, ?string $expectedFingerprint = null, ?string $reviewNotes = null): ContentReview
    {
        $this->authorize($review, $actor);

        return $this->decide($review, true, $actor, null, $expectedFingerprint, $reviewNotes);
    }

    public function reject(ContentReview $review, User $actor, string $reason, ?string $expectedFingerprint = null, ?string $reviewNotes = null): ContentReview
    {
        $this->authorize($review, $actor);
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => __('A rejection reason is required.')]);
        }

        return $this->decide($review, false, $actor, mb_substr(trim($reason), 0, 2000), $expectedFingerprint, $reviewNotes);
    }

    /** Only the scan coordinator calls this, after every required item is clean. */
    public function approveAutomatically(ContentReview $review): ContentReview
    {
        if ($review->mode !== ContentReview::MODE_AUTOMATIC) {
            return $review;
        }

        return $this->decide($review, true, null, null, $review->fingerprint);
    }

    public function retry(ContentReview $review, User $actor): ContentReview
    {
        $this->authorize($review, $actor);

        return DB::transaction(function () use ($review): ContentReview {
            $content = $review->content();
            if (!$content) {
                throw ValidationException::withMessages(['review' => __('The content is no longer available.')]);
            }
            $content = $content->newQuery()->lockForUpdate()->whereKey($content->getKey())->firstOrFail();
            $review = ContentReview::query()->lockForUpdate()->findOrFail($review->id);
            $this->assertCurrent($review, $content, $review->fingerprint);
            if ($review->status !== ContentReview::STATUS_PENDING || $review->mode === ContentReview::MODE_MANUAL) {
                throw ValidationException::withMessages(['review' => __('Only pending automatic checks can be retried.')]);
            }
            $review->update(['status' => ContentReview::STATUS_SUPERSEDED]);
            $retry = ContentReview::query()->create([
                'source_type' => $review->source_type, 'source_id' => $review->source_id,
                'owner_user_id' => $review->owner_user_id, 'fingerprint' => $review->fingerprint,
                'status' => ContentReview::STATUS_PENDING, 'mode' => $review->mode, 'policy' => $this->scanPolicy(),
            ]);
            DB::afterCommit(fn () => $this->dispatchScan($retry));

            return $retry;
        });
    }

    public function fingerprint(Post|Reel|Story $content): string
    {
        $fields = match (true) {
            $content instanceof Post => ['text'],
            $content instanceof Reel => ['caption', 'overlay', 'sound_id'],
            default => ['mode', 'text', 'overlay', 'bg_preset', 'link_url', 'link_text', 'sound_id'],
        };
        $data = ['user_id' => (int) $content->user_id];
        foreach ($fields as $field) {
            $value = $content->getRawOriginal($field);
            $data[$field] = $field === 'overlay' && is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : $value;
        }
        $data['attachments'] = $content->attachments()->get()->map(fn ($attachment) => array_intersect_key($attachment->getAttributes(), array_flip(['id', 'filename', 'type', 'driver'])))->all();
        if ($content instanceof Post) {
            $data['poll'] = $content->poll()->first()?->answers()->orderBy('id')->pluck('answer')->all();
        }
        $sort = function (array &$values) use (&$sort): void {
            if (!array_is_list($values)) {
                ksort($values);
            }
            foreach ($values as &$value) {
                if (is_array($value)) {
                    $sort($value);
                }
            }
        };
        $sort($data);

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    protected function decide(ContentReview $review, bool $approved, ?User $actor, ?string $reason, ?string $expected, ?string $reviewNotes = null): ContentReview
    {
        $review = $this->normalizeLegacy($review);

        return DB::transaction(function () use ($review, $approved, $actor, $reason, $expected, $reviewNotes): ContentReview {
            $content = $review->content();
            if (!$content) {
                throw ValidationException::withMessages(['review' => __('The content is no longer available.')]);
            }
            $content = $content->newQuery()->lockForUpdate()->whereKey($content->getKey())->firstOrFail();
            $review = ContentReview::query()->lockForUpdate()->findOrFail($review->id);
            $this->assertCurrent($review, $content, $expected);
            $status = $approved ? ContentReview::STATUS_APPROVED : ContentReview::STATUS_REJECTED;
            if ($review->status === $status) {
                return $review;
            }
            if ($approved && $actor && $review->status === ContentReview::STATUS_REJECTED) {
                // A changed decision is a new attempt; keep the original rejection immutable.
                $review = ContentReview::query()->create([
                    'source_type' => $review->source_type, 'source_id' => $review->source_id,
                    'owner_user_id' => $review->owner_user_id, 'fingerprint' => $review->fingerprint,
                    'status' => ContentReview::STATUS_PENDING, 'mode' => $review->mode, 'policy' => $review->policy,
                    'media_run_id' => $review->media_run_id, 'text_run_id' => $review->text_run_id,
                ]);
            }
            if ($review->status !== ContentReview::STATUS_PENDING) {
                throw ValidationException::withMessages(['review' => __('This review has already been decided.')]);
            }
            if (!$actor) {
                $policy = $this->scanPolicy();
                if ($review->mode !== ContentReview::MODE_AUTOMATIC
                    || !(bool) getSetting('moderation.publication_review_enabled', false)
                    || getSetting('moderation.publication_review_mode') !== ContentReview::MODE_AUTOMATIC
                    || !in_array($content instanceof Story ? 'stories' : $this->type($content).'s', (array) getSetting('moderation.publication_review_areas', []), true)
                    || array_intersect_key($review->policy, $policy) != $policy) {
                    $review->update(['attention_reason' => 'policy_changed']);

                    return $review;
                }
            }
            $review->update([
                'status' => $status, 'reviewed_by' => $actor?->id, 'reviewed_at' => now(),
                'decision_source' => $actor ? 'manual' : 'automatic', 'rejection_reason' => $reason,
                'review_notes' => $reviewNotes === null ? null : mb_substr(trim($reviewNotes), 0, 4000),
            ]);
            $this->setStatus($content, $approved ? $content::APPROVED_STATUS : $content::DISAPPROVED_STATUS);
            $content->save();
            DB::afterCommit(fn () => $this->invalidateVisibilityCache());
            DB::afterCommit(fn () => $this->notifyCreator($review));
            if ($approved && $content instanceof Post) {
                DB::afterCommit(function () use ($content): void {
                    try {
                        $post = Post::query()->find($content->id);
                        if ($post && self::isApproved($post)) {
                            app(ContentTagsMentionsHelper::class)->publishPostMentions($post);
                            if ($post->notify_followers) {
                                PostsHelperServiceProvider::sendPostNotifications($post);
                            }
                        }
                    } catch (Throwable $exception) {
                        Log::warning('Publication notifications failed.', ['post_id' => $content->id, 'exception' => $exception]);
                    }
                });
            }

            return $review;
        });
    }

    /** Call while holding the parent lock, so submissions and decisions use the same current row. */
    public function isCurrent(ContentReview $review, Post|Reel|Story $content): bool
    {
        return (int) $this->currentReview($content)?->id === (int) $review->id;
    }

    protected function currentReview(Post|Reel|Story $content): ?ContentReview
    {
        // Use a locking read directly; an aggregate subquery could use an older snapshot.
        return ContentReview::query()->where('source_type', $this->type($content))
            ->where('source_id', $content->getKey())->latest('id')->lockForUpdate()->first();
    }

    protected function assertCurrent(ContentReview $review, Post|Reel|Story $content, ?string $expected): void
    {
        if (!$this->isCurrent($review, $content)
            || ($expected !== null && !hash_equals($review->fingerprint, $expected))
            || !hash_equals($review->fingerprint, $this->fingerprint($content))) {
            throw ValidationException::withMessages(['review' => __('The content changed. Refresh it before reviewing.')]);
        }
    }

    protected function setStatus(Post|Reel|Story $content, int $status): void
    {
        $content->setAttribute('status', $status);
        if ($content instanceof Story && !$content->published_at) {
            $content->expires_at = null;
            if ($status === Story::APPROVED_STATUS) {
                $content->published_at = now();
                $content->expires_at = now()->addHours(max(1, (int) getSetting('stories.story_expires_hours', 24)));
            }
        }
    }

    protected function scanPolicy(): array
    {
        $configuration = app(ModerationConfiguration::class);

        return [
            'provider' => $configuration->provider(), 'models' => $configuration->models(),
            'media' => $configuration->policy(), 'text_models' => $configuration->textModels(),
            'text' => $configuration->textPolicy(), 'text_enabled' => $configuration->textEnabled(),
        ];
    }

    protected function dispatchScan(ContentReview $review): void
    {
        try {
            app(PublicationReviewScanner::class)->dispatch($review);
        } catch (Throwable $exception) {
            ContentReview::query()->whereKey($review->id)->where('status', ContentReview::STATUS_PENDING)
                ->update(['attention_reason' => 'dispatch_failed']);
            Log::warning('Publication scan dispatch failed.', ['review_id' => $review->id, 'exception' => $exception]);
        }
    }

    protected function invalidateVisibilityCache(): void
    {
        Cache::add('publication:visibility-version', 0, now()->addYears(5));
        Cache::increment('publication:visibility-version');
    }

    protected function notifyCreator(ContentReview $review): void
    {
        $locale = app()->getLocale() ?: 'en';
        $changedLocale = false;
        try {
            $owner = $review->owner;
            if (!$owner?->email) {
                return;
            }
            $changedLocale = true;
            app()->setLocale($owner->settings['locale'] ?? 'en');
            EmailsServiceProvider::sendGenericEmail([
                'email' => $owner->email, 'subject' => __('Content review updated'),
                'title' => __('Hello, :name,', ['name' => $owner->name]),
                'content' => $review->status === ContentReview::STATUS_APPROVED
                    ? __('Your content has been approved.')
                    : __('Your content was rejected: :reason', ['reason' => $review->rejection_reason]),
                'button' => ['text' => __('View profile'), 'url' => route('profile', ['username' => $owner->username])],
            ]);
        } catch (Throwable $exception) {
            Log::warning('Content review notification failed.', ['review_id' => $review->id, 'exception' => $exception]);
        } finally {
            if ($changedLocale) {
                app()->setLocale($locale);
            }
        }
    }

    protected function authorize(ContentReview $review, User $actor): void
    {
        if (!$actor->can('Update:ContentReview') && !($review->source_type === 'post' && $actor->can('Update:Post'))) {
            throw new AuthorizationException();
        }
    }

    protected function type(Post|Reel|Story $content): string
    {
        return match (true) {
            $content instanceof Post => 'post', $content instanceof Reel => 'reel', default => 'story',
        };
    }
}
