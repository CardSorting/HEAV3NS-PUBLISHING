<?php

namespace App\Services\Moderation;

use App\Model\MediaModerationRun;
use App\Model\TextModerationItem;
use App\Services\Queue\QueueRuntimeService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TextModerationRunService
{
    public function __construct(
        protected ModerationConfiguration $configuration,
        protected TextModerationSourceService $sources,
        protected ModerationProviderManager $providers,
        protected ModerationQuotaService $quota,
        protected QueueRuntimeService $runtime,
        protected ModerationJobDispatcher $dispatcher,
        protected ModerationRunProgressService $progress,
    ) {
    }

    public function estimate(array $scope): array
    {
        if (!$this->configuration->textEnabled()) {
            throw new DomainException('Text moderation is disabled or its provider setup is incomplete.');
        }

        $scope = $this->sources->normalizeScope($scope);
        $provider = $this->providers->textProvider($this->configuration->provider());
        $models = $this->configuration->textModels();
        $policy = $this->configuration->textPolicy();
        $sourceCounts = array_fill_keys($scope['sources'], 0);
        $items = 0;
        $operations = 0;

        foreach ($this->sources->candidates(
            $scope,
            $this->configuration->provider(),
            $this->configuration->textModelSignature(),
            $this->configuration->textPolicySignature(),
        ) as $candidate) {
            $items++;
            $operations += $provider->estimateOperations($candidate->text, $models, $policy);

            foreach ($this->sources->definitions() as $source => $definition) {
                if ($definition['source_type'] === $candidate->sourceType) {
                    $sourceCounts[$source] = ($sourceCounts[$source] ?? 0) + 1;
                    break;
                }
            }
        }

        $usage = $this->quota->usage($this->configuration->provider());

        return array_merge($usage, [
            'scan_type' => MediaModerationRun::TYPE_TEXT,
            'items' => $items,
            'estimated_operations' => $operations,
            'source_counts' => $sourceCounts,
            'mode' => $this->runtime->mode(),
            'mode_label' => $this->runtime->modeLabel(),
            'mode_item_limit' => $this->configuration->itemLimitForMode($this->runtime->mode()),
            'daily_remaining' => max(0, $usage['daily_limit'] - $usage['daily_used'] - $usage['daily_reserved']),
            'monthly_remaining' => max(0, $usage['monthly_limit'] - $usage['monthly_used'] - $usage['monthly_reserved']),
        ]);
    }

    public function start(
        array $scope,
        ?int $initiatedBy,
        ?int $requestedItems = null,
        ?int $requestedOperations = null,
    ): MediaModerationRun {
        if (!$this->configuration->textEnabled()) {
            throw new DomainException('Text moderation must be enabled and configured before a scan can start.');
        }

        $scope = $this->sources->normalizeScope($scope);

        if ($scope['sources'] === []) {
            throw new DomainException('Select at least one text source before starting a text scan.');
        }

        $estimate = $this->estimate($scope);
        $itemLimit = $this->configuration->itemLimitForMode($this->runtime->mode());
        $maxItems = min($itemLimit, max(1, $requestedItems ?? $itemLimit));
        $availableOperations = min($estimate['daily_remaining'], $estimate['monthly_remaining']);
        $maxOperations = min(
            max(1, $requestedOperations ?? max(1, $availableOperations)),
            max(1, $availableOperations),
        );

        if ($estimate['items'] < 1) {
            throw new DomainException('No matching unscanned text was found.');
        }

        $run = DB::transaction(function () use ($scope, $initiatedBy, $maxItems, $maxOperations) {
            return MediaModerationRun::query()->create([
                'id' => (string) Str::uuid(),
                'initiated_by' => $initiatedBy,
                'scan_type' => MediaModerationRun::TYPE_TEXT,
                'provider' => $this->configuration->provider(),
                'model_signature' => $this->configuration->textModelSignature(),
                'models' => $this->configuration->textModels(),
                'scope' => $scope,
                'policy' => $this->configuration->textPolicy(),
                'status' => MediaModerationRun::STATUS_QUEUED,
                'execution_mode' => $this->runtime->mode(),
                'connection' => $this->runtime->connection(),
                'queue' => $this->runtime->queueName(),
                'max_items' => $maxItems,
                'max_operations' => $maxOperations,
                'last_activity_at' => now(),
            ]);
        });

        $this->dispatcher->prepareText($run);

        return $run->refresh();
    }

    public function cancel(MediaModerationRun $run): MediaModerationRun
    {
        if ($run->isTerminal()) {
            return $run;
        }

        $run->update(['cancel_requested_at' => now(), 'last_activity_at' => now()]);
        $run->textItems()
            ->whereIn('scan_status', [
                TextModerationItem::STATUS_QUEUED,
                TextModerationItem::STATUS_RETRYING,
                TextModerationItem::STATUS_QUOTA_PAUSED,
                TextModerationItem::STATUS_ACTION_REQUIRED,
            ])
            ->update([
                'scan_status' => TextModerationItem::STATUS_SKIPPED,
                'error_type' => 'cancelled',
                'error_message' => 'The scan was cancelled by an administrator.',
                'updated_at' => now(),
            ]);

        return $this->progress->recalculate($run) ?? $run->refresh();
    }

    public function resume(MediaModerationRun $run, bool $includeFailed = false): MediaModerationRun
    {
        if ($run->status === MediaModerationRun::STATUS_CANCELLED) {
            throw new DomainException('A cancelled scan cannot be resumed.');
        }

        $run->update([
            'status' => MediaModerationRun::STATUS_RUNNING,
            'completed_at' => null,
            'failed_at' => null,
            'error' => null,
            'last_activity_at' => now(),
        ]);
        $items = $run->textItems()
            ->where(function ($query) use ($includeFailed): void {
                $query->where('scan_status', TextModerationItem::STATUS_QUOTA_PAUSED)
                    ->orWhere('scan_status', TextModerationItem::STATUS_ACTION_REQUIRED);

                if ($includeFailed) {
                    $query->orWhere(function ($query): void {
                        $query->where('scan_status', TextModerationItem::STATUS_FAILED)
                            ->where('retryable', true);
                    });
                }
            })
            ->get();
        $run->textItems()->whereIn('id', $items->modelKeys())->update([
            'scan_status' => TextModerationItem::STATUS_QUEUED,
            'error_type' => null,
            'error_code' => null,
            'error_message' => null,
            'provider_request_id' => null,
            'failed_at' => null,
            'updated_at' => now(),
        ]);

        $this->dispatcher->textItems($run, $items->modelKeys());

        return $this->progress->recalculate($run) ?? $run->refresh();
    }
}
