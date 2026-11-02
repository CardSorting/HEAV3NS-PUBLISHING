<?php

namespace App\Providers;

use App\Model\Attachment;
use Aws\CloudFront\CloudFrontClient;
use Aws\Exception\AwsException;
use InvalidArgumentException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Ramsey\Uuid\Uuid;
use App\Services\Media\AttachmentTranscodingService;
use App\Model\AttachmentTranscoding;

class AttachmentServiceProvider extends ServiceProvider
{
    /**
     * Filter attachments by their extension.
     * @param $type
     * @return false|mixed|string
     */
    public static function filterExtensions($type = false)
    {
        if ($type) {
            switch ($type) {
                case 'videosFallback':
                    return self::resolveAllowedUploadExtensions(
                        null,
                        (string) getSetting('media.allowed_file_extensions'),
                        (string) getSetting('media.transcoding_driver')
                    );
                case 'imagesOnly':
                    return implode(',', self::getTypeByExtension('images'));
                case 'manualPayments':
                    return 'jpg,jpeg,png,pdf,xls,xlsx';
            }
        }

        return false;
    }

    /**
     * Resolve the allowed extensions for public attachment upload endpoints.
     *
     * This is the authoritative policy used by both frontend file pickers and
     * backend upload validation.
     */
    public static function getAllowedUploadExtensions(?string $uploadType = null): string
    {
        $extensions = self::resolveAllowedUploadExtensions(
            $uploadType,
            (string) getSetting('media.allowed_file_extensions'),
            (string) getSetting('media.transcoding_driver'),
            $uploadType === 'message' ? self::getRecordedAudioExtensions() : []
        );
        if ($uploadType === 'verify-asset') return $extensions;
        $extensions = array_values(array_diff(explode(',', $extensions), array_keys(\App\Services\Media\FileAttachmentService::supportedFormats())));
        $files = app(\App\Services\Media\FileAttachmentService::class);
        if (\App\Services\Media\FileAttachmentService::supportsContext($uploadType) && $files->enabled()) {
            $extensions = array_merge($extensions, $files->allowedFormats());
        }
        return implode(',', array_unique($extensions));
    }

    public static function isFileAttachment(Attachment $attachment): bool
    {
        return \App\Services\Media\FileAttachmentService::isFile($attachment);
    }

    public static function getFileAttachmentData(Attachment $attachment, ?string $downloadUrl = null): array
    {
        return app(\App\Services\Media\FileAttachmentService::class)->payload($attachment, $downloadUrl);
    }

    /**
     * Apply the attachment upload policy to explicit settings values.
     */
    public static function resolveAllowedUploadExtensions(
        ?string $uploadType,
        string $configuredExtensions,
        ?string $transcodingDriver,
        array $recordedAudioExtensions = []
    ): string
    {
        if ($uploadType === 'verify-asset') {
            return 'jpg,jpeg,png,pdf,xls,xlsx';
        }

        if (in_array($transcodingDriver, ['ffmpeg', 'coconut'], true)) {
            $extensions = $configuredExtensions;
        } else {
            $extensions = explode(',', $configuredExtensions);
            $extensions = array_diff($extensions, self::getTypeByExtension('video'));
            $extensions[] = 'mp4';
            $extensions = implode(',', $extensions);
        }

        if (in_array($uploadType, ['message', 'message-template', 'vault'], true)) {
            return self::mergeExtensions($extensions, $recordedAudioExtensions);
        }

        return $extensions;
    }

    /**
     * Whether an unknown video duration should block publishing.
     *
     * Refusing a video we could not measure only makes sense when the pipeline had a
     * way to measure it. Local ffmpeg installs always do, so a missing duration there
     * means the probe genuinely failed on the file.
     *
     * Coconut installs commonly have no ffprobe binary at all. Videos Coconut actually
     * transcodes get their duration from the job webhook, but the already-mp4 fast path
     * in MediaEncoderServiceProvider::coconutEncode() skips Coconut entirely: it leaves
     * no transcoding record and no server-side source for a duration. Requiring one
     * there rejected every mp4 upload on a Coconut site.
     */
    public static function requiresVerifiedVideoDuration(Attachment $video): bool
    {
        $driver = (string) getSetting('media.transcoding_driver');

        if ($driver === 'ffmpeg') {
            return true;
        }

        if ($driver === 'coconut') {
            return $video->relationLoaded('transcoding')
                ? $video->transcoding !== null
                : $video->transcoding()->exists();
        }

        return false;
    }

    public static function withRecordedAudioExtensions(?string $extensions): string
    {
        return self::mergeExtensions($extensions, self::getRecordedAudioExtensions());
    }

    public static function getRecordedAudioExtensions(): array
    {
        if (!self::areVoiceMessagesEnabled()) {
            return [];
        }

        return ['m4a', 'ogg'];
    }

    private static function mergeExtensions(?string $extensions, array $additionalExtensions): string
    {
        $extensions = array_map('trim', explode(',', strtolower((string) $extensions)));
        $extensions = array_merge($extensions, $additionalExtensions);

        return implode(',', array_values(array_unique(array_filter($extensions))));
    }

    public static function isVaultEnabled(): bool
    {
        $filamentValue = function_exists('getSettingFilament')
            ? getSettingFilament('media.vault_enabled', null)
            : null;

        if ($filamentValue !== null && $filamentValue !== '') {
            return (bool) $filamentValue;
        }

        return (bool) getSetting('media.vault_enabled', true);
    }

    public static function areVoiceMessagesEnabled(): bool
    {
        $filamentValue = function_exists('getSettingFilament')
            ? getSettingFilament('media.voice_messages_enabled', null)
            : null;

        if ($filamentValue !== null && $filamentValue !== '') {
            return (bool) $filamentValue;
        }

        return (bool) getSetting('media.voice_messages_enabled', true);
    }

    public static function getVoiceRecorderFormats(): array
    {
        $extensions = self::getRecordedAudioExtensions();
        $formats = [
            ['mimeType' => 'audio/mp4', 'extension' => 'm4a'],
            ['mimeType' => 'audio/ogg;codecs=opus', 'extension' => 'ogg'],
            ['mimeType' => 'audio/ogg', 'extension' => 'ogg'],
        ];

        return array_values(array_filter(
            $formats,
            fn (array $format) => in_array($format['extension'], $extensions, true)
        ));
    }

    /**
     * Get attachment type by extension.
     *
     * @param $type
     * @return string
     */
    public static function getAttachmentType($type)
    {
        switch ($type) {
            case 'avi':
            case 'mp4':
            case 'wmw':
            case 'mpeg':
            case 'm4v':
            case 'moov':
            case 'mov':
            case 'mkv':
            case 'wmv':
            case 'asf':
                return 'video';
            case 'mp3':
            case 'wav':
            case 'ogg':
            case 'm4a':
            case 'webm':
                return 'audio';
            case 'png':
            case 'jpg':
            case 'jpeg':
                return 'image';
            case 'pdf':
            case 'xls':
            case 'xlsx':
                return 'document';
            default:
                return 'image';
        }
    }

    /**
     * Get file extensions by types.
     *
     * @param $type
     * @return array
     */
    public static function getTypeByExtension($type)
    {
        switch ($type) {
            case 'video':
                return ['mp4', 'avi', 'wmv', 'mpeg', 'm4v', 'moov', 'mov', 'mkv', 'asf'];
            case 'audio':
                return ['mp3', 'wav', 'ogg', 'm4a', 'webm'];
            default:
                return ['jpg', 'jpeg', 'png', 'webp'];
        }
    }

    /**
     * Return matching bookmarks category types to actual attachment types.
     *
     * @param $type
     * @return bool|string
     */
    public static function getActualTypeByBookmarkCategory($type)
    {
        switch ($type) {
            case 'photos':
                return 'image';
            case 'audio':
                return 'audio';
            case 'videos':
                return 'video';
            default:
                return false;
        }
    }

    /*
     * Gets recommended folder naming path based on mimetype
     */
    public static function getDirectoryByType($type)
    {
        switch ($type) {
            case 'video/mp4':
            case 'video/avi':
            case 'video/quicktime':
            case 'video/x-m4v':
            case 'video/mpeg':
            case 'video/wmw':
            case 'video/x-matroska':
            case 'video/x-ms-asf':
            case 'video/x-ms-wmv':
            case 'video/x-ms-wmx':
            case 'video/x-ms-wvx':
                $directory = 'videos';
                break;
            case 'audio/mpeg':
            case 'audio/ogg':
            case 'audio/wav':
            case 'audio/webm':
            case 'audio/mp4':
            case 'audio/x-m4a':
                $directory = 'audio';
                break;
            case 'application/vnd.ms-excel':
            case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
            case 'application/pdf':
                $directory = 'documents';
                break;
            default:
                $directory = 'images';
                break;
        }
        return $directory;
    }

    /**
     * Processes the attachment
     * Creates DB entry
     * Uploads it to the storage disk.
     *
     * @param $file
     * @param $directory
     * @param $generateThumbnail
     * @return mixed
     * @throws \Exception
     */
    public static function createAttachment($file, $directory, $generateThumbnail = false, $generateBlurredShot = false, $applyWatermark = true, ?int $userId = null, bool $saveToVault = false)
    {

        do {
            $fileId = Uuid::uuid4()->getHex();
        } while (Attachment::query()->where('id', $fileId)->first() != null);

        $fileMimeType = $file->getMimeType();
        $clientExtension = strtolower((string) $file->getClientOriginalExtension());
        $fileExtension = self::normalizeAttachmentExtension(
            in_array($clientExtension, self::getRecordedAudioExtensions()) ? $clientExtension : $file->guessExtension(),
            $fileMimeType
        );
        $attachmentType = str_starts_with((string) $fileMimeType, 'audio/')
            ? 'audio'
            : self::getAttachmentType($fileExtension);
        $storedExtension = $fileExtension;

        if (
            $attachmentType === 'video'
            && getSetting('media.transcoding_driver') === 'ffmpeg'
            && ($fileExtension !== 'mp4' || (bool) getSetting('media.enforce_mp4_conversion'))
        ) {
            return app(AttachmentTranscodingService::class)->createFfmpegAttachment(
                $file,
                $directory,
                $fileId,
                $fileExtension,
                $generateThumbnail,
                $generateBlurredShot,
                $applyWatermark,
                $userId,
                $saveToVault,
            );
        }

        if ($attachmentType === 'image') {
            // Converting all images to jpegs
            $asset = MediaEncoderServiceProvider::encodeImage(
                $file,
                $directory,
                $fileId,
                $generateThumbnail,
                $generateBlurredShot,
                $applyWatermark
            );
            $storedExtension = pathinfo($asset['filePath'], PATHINFO_EXTENSION) ?: $fileExtension;
        } elseif ($attachmentType === 'video') {
            // Convert videos to mp4s
            $asset = MediaEncoderServiceProvider::encodeVideo(
                $file,
                $directory,
                $fileId,
                $generateThumbnail,
                $generateBlurredShot,
                $applyWatermark
            );
            $storedExtension = pathinfo($asset['filePath'], PATHINFO_EXTENSION) ?: $fileExtension;
        } else {
            // For regular files, just upload them directly really
            $asset = MediaEncoderServiceProvider::encodeRegularFile(
                $file,
                $directory,
                $fileId,
                $fileExtension
            );
        }

        // Creating the db entry
        $storageDriver = config('filesystems.defaultFilesystemDriver');
        $attachment = new Attachment([
            'id' => $fileId,
            'user_id' => $userId ?? Auth::id(),
            'filename' => $asset['filePath'],
            'type' => $storedExtension,
            'driver' => self::getStorageProviderID($storageDriver),
            'has_thumbnail' => $asset['hasThumbnail'] ? 1 : null,
            'has_blurred_preview' => $asset['hasBlurredPreview'] ? 1 : null,
            'blurred_filename' => $asset['blurredFilename'] ?? null,
            'length' => $asset['length'] ?? null,
        ]);
        $attachment->vaulted_at = $saveToVault ? now() : null;
        try {
            return DB::transaction(function () use ($attachment, $asset, $directory, $fileId) {
                $attachment->save();
                if (!empty($asset['provider_job_id'])) {
                    app(AttachmentTranscodingService::class)->registerCoconut(
                        $attachment,
                        (string) $asset['filePath'],
                        trim($directory, '/').'/'.$fileId.'.mp4',
                        (string) $asset['provider_job_id'],
                    );
                    $attachment->load('transcoding');
                }
                return $attachment;
            });
        } catch (\Throwable $exception) {
            // A concurrently deleted owner (or a DB failure) must not strand a social upload.
            // The social upload endpoint owns this transaction, so cleanup commits separately.
            if ($saveToVault && DB::transactionLevel() === 0) {
                $remoteId = $asset['provider_job_id'] ?? null;
                $attachment->setRelation('transcoding', $remoteId ? new AttachmentTranscoding([
                    'source_path' => $asset['filePath'],
                    'output_path' => trim($directory, '/').'/'.$fileId.'.mp4',
                ]) : null);
                app(\Illuminate\Contracts\Bus\Dispatcher::class)->dispatch(new \App\Jobs\DeleteAttachmentFilesJob(
                    $storageDriver,
                    self::getStorageKeys($attachment),
                    $remoteId,
                    $remoteId ? getSetting('media.coconut_video_region') : null,
                ));
            }
            throw $exception;
        }
    }

    protected static function normalizeAttachmentExtension(?string $extension, ?string $mimeType): string
    {
        if ($mimeType === 'audio/mp4' || $mimeType === 'audio/x-m4a') {
            return 'm4a';
        }

        if ($mimeType === 'audio/webm') {
            return 'webm';
        }

        return $extension ?: 'file';
    }

    /**
     * Gets (full path, based on path virtual attribute) thumbnail path by resolution.
     * [Used to get final thumbnail URL].
     * @param $attachment
     * @param $width
     * @param $height
     * @param string $basePath Legacy argument kept for external callers.
     * @return string|string[]|null
     */
    public static function getThumbnailPathForAttachmentByResolution($attachment, $width, $height, $basePath = '/posts/images/')
    {
        if (self::isFileAttachment($attachment)) return null;
        if ($attachment->driver == Attachment::S3_DRIVER && getSetting('storage.aws_cdn_enabled') && getSetting('storage.aws_cdn_presigned_urls_enabled')) {
            return self::signAPrivateDistributionPolicy(
                'https://'.getSetting('storage.cdn_domain_name').'/'.self::getThumbnailFilenameByAttachmentAndResolution($attachment, $width, $height)
            );
        } else {
            return self::replaceAttachmentFilenameInUrl(
                $attachment,
                self::getThumbnailFilenameByAttachmentAndResolution($attachment, $width, $height)
            );
        }
    }

    /**
     * Gets (full path, based on path virtual attribute) thumbnail path by resolution.
     * * [Used to get final thumbnail URL].
     * @param $attachment
     * @param $basePath Legacy argument kept for external callers.
     * @return array|false|mixed|string|string[]
     */
    public static function getBlurredPreviewPathForAttachment($attachment, $basePath = 'posts/images/')
    {
        if (self::isFileAttachment($attachment)) return null;
        if(!$attachment->has_blurred_preview) return false;
        if ($attachment->driver == Attachment::S3_DRIVER && getSetting('storage.aws_cdn_enabled') && getSetting('storage.aws_cdn_presigned_urls_enabled')) {
            return self::signAPrivateDistributionPolicy(
                'https://'.getSetting('storage.cdn_domain_name').'/'.self::getBlurredPreviewByAttachment($attachment)
            );
        } else {
            return self::replaceAttachmentFilenameInUrl(
                $attachment,
                self::getBlurredPreviewByAttachment($attachment)
            );
        }
    }

    /**
     * Removes attachment from storage disk.
     * Note: This should be called internally, where we assume the attachment has been granted ownership already.
     * @param $attachment
     */
    public static function removeAttachment($attachment)
    {
        if ($attachment->vaulted_at || $attachment->placements()->exists()) {
            throw new \LogicException('Shared media must be deleted through AttachmentLifecycleService.');
        }
        $attachment->loadMissing('transcoding');
        if ($attachment->transcoding && !$attachment->transcoding->isTerminal()) {
            $attachment->transcoding->update([
                'status' => AttachmentTranscoding::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);
        }

        $diskName = self::getStorageProviderName($attachment->driver);
        $storage = Storage::disk($diskName);

        $keys = self::getStorageKeys($attachment);
        foreach ($keys as $key) {
            if (!$storage->delete($key) && $storage->exists($key)) {
                throw new \RuntimeException('Could not delete attachment object: '.$key);
            }
        }
    }

    public static function getStorageKeys(Attachment $attachment): array
    {
        if (self::isFileAttachment($attachment)) return [$attachment->filename];
        $attachment->loadMissing('transcoding');
        $keys = [];
        $keys[] = ltrim($attachment->filename, '/');
        $keys[] = ltrim((string) $attachment->blurred_filename, '/');
        // Deterministic shared-preview path is also cleaned after a rolled-back assignment.
        $keys[] = ltrim(dirname($attachment->filename).'/blurred/shared-'.$attachment->id.'.jpg', './');
        if ($attachment->transcoding) {
            $keys[] = ltrim((string) $attachment->transcoding->source_path, '/');
            $keys[] = ltrim((string) $attachment->transcoding->output_path, '/');
            $outputDirectory = trim(pathinfo((string) $attachment->transcoding->output_path, PATHINFO_DIRNAME), '.');
            $keys[] = ltrim($outputDirectory.'/thumbnails/'.$attachment->id.'.jpg', '/');
        }

        if (self::getAttachmentType($attachment->type) === 'image' || self::getAttachmentType($attachment->type) === 'video') {
            $thumb = self::getThumbnailFilenameByAttachmentAndResolution($attachment, 150, 150);
            if ($thumb) $keys[] = ltrim($thumb, '/');

            if ($attachment->has_blurred_preview) {
                $blur = self::getBlurredPreviewByAttachment($attachment);
                if ($blur) $keys[] = ltrim($blur, '/');
            }
        }

        return array_values(array_unique(array_filter($keys)));
    }

    /**
     * Returns file thumbnail relative path, by resolution.
     * [Used to get storage paths].
     * @param $attachment
     * @param $width
     * @param $height
     * @return string|string[]
     */
    public static function getThumbnailFilenameByAttachmentAndResolution($attachment, $width, $height)
    {
        if (self::getAttachmentType($attachment->type) === 'video') {
            return self::getMediaVariantFilename($attachment, 'thumbnails');
        }

        return self::getMediaVariantFilename($attachment, "{$width}X{$height}");
    }

    /**
     * Returns blurred preview relative path.
     * Uses the attachment's actual directory (posts/messenger/etc), not a hardcoded basePath.
     */
    public static function getBlurredPreviewByAttachment($attachment)
    {
        if (!empty($attachment->blurred_filename)) {
            return $attachment->blurred_filename;
        }

        return self::getMediaVariantFilename($attachment, 'blurred');
    }

    protected static function getMediaVariantFilename($attachment, string $variantDirectory): string
    {
        $filename = ltrim((string) $attachment->filename, '/');
        $dir = trim(pathinfo($filename, PATHINFO_DIRNAME), '.');

        if (basename($dir) === 'tmp') {
            $dir = trim(dirname($dir), '.');
        }

        $base = $attachment->id.'.jpg';

        if ($dir === '') {
            return "{$variantDirectory}/{$base}";
        }

        return "{$dir}/{$variantDirectory}/{$base}";
    }

    protected static function replaceAttachmentFilenameInUrl($attachment, string $replacementFilename): string
    {
        $filename = ltrim((string) $attachment->filename, '/');

        if ($filename === '') {
            return $attachment->path;
        }

        return str_replace($filename, ltrim($replacementFilename, '/'), $attachment->path);
    }

    /**
     * Returns file path by attachment.
     * TODO: Reproduce this one for non-attachment (avatars & admin uploads) as well.
     * @param $attachment
     * @return string|null
     */
    public static function getFilePathByAttachment($attachment)
    {
        if (self::isFileAttachment($attachment)) return null;
        $oldDriver = null;
        // Changing to attachment file system driver, if different from the configured one
        if($attachment->driver !== self::getStorageProviderID(getSetting('storage.driver'))){
            $oldDriver = config('filesystems.default');
            SettingsServiceProvider::setDefaultStorageDriver(self::getStorageProviderName($attachment->driver));
        }

        try {
            $fileUrl = '';
            if ($attachment->driver == Attachment::S3_DRIVER) {
                if (getSetting('storage.aws_cdn_enabled') && getSetting('storage.aws_cdn_presigned_urls_enabled')) {
                    $fileUrl = self::signAPrivateDistributionPolicy(
                        'https://'.getSetting('storage.cdn_domain_name').'/'.$attachment->filename
                    );
                } elseif (getSetting('storage.aws_cdn_enabled')) {
                    $fileUrl = 'https://'.getSetting('storage.cdn_domain_name').'/'.$attachment->filename;
                } else {
                    $fileUrl = 'https://'.getSetting('storage.aws_bucket_name').'.s3.'.getSetting('storage.aws_region').'.amazonaws.com/'.$attachment->filename;
                }
            }
            elseif ($attachment->driver == Attachment::WAS_DRIVER || $attachment->driver == Attachment::DO_DRIVER || $attachment->driver == Attachment::R2_DRIVER || $attachment->driver == Attachment::B2_DRIVER) {
                $fileUrl = Storage::url($attachment->filename);
            }
            elseif($attachment->driver == Attachment::MINIO_DRIVER){
                $fileUrl = rtrim(getSetting('storage.minio_endpoint'), '/').'/'.getSetting('storage.minio_bucket_name').'/'.$attachment->filename;
            }
            elseif($attachment->driver == Attachment::PUSHR_DRIVER){
                $fileUrl = rtrim(getSetting('storage.pushr_cdn_hostname'), '/').'/'.$attachment->filename;
            }
            elseif ($attachment->driver == Attachment::PUBLIC_DRIVER) {
                $fileUrl = Storage::disk('public')->url($attachment->filename);
            }

            return $fileUrl;
        } catch (InvalidArgumentException|AwsException $exception) {
            Log::warning('Unable to generate attachment URL.', [
                'attachment_id' => $attachment->id ?? null,
                'attachment_driver' => $attachment->driver ?? null,
                'filename' => $attachment->filename ?? null,
                'exception' => $exception->getMessage(),
            ]);

            return '';
        } finally {
            // Changing filesystem driver back, if needed
            if($oldDriver !== null) {
                SettingsServiceProvider::setDefaultStorageDriver($oldDriver);
            }
        }
    }

    /**
     * Method used for signing assets via CF.
     *
     * @param $cloudFrontClient
     * @param $resourceKey
     * @param $customPolicy
     * @param $privateKey
     * @param $keyPairId
     * @return mixed
     */
    private static function signPrivateDistributionPolicy(
        $cloudFrontClient,
        $resourceKey,
        $customPolicy,
        $privateKey,
        $keyPairId
    ) {
        try {
            $result = $cloudFrontClient->getSignedUrl([
                'url' => $resourceKey,
                'policy' => $customPolicy,
                'private_key' => $privateKey,
                'key_pair_id' => $keyPairId,
            ]);

            return $result;
        } catch (AwsException $e) {
        }
    }

    /**
     * Method used for signing assets via CF.
     *
     * @param $resourceKey
     * @return mixed
     */
    public static function signAPrivateDistributionPolicy($resourceKey)
    {
        $resourceKey = str_replace('\\', '/', $resourceKey); // Windows glitching otherwise
        $expires = time() + 24 * 60 * 60; // 24 hours (60 * 60 seconds) from now.
        $customPolicy = <<<POLICY
{
    "Statement": [
        {
            "Resource": "{$resourceKey}",
            "Condition": {
                "DateLessThan": {"AWS:EpochTime": {$expires}}
            }
        }
    ]
}
POLICY;
        $privateKey = base_path().'/'.getSetting('storage.aws_cdn_private_key_path');
        $keyPairId = getSetting('storage.aws_cdn_key_pair_id');

        $cloudFrontClient = new CloudFrontClient([
            'profile' => 'default',
            'version' => '2014-11-06',
            'region' => getSetting('storage.aws_region'),
        ]);

        return self::signPrivateDistributionPolicy(
            $cloudFrontClient,
            $resourceKey,
            $customPolicy,
            $privateKey,
            $keyPairId
        );
    }

    public static function getStorageProviderID($storageDriver) {
        if($storageDriver)
            if($storageDriver == 'public'){
                return Attachment::PUBLIC_DRIVER;
            }
        if($storageDriver == 's3'){
            return Attachment::S3_DRIVER;
        }
        if($storageDriver == 'wasabi'){
            return Attachment::WAS_DRIVER;
        }
        if($storageDriver == 'do_spaces'){
            return Attachment::DO_DRIVER;
        }
        if($storageDriver == 'minio'){
            return Attachment::MINIO_DRIVER;
        }
        if($storageDriver == 'pushr'){
            return Attachment::PUSHR_DRIVER;
        }
        if($storageDriver == 'r2'){
            return Attachment::R2_DRIVER;
        }
        if($storageDriver == 'b2'){
            return Attachment::B2_DRIVER;
        }
        else{
            return Attachment::PUBLIC_DRIVER;
        }
    }

    public static function getStorageProviderName($storageDriver) {
        if($storageDriver){
            if($storageDriver == Attachment::PUBLIC_DRIVER){
                return 'public';
            }
            if($storageDriver == Attachment::S3_DRIVER){
                return 's3';
            }
            if($storageDriver == Attachment::WAS_DRIVER){
                return 'wasabi';
            }
            if($storageDriver == Attachment::DO_DRIVER){
                return 'do_spaces';
            }
            if($storageDriver == Attachment::MINIO_DRIVER){
                return 'minio';
            }
            if($storageDriver == Attachment::PUSHR_DRIVER){
                return 'pushr';
            }
            if($storageDriver == Attachment::R2_DRIVER){
                return 'r2';
            }
            if($storageDriver == Attachment::B2_DRIVER){
                return 'b2';
            }
        }
        else{
            return 'public';
        }
    }

    /**
     * Attempts to fetch file name from a give url.
     * @param $url
     * @return bool|mixed
     */
    public static function getFileNameFromUrl($url) {
        if(preg_match('/[^\/\\&\?]+\.\w{3,4}(?=([\?&].*$|$))/', $url, $matches)){
            return $matches[0];
        }
        return false;
    }

    public static function hasBlurredPreview($attachment)
    {
        if(getSetting('media.use_blurred_previews_for_locked_posts') && $attachment->has_blurred_preview){
            return true;
        }
        return false;
    }

    public static function getPostPreviewData($post)
    {
        $attachment = isset($post->attachments[0]) ? $post->attachments[0] : null;
        $hasBlurred = $attachment && self::hasBlurredPreview($attachment);

        return [
            'attachmentExists' => (bool) $attachment,
            'hasBlurred' => $hasBlurred,
            'backgroundImage' => $hasBlurred
                ? $attachment->BlurredPreview
                : asset('/img/post-locked.svg'),
            'backgroundClass' => $hasBlurred
                ? ''
                : 'bg-contain svg-locked-post',
        ];
    }

    public static function extensionsToMimeTypes(string $extensions): array
    {
        $extensionToMime = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'md' => 'text/markdown',
            'zip' => 'application/zip',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'wav' => 'audio/wav',
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
            'm4a' => 'audio/mp4',
            'webm' => 'audio/webm',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'moov' => 'video/quicktime',
            'm4v' => 'video/x-m4v',
            'mpeg' => 'video/mpeg',
            'wmv' => 'video/x-ms-wmv',
            'asf' => 'video/x-ms-asf',
        ];

        $extensionsArray = array_unique(array_map('trim', explode(',', strtolower($extensions))));

        return array_values(array_unique(array_filter(
            array_map(fn ($ext) => $extensionToMime[$ext] ?? null, $extensionsArray)
        )));
    }

    public static function getAdminFileUploadVisibility(): string
    {
        $s3Enabled = getSetting('storage.driver') === 's3';
        $cdnEnabled = (bool) getSetting('storage.aws_cdn_enabled');
        $signedUrlsEnabled = (bool) getSetting('storage.aws_cdn_presigned_urls_enabled');

        // For S3, use 'private' if CDN or signed URLs are enabled, otherwise 'public'
        if ($s3Enabled) {
            return ($cdnEnabled || $signedUrlsEnabled) ? 'private' : 'public';
        }

        // Default to 'public' for local or other drivers
        return 'public';
    }

    public static function getUploadMaxFilesize(): int {
        return getSetting('media.max_file_upload_size') ? ((int) getSetting('media.max_file_upload_size') * 1000) : 4000;
    }

    /**
     * Copy a file inside the same disk.
     * - Uses native ->copy when possible
     * - For Pushr (or any adapter that fails copy), falls back to get+put.
     */
    public static function copyOnDisk(int $driverId, string $src, string $dst, string $visibility = 'public'): void
    {
        $diskName = self::getStorageProviderName($driverId);
        $disk = Storage::disk($diskName);

        // Pushr: keep custom path
        if ($driverId === Attachment::PUSHR_DRIVER) {
            self::pushrCDNCopyByPath($src, $dst, $driverId, $visibility);
            return;
        }

        // Non-pushr: try native copy, then fallback
        try {
            $disk->copy($src, $dst);
            return;
        } catch (\Throwable $e) {
            // Fallback
        }

        $contents = $disk->get($src);
        $disk->put($dst, $contents, $visibility);
    }

    /**
     * (3) Fix: allow copying arbitrary source path, not just $attachment->filename.
     * (5) Keep: still exists as a Pushr-specific workaround.
     */
    public static function pushrCDNCopyByPath(string $srcPath, string $dstPath, int $driverId, string $visibility = 'public'): void
    {
        $diskName = self::getStorageProviderName($driverId);
        $storage = Storage::disk($diskName);

        $remoteFile = $storage->get($srcPath);

        // temp local
        $localStorage = Storage::disk('public');
        $tmpFile = 'tmp/'.basename($srcPath);

        $localStorage->put($tmpFile, $remoteFile);
        $storage->put($dstPath, $localStorage->get($tmpFile), $visibility);
        $localStorage->delete($tmpFile);
    }
}
