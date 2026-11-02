<?php

namespace App\Jobs;

use App\Model\MediaModerationItem;
use App\Model\MediaModerationRun;
use App\Model\User;
use App\Services\Moderation\ModerationDeletionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeleteConfirmedModerationItemsJob implements ShouldQueue
{
    use Dispatchable;

    use InteractsWithQueue;

    use Queueable;

    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 240;

    public function __construct(
        public string $runId,
        public string $target,
        public ?int $actorId = null,
        public ?string $notes = null,
    ) {
    }

    public function handle(ModerationDeletionService $deletion): void
    {
        $run = MediaModerationRun::query()->find($this->runId);

        if (!$run) {
            return;
        }

        $actorId = $this->actorId && User::query()->whereKey($this->actorId)->exists()
            ? $this->actorId
            : null;

        $run->items()
            ->where('admin_outcome', MediaModerationItem::OUTCOME_CONFIRMED)
            ->whereNull('content_deleted_at')
            ->orderBy('id')
            ->chunkById(50, function ($items) use ($deletion, $actorId) {
                foreach ($items as $item) {
                    $freshItem = $item->fresh();

                    if ($freshItem?->admin_outcome === MediaModerationItem::OUTCOME_CONFIRMED) {
                        $deletion->deleteConfirmed(
                            $freshItem,
                            $this->target,
                            $actorId,
                            $this->notes,
                        );
                    }
                }
            });
    }
}
