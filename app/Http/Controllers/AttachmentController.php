<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadAttachamentRequest;
use App\Model\Attachment;
use App\Model\AttachmentTranscoding;
use App\Providers\AttachmentServiceProvider;
use App\Services\CreatorAccessService;
use App\Services\Media\AttachmentSelectionResolver;
use App\Services\Media\AttachmentTranscodingNotifier;
use App\Services\Media\AttachmentTranscodingService;
use App\Services\Media\FileAttachmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;
use Log;

class AttachmentController extends Controller
{
    /**
     * Process the attachment and upload it to the selected storage driver.
     *
     * @param UploadAttachamentRequest $request
     * @param string|false $type Dummy param to follow route parameters
     * @param UploadedFile|false $chunkedFile If using chunk uploads, this final chunked file is sent over this request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(UploadAttachamentRequest $request, $type = false, $chunkedFile = false)
    {
        if(!$type) {
            $type = $request->route('type');
        }
        $socialUpload = in_array($type, ['post', 'message', 'message-template', 'story', 'reel', 'vault'], true);
        abort_unless($socialUpload, 422, __('Invalid upload type.'));
        abort_if($type === 'vault' && !AttachmentServiceProvider::isVaultEnabled(), 404);
        $this->ensurePublicUploadIsAllowed($type);
        if ($type === 'message-template' && getSetting('site.enforce_user_identity_checks')
            && !\App\Providers\GenericHelperServiceProvider::isUserVerified()) {
            abort(403, __('User not verified. Can not post content.'));
        }
        $file = $chunkedFile ?: $request->file('file');
        $fileMimeType = $file->getMimeType();
        $fileExtension = strtolower($file->getClientOriginalExtension());
        if (array_key_exists($fileExtension, FileAttachmentService::supportedFormats())) {
            $attachment = app(FileAttachmentService::class)->create($file, $type, (int) Auth::id());

            return response()->json(array_merge(['success' => true], app(FileAttachmentService::class)->payload($attachment)));
        }
        if (!preg_match('~^(image|video|audio)/~', (string) $fileMimeType)) {
            abort(422, __('Only images, videos, and audio can be saved to the Vault.'));
        }

        try {
            $generateThumbnail = false;
            $generateBlurredPreview = true;
            $applyWatermark = true;
            $directory = AttachmentServiceProvider::getDirectoryByType($fileMimeType);
            if (in_array($type, ['message', 'message-template', 'vault'], true) && in_array($fileExtension, AttachmentServiceProvider::getRecordedAudioExtensions())) {
                $directory = 'audio';
            }
            if ($type == 'post') {
                $directory = 'posts/'.$directory;
                $generateThumbnail = true;
            } elseif (in_array($type, ['message', 'message-template'], true)) {
                $directory = 'messenger/'.$directory;
                $generateThumbnail = true;
            }
            elseif ($type == 'story') {
                $directory = 'stories/'.$directory;
                $generateBlurredPreview = false;
                $generateThumbnail = true;
            }
            elseif ($type == 'reel') {
                $directory = 'reels/'.$directory;
                $generateBlurredPreview = false;
                $generateThumbnail = true;
            }
            elseif ($type == 'vault') {
                $directory = 'vault/'.$directory;
                $generateThumbnail = true;
            }
            $attachment = AttachmentServiceProvider::createAttachment($file, $directory, $generateThumbnail, $generateBlurredPreview, $applyWatermark, null, $socialUpload);
            if($chunkedFile) {
                unlink($file->getPathname());
            }

        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'errors' => [$exception->getMessage()],
                'message' => $exception->getMessage(),
            ], 500);
        }

        return response()->json(array_merge(
            ['success' => true],
            app(AttachmentTranscodingService::class)->payload($attachment),
        ));
    }

    /**
     * Chunk uploadining method.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws UploadMissingFileException
     * @throws \Pion\Laravel\ChunkUpload\Exceptions\UploadFailedException
     */
    public function uploadChunk(Request $request, $type = false)
    {
        $type = $type ?: $request->route('type');
        abort_unless(in_array($type, ['post', 'message', 'message-template', 'story', 'reel', 'vault'], true), 422);
        abort_if($type === 'vault' && !AttachmentServiceProvider::isVaultEnabled(), 404);
        $this->ensurePublicUploadIsAllowed($type);
        if ($type === 'message-template' && getSetting('site.enforce_user_identity_checks')
            && !\App\Providers\GenericHelperServiceProvider::isUserVerified()) {
            abort(403, __('User not verified. Can not post content.'));
        }
        $receiver = new FileReceiver("file", $request, HandlerFactory::classFromRequest($request));
        if ($receiver->isUploaded() === false) {
            throw new UploadMissingFileException();
        }
        $save = $receiver->receive();
        // check if the upload has finished (in chunk mode it will send smaller files)
        if ($save->isFinished()) {
            $file = $save->getFile();
            try {
                $saveRequest = new UploadAttachamentRequest();
                $saveRequest->files->set('file', $file);
                $saveRequest->setRouteResolver($request->getRouteResolver());
                validator(['file' => $file], $saveRequest->rules())->validate();
                return $this->upload($saveRequest, $type, $file);
            } finally {
                if (is_file($file->getPathname())) unlink($file->getPathname());
            }
        }
        // we are in chunk mode, lets send the current progress
        $handler = $save->handler();
        return response()->json(['success' => true, 'data' => ['percentage'=>$handler->getPercentageDone()]]);
    }

    /** Restore owner-only media selections independently of the Vault interface. */
    public function resolve(Request $request, AttachmentTranscodingService $transcoding)
    {
        return app(AttachmentSelectionResolver::class)->resolve($request, $transcoding);
    }

    private function ensurePublicUploadIsAllowed(string $type): void
    {
        if (!in_array($type, ['post', 'story', 'reel'], true)) {
            return;
        }

        $creatorAccess = app(CreatorAccessService::class);
        abort_unless($creatorAccess->canPublish(Auth::user()), 403, $creatorAccess->restrictionMessage(Auth::user()));
    }

    /**
     * Removes attachment out of db & out of the storage driver.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeAttachment(Request $request)
    {
        try {
            $attachment = Attachment::where('id', $request->get('attachmentId'))->where('user_id', Auth::user()->id)->first();
            if ($attachment != null) {
                if ($attachment->vaulted_at) {
                    return response()->json(['success' => false, 'message' => __('Saved media must be managed from the Vault.')], 422);
                }
                app(\App\Services\Media\AttachmentLifecycleService::class)->deleteUnused($attachment, (int) Auth::id());
                return response()->json(['success' => true, 'data' => [__('Attachments removed successfully')]]);
            }
            else {
                return response()->json(['success' => false, 'data' => [__('Attachment not found')]], 403);
            }
        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'errors' => [$exception->getMessage()],
                'message' => __('Failed to remove the attachment.'),
            ], 500);
        }
    }

    /**
     * Returns the latest state for restored draft attachments.
     */
    public function transcodingStatuses(Request $request, AttachmentTranscodingService $transcodings)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:50'],
            'ids.*' => ['required', 'string', 'max:64', 'distinct'],
        ]);

        $attachments = Attachment::query()
            ->whereIn('id', $data['ids'])
            ->where('user_id', Auth::id())
            ->with('transcoding')
            ->get();

        return response()->json([
            'success' => true,
            'attachments' => $attachments
                ->map(fn (Attachment $attachment) => $transcodings->payload($attachment))
                ->values(),
        ]);
    }

    /**
     * Retries a failed local FFmpeg operation.
     */
    public function retryTranscoding(Request $request, AttachmentTranscodingService $transcodings)
    {
        $data = $request->validate([
            'attachmentId' => ['required', 'string', 'max:64'],
        ]);

        $attachment = Attachment::query()
            ->whereKey($data['attachmentId'])
            ->where('user_id', Auth::id())
            ->with('transcoding')
            ->firstOrFail();

        try {
            $transcodings->retry($attachment);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable $exception) {
            Log::warning('Video processing retry could not be dispatched.', [
                'attachment_id' => $attachment->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Video processing could not be started. Please try again.'),
            ], 500);
        }

        return response()->json(array_merge(
            ['success' => true],
            $transcodings->payload($attachment->refresh()),
        ));
    }

    /**
     * Handles coconut webhook.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleCoconutHook(
        Request $request,
        AttachmentTranscodingNotifier $notifier,
    ) {
        $event = (string) $request->get('event');
        $attachmentId = (string) ($request->query('attachment') ?: $request->get('attachmentId'));

        Log::channel('coconut')->info('Coconut transcoding callback received.', [
            'event' => $event,
            'attachment_id' => $attachmentId,
        ]);

        if ($attachmentId === '' || !in_array($event, ['job.completed', 'job.failed', 'output.failed'], true)) {
            return response()->json(['success' => false, 'message' => __('Invalid transcoding callback.')], 422);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $notifier, $event, $attachmentId) {
            $attachment = Attachment::query()->with('user')->whereKey($attachmentId)->lockForUpdate()->first();
            if (!$attachment) {
                return response()->json(['success' => true, 'message' => __('Attachment no longer exists.')]);
            }

            $transcoding = $attachment->transcoding()->lockForUpdate()->first();
            if (!$transcoding) {
                Log::channel('coconut')->warning('Rejected Coconut callback without a transcoding record.', [
                    'event' => $event,
                    'attachment_id' => $attachmentId,
                ]);

                return response()->json(['success' => false, 'message' => __('Invalid transcoding job.')], 403);
            }

            $providerJobId = trim((string) ($request->get('job_id') ?: data_get($request->all(), 'data.id')));
            $expectedProviderJobId = trim((string) $transcoding->provider_job_id);

            if (
                $providerJobId === ''
                || $expectedProviderJobId === ''
                || !hash_equals($expectedProviderJobId, $providerJobId)
            ) {
                Log::channel('coconut')->warning('Rejected Coconut callback with an invalid job ID.', [
                    'event' => $event,
                    'attachment_id' => $attachmentId,
                ]);

                return response()->json(['success' => false, 'message' => __('Invalid transcoding job.')], 403);
            }

            if (
                $transcoding->status === AttachmentTranscoding::STATUS_CANCELLED
                || ($transcoding->status === AttachmentTranscoding::STATUS_COMPLETED && $event !== 'job.completed')
            ) {
                return response()->json(['success' => true, 'message' => __('Media was already processed.')]);
            }

            if ($event === 'job.completed') {
                $payload = $request->all();
                $duration = data_get($payload, 'data.input.metadata.streams.0.duration');

                if (!$duration) {
                    foreach ((array) data_get($payload, 'data.input.metadata.streams', []) as $stream) {
                        if (($stream['codec_type'] ?? null) === 'video' && !empty($stream['duration'])) {
                            $duration = $stream['duration'];
                            break;
                        }
                    }
                }

                $duration ??= data_get($payload, 'data.input.metadata.format.duration')
                    ?? data_get($payload, 'data.input.metadata.duration')
                    ?? data_get($payload, 'data.input.duration');

                $sourcePath = $transcoding->source_path;
                $attachment->update([
                    'filename' => $transcoding->output_path,
                    'type' => 'mp4',
                    'has_thumbnail' => 1,
                    'has_blurred_preview' => $attachment->blurred_filename ? 1 : null,
                    'length' => $duration !== null && $duration !== '' ? (int) round((float) $duration) : $attachment->length,
                ]);
                $transcoding->update([
                    'status' => AttachmentTranscoding::STATUS_COMPLETED,
                    'error_code' => null,
                    'error_message' => null,
                    'completed_at' => now(),
                    'failed_at' => null,
                ]);

                $diskName = AttachmentServiceProvider::getStorageProviderName($attachment->driver);
                \Illuminate\Support\Facades\DB::afterCommit(function () use ($sourcePath, $diskName, $attachmentId, $notifier) {
                    $fresh = AttachmentTranscoding::query()->find($attachmentId);
                    if ($fresh && $sourcePath !== $fresh->output_path) {
                        Storage::disk($diskName)->delete($sourcePath);
                    }
                    if ($fresh) $notifier->notify($fresh, 100);
                });
            } else {
                $transcoding->update([
                    'status' => AttachmentTranscoding::STATUS_FAILED,
                    'error_code' => $event === 'output.failed' ? 'provider_output_failed' : 'provider_job_failed',
                    'error_message' => 'The video processing provider could not complete this video.',
                    'failed_at' => now(),
                ]);
                \Illuminate\Support\Facades\DB::afterCommit(function () use ($attachmentId, $notifier) {
                    $fresh = AttachmentTranscoding::query()->find($attachmentId);
                    if ($fresh) $notifier->notify($fresh);
                });
            }

            return response()->json(['success' => true, 'message' => __('Media updated')]);
        }, 3);
    }
}
