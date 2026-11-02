<?php

namespace App\Services\Moderation;

use App\Model\Attachment;
use App\Model\AttachmentPlacement;
use App\Providers\AttachmentServiceProvider;
use App\Services\Moderation\Data\ModerationInput;
use App\Services\Moderation\Exceptions\ModerationProviderException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AttachmentMediaService
{
    public function mediaType(Attachment $attachment): ?string
    {
        if (\App\Services\Media\FileAttachmentService::isFile($attachment)) return null;
        $type = strtolower((string) $attachment->type);

        if (in_array($type, AttachmentServiceProvider::getTypeByExtension('video'), true)) {
            return 'video';
        }

        if (in_array($type, AttachmentServiceProvider::getTypeByExtension('images'), true)) {
            return 'image';
        }

        return null;
    }

    public function input(Attachment $attachment): ModerationInput
    {
        $descriptor = $this->descriptor($attachment);

        $disk = AttachmentServiceProvider::getStorageProviderName($attachment->driver);
        $filename = ltrim((string) $attachment->filename, '/');

        try {
            $stream = Storage::disk($disk)->readStream($filename);
        } catch (Throwable $e) {
            throw new ModerationProviderException(
                'The media file could not be opened from its storage provider.',
                errorType: 'media_storage_error',
                retryable: true,
                previous: $e,
            );
        }

        if (!is_resource($stream)) {
            throw new ModerationProviderException(
                'The media file is missing or unreadable.',
                errorType: 'media_missing',
            );
        }

        return new ModerationInput(
            mediaType: $descriptor->mediaType,
            filename: basename($filename),
            stream: $stream,
            durationSeconds: $descriptor->durationSeconds,
        );
    }

    public function descriptor(Attachment $attachment): ModerationInput
    {
        $mediaType = $this->mediaType($attachment);

        if ($mediaType === null) {
            throw new ModerationProviderException(
                'This attachment type is not supported for media moderation.',
                errorType: 'unsupported_media',
                providerCode: (string) $attachment->type,
            );
        }

        return new ModerationInput(
            mediaType: $mediaType,
            filename: basename(ltrim((string) $attachment->filename, '/')),
            stream: null,
            durationSeconds: $attachment->length !== null ? (int) $attachment->length : null,
        );
    }

    public function fingerprint(Attachment $attachment): string
    {
        if (\App\Services\Media\FileAttachmentService::isFile($attachment)) {
            return hash('sha256', implode('|', [$attachment->driver, $attachment->filename, $attachment->size, $attachment->mime_type]));
        }
        $diskName = AttachmentServiceProvider::getStorageProviderName($attachment->driver);
        $filename = ltrim((string) $attachment->filename, '/');

        try {
            $checksum = Storage::disk($diskName)->checksum($filename);

            if (is_string($checksum) && $checksum !== '') {
                return hash('sha256', $attachment->driver.'|'.$checksum);
            }
        } catch (Throwable) {
            // Some Flysystem adapters do not expose checksums. The stable source identity below
            // still prevents repeat scans while invalidating normal application-level changes.
        }

        $size = null;
        $lastModified = null;

        try {
            $size = Storage::disk($diskName)->size($filename);
            $lastModified = Storage::disk($diskName)->lastModified($filename);
        } catch (Throwable) {
            // Storage metadata is best-effort for adapters without checksum support.
        }

        return hash('sha256', implode('|', [
            (string) $attachment->driver,
            $filename,
            (string) $attachment->type,
            (string) optional($attachment->updated_at)->getTimestamp(),
            (string) $size,
            (string) $lastModified,
        ]));
    }

    public function subject(Attachment $attachment): array
    {
        // A reusable file has no single social parent. The scan describes the file;
        // current placements are resolved separately from historical scan subjects.
        return ['type' => 'attachment', 'id' => null];
    }

    /** @return Collection<int, array{type: string, id: int, parent: \App\Model\MessageTemplate|\App\Model\Post|\App\Model\Reel|\App\Model\Story|\App\Model\UserMessage}> */
    public function usages(Attachment $attachment): Collection
    {
        $relations = ['post', 'message', 'story', 'reel', 'messageTemplate'];

        return $attachment->placements()->with($relations)->get()->flatMap(function ($placement): array {
            if (!$placement instanceof AttachmentPlacement) {
                return [];
            }
            foreach (['post' => 'post', 'message' => 'message', 'story' => 'story', 'reel' => 'reel', 'message_template' => 'messageTemplate'] as $type => $relation) {
                if ($parent = $placement->{$relation}) {
                    return [['type' => $type, 'id' => (int) $parent->getKey(), 'parent' => $parent]];
                }
            }

            return [];
        });
    }

    public function ownerId(Attachment $attachment): ?int
    {
        if ($attachment->user_id) {
            return (int) $attachment->user_id;
        }

        return null;
    }
}
