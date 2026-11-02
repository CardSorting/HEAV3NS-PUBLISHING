<?php

namespace App\Observers;

use App\Model\Attachment;
use App\Model\MediaModerationItem;
use App\Providers\AttachmentServiceProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AttachmentsObserver
{
    protected static ?bool $moderationTableExists = null;

    public function deleting(Attachment $attachment): void
    {
        if ($attachment->placements()->exists()) {
            throw new \LogicException('Remove attachment placements before deleting the shared file.');
        }
        $keys = AttachmentServiceProvider::getStorageKeys($attachment);
        $pending = $attachment->transcoding?->isPending();
        if ($pending) {
            $attachment->transcoding->update([
                'status' => \App\Model\AttachmentTranscoding::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);
        }
        $remote = $attachment->transcoding?->driver === \App\Model\AttachmentTranscoding::DRIVER_COCONUT
            && $attachment->transcoding->status !== \App\Model\AttachmentTranscoding::STATUS_COMPLETED;
        $job = new \App\Jobs\DeleteAttachmentFilesJob(
            AttachmentServiceProvider::getStorageProviderName($attachment->driver),
            $keys,
            $remote ? $attachment->transcoding->provider_job_id : null,
            $remote ? data_get($attachment->transcoding->options, 'region', getSetting('media.coconut_video_region')) : null,
            \App\Services\Media\FileAttachmentService::isFile($attachment),
        );
        // Give in-flight encoders time to observe cancellation before the final purge.
        if ($pending) {
            $job->delay(now()->addSeconds(960));
        }
        app(\Illuminate\Contracts\Bus\Dispatcher::class)->dispatch($job);

        self::$moderationTableExists ??= Schema::hasTable('media_moderation_items');

        if (!self::$moderationTableExists) {
            return;
        }

        $attachment->setRelation(
            '_moderationItemsBeingDeleted',
            $attachment->moderationItems()->get(),
        );
    }

    /**
     * Listen to the Attachment deleted event.
     *
     * @param  Attachment  $attachment
     * @return void
     */
    public function deleted(Attachment $attachment)
    {
        if (!$attachment->relationLoaded('_moderationItemsBeingDeleted')) {
            return;
        }

        $items = $attachment->getRelation('_moderationItemsBeingDeleted');

        foreach ($items as $item) {
            $item->update([
                'admin_outcome' => MediaModerationItem::OUTCOME_DELETED,
                'reviewed_at' => $item->reviewed_at ?? now(),
                'content_deleted_at' => now(),
            ]);
        }
    }
}
