<?php

namespace App\Services\Media;

use ArrayAccess;

class VaultAttachmentPresentation
{
    public static function cards(iterable $attachments, array|ArrayAccess $payloads): array
    {
        $cards = [];
        foreach ($attachments as $attachment) {
            $payload = $payloads[(string) $attachment->id];
            $cards[] = [
                'attachment' => $attachment,
                'payload' => $payload,
                'mediaLabel' => match ($payload['type']) {
                    'video' => __('Video'), 'audio' => __('Audio'), 'file' => __('File'), default => __('Photo'),
                },
                'mediaIcon' => match ($payload['type']) {
                    'video' => 'videocam-outline', 'audio' => 'musical-note-outline', 'file' => 'document-text-outline', default => 'image-outline',
                },
                'deleteReason' => $attachment->placements_count
                    ? __('Media still in use.')
                    : (in_array($payload['transcoding_status'], ['queued', 'processing'], true) ? __('Wait for processing to finish.') : null),
            ];
        }

        return $cards;
    }
}
