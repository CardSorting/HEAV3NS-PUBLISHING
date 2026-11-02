<?php

namespace App\Filament\Resources\Attachments;

use App\Model\Attachment;
use App\Model\MediaModerationItem;
use App\Services\Media\FileAttachmentService;
use App\Services\Moderation\ProfileMediaSourceService;
use App\Services\Moderation\StreamPosterSourceService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;

class AttachmentPresentation
{
    public static function url(Attachment $attachment): ?string
    {
        if (FileAttachmentService::isFile($attachment)) {
            return Gate::allows('view', $attachment)
                ? route('file-attachments.admin', ['attachment' => $attachment->getKey()])
                : null;
        }

        return $attachment->path;
    }

    /** @return array{type: string, path: ?string, thumbnail: ?string, name: string, format: string, size: string, icon: string} */
    public static function data(Attachment $attachment): array
    {
        $isFile = FileAttachmentService::isFile($attachment);
        $type = $isFile ? 'file' : (string) $attachment->attachmentType;

        return [
            'type' => $type,
            'path' => self::url($attachment),
            'thumbnail' => $isFile ? null : match (true) {
                (bool) $attachment->has_thumbnail => $attachment->thumbnail,
                $type === 'image' => $attachment->path,
                default => null,
            },
            'name' => basename((string) ($isFile ? $attachment->original_name : $attachment->filename)),
            'format' => strtoupper((string) $attachment->type),
            'size' => $isFile && $attachment->size !== null ? Number::fileSize(max(0, (int) $attachment->size)) : '',
            'icon' => match ($type) {
                'image' => 'heroicon-o-photo',
                'video' => 'heroicon-o-video-camera',
                'audio' => 'heroicon-o-speaker-wave',
                'file', 'document' => 'heroicon-o-document-text',
                default => 'heroicon-o-paper-clip',
            },
        ];
    }

    public static function moderationData(MediaModerationItem $item): array
    {
        if ($item->attachment && !$item->isManagedAsset()) {
            return self::data($item->attachment);
        }

        $preview = match (true) {
            $item->isProfileAsset() => app(ProfileMediaSourceService::class)->previewUrl($item),
            $item->isStreamPoster() => app(StreamPosterSourceService::class)->previewUrl($item),
            default => null,
        };

        return [
            'type' => (string) $item->media_type,
            'thumbnail' => $preview,
            'icon' => $item->media_type === 'image' ? 'heroicon-o-photo' : 'heroicon-o-paper-clip',
        ];
    }
}
