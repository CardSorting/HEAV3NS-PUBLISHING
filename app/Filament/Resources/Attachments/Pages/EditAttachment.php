<?php

namespace App\Filament\Resources\Attachments\Pages;

use App\Filament\Resources\Attachments\AttachmentResource;
use App\Model\Attachment;
use App\Services\Moderation\PublicationReviewService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditAttachment extends EditRecord
{
    protected static string $resource = AttachmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AttachmentResource::getRecordActionsGroup(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Attachment {
            $attachment = Attachment::query()->lockForUpdate()->findOrFail($record->getKey());
            abort_unless(AttachmentResource::canEdit($attachment), 403);

            if (AttachmentResource::isShared($attachment)) {
                // Keep placement identities intact while retaining the existing editable metadata.
                $data = array_intersect_key($data, array_flip(['filename', 'has_thumbnail', 'has_blurred_preview']));
            }
            $attachment->fill($data)->save();
            if ($attachment->wasChanged(['filename', 'type', 'driver'])) {
                app(PublicationReviewService::class)->resubmitAttachmentUses($attachment);
            }

            return $attachment;
        }, 3);
    }
}
