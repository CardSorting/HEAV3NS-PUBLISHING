<?php

namespace App\Services\Media;

use App\Model\Attachment;
use App\Model\AttachmentTranscoding;
use App\Providers\AttachmentServiceProvider;
use App\Providers\MediaEncoderServiceProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class AttachmentTranscodingProcessor
{
    public function process(string $attachmentId, callable $onProgress): void
    {
        $transcoding = AttachmentTranscoding::query()->with('attachment')->find($attachmentId);

        if (!$transcoding || !$transcoding->attachment) {
            return;
        }

        if ($transcoding->driver !== AttachmentTranscoding::DRIVER_FFMPEG) {
            return;
        }

        if (in_array($transcoding->status, [
            AttachmentTranscoding::STATUS_COMPLETED,
            AttachmentTranscoding::STATUS_CANCELLED,
        ], true)) {
            return;
        }

        $transcoding->update([
            'status' => AttachmentTranscoding::STATUS_PROCESSING,
            'attempts' => $transcoding->attempts + 1,
            'started_at' => $transcoding->started_at ?? now(),
            'failed_at' => null,
            'error_code' => null,
            'error_message' => null,
        ]);
        $onProgress(0);

        $attachment = $transcoding->attachment;
        $diskName = AttachmentServiceProvider::getStorageProviderName($attachment->driver);
        $disk = Storage::disk($diskName);
        $tmpDisk = Storage::disk('tmp');
        $sourceExtension = strtolower((string) data_get($transcoding->options, 'source_extension'))
            ?: strtolower(pathinfo($transcoding->source_path, PATHINFO_EXTENSION))
            ?: 'mp4';
        // Keep scratch files directly under the configured temporary disk. A nested
        // directory can be created by a queue worker with owner-only permissions,
        // preventing the web PHP user from processing the same job inline later.
        $workingPath = 'transcoding-'.$attachment->id.'-'.bin2hex(random_bytes(6)).'.'.$sourceExtension;
        $sourceStream = null;

        try {
            $sourceStream = $disk->readStream($transcoding->source_path);
            if (!is_resource($sourceStream)) {
                throw new RuntimeException('The original uploaded video is no longer available.');
            }

            if (!$tmpDisk->put($workingPath, $sourceStream)) {
                throw new RuntimeException('The uploaded video could not be prepared for processing.');
            }
            fclose($sourceStream);
            $sourceStream = null;

            $localPath = $tmpDisk->path($workingPath);
            $uploadedFile = new UploadedFile(
                $localPath,
                basename($transcoding->source_path),
                null,
                null,
                true,
            );
            $options = (array) $transcoding->options;
            $outputDirectory = trim(pathinfo($transcoding->output_path, PATHINFO_DIRNAME), '.');

            $asset = MediaEncoderServiceProvider::ffmpegEncode(
                $uploadedFile,
                $outputDirectory,
                $attachment->id,
                (bool) ($options['generate_thumbnail'] ?? true),
                (bool) ($options['generate_blurred_preview'] ?? false),
                (bool) ($options['apply_watermark'] ?? true),
                $diskName,
                $sourceExtension,
                $onProgress,
                (int) $attachment->user_id,
            );

            $freshAttachment = Attachment::query()->find($attachment->id);
            $freshTranscoding = AttachmentTranscoding::query()->find($attachment->id);
            if (!$freshAttachment || !$freshTranscoding || $freshTranscoding->status === AttachmentTranscoding::STATUS_CANCELLED) {
                $this->removeGeneratedFiles($diskName, $asset);

                return;
            }

            $published = DB::transaction(function () use ($freshAttachment, $freshTranscoding, $asset): bool {
                $freshAttachment = Attachment::query()->whereKey($freshAttachment->id)->lockForUpdate()->first();
                $freshTranscoding = AttachmentTranscoding::query()->whereKey($freshTranscoding->attachment_id)->lockForUpdate()->first();
                if (!$freshAttachment || !$freshTranscoding || $freshTranscoding->status === AttachmentTranscoding::STATUS_CANCELLED) {
                    return false;
                }
                $freshAttachment->update([
                    'filename' => $asset['filePath'],
                    'type' => pathinfo($asset['filePath'], PATHINFO_EXTENSION) ?: 'mp4',
                    'has_thumbnail' => !empty($asset['hasThumbnail']) ? 1 : null,
                    'has_blurred_preview' => !empty($asset['hasBlurredPreview']) ? 1 : null,
                    'blurred_filename' => $asset['blurredFilename'] ?? null,
                    'length' => $asset['length'] ?? null,
                ]);

                $freshTranscoding->update([
                    'status' => AttachmentTranscoding::STATUS_COMPLETED,
                    'error_code' => null,
                    'error_message' => null,
                    'completed_at' => now(),
                    'failed_at' => null,
                ]);
                return true;
            });

            if (!$published) {
                $this->removeGeneratedFiles($diskName, $asset);
                return;
            }

            if ($transcoding->source_path !== $asset['filePath']) {
                $sourcePath = $transcoding->source_path;
                DB::afterCommit(fn () => $disk->delete($sourcePath));
            }
        } catch (Throwable $exception) {
            AttachmentTranscoding::query()
                ->whereKey($attachmentId)
                ->whereNotIn('status', [AttachmentTranscoding::STATUS_COMPLETED, AttachmentTranscoding::STATUS_CANCELLED])
                ->update([
                    'error_code' => 'processing_error',
                    'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                    'updated_at' => now(),
                ]);

            throw $exception;
        } finally {
            if (is_resource($sourceStream)) {
                fclose($sourceStream);
            }

            $tmpDisk->delete($workingPath);
        }
    }

    protected function removeGeneratedFiles(string $diskName, array $asset): void
    {
        $disk = Storage::disk($diskName);
        $paths = array_filter([
            $asset['filePath'] ?? null,
            $asset['blurredFilename'] ?? null,
        ]);

        foreach ($paths as $path) {
            $disk->delete($path);
        }

        if (!empty($asset['filePath'])) {
            $directory = trim(pathinfo($asset['filePath'], PATHINFO_DIRNAME), '.');
            $id = pathinfo($asset['filePath'], PATHINFO_FILENAME);
            $disk->delete($directory.'/thumbnails/'.$id.'.jpg');
        }
    }
}
