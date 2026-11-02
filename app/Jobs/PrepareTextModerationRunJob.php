<?php

namespace App\Jobs;

use App\Model\MediaModerationRun;
use App\Model\TextModerationItem;
use App\Services\Moderation\ModerationConfiguration;
use App\Services\Moderation\ModerationJobDispatcher;
use App\Services\Moderation\ModerationQuotaService;
use App\Services\Moderation\ModerationRunProgressService;
use App\Services\Moderation\TextModerationScanService;
use App\Services\Moderation\TextModerationSourceService;
use App\Services\Queue\QueueRuntimeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class PrepareTextModerationRunJob implements ShouldQueue
{
    use Dispatchable;

    use InteractsWithQueue;

    use Queueable;

    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public string $runId)
    {
    }

    public function handle(
        ModerationConfiguration $configuration,
        TextModerationSourceService $sources,
        ModerationJobDispatcher $dispatcher,
        ModerationRunProgressService $progress,
        TextModerationScanService $scanner,
    ): void {
        $run = MediaModerationRun::query()->find($this->runId);

        if (!$run || !$run->isText() || $run->isTerminal() || $run->cancel_requested_at) {
            return;
        }

        $run->update([
            'status' => MediaModerationRun::STATUS_PREPARING,
            'started_at' => $run->started_at ?? now(),
            'last_activity_at' => now(),
            'error' => null,
        ]);

        $modelSignature = $configuration->textModelSignature();
        $policySignature = $configuration->textPolicySignature();
        $itemIds = [];

        foreach ($sources->candidates(
            $run->scope,
            $run->provider,
            $modelSignature,
            $policySignature,
        ) as $candidate) {
            $item = TextModerationItem::query()->firstOrCreate(
                [
                    'run_id' => $run->id,
                    'source_type' => $candidate->sourceType,
                    'source_id' => $candidate->sourceId,
                    'source_field' => $candidate->sourceField,
                ],
                [
                    'owner_user_id' => $candidate->ownerUserId,
                    'subject_type' => $candidate->subjectType,
                    'subject_id' => $candidate->subjectId,
                    'source_snapshot' => $candidate->text,
                    'source_hash' => $candidate->hash(),
                    'source_updated_at' => $candidate->updatedAt,
                    'provider' => $run->provider,
                    'model_signature' => $modelSignature,
                    'policy_signature' => $policySignature,
                    'scan_status' => TextModerationItem::STATUS_QUEUED,
                    'queued_at' => now(),
                ],
            );
            $itemIds[] = $item->id;

            if (count($itemIds) >= $run->max_items) {
                break;
            }
        }

        if ($itemIds === []) {
            $run->update([
                'status' => MediaModerationRun::STATUS_COMPLETED,
                'total_items' => 0,
                'completed_at' => now(),
                'last_activity_at' => now(),
            ]);

            return;
        }

        $run->update([
            'status' => MediaModerationRun::STATUS_RUNNING,
            'total_items' => count($itemIds),
            'last_activity_at' => now(),
        ]);

        foreach ($itemIds as $itemId) {
            if ($run->execution_mode === QueueRuntimeService::MODE_WORKER) {
                $dispatcher->textItem($run, $itemId);
            } else {
                $scanner->process($itemId);
            }
        }

        $progress->recalculate($run);
    }

    public function failed(?Throwable $exception): void
    {
        $run = MediaModerationRun::query()->find($this->runId);

        if (!$run || !$run->isText()) {
            return;
        }

        $run->textItems()
            ->where('scan_status', TextModerationItem::STATUS_PROCESSING)
            ->get()
            ->each(fn (TextModerationItem $item) => app(ModerationQuotaService::class)
                ->consumeInterruptedReservation($item));

        $run->textItems()
            ->whereIn('scan_status', [
                TextModerationItem::STATUS_QUEUED,
                TextModerationItem::STATUS_PROCESSING,
                TextModerationItem::STATUS_RETRYING,
            ])
            ->update([
                'scan_status' => TextModerationItem::STATUS_FAILED,
                'error_type' => 'preparation_failed',
                'error_message' => 'The text scan process ended unexpectedly. This item can be retried.',
                'retryable' => true,
                'failed_at' => now(),
                'updated_at' => now(),
            ]);

        app(ModerationRunProgressService::class)->recalculate($run);

        $run->update([
            'status' => MediaModerationRun::STATUS_FAILED,
            'failed_at' => now(),
            'last_activity_at' => now(),
            'error' => $exception ? mb_substr($exception->getMessage(), 0, 2000) : 'The text scan could not be prepared.',
        ]);
    }
}
