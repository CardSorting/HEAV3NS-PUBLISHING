<?php

namespace App\Services\Media;

use App\Model\AttachmentTranscoding;
use Illuminate\Support\Facades\Log;
use Pusher\Pusher;
use Throwable;

class AttachmentTranscodingNotifier
{
    public function __construct(protected AttachmentTranscodingService $transcodings)
    {
    }

    public function notify(AttachmentTranscoding $transcoding, ?int $progress = null): void
    {
        if (!config('broadcasting.connections.pusher.key')) {
            return;
        }

        $transcoding->loadMissing('attachment.user');
        $attachment = $transcoding->attachment;
        $username = $attachment?->user?->username;

        if (!$attachment || !$username) {
            return;
        }

        $attachment->setRelation('transcoding', $transcoding);
        $payload = $this->transcodings->payload($attachment, $progress);
        $payload['success'] = match ($transcoding->status) {
            AttachmentTranscoding::STATUS_COMPLETED => true,
            AttachmentTranscoding::STATUS_FAILED, AttachmentTranscoding::STATUS_CANCELLED => false,
            default => null,
        };

        try {
            $options = (array) config('broadcasting.connections.pusher.options', []);
            $options['useTLS'] ??= (($options['scheme'] ?? 'http') === 'https');

            if (isset($options['port'])) {
                $options['port'] = (int) $options['port'];
            }

            $pusher = new Pusher(
                config('broadcasting.connections.pusher.key'),
                config('broadcasting.connections.pusher.secret'),
                config('broadcasting.connections.pusher.app_id'),
                $options,
            );

            $pusher->trigger($username, 'video-processing', $payload);
        } catch (Throwable $exception) {
            Log::warning('Failed to publish a video processing update.', [
                'attachment_id' => $attachment->id,
                'status' => $transcoding->status,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
