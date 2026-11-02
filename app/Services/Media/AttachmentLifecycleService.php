<?php

namespace App\Services\Media;

use App\Model\Attachment;
use App\Model\MessageTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttachmentLifecycleService
{
    public function __construct(protected AttachmentEligibility $eligibility)
    {
    }

    public function archive(Attachment $attachment, int $ownerId): void
    {
        $this->setArchive($attachment, $ownerId, true);
    }

    public function restore(Attachment $attachment, int $ownerId): void
    {
        $this->setArchive($attachment, $ownerId, false);
    }

    private function setArchive(Attachment $attachment, int $ownerId, bool $archive): void
    {
        DB::transaction(function () use ($attachment, $ownerId, $archive) {
            $asset = Attachment::query()->whereKey($attachment->id)->lockForUpdate()->firstOrFail();
            if ((int) $asset->user_id !== $ownerId || !$asset->vaulted_at || $this->eligibility->isProtected($asset)) {
                $this->invalid();
            }
            $asset->vault_archived_at = $archive ? ($asset->vault_archived_at ?? now()) : null;
            $asset->timestamps = false;
            $asset->save();
        }, 3);
    }

    public function deleteUnused(Attachment $attachment, int $ownerId): void
    {
        DB::transaction(function () use ($attachment, $ownerId) {
            $asset = Attachment::query()->whereKey($attachment->id)->lockForUpdate()->firstOrFail();
            if ((int) $asset->user_id !== $ownerId || $asset->placements()->exists() || $this->eligibility->isProtected($asset, null, false)) {
                $this->invalid(__('Media still in use cannot be permanently deleted.'));
            }
            $transcoding = $asset->transcoding()->lockForUpdate()->first();
            if ($transcoding?->isPending()) {
                $this->invalid(__('Wait for media processing to finish before deleting it.'));
            }
            $asset->delete();
        }, 3);
    }

    public function deleteParent(Model $parent): void
    {
        DB::transaction(function () use ($parent) {
            $locked = $parent->newQuery()->whereKey($parent->getKey())->lockForUpdate()->firstOrFail();
            if (!method_exists($locked, 'placements')) {
                throw new \LogicException('The selected parent does not support attachment placements.');
            }
            $locked->placements()->delete();
            $locked->delete();
        }, 3);
    }

    public function deleteEverywhere(Attachment $attachment): void
    {
        DB::transaction(function () use ($attachment) {
            // Assignment locks its template before the asset, so takedown must use the same order.
            $templateIds = $attachment->placements()->whereNotNull('message_template_id')
                ->distinct()->orderBy('message_template_id')->pluck('message_template_id');
            $lockedTemplateIds = MessageTemplate::query()->whereIn('id', $templateIds)
                ->orderBy('id')->lockForUpdate()->pluck('id');
            $asset = Attachment::query()->whereKey($attachment->id)->lockForUpdate()->firstOrFail();
            $currentTemplateIds = $asset->placements()->whereNotNull('message_template_id')
                ->orderBy('message_template_id')->lockForUpdate()->pluck('message_template_id');
            if ($currentTemplateIds->diff($lockedTemplateIds)->isNotEmpty()) {
                // Never acquire an additional template lock after the asset: retry from the start.
                $this->invalid(__('Media usage changed. Please retry this operation.'));
            }
            // A removed template asset must never turn an automated paid send into a partial send.
            MessageTemplate::query()->whereIn('id', $currentTemplateIds)
                ->update(['enabled' => false]);
            $asset->placements()->delete();
            $asset->delete();
        }, 3);
    }

    private function invalid(?string $message = null): never
    {
        throw ValidationException::withMessages(['attachment' => $message ?? __('Invalid attachment.')]);
    }
}
