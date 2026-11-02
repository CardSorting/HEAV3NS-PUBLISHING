<?php

namespace App\Services\Moderation;

use App\Model\TextModerationItem;
use DomainException;

class TextModerationDeletionService
{
    public function __construct(protected TextModerationSourceService $sources)
    {
    }

    public function deleteConfirmed(
        TextModerationItem $item,
        ?int $actorId = null,
        ?string $notes = null,
    ): void {
        if ($item->admin_outcome !== TextModerationItem::OUTCOME_CONFIRMED) {
            throw new DomainException('Only administrator-confirmed text can be deleted.');
        }

        $this->sources->deleteCurrent($item);

        $relatedItems = TextModerationItem::query()
            ->where('source_type', $item->source_type)
            ->where('source_id', $item->source_id)
            ->where('source_field', $item->source_field)
            ->where('source_hash', $item->source_hash)
            ->get();
        $now = now();
        $notes = trim((string) $notes);
        $notes = $notes === '' ? null : mb_substr($notes, 0, 2000);

        foreach ($relatedItems as $relatedItem) {
            $relatedItem->update([
                'admin_outcome' => TextModerationItem::OUTCOME_DELETED,
                'deleted_by' => $actorId,
                'content_deleted_at' => $now,
                'deletion_notes' => $notes,
            ]);
        }
    }
}
