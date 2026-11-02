<?php

namespace App\Services\Media;

use App\Model\Attachment;
use App\Model\AttachmentPlacement;
use App\Model\AttachmentTranscoding;
use App\Model\MessageTemplate;
use App\Model\Post;
use App\Model\Reel;
use App\Model\Story;
use App\Model\UserMessage;
use App\Providers\AttachmentServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttachmentPlacementService
{
    public function __construct(protected AttachmentEligibility $eligibility)
    {
    }

    public function sync(Post|Reel|Story|UserMessage|MessageTemplate $parent, array $orderedAttachmentIds, int $ownerId, ?MessageTemplate $sourceTemplate = null): void
    {
        DB::transaction(function () use ($parent, $orderedAttachmentIds, $ownerId, $sourceTemplate) {
            $key = AttachmentPlacement::parentKey($parent);
            $lockedParent = $parent->newQuery()->whereKey($parent->getKey())->lockForUpdate()->firstOrFail();
            $actualOwner = $lockedParent instanceof UserMessage ? $lockedParent->sender_id : $lockedParent->user_id;
            if ((int) $actualOwner !== $ownerId) {
                $this->invalid();
            }
            $ids = array_values(array_unique(array_map('strval', $orderedAttachmentIds)));
            $templateIds = [];
            $template = null;
            if ($sourceTemplate !== null) {
                $template = MessageTemplate::query()->whereKey($sourceTemplate->id)->lockForUpdate()->firstOrFail();
                if (!$lockedParent instanceof UserMessage || !$template->enabled
                    || (int) $template->user_id !== $ownerId
                    || (int) $lockedParent->message_template_id !== (int) $template->id) {
                    $this->invalid();
                }
            }
            $assets = Attachment::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($assets->count() !== count($ids)) {
                $this->invalid();
            }
            // Current reads after asset locks avoid stale repeatable-read snapshots during edits.
            $retained = $lockedParent->placements()->lockForUpdate()->pluck('attachment_id')->all();
            if ($template) {
                $templateIds = $template->placements()->orderBy('position')->orderBy('id')
                    ->lockForUpdate()->pluck('attachment_id')->all();
                // Never send a partially changed/deleted template, particularly a paid one.
                if ($ids !== $templateIds) {
                    $this->invalid();
                }
            }
            $protected = $ids ? $this->eligibility->protectedFilenames() : [];
            foreach ($ids as $id) {
                $asset = $assets[$id];
                if ((int) $asset->user_id !== $ownerId || !$asset->vaulted_at || $this->eligibility->isProtected($asset, $protected)) {
                    $this->invalid();
                }
                if ($asset->vault_archived_at && !in_array($id, $retained, true) && !in_array($id, $templateIds, true)) {
                    $this->invalid(__('Restore archived media before adding it to new content.'));
                }
                $transcoding = $asset->transcoding()->lockForUpdate()->first();
                $asset->setRelation('transcoding', $transcoding);
                if ($transcoding && $transcoding->status !== AttachmentTranscoding::STATUS_COMPLETED) {
                    $this->invalid(__('A video is still processing or needs attention.'));
                }
                if (FileAttachmentService::isFile($asset)) {
                    if (!$lockedParent instanceof Post && !$lockedParent instanceof UserMessage && !$lockedParent instanceof MessageTemplate) {
                        $this->invalid();
                    }
                    if (!in_array($id, $retained, true) && !in_array($id, $templateIds, true)) {
                        app(FileAttachmentService::class)->assertSelectable($asset);
                    }
                } elseif (!in_array(AttachmentServiceProvider::getAttachmentType($asset->type), ['image', 'video', 'audio'], true)) {
                    $this->invalid();
                }
            }
            $types = array_map(fn ($id) => $assets[$id]->attachmentType, $ids);
            if ($lockedParent instanceof Story && (count($ids) !== ($lockedParent->mode === 'media' ? 1 : 0) || in_array('audio', $types, true))) {
                $this->invalid(__('A story accepts one image or video.'));
            }
            if ($lockedParent instanceof Reel && (count(array_filter($types, fn ($type) => $type === 'video')) !== 1
                || count(array_filter($types, fn ($type) => $type === 'image')) > 1 || in_array('audio', $types, true))) {
                $this->invalid(__('A reel requires one video and an optional image cover.'));
            }
            if ($lockedParent instanceof Reel) {
                $video = $assets->first(fn ($asset) => AttachmentServiceProvider::getAttachmentType($asset->type) === 'video');
                $maxLength = (int) getSetting('reels.max_video_length_seconds');
                if ($maxLength > 0 && $video->length && $video->length > $maxLength) {
                    $this->invalid(__('Video is too long.'));
                }
                if ($maxLength > 0 && !$video->length
                    && AttachmentServiceProvider::requiresVerifiedVideoDuration($video)) {
                    $this->invalid(__('Video duration could not be verified.'));
                }
            }
            $lockedParent->placements()->whereNotIn('attachment_id', $ids)->delete();
            foreach ($ids as $position => $id) {
                if ($lockedParent instanceof Post || (float) ($lockedParent->price ?? 0) > 0) {
                    app(SharedAttachmentPreviewService::class)->ensure($assets[$id]);
                }
                AttachmentPlacement::query()->updateOrCreate(
                    [$key => $lockedParent->getKey(), 'attachment_id' => $id],
                    ['position' => $position],
                );
            }
            $parent->unsetRelation('attachments')->unsetRelation('placements');
        }, 3);
    }

    protected function invalid(?string $message = null): never
    {
        throw ValidationException::withMessages(['attachments' => $message ?? __('One or more attachments are no longer available. Please remove them and try again.')]);
    }
}
