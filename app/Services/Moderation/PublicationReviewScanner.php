<?php

namespace App\Services\Moderation;

use App\Jobs\ScanContentReviewJob;
use App\Model\ContentReview;
use App\Model\MediaModerationItem;
use App\Model\MediaModerationRun;
use App\Model\Post;
use App\Model\Reel;
use App\Model\TextModerationItem;
use App\Providers\AttachmentServiceProvider;
use App\Services\YouTubeEmbed;
use App\Services\Queue\QueueRuntimeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicationReviewScanner
{
    public function __construct(
        protected PublicationReviewService $reviews,
        protected ModerationConfiguration $configuration,
        protected QueueRuntimeService $runtime,
        protected ModerationSignatureService $signatures,
        protected AttachmentMediaService $media,
        protected ModerationJobDispatcher $dispatcher,
    ) {
    }

    public function dispatch(ContentReview $review): void
    {
        ScanContentReviewJob::dispatch((int) $review->id)
            ->onConnection($this->runtime->connection())
            ->onQueue($this->runtime->queueName());
    }

    /** Prepare only this submission; persist both checklists before dispatching either. */
    public function prepare(int $reviewId): void
    {
        $runs = DB::transaction(function () use ($reviewId): array {
            $review = ContentReview::query()->find($reviewId);
            $content = $review?->content();
            if (!$content) {
                return [];
            }
            $content = $content->newQuery()->lockForUpdate()->whereKey($content->getKey())->firstOrFail();
            $review = ContentReview::query()->lockForUpdate()->findOrFail($reviewId);
            if ($review->status !== ContentReview::STATUS_PENDING || $review->mode === ContentReview::MODE_MANUAL
                || !$this->reviews->isCurrent($review, $content) || ($review->policy['prepared'] ?? false)) {
                return [];
            }
            if (!hash_equals($review->fingerprint, $this->reviews->fingerprint($content))) {
                $review->update(['attention_reason' => 'content_changed']);

                return [];
            }
            if (!$this->configuration->enabled()) {
                $review->update(['attention_reason' => 'provider_not_configured']);

                return [];
            }
            $policy = $review->policy;
            $attention = null;
            $attachments = $content->attachments()->get();
            $media = $attachments->filter(function ($attachment) use (&$attention, $policy): bool {
                $type = $this->media->mediaType($attachment);
                if (!$type || ($type === 'video' && !($policy['media']['video_enabled'] ?? false))) {
                    $attention = 'unsupported_media';

                    return false;
                }

                return true;
            });
            $field = $content instanceof Reel ? 'caption' : 'text';
            $text = trim((string) $content->getRawOriginal($field));
            $textRequired = $text !== '';
            if ($textRequired && !($policy['text_enabled'] ?? false)) {
                $attention = 'text_checks_disabled';
            }
            // Extra presentation text has no existing source scanner; it requires a human.
            $overlay = (array) $content->overlay;
            if (filled($overlay['text'] ?? null) || filled($content->getRawOriginal('link_text')) || $content->sound_id
                || ($content instanceof Post && $content->poll()->exists())) {
                $attention = 'additional_content_requires_review';
            }
            if ($content instanceof Post && app(YouTubeEmbed::class)->videoIds($text) !== []
                && app(YouTubeEmbed::class)->forPost($text) !== []) {
                $attention = 'unsupported_embed';
            }
            $runs = [];
            if ($media->count() + ($textRequired ? 1 : 0) > $this->configuration->itemLimitForMode($this->runtime->mode())) {
                $review->update(['attention_reason' => 'submission_exceeds_scan_limit']);

                return [];
            }
            if ($media->isNotEmpty()) {
                $run = $this->run($review, false, $media->count());
                foreach ($media as $attachment) {
                    $type = $this->media->mediaType($attachment);
                    MediaModerationItem::query()->create([
                        'run_id' => $run->id, 'source_type' => MediaModerationItem::SOURCE_ATTACHMENT,
                        'source_id' => (string) $attachment->id, 'attachment_id' => $attachment->id,
                        'source_path' => $attachment->filename,
                        'source_disk' => AttachmentServiceProvider::getStorageProviderName($attachment->driver),
                        'owner_user_id' => $content->user_id, 'subject_type' => 'attachment',
                        'media_type' => $type, 'provider' => $run->provider,
                        'model_signature' => $this->signatures->model(
                            $run->provider,
                            $run->models,
                            (float) ($run->policy['video_effective_interval_seconds'] ?? ModerationConfiguration::DEFAULT_VIDEO_INTERVAL_SECONDS),
                            $type
                        ),
                        'policy_signature' => $this->signatures->policy($run->policy),
                        'source_updated_at' => $attachment->updated_at,
                        'scan_status' => MediaModerationItem::STATUS_QUEUED, 'queued_at' => now(),
                    ]);
                }
                $review->media_run_id = $run->id;
                $runs[] = $run;
            }
            if ($textRequired && ($policy['text_enabled'] ?? false)) {
                $run = $this->run($review, true, 1);
                TextModerationItem::query()->create([
                    'run_id' => $run->id, 'source_type' => $review->source_type,
                    'source_id' => (string) $content->id, 'source_field' => $field,
                    'owner_user_id' => $content->user_id, 'subject_type' => $review->source_type, 'subject_id' => $content->id,
                    'source_snapshot' => $text, 'source_hash' => hash('sha256', $text),
                    'source_updated_at' => $content->updated_at, 'provider' => $run->provider,
                    'model_signature' => $run->model_signature,
                    'policy_signature' => $this->signatures->policy($run->policy),
                    'scan_status' => TextModerationItem::STATUS_QUEUED, 'queued_at' => now(),
                ]);
                $review->text_run_id = $run->id;
                $runs[] = $run;
            }
            $policy['prepared'] = true;
            $policy['required_media_count'] = $media->count();
            $policy['required_text_count'] = $textRequired ? 1 : 0;
            $review->policy = $policy;
            $review->attention_reason = $attention ?: ($runs === [] ? 'no_supported_checks' : null);
            $review->save();

            return $runs;
        });

        foreach ($runs as $run) {
            if ($run->execution_mode !== QueueRuntimeService::MODE_WORKER) {
                $items = $run->isText() ? $run->textItems() : $run->items();
                $scanner = app($run->isText() ? TextModerationScanService::class : ModerationScanService::class);
                foreach ($items->pluck('id') as $itemId) {
                    $scanner->process((int) $itemId);
                }
            } elseif ($run->isText()) {
                $this->dispatcher->textItems($run, $run->textItems()->pluck('id')->all());
            } else {
                $this->dispatcher->items($run, $run->items()->pluck('id')->all());
            }
        }
        $this->reconcile($reviewId);
    }

    public function reconcile(int $reviewId): void
    {
        $review = ContentReview::query()->find($reviewId);
        if (!$review || $review->status !== ContentReview::STATUS_PENDING || !($review->policy['prepared'] ?? false)) {
            return;
        }
        $allClean = true;
        $attention = $review->attention_reason;
        if ((int) ($review->policy['required_media_count'] ?? 0) + (int) ($review->policy['required_text_count'] ?? 0) < 1) {
            $allClean = false;
            $attention = 'no_supported_checks';
        }
        foreach ([[$review->mediaRun, 'required_media_count'], [$review->textRun, 'required_text_count']] as [$run, $countKey]) {
            $required = (int) ($review->policy[$countKey] ?? 0);
            if (!$required) {
                continue;
            }
            if (!$run || $run->cancel_requested_at || in_array($run->status, [MediaModerationRun::STATUS_FAILED, MediaModerationRun::STATUS_CANCELLED], true)) {
                $allClean = false;
                $attention = 'scan_incomplete';
                continue;
            }
            $items = ($run->isText() ? $run->textItems() : $run->items())->get();
            if ($items->count() !== $required) {
                $allClean = false;
                $attention = 'scan_incomplete';
            }
            foreach ($items as $item) {
                if ($item->scan_status !== MediaModerationItem::STATUS_COMPLETED || $item->decision !== MediaModerationItem::DECISION_SAFE) {
                    $allClean = false;
                    if (!in_array($item->scan_status, [MediaModerationItem::STATUS_QUEUED, MediaModerationItem::STATUS_PROCESSING,
                        MediaModerationItem::STATUS_AWAITING_PROVIDER, MediaModerationItem::STATUS_WAITING_PROVIDER_CAPACITY,
                        MediaModerationItem::STATUS_RETRYING], true)) {
                        $attention = $item->scan_status === MediaModerationItem::STATUS_COMPLETED ? 'flagged_content' : 'scan_incomplete';
                    }
                }
            }
        }
        if ($attention) {
            ContentReview::query()->whereKey($reviewId)->where('status', ContentReview::STATUS_PENDING)->update(['attention_reason' => $attention]);
        }
        if ($allClean && !$attention) {
            $policy = $review->policy;
            $policy['checks_complete'] = true;
            ContentReview::query()->whereKey($reviewId)->where('status', ContentReview::STATUS_PENDING)->update(['policy' => json_encode($policy, JSON_THROW_ON_ERROR)]);
        }
        if ($allClean && !$attention && $review->mode === ContentReview::MODE_AUTOMATIC) {
            try {
                $this->reviews->approveAutomatically($review);
            } catch (ValidationException $exception) {
                // A concurrent edit or administrator decision is authoritative.
                ContentReview::query()->whereKey($reviewId)->where('status', ContentReview::STATUS_PENDING)
                    ->update(['attention_reason' => 'content_changed']);
            }
        }
    }

    protected function run(ContentReview $review, bool $text, int $count): MediaModerationRun
    {
        $policy = $review->policy;
        $models = $policy[$text ? 'text_models' : 'models'];
        $rules = $policy[$text ? 'text' : 'media'];

        return MediaModerationRun::query()->create([
            'id' => (string) Str::uuid(), 'scan_type' => $text ? MediaModerationRun::TYPE_TEXT : MediaModerationRun::TYPE_MEDIA,
            'provider' => $policy['provider'], 'model_signature' => $text
                ? $this->signatures->textModel($policy['provider'], $models, $rules['languages'])
                : $this->signatures->model($policy['provider'], $models, (float) ($rules['video_effective_interval_seconds'] ?? ModerationConfiguration::DEFAULT_VIDEO_INTERVAL_SECONDS)),
            'models' => $models, 'policy' => $rules, 'scope' => ['content_review_id' => $review->id],
            'status' => MediaModerationRun::STATUS_RUNNING, 'execution_mode' => $this->runtime->mode(),
            'connection' => $this->runtime->connection(), 'queue' => $this->runtime->queueName(),
            'max_items' => $count, 'max_operations' => $this->configuration->dailyOperationLimit(),
            'total_items' => $count, 'started_at' => now(), 'last_activity_at' => now(),
        ]);
    }
}
