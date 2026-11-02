<?php

namespace App\Services\Moderation;

use App\Model\MediaModerationItem;

class ModerationReviewService
{
    public function dismiss(MediaModerationItem $item, ?int $actorId = null, ?string $notes = null): void
    {
        if ($item->content_deleted_at) {
            return;
        }

        $item->update([
            'admin_outcome' => MediaModerationItem::OUTCOME_DISMISSED,
            'reviewed_by' => $actorId,
            'reviewed_at' => now(),
            'review_notes' => $this->notes($notes),
        ]);
    }

    public function confirm(MediaModerationItem $item, ?int $actorId = null, ?string $notes = null): void
    {
        if ($item->content_deleted_at) {
            return;
        }

        $item->update([
            'admin_outcome' => MediaModerationItem::OUTCOME_CONFIRMED,
            'reviewed_by' => $actorId,
            'reviewed_at' => now(),
            'review_notes' => $this->notes($notes),
        ]);
    }

    protected function notes(?string $notes): ?string
    {
        $notes = trim((string) $notes);

        return $notes === '' ? null : mb_substr($notes, 0, 2000);
    }
}
