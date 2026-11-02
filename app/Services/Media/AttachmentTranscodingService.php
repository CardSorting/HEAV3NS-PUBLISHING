<?php

namespace App\Services\Media;

use App\Jobs\ProcessAttachmentTranscodingJob;
use App\Model\Attachment;
use App\Model\AttachmentTranscoding;
use App\Providers\AttachmentServiceProvider;
use App\Services\Queue\QueueRuntimeService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class AttachmentTranscodingService
{
    public const JOB_TIMEOUT_SECONDS = 900;
    public const QUEUE_RETRY_AFTER_SECONDS = 960;

    public function __construct(protected QueueRuntimeService $runtime)
    {
    }

    public function createFfmpegAttachment(
        UploadedFile $file,
        string $directory,
        string $fileId,
        string $sourceExtension,
        bool $generateThumbnail,
        bool $generateBlurredPreview,
        bool $applyWatermark,
        ?int $userId = null,
        bool $saveToVault = false,
    ): Attachment {
        $diskName = (string) config('filesystems.defaultFilesystemDriver');
        $disk = Storage::disk($diskName);
        $directory = trim($directory, '/');
        $sourceExtension = strtolower(trim($sourceExtension)) ?: 'mp4';
        $sourcePath = "{$directory}/tmp/{$fileId}.{$sourceExtension}";
        $outputPath = "{$directory}/{$fileId}.mp4";

        $this->storeUploadedFile($diskName, $sourcePath, $file);

        try {
            $attachment = DB::transaction(function () use (
                $fileId,
                $userId,
                $sourcePath,
                $sourceExtension,
                $diskName,
                $outputPath,
                $generateThumbnail,
                $generateBlurredPreview,
                $applyWatermark,
                $saveToVault,
            ): Attachment {
                $attachment = new Attachment([
                    'id' => $fileId,
                    'user_id' => $userId ?? Auth::id(),
                    'filename' => $sourcePath,
                    'type' => $sourceExtension,
                    'driver' => AttachmentServiceProvider::getStorageProviderID($diskName),
                    'has_thumbnail' => null,
                    'has_blurred_preview' => null,
                    'blurred_filename' => null,
                    'length' => null,
                ]);
                $attachment->vaulted_at = $saveToVault ? now() : null;
                $attachment->save();

                $attachment->transcoding()->create([
                    'driver' => AttachmentTranscoding::DRIVER_FFMPEG,
                    'status' => AttachmentTranscoding::STATUS_QUEUED,
                    'source_path' => $sourcePath,
                    'output_path' => $outputPath,
                    'execution_mode' => $this->runtime->mode(),
                    'connection' => $this->runtime->connection(),
                    'queue' => $this->runtime->queueName(),
                    'options' => [
                        'generate_thumbnail' => $generateThumbnail,
                        'generate_blurred_preview' => $generateBlurredPreview,
                        'apply_watermark' => $applyWatermark,
                        'source_extension' => $sourceExtension,
                    ],
                    'queued_at' => now(),
                ]);

                return $attachment;
            });

            $this->dispatch($attachment->transcoding()->firstOrFail());

            return $attachment->refresh()->load('transcoding');
        } catch (Throwable $exception) {
            $disk->delete($sourcePath);

            if (isset($attachment)) {
                $attachment->delete();
            }

            throw $exception;
        }
    }

    public function registerCoconut(
        Attachment $attachment,
        string $sourcePath,
        string $outputPath,
        string $providerJobId,
    ): AttachmentTranscoding {
        return $attachment->transcoding()->updateOrCreate(
            ['attachment_id' => $attachment->id],
            [
                'driver' => AttachmentTranscoding::DRIVER_COCONUT,
                'status' => AttachmentTranscoding::STATUS_PROCESSING,
                'source_path' => $sourcePath,
                'output_path' => $outputPath,
                'provider_job_id' => $providerJobId,
                'execution_mode' => 'provider',
                'connection' => null,
                'queue' => null,
                'options' => ['region' => getSetting('media.coconut_video_region')],
                'attempts' => 1,
                'error_code' => null,
                'error_message' => null,
                'queued_at' => now(),
                'started_at' => now(),
                'completed_at' => null,
                'failed_at' => null,
                'cancelled_at' => null,
            ],
        );
    }

    public function retry(Attachment $attachment): AttachmentTranscoding
    {
        return DB::transaction(function () use ($attachment) {
            $attachment = Attachment::query()->whereKey($attachment->id)->lockForUpdate()->firstOrFail();
            $transcoding = $attachment->transcoding()->lockForUpdate()->firstOrFail();

            if ($transcoding->driver !== AttachmentTranscoding::DRIVER_FFMPEG) {
                throw new RuntimeException('This transcoding provider cannot be retried from the application.');
            }

            if ($transcoding->status !== AttachmentTranscoding::STATUS_FAILED) {
                throw new RuntimeException('Only failed video processing jobs can be retried.');
            }

            $disk = Storage::disk(AttachmentServiceProvider::getStorageProviderName($attachment->driver));
            if (!$disk->exists($transcoding->source_path)) {
                throw new RuntimeException('The original upload is no longer available. Please upload the video again.');
            }

            $transcoding->update([
                'status' => AttachmentTranscoding::STATUS_QUEUED,
                'execution_mode' => $this->runtime->mode(),
                'connection' => $this->runtime->connection(),
                'queue' => $this->runtime->queueName(),
                'error_code' => null,
                'error_message' => null,
                'queued_at' => now(),
                'started_at' => null,
                'completed_at' => null,
                'failed_at' => null,
                'cancelled_at' => null,
            ]);

            $this->dispatch($transcoding);

            return $transcoding->refresh();
        }, 3);
    }

    public function dispatch(AttachmentTranscoding $transcoding): void
    {
        ProcessAttachmentTranscodingJob::dispatch($transcoding->attachment_id)
            ->onConnection($transcoding->connection ?: $this->runtime->connection())
            ->onQueue($transcoding->queue ?: $this->runtime->queueName())
            ->afterCommit();
    }

    public function payload(Attachment $attachment, ?int $progress = null): array
    {
        if (FileAttachmentService::isFile($attachment)) {
            return app(FileAttachmentService::class)->payload($attachment);
        }
        $attachment->loadMissing('transcoding');
        $transcoding = $attachment->transcoding;
        $status = $transcoding?->status ?: AttachmentTranscoding::STATUS_COMPLETED;
        $ready = $status === AttachmentTranscoding::STATUS_COMPLETED;

        return [
            'id' => (string) $attachment->id,
            'attachmentID' => (string) $attachment->id,
            'path' => $ready ? $attachment->path : null,
            'type' => $transcoding
                ? 'video'
                : AttachmentServiceProvider::getAttachmentType($attachment->type),
            'thumbnail' => $ready && $attachment->has_thumbnail ? $attachment->thumbnail : null,
            'blurred' => $ready ? $attachment->blurred_preview : null,
            'has_thumbnail' => $ready && (bool) $attachment->has_thumbnail,
            'length' => $ready ? $attachment->length : null,
            'transcoding_driver' => $transcoding?->driver,
            'transcoding_status' => $status,
            'transcoding_progress' => $progress,
            'can_retry_transcoding' => $transcoding?->driver === AttachmentTranscoding::DRIVER_FFMPEG
                && $status === AttachmentTranscoding::STATUS_FAILED,
            'transcoding_message' => $this->userMessage($status),
        ];
    }

    public function hasUnavailableAttachments(array $attachmentIds, int $userId): bool
    {
        if ($attachmentIds === []) {
            return false;
        }

        return Attachment::query()
            ->whereIn('id', $attachmentIds)
            ->where('user_id', $userId)
            ->whereHas('transcoding', fn ($query) => $query->where('status', '!=', AttachmentTranscoding::STATUS_COMPLETED))
            ->exists();
    }

    protected function userMessage(string $status): ?string
    {
        return match ($status) {
            AttachmentTranscoding::STATUS_QUEUED => __('Video is queued for processing.'),
            AttachmentTranscoding::STATUS_PROCESSING => __('Video is being processed.'),
            AttachmentTranscoding::STATUS_FAILED => __('Video processing failed. You can retry it or remove the video.'),
            AttachmentTranscoding::STATUS_CANCELLED => __('Video processing was cancelled.'),
            default => null,
        };
    }

    protected function storeUploadedFile(string $diskName, string $path, UploadedFile $file): void
    {
        $stream = fopen($file->getRealPath(), 'rb');
        if (!is_resource($stream)) {
            throw new RuntimeException('The uploaded video could not be read.');
        }

        try {
            if (!Storage::disk($diskName)->put($path, $stream, 'public')) {
                throw new RuntimeException('The uploaded video could not be stored for processing.');
            }
        } finally {
            fclose($stream);
        }
    }
}
