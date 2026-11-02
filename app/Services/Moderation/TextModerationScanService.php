<?php

namespace App\Services\Moderation;

use App\Model\MediaModerationRun;
use App\Model\TextModerationItem;
use App\Services\Moderation\Data\ModerationResult;
use App\Services\Moderation\Data\QuotaReservation;
use App\Services\Moderation\Exceptions\ModerationProviderException;
use App\Services\Queue\QueueRuntimeService;
use Illuminate\Support\Facades\DB;
use Throwable;

class TextModerationScanService
{
    public function __construct(
        protected TextModerationSourceService $sources,
        protected ModerationProviderManager $providers,
        protected ModerationDecisionService $decisions,
        protected ModerationSignatureService $signatures,
        protected ModerationQuotaService $quota,
        protected ModerationRunProgressService $progress,
    ) {
    }

    public function process(TextModerationItem|int $item): void
    {
        $itemId = $item instanceof TextModerationItem ? $item->id : $item;
        $item = $this->claim($itemId);

        if (!$item) {
            return;
        }

        $run = $item->run;

        if (!$run || !$run->isText() || $run->cancel_requested_at || $run->isTerminal()) {
            $this->skip($item, 'cancelled', 'The scan was cancelled before this text was processed.');
            $this->progress->recalculate($item->run_id);

            return;
        }

        if (!$this->sources->isCurrent($item)) {
            $this->skip($item, 'text_changed', 'This text was edited or removed before it could be scanned.');
            $this->progress->recalculate($run);

            return;
        }

        $reservation = null;

        try {
            if (!($run->scope['force_rescan'] ?? false)) {
                $reusable = $this->findReusable($item);

                if ($reusable) {
                    $this->reuse($item, $reusable, $run);
                    $this->progress->recalculate($run);

                    return;
                }
            }

            $provider = $this->providers->textProvider($run->provider);
            $estimatedOperations = $provider->estimateOperations(
                $item->source_snapshot,
                $run->models,
                $run->policy,
            );
            $reservation = $this->quota->reserve($run, $item, $estimatedOperations);

            if (!$reservation) {
                $item->update([
                    'scan_status' => TextModerationItem::STATUS_QUOTA_PAUSED,
                    'error_type' => 'operation_budget',
                    'error_message' => 'The configured operation budget has been reached. This item can be resumed later.',
                    'retryable' => true,
                ]);
                $this->progress->recalculate($run);

                return;
            }

            $result = $provider->scan($item->source_snapshot, $run->models, $run->policy);
            $this->complete($item, $run, $result, $reservation);
            $reservation = null;
        } catch (ModerationProviderException $exception) {
            $this->settle($reservation, $exception->operations);
            $reservation = null;
            $this->handleProviderFailure($item, $run, $exception);
        } catch (Throwable $exception) {
            $this->settle($reservation, 0);
            $reservation = null;
            $this->handleUnexpectedFailure($item, $run, $exception);
        } finally {
            $this->settle($reservation, 0);
            $this->progress->recalculate($run);
        }
    }

    protected function claim(int $itemId): ?TextModerationItem
    {
        return DB::transaction(function () use ($itemId): ?TextModerationItem {
            /** @var TextModerationItem|null $item */
            $item = TextModerationItem::query()->lockForUpdate()->find($itemId);

            if (!$item || !in_array($item->scan_status, [
                TextModerationItem::STATUS_QUEUED,
                TextModerationItem::STATUS_RETRYING,
                TextModerationItem::STATUS_QUOTA_PAUSED,
                TextModerationItem::STATUS_FAILED,
            ], true)) {
                return null;
            }

            $item->update([
                'scan_status' => TextModerationItem::STATUS_PROCESSING,
                'attempts' => $item->attempts + 1,
                'started_at' => now(),
                'failed_at' => null,
                'error_type' => null,
                'error_code' => null,
                'error_message' => null,
            ]);

            return $item->refresh();
        }, 3);
    }

    protected function findReusable(TextModerationItem $item): ?TextModerationItem
    {
        $base = TextModerationItem::query()
            ->whereKeyNot($item->id)
            ->where('provider', $item->provider)
            ->where('model_signature', $item->model_signature)
            ->where('scan_status', TextModerationItem::STATUS_COMPLETED)
            ->whereNotNull('decision');
        $sameSource = (clone $base)
            ->where('source_type', $item->source_type)
            ->where('source_id', $item->source_id)
            ->where('source_field', $item->source_field)
            ->where('source_hash', $item->source_hash)
            ->latest('scanned_at')
            ->first();

        return $sameSource ?: (clone $base)
            ->where('source_hash', $item->source_hash)
            ->latest('scanned_at')
            ->first();
    }

    protected function reuse(
        TextModerationItem $item,
        TextModerationItem $reusable,
        MediaModerationRun $run,
    ): void {
        $policySignature = $this->signatures->policy($run->policy);
        $sameSource = $reusable->source_type === $item->source_type
            && (string) $reusable->source_id === (string) $item->source_id
            && $reusable->source_field === $item->source_field
            && hash_equals($reusable->source_hash, $item->source_hash);
        $carryReview = $sameSource
            && hash_equals($policySignature, (string) $reusable->policy_signature);
        $supersedesPriorReview = $sameSource
            && !$carryReview
            && $reusable->admin_outcome === TextModerationItem::OUTCOME_UNREVIEWED;
        $storedResult = is_array($reusable->provider_response)
            ? $this->providers->textProvider($run->provider)->normalizeStored(
                $reusable->provider_response,
                $run->policy,
            )
            : null;
        $maxScore = $storedResult->maxScore ?? (float) $reusable->max_score;

        $item->update([
            'reused_from_item_id' => $reusable->id,
            'scan_status' => TextModerationItem::STATUS_COMPLETED,
            'decision' => $this->decisions->decide($maxScore, $run->policy),
            'admin_outcome' => $carryReview
                ? $reusable->admin_outcome
                : TextModerationItem::OUTCOME_UNREVIEWED,
            'max_score' => $maxScore,
            'matched_class' => $storedResult->matchedClass ?? $reusable->matched_class,
            'scores' => $storedResult->scores ?? $reusable->scores,
            'provider_response' => $reusable->provider_response,
            'provider_request_id' => $reusable->provider_request_id,
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

        if ($supersedesPriorReview) {
            $reusable->update([
                'admin_outcome' => TextModerationItem::OUTCOME_SUPERSEDED,
                'reviewed_at' => now(),
            ]);
        }
    }

    protected function complete(
        TextModerationItem $item,
        MediaModerationRun $run,
        ModerationResult $result,
        QuotaReservation $reservation,
    ): void {
        $actualOperations = $result->operations > 0
            ? $result->operations
            : $reservation->operations;
        $this->quota->settle($reservation, $actualOperations);

        $item->update([
            'scan_status' => TextModerationItem::STATUS_COMPLETED,
            'decision' => $this->decisions->decide($result->maxScore, $run->policy),
            'max_score' => $result->maxScore,
            'matched_class' => $result->matchedClass,
            'scores' => $result->scores,
            'provider_response' => $result->raw,
            'provider_request_id' => $result->requestId,
            'operations' => $actualOperations,
            'error_type' => null,
            'error_code' => null,
            'error_message' => null,
            'retryable' => false,
            'scanned_at' => now(),
            'failed_at' => null,
        ]);
    }

    protected function handleProviderFailure(
        TextModerationItem $item,
        MediaModerationRun $run,
        ModerationProviderException $exception,
    ): void {
        if ($exception->requiresAction()) {
            $this->pauseForProviderAction($item, $run, $exception);

            return;
        }

        if ($exception->isUsageLimit()) {
            $status = TextModerationItem::STATUS_QUOTA_PAUSED;
        } elseif ($exception->retryable
            && $run->execution_mode === QueueRuntimeService::MODE_WORKER
            && $item->attempts < 3) {
            $status = TextModerationItem::STATUS_RETRYING;
        } else {
            $status = TextModerationItem::STATUS_FAILED;
        }

        $item->update([
            'scan_status' => $status,
            'error_type' => $exception->errorType,
            'error_code' => $exception->providerCode,
            'error_message' => mb_substr($exception->getMessage(), 0, 1000),
            'provider_request_id' => $exception->requestId,
            'operations' => max((int) $item->operations, $exception->operations),
            'retryable' => $exception->retryable || $exception->isUsageLimit(),
            'failed_at' => $status === TextModerationItem::STATUS_FAILED ? now() : null,
        ]);

        if ($status === TextModerationItem::STATUS_RETRYING) {
            throw $exception;
        }
    }

    protected function pauseForProviderAction(
        TextModerationItem $item,
        MediaModerationRun $run,
        ModerationProviderException $exception,
    ): void {
        $message = mb_substr($exception->getMessage(), 0, 1000);

        DB::transaction(function () use ($item, $run, $exception, $message): void {
            $item->update([
                'scan_status' => TextModerationItem::STATUS_ACTION_REQUIRED,
                'error_type' => $exception->errorType,
                'error_code' => $exception->providerCode,
                'error_message' => $message,
                'provider_request_id' => $exception->requestId,
                'operations' => max((int) $item->operations, $exception->operations),
                'retryable' => true,
                'failed_at' => null,
            ]);
            $run->textItems()
                ->whereIn('scan_status', [
                    TextModerationItem::STATUS_QUEUED,
                    TextModerationItem::STATUS_RETRYING,
                    TextModerationItem::STATUS_QUOTA_PAUSED,
                ])
                ->update([
                    'scan_status' => TextModerationItem::STATUS_ACTION_REQUIRED,
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
        TextModerationItem $item,
        MediaModerationRun $run,
        Throwable $exception,
    ): void {
        $retrying = $run->execution_mode === QueueRuntimeService::MODE_WORKER && $item->attempts < 3;
        $item->update([
            'scan_status' => $retrying
                ? TextModerationItem::STATUS_RETRYING
                : TextModerationItem::STATUS_FAILED,
            'error_type' => 'internal_error',
            'error_message' => 'The text scan could not be completed. Review the application log for diagnostic details.',
            'retryable' => true,
            'failed_at' => $retrying ? null : now(),
        ]);

        report($exception);

        if ($retrying) {
            throw $exception;
        }
    }

    protected function skip(TextModerationItem $item, string $type, string $message): void
    {
        $item->update([
            'scan_status' => TextModerationItem::STATUS_SKIPPED,
            'error_type' => $type,
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
