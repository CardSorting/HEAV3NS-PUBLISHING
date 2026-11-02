<?php

namespace App\Services\Moderation;

use App\Model\MediaModerationItem;
use App\Model\User;
use App\Services\Media\AttachmentLifecycleService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ModerationDeletionService
{
    public function __construct(
        protected ProfileMediaSourceService $profileMedia,
        protected StreamPosterSourceService $streamPosters,
    ) {
    }

    public function deleteMedia(MediaModerationItem $item, ?int $actorId = null, ?string $notes = null): void
    {
        if ($item->isProfileAsset()) {
            $this->profileMedia->removeCurrent($item);
            $relatedItems = MediaModerationItem::query()
                ->where('source_type', $item->source_type)
                ->where('source_id', $item->source_id)
                ->where('source_path', $item->source_path)
                ->get();
            $this->markDeleted($relatedItems, $actorId, $notes);

            return;
        }

        if ($item->isStreamPoster()) {
            $this->streamPosters->removeCurrent($item);
            $relatedItems = MediaModerationItem::query()
                ->where('source_type', $item->source_type)
                ->where('source_id', $item->source_id)
                ->where('source_path', $item->source_path)
                ->get();
            $this->markDeleted($relatedItems, $actorId, $notes);

            return;
        }

        $attachment = $item->attachment;

        if (!$attachment) {
            $items = collect([$item]);
            $this->markDeleted($items, $actorId, $notes);

            return;
        }

        $relatedItems = MediaModerationItem::query()
            ->where('attachment_id', $attachment->id)
            ->get();

        DB::transaction(function () use ($attachment, $relatedItems, $actorId, $notes): void {
            app(AttachmentLifecycleService::class)->deleteEverywhere($attachment);
            $this->markDeleted($relatedItems, $actorId, $notes);
        });
    }

    public function deleteParent(MediaModerationItem $item, ?int $actorId = null, ?string $notes = null, ?string $parentTarget = null): void
    {
        if (!$item->attachment || !$parentTarget) {
            throw new DomainException('Select the current parent content to delete.');
        }

        $usage = app(AttachmentMediaService::class)->usages($item->attachment)
            ->first(fn (array $usage): bool => $usage['type'].':'.$usage['id'] === $parentTarget
                && in_array($usage['type'], ['post', 'message', 'story', 'reel'], true));

        if (!$usage) {
            throw new DomainException('The selected content no longer uses this media. Refresh the finding.');
        }

        DB::transaction(function () use ($item, $usage, $actorId, $notes): void {
            $parent = $usage['parent']->newQuery()->whereKey($usage['id'])->lockForUpdate()->firstOrFail();
            if (!$parent->placements()->where('attachment_id', $item->attachment_id)->exists()) {
                throw new DomainException('The selected content no longer uses this media. Refresh the finding.');
            }
            if ($actorId !== null) {
                Gate::forUser(User::query()->findOrFail($actorId))->authorize('delete', $parent);
            }
            app(AttachmentLifecycleService::class)->deleteParent($parent);

            // The finding still describes a retained asset. Do not mark it globally
            // deleted simply because one of its placements was removed.
            $entry = now()->toIso8601String().' — Deleted '.$usage['type'].' #'.$usage['id']
                .($actorId !== null ? ' by administrator #'.$actorId : '')
                .(filled($notes) ? ': '.trim($notes) : '.');
            $lockedItem = $item->newQuery()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $lockedItem->update([
                'deletion_notes' => mb_substr(trim((string) $lockedItem->deletion_notes."\n".$entry), -2000),
            ]);
        }, 3);
    }

    public function deleteConfirmed(
        MediaModerationItem $item,
        string $target,
        ?int $actorId = null,
        ?string $notes = null,
        ?string $parentTarget = null,
    ): void
    {
        if ($item->admin_outcome !== MediaModerationItem::OUTCOME_CONFIRMED) {
            throw new DomainException('Only administrator-confirmed items can be deleted in bulk.');
        }

        match ($target) {
            'media' => $this->deleteMedia($item, $actorId, $notes),
            'parent' => $this->deleteParent($item, $actorId, $notes, $parentTarget),
            default => throw new DomainException('Unknown moderation deletion target.'),
        };
    }

    protected function markDeleted($items, ?int $actorId, ?string $notes): void
    {
        $now = now();
        $notes = trim((string) $notes);
        $notes = $notes === '' ? null : mb_substr($notes, 0, 2000);

        foreach ($items as $item) {
            $item->update([
                'admin_outcome' => MediaModerationItem::OUTCOME_DELETED,
                'deleted_by' => $actorId,
                'content_deleted_at' => $now,
                'deletion_notes' => $notes,
            ]);
        }
    }
}
