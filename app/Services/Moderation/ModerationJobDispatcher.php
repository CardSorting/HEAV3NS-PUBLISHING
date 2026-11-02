<?php

namespace App\Services\Moderation;

use App\Jobs\PrepareMediaModerationRunJob;
use App\Jobs\ScanMediaModerationItemJob;
use App\Jobs\DeleteConfirmedModerationItemsJob;
use App\Jobs\ProcessMediaModerationItemsJob;
use App\Jobs\PrepareTextModerationRunJob;
use App\Jobs\ProcessTextModerationItemsJob;
use App\Jobs\ScanTextModerationItemJob;
use App\Model\MediaModerationItem;
use App\Model\MediaModerationRun;
use App\Model\TextModerationItem;
use Illuminate\Support\Facades\DB;
use App\Services\Queue\QueueRuntimeService;

class ModerationJobDispatcher
{
    public function __construct(protected QueueRuntimeService $runtime)
    {
    }

    public function prepare(MediaModerationRun $run): void
    {
        $this->afterCommit(static function () use ($run): void {
            PrepareMediaModerationRunJob::dispatch($run->id)
                ->onConnection($run->connection)
                ->onQueue($run->queue);
        });
    }

    public function item(MediaModerationRun $run, MediaModerationItem|int $item): void
    {
        $itemId = $item instanceof MediaModerationItem ? $item->id : $item;

        ScanMediaModerationItemJob::dispatch($itemId)
            ->onConnection($run->connection)
            ->onQueue($run->queue);
    }

    public function items(MediaModerationRun $run, array $itemIds): void
    {
        if ($itemIds === []) {
            return;
        }

        if ($run->execution_mode === QueueRuntimeService::MODE_WORKER) {
            foreach ($itemIds as $itemId) {
                $this->item($run, (int) $itemId);
            }

            return;
        }

        ProcessMediaModerationItemsJob::dispatch(array_values(array_map('intval', $itemIds)))
            ->onConnection($run->connection)
            ->onQueue($run->queue);
    }

    public function prepareText(MediaModerationRun $run): void
    {
        $this->afterCommit(static function () use ($run): void {
            PrepareTextModerationRunJob::dispatch($run->id)
                ->onConnection($run->connection)
                ->onQueue($run->queue);
        });
    }

    public function textItem(MediaModerationRun $run, TextModerationItem|int $item): void
    {
        $itemId = $item instanceof TextModerationItem ? $item->id : $item;

        ScanTextModerationItemJob::dispatch($itemId)
            ->onConnection($run->connection)
            ->onQueue($run->queue);
    }

    public function textItems(MediaModerationRun $run, array $itemIds): void
    {
        if ($itemIds === []) {
            return;
        }

        if ($run->execution_mode === QueueRuntimeService::MODE_WORKER) {
            foreach ($itemIds as $itemId) {
                $this->textItem($run, (int) $itemId);
            }

            return;
        }

        ProcessTextModerationItemsJob::dispatch(array_values(array_map('intval', $itemIds)))
            ->onConnection($run->connection)
            ->onQueue($run->queue);
    }

    public function deleteConfirmed(
        MediaModerationRun $run,
        string $target,
        ?int $actorId = null,
        ?string $notes = null,
    ): void
    {
        $connection = $this->runtime->connection();
        $queue = $this->runtime->queueName();

        DeleteConfirmedModerationItemsJob::dispatch($run->id, $target, $actorId, $notes)
            ->onConnection($connection)
            ->onQueue($queue);
    }

    protected function afterCommit(callable $callback): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit($callback);

            return;
        }

        $callback();
    }
}
