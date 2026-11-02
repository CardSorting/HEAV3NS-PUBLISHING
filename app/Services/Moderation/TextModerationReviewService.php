<?php

namespace App\Services\Moderation;

use App\Model\TextModerationItem;

class TextModerationReviewService
{
    public function dismiss(TextModerationItem $item, ?int $actorId = null, ?string $notes = null): void
    {
        if ($item->content_deleted_at) {
            return;
        }

        $item->update([
            'admin_outcome' => TextModerationItem::OUTCOME_DISMISSED,
            'reviewed_by' => $actorId,
            'reviewed_at' => now(),
            'review_notes' => $this->notes($notes),
        ]);
    }

    public function confirm(TextModerationItem $item, ?int $actorId = null, ?string $notes = null): void
    {
        if ($item->content_deleted_at) {
            return;
        }

        $item->update([
            'admin_outcome' => TextModerationItem::OUTCOME_CONFIRMED,
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
