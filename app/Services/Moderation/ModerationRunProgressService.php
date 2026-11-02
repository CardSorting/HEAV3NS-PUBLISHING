<?php

namespace App\Services\Moderation;

use App\Model\MediaModerationItem;
use App\Model\MediaModerationRun;
use App\Model\TextModerationItem;
use App\Services\Queue\QueueRuntimeService;

class ModerationRunProgressService
{
    public function __construct(
        protected ModerationConfiguration $configuration,
        protected ModerationQuotaService $quota,
    ) {
    }

    public function recalculate(string|MediaModerationRun $run): ?MediaModerationRun
    {
        $run = is_string($run) ? MediaModerationRun::query()->find($run) : $run->refresh();

        if (!$run) {
            return null;
        }

        $items = $run->isText() ? $run->textItems() : $run->items();
        $itemClass = $run->isText() ? TextModerationItem::class : MediaModerationItem::class;
        $counts = $items
            ->selectRaw('scan_status, decision, is_cached, COUNT(*) as aggregate')
            ->groupBy(['scan_status', 'decision', 'is_cached'])
            ->get();
        $countStatus = fn (array $statuses): int => (int) $counts
            ->whereIn('scan_status', $statuses)
            ->sum('aggregate');
        $countDecision = fn (string $decision): int => (int) $counts
            ->where('scan_status', $itemClass::STATUS_COMPLETED)
            ->where('decision', $decision)
            ->sum('aggregate');

        $total = (int) $counts->sum('aggregate');
        $completed = $countStatus([$itemClass::STATUS_COMPLETED]);
        $failed = $countStatus([$itemClass::STATUS_FAILED]);
        $skipped = $countStatus([$itemClass::STATUS_SKIPPED]);
        $pending = $countStatus([
            $itemClass::STATUS_QUEUED,
            $itemClass::STATUS_PROCESSING,
            MediaModerationItem::STATUS_AWAITING_PROVIDER,
            MediaModerationItem::STATUS_WAITING_PROVIDER_CAPACITY,
            $itemClass::STATUS_RETRYING,
        ]);
        $waitingProviderCapacity = $countStatus([MediaModerationItem::STATUS_WAITING_PROVIDER_CAPACITY]);
        $quotaPaused = $countStatus([$itemClass::STATUS_QUOTA_PAUSED]);
        $actionRequired = $countStatus([$itemClass::STATUS_ACTION_REQUIRED]);
        $status = $run->status;

        if ($run->cancel_requested_at && $pending === 0) {
            $status = MediaModerationRun::STATUS_CANCELLED;
        } elseif ($actionRequired > 0 && $pending === 0) {
            $status = MediaModerationRun::STATUS_ACTION_REQUIRED;
        } elseif ($quotaPaused > 0 && $pending === 0) {
            $status = MediaModerationRun::STATUS_QUOTA_PAUSED;
        } elseif ($waitingProviderCapacity > 0) {
            $status = MediaModerationRun::STATUS_WAITING_PROVIDER_CAPACITY;
        } elseif ($total > 0 && ($completed + $failed + $skipped) === $total) {
            $status = $failed > 0
                ? MediaModerationRun::STATUS_COMPLETED_WITH_ERRORS
                : MediaModerationRun::STATUS_COMPLETED;
        } elseif ($total > 0) {
            $status = MediaModerationRun::STATUS_RUNNING;
        }

        $terminal = in_array($status, [
            MediaModerationRun::STATUS_COMPLETED,
            MediaModerationRun::STATUS_COMPLETED_WITH_ERRORS,
            MediaModerationRun::STATUS_CANCELLED,
        ], true);

        $run->update([
            'status' => $status,
            'total_items' => $total,
            'processed_items' => $completed + $failed + $skipped,
            'safe_items' => $countDecision($itemClass::DECISION_SAFE),
            'review_items' => $countDecision($itemClass::DECISION_REVIEW),
            'violation_items' => $countDecision($itemClass::DECISION_VIOLATION),
            'failed_items' => $failed,
            'skipped_items' => $skipped,
            'reused_items' => (int) $counts
                ->where('scan_status', $itemClass::STATUS_COMPLETED)
                ->where('is_cached', true)
                ->sum('aggregate'),
            'last_activity_at' => now(),
            'completed_at' => $terminal ? ($run->completed_at ?? now()) : null,
        ]);

        $run->refresh();
        if (isset($run->scope['content_review_id'])) {
            app(PublicationReviewScanner::class)->reconcile((int) $run->scope['content_review_id']);
        }

        return $run;
    }

    public function recoverStale(MediaModerationRun $run): int
    {
        $items = $run->isText() ? $run->textItems() : $run->items();
        $itemClass = $run->isText() ? TextModerationItem::class : MediaModerationItem::class;
        $staleItems = $items
            ->where('scan_status', $itemClass::STATUS_PROCESSING)
            ->where('started_at', '<', now()->subMinutes($this->configuration->staleAfterMinutes()))
            ->get();

        foreach ($staleItems as $item) {
            $this->quota->consumeInterruptedReservation($item);
            $item->update([
                'scan_status' => $itemClass::STATUS_FAILED,
                'error_type' => 'interrupted',
                'error_message' => 'The scan process stopped before reporting a result. Its reserved operation budget was conservatively counted as used, and the item can be retried.',
                'retryable' => true,
                'failed_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $recovered = $staleItems->count();

        if ($run->execution_mode !== QueueRuntimeService::MODE_WORKER
            && $run->last_activity_at?->lt(now()->subMinutes($this->configuration->staleAfterMinutes()))) {
            $orphanedItems = ($run->isText() ? $run->textItems() : $run->items())
                ->whereIn('scan_status', [
                    $itemClass::STATUS_QUEUED,
                    $itemClass::STATUS_RETRYING,
                ])
                ->get();

            foreach ($orphanedItems as $item) {
                $item->update([
                    'scan_status' => $itemClass::STATUS_FAILED,
                    'error_type' => 'interrupted',
                    'error_message' => 'The workerless scan process ended before this item ran. It can be retried.',
                    'retryable' => true,
                    'failed_at' => now(),
                ]);
            }

            $recovered += $orphanedItems->count();
        }

        $this->recalculate($run);

        return $recovered;
    }
}
