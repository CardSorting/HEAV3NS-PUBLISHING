<?php

namespace App\Services\Moderation;

use App\Model\MediaModerationItem;
use App\Model\MediaModerationRun;
use App\Services\Moderation\Data\ModerationPendingResult;
use App\Services\Moderation\Data\ModerationResult;
use App\Services\Moderation\Data\QuotaReservation;
use App\Services\Moderation\Exceptions\ModerationProviderException;
use App\Services\Queue\QueueRuntimeService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Throwable;

class ModerationScanService
{
    public const INITIAL_PROVIDER_POLL_DELAY_MINUTES = 15;

    public function __construct(
        protected AttachmentMediaService $media,
        protected ProfileMediaSourceService $profileMedia,
        protected StreamPosterSourceService $streamPosters,
        protected ModerationProviderManager $providers,
        protected ModerationDecisionService $decisions,
        protected ModerationSignatureService $signatures,
        protected ModerationQuotaService $quota,
        protected ModerationRunProgressService $progress,
        protected ModerationProviderCapacityService $capacity,
    ) {
    }

    public function process(MediaModerationItem|int $item): void
    {
        $itemId = $item instanceof MediaModerationItem ? $item->id : $item;
        $item = $this->claim($itemId);

        if (!$item) {
            return;
        }

        $run = $item->run;

        if (!$run || !$run->isMedia() || $run->cancel_requested_at || $run->isTerminal()) {
            $this->skip($item, 'cancelled', 'The scan was cancelled before this file was processed.');
            $this->progress->recalculate($item->run_id);

            return;
        }

        $isProfileAsset = $item->isProfileAsset();
        $isStreamPoster = $item->isStreamPoster();
        $isManagedAsset = $item->isManagedAsset();
        $attachment = $item->attachment;

        if (!$isManagedAsset && !$attachment) {
            $this->skip($item, 'media_missing', 'The attachment was removed before it could be scanned.');
            $this->progress->recalculate($run);

            return;
        }

        if ($isProfileAsset && !$this->profileMedia->isCurrent($item)) {
            $this->skip($item, 'media_changed', 'The profile image was replaced or removed before it could be scanned.');
            $this->progress->recalculate($run);

            return;
        }

        if ($isStreamPoster && !$this->streamPosters->isCurrent($item)) {
            $this->skip($item, 'media_changed', 'The stream poster was replaced or removed before it could be scanned.');
            $this->progress->recalculate($run);

            return;
        }

        $reservation = null;

        try {
            $fingerprint = match (true) {
                $isProfileAsset => $this->profileMedia->fingerprint($item),
                $isStreamPoster => $this->streamPosters->fingerprint($item),
                default => $this->media->fingerprint($attachment),
            };
            $item->update(['fingerprint' => $fingerprint]);

            if (!($run->scope['force_rescan'] ?? false)) {
                $reusable = $this->findReusable($item, $fingerprint);

                if ($reusable) {
                    $this->reuse($item, $reusable, $run);
                    $this->progress->recalculate($run);

                    return;
                }
            }

            $provider = $this->providers->provider($run->provider);
            $descriptor = match (true) {
                $isProfileAsset => $this->profileMedia->descriptor($item),
                $isStreamPoster => $this->streamPosters->descriptor($item),
                default => $this->media->descriptor($attachment),
            };

            if (!$provider->supports($descriptor->mediaType)) {
                $this->skip($item, 'unsupported_media', 'This media type is disabled or unsupported by the configured moderation provider.');
                $this->progress->recalculate($run);

                return;
            }

            $processingMode = $provider->processingMode($descriptor, $run->policy);

            if ($processingMode === 'async' && !$this->capacity->acquire($item)) {
                $this->capacity->wait($item);
                $this->progress->recalculate($run);

                return;
            }

            if ($processingMode !== 'async') {
                $item->update(['provider_processing_mode' => $processingMode]);
            }

            $estimatedOperations = $provider->estimateOperations($descriptor, $run->models, $run->policy);
            $reservation = $this->quota->reserve($run, $item, $estimatedOperations);

            if (!$reservation) {
                $item->update([
                    'scan_status' => MediaModerationItem::STATUS_QUOTA_PAUSED,
                    'error_type' => 'operation_budget',
                    'error_message' => 'The configured operation budget has been reached. This item can be resumed later.',
                    'retryable' => true,
                ]);
                $this->progress->recalculate($run);

                return;
            }

            $input = match (true) {
                $isProfileAsset => $this->profileMedia->input($item),
                $isStreamPoster => $this->streamPosters->input($item),
                default => $this->media->input($attachment),
            };
            $callbackUrl = $processingMode === 'async'
                ? URL::temporarySignedRoute(
                    'moderation.sightengine.callback',
                    now()->addDays(2),
                    ['item' => $item->id],
                )
                : null;
            $result = $provider->scan($input, $run->models, $run->policy, $callbackUrl);

            if ($result instanceof ModerationPendingResult) {
                $this->markPending($item, $result);
                $reservation = null;

                return;
            }

            $this->complete($item, $run, $result, $reservation, $processingMode);
            $reservation = null;
        } catch (ModerationProviderException $e) {
            $this->settle($reservation, $e->operations);
            $reservation = null;
            $this->handleProviderFailure($item, $run, $e);
        } catch (Throwable $e) {
            $this->settle($reservation, 0);
            $reservation = null;
            $this->handleUnexpectedFailure($item, $run, $e);
        } finally {
            $this->settle($reservation, 0);
            $this->progress->recalculate($run);
            $item->refresh();

            if ($item->provider_processing_mode === 'async'
                && $item->scan_status !== MediaModerationItem::STATUS_WAITING_PROVIDER_CAPACITY) {
                $this->capacity->releaseAvailable($item->provider);
            }
        }
    }

    public function receiveAsyncResult(MediaModerationItem|int $item, array $payload): MediaModerationItem
    {
        $item = $item instanceof MediaModerationItem
            ? $item->refresh()
            : MediaModerationItem::query()->find($item);

        if (!$item || !$item->run || $item->provider_processing_mode !== 'async') {
            throw new DomainException('The asynchronous moderation item is not ready.');
        }

        if ($item->run->cancel_requested_at
            || ($item->scan_status === MediaModerationItem::STATUS_SKIPPED
                && $item->error_type === 'cancelled')) {
            $this->capacity->releaseAvailable($item->provider);

            return $item;
        }

        if ($item->scan_status === MediaModerationItem::STATUS_FAILED
            && $item->error_type === 'async_provider_timeout') {
            return $item;
        }

        $this->assertProviderIdentity(
            $item,
            data_get($payload, 'media.id'),
            data_get($payload, 'request.id', data_get($payload, 'request')),
        );

        if ($item->scan_status === MediaModerationItem::STATUS_COMPLETED) {
            $this->capacity->releaseAvailable($item->provider);

            return $item;
        }

        try {
            $result = $this->providers->provider($item->provider)->normalizeCallback($payload, $item->run->policy);
        } catch (ModerationProviderException $exception) {
            if (str_starts_with($exception->errorType, 'async_video_')) {
                $this->failAsync($item, $exception);

                return $item->refresh();
            }

            throw $exception;
        }

        $this->applyAsyncResult($item, $result);

        return $item->refresh();
    }

    public function refreshAsyncResult(MediaModerationItem|int $item): MediaModerationItem
    {
        $item = $item instanceof MediaModerationItem
            ? $item->refresh()
            : MediaModerationItem::query()->find($item);

        if (!$item || !$item->run || $item->scan_status !== MediaModerationItem::STATUS_AWAITING_PROVIDER) {
            throw new DomainException('This item is not waiting for an asynchronous provider result.');
        }

        if (!$item->provider_media_id) {
            throw new DomainException('The provider media identifier has not been saved yet.');
        }

        try {
            $result = $this->providers
                ->provider($item->provider)
                ->poll($item->provider_media_id, $item->run->policy);
        } catch (ModerationProviderException $exception) {
            if (str_starts_with($exception->errorType, 'async_video_')) {
                $this->failAsync($item, $exception);

                return $item->refresh();
            }

            throw $exception;
        }

        $this->applyAsyncResult($item, $result);

        return $item->refresh();
    }

    public function failAsyncResult(
        MediaModerationItem|int $item,
        ModerationProviderException $exception,
    ): MediaModerationItem {
        $item = $item instanceof MediaModerationItem
            ? $item->refresh()
            : MediaModerationItem::query()->findOrFail($item);

        $this->failAsync($item, $exception, true);

        return $item->refresh();
    }

    public function expireAsyncResult(MediaModerationItem|int $item): MediaModerationItem
    {
        $item = $item instanceof MediaModerationItem
            ? $item->refresh()
            : MediaModerationItem::query()->findOrFail($item);
        $claimed = MediaModerationItem::query()
            ->whereKey($item->id)
            ->where('scan_status', MediaModerationItem::STATUS_AWAITING_PROVIDER)
            ->update(['scan_status' => MediaModerationItem::STATUS_PROCESSING]);

        if ($claimed < 1) {
            return $item->refresh();
        }

        $item = $item->refresh();
        $this->quota->consumeInterruptedReservation($item);
        $item->update([
            'scan_status' => MediaModerationItem::STATUS_FAILED,
            'error_type' => 'async_provider_timeout',
            'error_message' => 'The provider did not return a final video result before the configured timeout. Its reserved operation budget was conservatively counted as used, and the item can be retried.',
            'provider_next_poll_at' => null,
            'retryable' => true,
            'failed_at' => now(),
        ]);
        $this->progress->recalculate($item->run_id);
        $this->capacity->releaseAvailable($item->provider);

        return $item->refresh();
    }

    protected function claim(int $itemId): ?MediaModerationItem
    {
        return DB::transaction(function () use ($itemId) {
            /** @var MediaModerationItem|null $item */
            $item = MediaModerationItem::query()->lockForUpdate()->find($itemId);

            if (!$item || !in_array($item->scan_status, [
                MediaModerationItem::STATUS_QUEUED,
                MediaModerationItem::STATUS_RETRYING,
                MediaModerationItem::STATUS_WAITING_PROVIDER_CAPACITY,
                MediaModerationItem::STATUS_QUOTA_PAUSED,
                MediaModerationItem::STATUS_FAILED,
            ], true)) {
                return null;
            }

            $item->update([
                'scan_status' => MediaModerationItem::STATUS_PROCESSING,
                'attempts' => $item->attempts + 1,
                'started_at' => now(),
                'failed_at' => null,
                'error_type' => null,
                'error_code' => null,
                'error_message' => null,
                'provider_poll_attempts' => 0,
                'provider_next_poll_at' => null,
                'provider_poll_error' => null,
            ]);

            return $item->refresh();
        }, 3);
    }

    protected function findReusable(MediaModerationItem $item, string $fingerprint): ?MediaModerationItem
    {
        $base = MediaModerationItem::query()
            ->whereKeyNot($item->id)
            ->where('provider', $item->provider)
            ->where('model_signature', $item->model_signature)
            ->where('scan_status', MediaModerationItem::STATUS_COMPLETED)
            ->whereNotNull('decision');

        $sameSourceQuery = (clone $base)
            ->where('source_type', $item->source_type)
            ->where('source_id', $item->source_id);

        if ($item->isManagedAsset()) {
            $sameSourceQuery
                ->where('source_path', $item->source_path)
                ->where('source_disk', $item->source_disk);
        } else {
            $sameSourceQuery->where('source_updated_at', $item->source_updated_at);
        }

        $sameSource = $sameSourceQuery->latest('scanned_at')->first();

        return $sameSource ?: (clone $base)
            ->where('fingerprint', $fingerprint)
            ->latest('scanned_at')
            ->first();
    }

    protected function reuse(MediaModerationItem $item, MediaModerationItem $reusable, MediaModerationRun $run): void
    {
        $policySignature = $this->signatures->policy($run->policy);
        $sameSource = $this->sameSourceRevision($reusable, $item);
        $carryReview = $sameSource
            && hash_equals($policySignature, (string) $reusable->policy_signature);
        $supersedesPriorReview = $sameSource
            && !$carryReview
            && $reusable->admin_outcome === MediaModerationItem::OUTCOME_UNREVIEWED;
        $adminOutcome = $carryReview
            ? $reusable->admin_outcome
            : MediaModerationItem::OUTCOME_UNREVIEWED;
        $storedResult = is_array($reusable->provider_response)
            ? $this->providers->provider($run->provider)->normalizeStored($reusable->provider_response, $run->policy)
            : null;
        $maxScore = $storedResult->maxScore ?? (float) $reusable->max_score;
        $decision = $this->decisions->decide($maxScore, $run->policy);

        $item->update([
            'reused_from_item_id' => $reusable->id,
            'scan_status' => MediaModerationItem::STATUS_COMPLETED,
            'decision' => $decision,
            'admin_outcome' => $adminOutcome,
            'max_score' => $maxScore,
            'matched_class' => $storedResult->matchedClass ?? $reusable->matched_class,
            'matched_at_ms' => $storedResult->matchedAtMs ?? $reusable->matched_at_ms,
            'scores' => $storedResult->scores ?? $reusable->scores,
            'provider_response' => $reusable->provider_response,
            'provider_request_id' => $reusable->provider_request_id,
            'provider_media_id' => $reusable->provider_media_id,
            'provider_processing_mode' => $reusable->provider_processing_mode,
            'provider_progress' => $reusable->provider_progress,
            'provider_submitted_at' => $reusable->provider_submitted_at,
            'provider_last_update_at' => $reusable->provider_last_update_at,
            'operations' => 0,
            'retryable' => false,
            'is_cached' => true,
            'scanned_at' => now(),
            'reviewed_by' => $carryReview ? $reusable->reviewed_by : null,
            'reviewed_at' => $carryReview ? $reusable->reviewed_at : null,
            'review_notes' => $carryReview ? $reusable->review_notes : null,
            'deleted_by' => $carryReview ? $reusable->deleted_by : null,
            'content_deleted_at' => $carryReview ? $reusable->content_deleted_at : null,
            'deletion_notes' => $carryReview ? $reusable->deletion_notes : null,
        ]);

        $item->refresh();

        if ($supersedesPriorReview) {
            $reusable->update([
                'admin_outcome' => MediaModerationItem::OUTCOME_SUPERSEDED,
                'reviewed_at' => now(),
            ]);
        }
    }

    protected function sameSourceRevision(MediaModerationItem $first, MediaModerationItem $second): bool
    {
        if ($first->source_type !== $second->source_type
            || (string) $first->source_id !== (string) $second->source_id) {
            return false;
        }

        if ($first->isManagedAsset() || $second->isManagedAsset()) {
            return (string) $first->source_path === (string) $second->source_path
                && (string) $first->source_disk === (string) $second->source_disk;
        }

        return (string) $first->source_updated_at === (string) $second->source_updated_at;
    }

    protected function applyAsyncResult(
        MediaModerationItem $item,
        ModerationPendingResult|ModerationResult $result,
    ): void {
        $this->assertProviderIdentity($item, $result->mediaId, $result->requestId);

        if ($result instanceof ModerationPendingResult) {
            if (in_array($item->scan_status, [
                MediaModerationItem::STATUS_COMPLETED,
                MediaModerationItem::STATUS_FAILED,
                MediaModerationItem::STATUS_SKIPPED,
            ], true)) {
                return;
            }

            $item->update([
                'scan_status' => MediaModerationItem::STATUS_AWAITING_PROVIDER,
                'provider_progress' => $result->progress,
                'provider_response' => $result->raw,
                'provider_last_update_at' => now(),
                'provider_next_poll_at' => now()->addMinutes(self::INITIAL_PROVIDER_POLL_DELAY_MINUTES),
                'provider_poll_error' => null,
                'error_type' => null,
                'error_code' => null,
                'error_message' => null,
                'failed_at' => null,
            ]);
            $this->progress->recalculate($item->run_id);

            return;
        }

        $claimed = MediaModerationItem::query()
            ->whereKey($item->id)
            ->whereIn('scan_status', [
                MediaModerationItem::STATUS_AWAITING_PROVIDER,
                MediaModerationItem::STATUS_FAILED,
            ])
            ->update(['scan_status' => MediaModerationItem::STATUS_PROCESSING]);

        if ($claimed < 1) {
            if ($item->refresh()->scan_status === MediaModerationItem::STATUS_COMPLETED) {
                return;
            }

            throw new DomainException('The asynchronous moderation result is already being processed.');
        }

        try {
            $item = $item->refresh();
            $run = $item->run;

            if (!$run) {
                throw new DomainException('The moderation run no longer exists.');
            }

            $this->complete(
                $item,
                $run,
                $result,
                $this->quota->reservationFor($item),
                'async',
            );
        } catch (Throwable $exception) {
            MediaModerationItem::query()
                ->whereKey($item->id)
                ->where('scan_status', MediaModerationItem::STATUS_PROCESSING)
                ->update(['scan_status' => MediaModerationItem::STATUS_AWAITING_PROVIDER]);

            throw $exception;
        } finally {
            $this->progress->recalculate($item->run_id);
        }
    }

    protected function markPending(MediaModerationItem $item, ModerationPendingResult $result): void
    {
        $item->update([
            'scan_status' => MediaModerationItem::STATUS_AWAITING_PROVIDER,
            'provider_request_id' => $result->requestId,
            'provider_media_id' => $result->mediaId,
            'provider_processing_mode' => 'async',
            'provider_progress' => $result->progress,
            'provider_response' => $result->raw,
            'provider_submitted_at' => now(),
            'provider_last_update_at' => now(),
            'provider_poll_attempts' => 0,
            'provider_next_poll_at' => now()->addMinutes(self::INITIAL_PROVIDER_POLL_DELAY_MINUTES),
            'provider_poll_error' => null,
            'operations' => 0,
            'retryable' => false,
            'error_type' => null,
            'error_code' => null,
            'error_message' => null,
            'failed_at' => null,
        ]);
    }

    protected function complete(
        MediaModerationItem $item,
        MediaModerationRun $run,
        ModerationResult $result,
        ?QuotaReservation $reservation,
        string $processingMode,
    ): void {
        $actualOperations = $result->operations > 0
            ? $result->operations
            : (int) ($reservation->operations ?? 0);
        $this->settle($reservation, $actualOperations);
        $decision = $this->decisions->decide($result->maxScore, $run->policy);

        $item->update([
            'scan_status' => MediaModerationItem::STATUS_COMPLETED,
            'decision' => $decision,
            'max_score' => $result->maxScore,
            'matched_class' => $result->matchedClass,
            'matched_at_ms' => $result->matchedAtMs,
            'scores' => $result->scores,
            'provider_response' => $result->raw,
            'provider_request_id' => $result->requestId,
            'provider_media_id' => $result->mediaId,
            'provider_processing_mode' => $processingMode,
            'provider_progress' => $processingMode === 'async' ? 1 : null,
            'provider_last_update_at' => now(),
            'provider_next_poll_at' => null,
            'provider_poll_error' => null,
            'operations' => $actualOperations,
            'error_type' => null,
            'error_code' => null,
            'error_message' => null,
            'retryable' => false,
            'scanned_at' => now(),
            'failed_at' => null,
        ]);

        if ($processingMode === 'async') {
            $this->capacity->releaseAvailable($item->provider);
        }
    }

    protected function failAsync(
        MediaModerationItem $item,
        ModerationProviderException $exception,
        bool $consumeReservedBudget = false,
    ): void
    {
        $claimed = MediaModerationItem::query()
            ->whereKey($item->id)
            ->where('scan_status', MediaModerationItem::STATUS_AWAITING_PROVIDER)
            ->update(['scan_status' => MediaModerationItem::STATUS_PROCESSING]);

        if ($claimed < 1) {
            return;
        }

        $item = $item->refresh();

        if ($consumeReservedBudget) {
            $this->quota->consumeInterruptedReservation($item);
        } else {
            $reservation = $this->quota->reservationFor($item);
            $this->settle($reservation, $exception->operations);
        }

        $item->refresh();
        $item->update([
            'scan_status' => MediaModerationItem::STATUS_FAILED,
            'error_type' => $exception->errorType,
            'error_code' => $exception->providerCode,
            'error_message' => mb_substr($exception->getMessage(), 0, 1000),
            'operations' => max((int) $item->operations, $exception->operations),
            'provider_progress' => null,
            'provider_last_update_at' => now(),
            'provider_next_poll_at' => null,
            'retryable' => true,
            'failed_at' => now(),
        ]);
        $this->progress->recalculate($item->run_id);
        $this->capacity->releaseAvailable($item->provider);
    }

    protected function assertProviderIdentity(
        MediaModerationItem $item,
        mixed $mediaId,
        mixed $requestId,
    ): void {
        if (!$item->provider_media_id) {
            throw new DomainException('The asynchronous moderation submission has not finished saving yet.');
        }

        if (!is_string($mediaId) || !hash_equals($item->provider_media_id, $mediaId)) {
            throw new DomainException('The callback media identifier does not match this moderation item.');
        }

        if ($item->provider_request_id
            && (!is_string($requestId) || !hash_equals($item->provider_request_id, $requestId))) {
            throw new DomainException('The callback request identifier does not match this moderation item.');
        }
    }

    protected function handleProviderFailure(
        MediaModerationItem $item,
        MediaModerationRun $run,
        ModerationProviderException $exception,
    ): void {
        if ($exception->errorType === 'unsupported_media') {
            $this->skip($item, $exception->errorType, $exception->getMessage(), $exception->providerCode);

            return;
        }

        if ($exception->requiresAction()) {
            $this->pauseForProviderAction($item, $run, $exception);

            return;
        }

        if ($exception->isProviderCapacity() && $item->provider_processing_mode === 'async') {
            $this->capacity->wait($item, $exception->providerCode);

            return;
        }

        if ($exception->isUsageLimit()) {
            $status = MediaModerationItem::STATUS_QUOTA_PAUSED;
        } elseif ($exception->retryable && $run->execution_mode === QueueRuntimeService::MODE_WORKER && $item->attempts < 3) {
            $status = MediaModerationItem::STATUS_RETRYING;
        } else {
            $status = MediaModerationItem::STATUS_FAILED;
        }

        $item->update([
            'scan_status' => $status,
            'error_type' => $exception->errorType,
            'error_code' => $exception->providerCode,
            'error_message' => mb_substr($exception->getMessage(), 0, 1000),
            'provider_request_id' => $exception->requestId,
            'operations' => max((int) $item->operations, $exception->operations),
            'retryable' => $exception->retryable || $exception->isUsageLimit(),
            'failed_at' => $status === MediaModerationItem::STATUS_FAILED ? now() : null,
        ]);

        if ($status === MediaModerationItem::STATUS_RETRYING) {
            throw $exception;
        }
    }

    protected function pauseForProviderAction(
        MediaModerationItem $item,
        MediaModerationRun $run,
        ModerationProviderException $exception,
    ): void {
        $message = mb_substr($exception->getMessage(), 0, 1000);
        $affected = $run->items()
            ->whereIn('scan_status', [
                MediaModerationItem::STATUS_QUEUED,
                MediaModerationItem::STATUS_RETRYING,
                MediaModerationItem::STATUS_QUOTA_PAUSED,
            ]);

        if (in_array($exception->errorType, ['plan_error', 'configuration_error'], true)) {
            $affected->where('media_type', $item->media_type);
        }

        DB::transaction(function () use ($item, $run, $exception, $message, $affected): void {
            $item->update([
                'scan_status' => MediaModerationItem::STATUS_ACTION_REQUIRED,
                'error_type' => $exception->errorType,
                'error_code' => $exception->providerCode,
                'error_message' => $message,
                'provider_request_id' => $exception->requestId,
                'operations' => max((int) $item->operations, $exception->operations),
                'retryable' => true,
                'failed_at' => null,
            ]);

            $affected->update([
                'scan_status' => MediaModerationItem::STATUS_ACTION_REQUIRED,
                'error_type' => $exception->errorType,
                'error_code' => $exception->providerCode,
                'error_message' => $message,
                'retryable' => true,
                'failed_at' => null,
                'updated_at' => now(),
            ]);

            $run->update([
                'status' => MediaModerationRun::STATUS_ACTION_REQUIRED,
                'error' => $message,
                'last_activity_at' => now(),
            ]);
        });
    }

    protected function handleUnexpectedFailure(
        MediaModerationItem $item,
        MediaModerationRun $run,
        Throwable $exception,
    ): void {
        $retrying = $run->execution_mode === QueueRuntimeService::MODE_WORKER && $item->attempts < 3;
        $item->update([
            'scan_status' => $retrying
                ? MediaModerationItem::STATUS_RETRYING
                : MediaModerationItem::STATUS_FAILED,
            'error_type' => 'internal_error',
            'error_message' => 'The scan could not be completed. Review the application log for diagnostic details.',
            'retryable' => true,
            'failed_at' => $retrying ? null : now(),
        ]);

        report($exception);

        if ($retrying) {
            throw $exception;
        }
    }

    protected function skip(MediaModerationItem $item, string $type, string $message, ?string $code = null): void
    {
        $item->update([
            'scan_status' => MediaModerationItem::STATUS_SKIPPED,
            'error_type' => $type,
            'error_code' => $code,
            'error_message' => $message,
            'retryable' => false,
        ]);
    }

    protected function settle(?QuotaReservation $reservation, int $operations): void
    {
        if ($reservation) {
            $this->quota->settle($reservation, $operations);
        }
    }
}
