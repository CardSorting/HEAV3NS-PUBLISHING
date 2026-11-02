<?php

namespace App\Services;

use App\Model\Attachment;
use App\Model\Sound;
use App\Providers\AttachmentServiceProvider;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;

class SoundMediaService
{
    public function sync(
        Sound $sound,
        ?UploadedFile $coverUpload = null,
        ?UploadedFile $audioUpload = null,
    ): void {
        $createdAttachments = [];
        $replacedAttachments = collect();

        try {
            if ($coverUpload) {
                $replacedAttachments = $replacedAttachments->merge(
                    $sound->attachments()
                        ->whereIn('type', AttachmentServiceProvider::getTypeByExtension('default'))
                        ->get()
                );

                $createdAttachments[] = $this->createAttachment($sound, $coverUpload, 'image');
            }

            if ($audioUpload) {
                $replacedAttachments = $replacedAttachments->merge(
                    $sound->attachments()
                        ->whereIn('type', AttachmentServiceProvider::getTypeByExtension('audio'))
                        ->get()
                );

                $createdAttachments[] = $this->createAttachment($sound, $audioUpload, 'audio');
            }
        } catch (Throwable $exception) {
            foreach ($createdAttachments as $attachment) {
                $attachment->delete();
            }

            throw $exception;
        }

        foreach ($replacedAttachments->unique('id') as $attachment) {
            $attachment->delete();
        }

        $sound->unsetRelation('attachments');
        $sound->unsetRelation('coverAttachment');
        $sound->unsetRelation('audioAttachment');
    }

    public function deleteAll(Sound $sound): void
    {
        foreach ($sound->attachments()->get() as $attachment) {
            $attachment->delete();
        }
    }

    private function createAttachment(Sound $sound, UploadedFile $upload, string $expectedType): Attachment
    {
        $attachment = AttachmentServiceProvider::createAttachment(
            $upload,
            "sounds/{$sound->getKey()}/{$expectedType}",
            false,
            false,
            false,
        );

        if (!$attachment instanceof Attachment) {
            throw new RuntimeException('The sound attachment could not be created.');
        }

        if (AttachmentServiceProvider::getAttachmentType($attachment->type) !== $expectedType) {
            $attachment->delete();

            throw new RuntimeException("The uploaded sound {$expectedType} file has an invalid type.");
        }

        try {
            $sound->attachments()->save($attachment);
        } catch (Throwable $exception) {
            $attachment->delete();

            throw $exception;
        }

        return $attachment;
    }
}
