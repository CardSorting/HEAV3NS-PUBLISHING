<?php

namespace App\Providers;

use App\Services\FFmpeg\Formats\H264Amf;
use App\Services\FFmpeg\Formats\H264Nvenc;
use App\Services\FFmpeg\Formats\H264Qsv;
use FFMpeg\Format\Video\X264;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Facades\Image;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use App\Services\Media\WatermarkService;
use Ramsey\Uuid\Uuid;

class MediaEncoderServiceProvider extends ServiceProvider
{
    protected const SUPPORTED_FFMPEG_VIDEO_ENCODERS = [
        'libx264',
        'h264_nvenc',
        'h264_qsv',
        'h264_amf',
    ];

    // Mixed for ffmpeg and coconut
    public static $videoEncodingPresets = [
        'legacy'   => ['videoBitrate'=> 1200,  'audioBitrate' => 96,  'quality' => 1],
        'size' => ['videoBitrate'=> 6000, 'audioBitrate' => 128, 'quality' => 1],
        'balanced' => ['videoBitrate'=> 12000, 'audioBitrate' => 192, 'quality' => 3],
        'quality' => ['videoBitrate'=> 20000, 'audioBitrate' => 256, 'quality' => 5],
    ];

    protected static function watermarks(): WatermarkService
    {
        return app(WatermarkService::class);
    }

    protected static function makeBlurredPreviewPath(string $directory): string
    {
        $directory = trim($directory, '/');
        $prefix = $directory === '' ? 'blurred' : $directory.'/blurred';

        return $prefix.'/'.Uuid::uuid4()->getHex().'.jpg';
    }

    /**
     * Generates coconut storage configuration.
     * @param $storageDriver
     * @return array|bool
     */
    public static function getCoconutStorageSettings($storageDriver) {
        switch ($storageDriver) {
            case 's3':
                return [
                    'service' => 's3',
                    'bucket' => getSetting('storage.aws_bucket_name'),
                    'region' => getSetting('storage.aws_region'),
                    'credentials' => [
                        'access_key_id' => getSetting('storage.aws_access_key'),
                        'secret_access_key' => getSetting('storage.aws_secret_key'),
                    ],
                ];
            case 'do_spaces':
                return [
                    'service' => 'dospaces',
                    'bucket' => getSetting('storage.do_bucket_name'),
                    'region' => getSetting('storage.do_region'),
                    'credentials' => [
                        'access_key_id' => getSetting('storage.do_access_key'),
                        'secret_access_key' => getSetting('storage.do_secret_key'),
                    ],
                ];
            case 'wasabi':
                return [
                    'service' => 'wasabi',
                    'bucket' => getSetting('storage.was_bucket_name'),
                    'region' => getSetting('storage.was_region'),
                    'credentials' => [
                        'access_key_id' => getSetting('storage.was_access_key'),
                        'secret_access_key' => getSetting('storage.was_secret_key'),
                    ],
                ];
            case 'minio':
                return [
                    'service' => 's3other',
                    'bucket' => getSetting('storage.minio_bucket_name'),
                    'force_path_style' => true,
                    'region' => getSetting('storage.minio_region'),
                    'credentials' => [
                        'access_key_id' => getSetting('storage.minio_access_key'),
                        'secret_access_key' => getSetting('storage.minio_secret_key'),
                    ],
                    'endpoint' => getSetting('storage.minio_endpoint'),
                ];
            case 'pushr':
                return [
                    'service' => 's3other',
                    'bucket' => getSetting('storage.pushr_bucket_name'),
                    'force_path_style' => true,
                    'region' => 'us-east-1',
                    'credentials' => [
                        'access_key_id' => getSetting('storage.pushr_access_key'),
                        'secret_access_key' => getSetting('storage.pushr_secret_key'),
                    ],
                    'endpoint' => getSetting('storage.pushr_endpoint'),
                ];
            case 'r2':
                return [
                    'service'          => 's3other',
                    'bucket'           => getSetting('storage.r2_bucket_name'),
                    'force_path_style' => false,
                    'region'           => 'auto',
                    'credentials'      => [
                        'access_key_id'     => getSetting('storage.r2_access_key'),
                        'secret_access_key' => getSetting('storage.r2_secret_key'),
                    ],
                    'endpoint' => getSetting('storage.r2_endpoint'),
                ];
            case 'b2':
                return [
                    'service'          => 's3other',
                    'bucket'           => getSetting('storage.b2_bucket_name'),
                    'force_path_style' => false,
                    'region'           => getSetting('storage.b2_region'),
                    'credentials'      => [
                        'access_key_id'     => getSetting('storage.b2_access_key'),
                        'secret_access_key' => getSetting('storage.b2_secret_key'),
                    ],
                    'endpoint' => getSetting('storage.b2_endpoint'),
                ];
            default:
                return false;
        }
    }

    /**
     * Generates Coconut input configuration.
     * Keep the legacy URL-based flow by default and only switch to native S3 access
     * when CloudFront presigned URLs would make the source unreadable to Coconut.
     */
    public static function getCoconutInputSettings($storageDriver, string $filePath): array
    {
        if (
            $storageDriver === 's3' &&
            getSetting('storage.aws_cdn_presigned_urls_enabled')
        ) {
            $storageConfig = self::getCoconutStorageSettings($storageDriver);
            if ($storageConfig) {
                $storageConfig['key'] = '/'.$filePath;

                return $storageConfig;
            }
        }

        $tempFileUrl = Storage::url($filePath);

        if ($storageDriver === 's3' && getSetting('storage.aws_cdn_enabled')) {
            $tempFileUrl = 'https://'.getSetting('storage.cdn_domain_name').'/'.$filePath;
        }

        return [
            'url' => $tempFileUrl,
        ];
    }

    public static function encodeVideo($file, $directory, $fileId, $generateThumbnail, $generateBlurredShot, $applyWatermark)
    {
        $storage = Storage::disk(config('filesystems.defaultFilesystemDriver'));
        $fileExtension = $file->guessExtension();

        if (getSetting('media.transcoding_driver') === 'ffmpeg') {
            $data = self::ffmpegEncode($file, $directory, $fileId, $generateThumbnail, $generateBlurredShot, $applyWatermark);
        } elseif (getSetting('media.transcoding_driver') === 'coconut') {
            $data = self::coconutEncode($file, $directory, $fileId, $generateThumbnail, $generateBlurredShot, $applyWatermark);
        } else {
            $filePath = $directory.'/'.$fileId.'.'.$fileExtension;
            $videoLength = self::getUploadedVideoLength($file, $fileId);
            self::putUploadedFileToDisk($storage, $filePath, $file, 'public');

            $data = [
                'filePath' => $filePath,
                'hasThumbnail' => false,
                'hasBlurredPreview' => false,
                'length' => $videoLength,
            ];
        }

        $hasThumbnail = (bool) ($data['hasThumbnail'] ?? false);
        $hasBlurredPreview = (bool) ($data['hasBlurredPreview'] ?? false);
        $blurredFilename = $data['blurredFilename'] ?? null;
        $videoLength = (int) ($data['length'] ?? 0);
        $filePath = (string) ($data['filePath'] ?? '');

        return [
            'filePath' => $filePath,
            'provider_job_id' => $data['coconutJob']->id ?? null,
            'hasBlurredPreview' => $hasBlurredPreview,
            'blurredFilename' => $blurredFilename,
            'hasThumbnail' => $hasThumbnail,
            'length' => $videoLength,
        ];
    }

    public static function ffmpegEncode(
        $file,
        $directory,
        $fileId,
        $generateThumbnail,
        $generateBlurredShot,
        $applyWatermark,
        ?string $storageDiskName = null,
        ?string $sourceExtension = null,
        ?callable $progressCallback = null,
        ?int $watermarkOwnerId = null,
    )
    {

        $storageDiskName ??= config('filesystems.defaultFilesystemDriver');
        $storage = Storage::disk($storageDiskName);
        $fileExtension = $initialFileExtension = strtolower((string) ($sourceExtension ?: $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'mp4'));
        $hasBlurredPreview = false;
        $hasThumbnail = false;
        $blurredFilename = null;
        $tmpWatermarkFile = null;

        // Move tmp file onto local files path, as ffmpeg can't handle absolute paths
        // A unique name prevents a stale file created by another execution mode
        // (web, deferred, spawned, or worker) from blocking a later attempt.
        $temporaryInputPath = 'ffmpeg-input-'.$fileId.'-'.bin2hex(random_bytes(6)).'.'.$fileExtension;
        $stream = fopen($file->getRealPath(), 'rb');
        if (!is_resource($stream)) {
            throw new \RuntimeException('The uploaded video could not be read.');
        }
        try {
            if (!Storage::disk('tmp')->put($temporaryInputPath, $stream)) {
                throw new \RuntimeException('The uploaded video could not be prepared for processing.');
            }
        } finally {
            fclose($stream);
        }

        try {
            $filePath = $temporaryInputPath;
            $fileExtension = 'mp4';
            $newfilePath = $directory.'/'.$fileId.'.'.$fileExtension;

        // Converting the video
        $video = FFMpeg::
        fromDisk('tmp')
            ->open($filePath);

        $videoLength = $video->getDriver()->getDurationInSeconds();

        // Checking if uploaded videos do no exceed maximum length in seconds
        if(getSetting('media.max_videos_length')){
            $maxLength = (int) getSetting('media.max_videos_length');
            if($videoLength > $maxLength){
                throw new \Exception(__("Uploaded videos can not longer than :length seconds.", ['length'=>$maxLength]));
            }
        }

        // Add watermark if enabled in admin
        if ($applyWatermark) {
            $result = self::watermarks()->applyToFfmpegVideo($video, (string) $fileId, $watermarkOwnerId);
            $tmpWatermarkFile = $result['tmpWatermarkFile'] ?? null;
        }

        // Re-converting mp4 only if enforced by the admin setting
        if($initialFileExtension == 'mp4' && !getSetting('media.enforce_mp4_conversion')){
            $filePath = $directory.'/'.$fileId.'.'.$fileExtension;
            self::putUploadedFileToDisk($storage, $filePath, $file, 'public');
        }
        else{
            // Overriding default ffmpeg lib temporary_files_root behaviour
            $ffmpegOutputLogDir = storage_path().'/logs/ffmpeg';
            if(!is_dir($ffmpegOutputLogDir)){
                mkdir($ffmpegOutputLogDir);
            }

            $videoEncoder = self::pickVideoEncoder();

            $videoQualityPreset = self::$videoEncodingPresets[getSetting('media.ffmpeg_video_conversion_quality_preset')];

            $audioCodec = getSetting('media.ffmpeg_audio_encoder');

            $video = $video->export()->toDisk($storageDiskName);

            $format = self::makeVideoFormat($videoEncoder, $audioCodec);

            $format->setKiloBitrate($videoQualityPreset['videoBitrate'])
                ->setAudioKiloBitrate($videoQualityPreset['audioBitrate']);

            $video->inFormat($format);

            if ($progressCallback) {
                $video->onProgress($progressCallback);
            }

            $speedPreset = self::coerceVideoEncoderPreset(
                $videoEncoder,
                (string) (getSetting('media.ffmpeg_video_speed_preset') ?: '')
            );

            self::applyVideoEncoderPreset($video, $videoEncoder, $speedPreset);

            // Web-friendly MP4 playback
            $video->addFilter(['-movflags', '+faststart']);
            $video->addFilter(['-pix_fmt', 'yuv420p']);

            $video->save($newfilePath);

            // Generating thumbnail from converted video
            $thumbnailPath = $directory.'/thumbnails/'.$fileId.'.jpg';
            FFMpeg::fromDisk($storageDiskName)
                ->open($newfilePath)
                ->getFrameFromSeconds(1)
                ->export()
                ->toDisk($storageDiskName)
                ->save($thumbnailPath);
            $hasThumbnail = true;

            // Generate blurred version of the thumbnail (FFmpeg-filter based; much faster than GD)
            if (getSetting('media.use_blurred_previews_for_locked_posts') && $generateBlurredShot) {
                $blurredFilename = self::generateVideoBlurredPreview(
                    $newfilePath,
                    $directory,
                    $fileId,
                    $storageDiskName,
                );
                $hasBlurredPreview = $blurredFilename !== null;
            }

        }

            $filePath = $newfilePath;

            return [
                'filePath' => $filePath,
                'hasBlurredPreview' => $hasBlurredPreview,
                'blurredFilename' => $blurredFilename,
                'hasThumbnail' => $hasThumbnail,
                'length' => $videoLength,
            ];
        } finally {
            Storage::disk('tmp')->delete($temporaryInputPath);
            if ($tmpWatermarkFile) {
                Storage::disk('tmp')->delete($tmpWatermarkFile);
            }
        }

    }

    public static function coconutEncode($file, $directory, $fileId, $generateThumbnail, $generateBlurredShot, $applyWatermark) {

        $storage = Storage::disk(config('filesystems.defaultFilesystemDriver'));
        $fileExtension = $initialFileExtension = $file->guessExtension();

        if($initialFileExtension == 'mp4' && !getSetting('media.coconut_enforce_mp4_conversion')){
            $filePath = $directory.'/'.$fileId.'.'.$fileExtension;
            $videoLength = self::getUploadedVideoLength($file, $fileId);
            self::putUploadedFileToDisk($storage, $filePath, $file, 'public');
            return [
                'coconutJob' => null,
                'filePath' => $filePath,
                'hasBlurredPreview' => false,
                'blurredFilename' => null,
                'hasThumbnail' => false,
                'length' => $videoLength,
            ];
        }
        else{
            $region = getSetting('media.coconut_video_region');
            $configData = [];
            if($region && $region !== 'us-east-1'){
                $configData['region'] = $region;
            }
            $coconut = new \Coconut\Client(getSetting('media.coconut_api_key'), $configData);
            // Uploading the original video onto s3
            $filePath = $directory.'/tmp/'.$fileId.'.'.$fileExtension;
            self::putUploadedFileToDisk($storage, $filePath, $file, 'public');
            Storage::url($filePath);

            // Setting up the coconut notification
            $coconut->notification = [
                'type' => 'http',
                'url' => config('services.coconut.webhook_url') ?: route('transcoding.coconut.update'),
                'params' => [
                    'attachmentId' => $fileId,
                ],
                'metadata' => true,
            ];

            // Setting up the storage for coconut
            if(getSetting('storage.driver') === 'public'){
                throw new \Exception("Local storage driver is not supported by Coconut.");
            }
            $coconut->storage = self::getCoconutStorageSettings(getSetting('storage.driver'));

            $videoQualityPreset = self::$videoEncodingPresets[str_replace("coconut_", "", getSetting('media.coconut_video_conversion_quality_preset'))];
            // Use provider-native input settings so Coconut can read private objects directly.
            $storageDriver = getSetting('storage.driver');
            $outputDirectory = trim($directory, '/');

            $jobData = [
                'input' => self::getCoconutInputSettings($storageDriver, $filePath),
                "settings"=> [
                    "ultrafast"=> true,
                ],
                // Review if 480 isn't too small
                // For ffmpeg - we take a frame out of the full resolution video
                // Add another image output with blur filter (1-5)
                'outputs' => [
                    'jpg:540p' => [
                        'key' => 'jpg:medium',
                        'path' => '/'.$outputDirectory.'/thumbnails/'.$fileId.'.jpg',
                        "offsets" => [1],
                    ],
                    'mp4' => [
                        [
                            'key' => 'mp4',
                            'path' => '/'.$outputDirectory.'/'.$fileId.'.mp4',
                            'format' => [
                                'quality' => $videoQualityPreset['quality'],
                                'video_codec' => 'h264',
                                'audio_codec' => getSetting('media.coconut_audio_encoder'),
                                'video_bitrate' => $videoQualityPreset['videoBitrate'].'k',
                                'audio_bitrate' => $videoQualityPreset['audioBitrate'].'k',
                            ],
                        ],
                    ],
                ],
            ];

            $hasBlurredPreview = false;
            $blurredFilename = null;
            if ($generateBlurredShot && getSetting('media.use_blurred_previews_for_locked_posts')) {
                $blurredFilename = self::makeBlurredPreviewPath($outputDirectory);
                // Blurred thumbnail
                $jobData['outputs']['jpg:720p'] = [
                    'key' => 'jpg',
                    'path' => '/'.$blurredFilename,
                    "offsets" => [1],
                    'blur' => 5,
                ];
                $hasBlurredPreview = true;
            }

            // Watermark
            if ($applyWatermark) {
                self::watermarks()->applyToCoconutJobData($jobData);
            }

            $coconutJob = (new \Coconut\Job($coconut))->create($jobData);
        }

        return [
            'coconutJob' => $coconutJob,
            'filePath' => $filePath,
            'hasBlurredPreview' => $hasBlurredPreview,
            'blurredFilename' => $blurredFilename,
            'hasThumbnail' => true,
            'length' => 0,
        ];

    }

    protected static function getUploadedVideoLength($file, string $fileId): int
    {
        $fileExtension = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'mp4';
        $tmpPath = 'metadata/'.$fileId.'.'.$fileExtension;
        $stream = null;

        try {
            $stream = fopen($file->getRealPath(), 'rb');
            Storage::disk('tmp')->put($tmpPath, $stream);

            $duration = FFMpeg::fromDisk('tmp')
                ->open($tmpPath)
                ->getDriver()
                ->getDurationInSeconds();

            return $duration && (float) $duration > 0
                ? (int) ceil((float) $duration)
                : 0;
        } catch (\Throwable $exception) {
            return 0;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            Storage::disk('tmp')->delete($tmpPath);
        }
    }

    public static function encodeImage($file, $directory, $fileId, $generateThumbnail, $generateBlurredShot, $applyWatermark)
    {
        $storage = Storage::disk(config('filesystems.defaultFilesystemDriver'));
        $outputs = [];
        $fileExtension = $file->guessExtension();
        $hasThumbnail = false;
        $hasBlurredPreview = false;
        $blurredFilename = null;

        // Create the initial image instance and orientate it
        $jpgImage = Image::make($file);
        $jpgImage->fit($jpgImage->width(), $jpgImage->height())->orientate();

        // Save the original file content before any processing
        $originalFileContent = (string) $jpgImage->encode('jpg', 85); // Save the high-quality original

        if ($applyWatermark) {
            self::watermarks()->applyToInterventionImage($jpgImage);
        }

        // Handle GIFs without processing
        if ($fileExtension == 'gif') {
            $filePath = $directory.'/'.$fileId.'.'.$fileExtension;
            $outputs[$filePath] = file_get_contents($file->getRealPath());
        } else {
            // Save the processed image
            $fileExtension = 'jpg';
            $filePath = $directory.'/'.$fileId.'.'.$fileExtension;
            $outputs[$filePath] = (string) $jpgImage->encode('jpg', 85);
        }

        // Generate thumbnail
        if ($generateThumbnail) {
            $width = 150;
            $height = 150;
            $thumbnailImg = Image::make($originalFileContent); // Use the saved original content
            $thumbnailImg->fit($width, $height, function ($constraint) {
                $constraint->aspectRatio();
            });
            $thumbnailImg->encode('jpg', 85);
            $thumbnailDir = $directory.'/'.$width.'X'.$height;
            $thumbnailfilePath = $thumbnailDir.'/'.$fileId.'.jpg';
            // Upload the thumbnail to storage
            $outputs[$thumbnailfilePath] = (string) $thumbnailImg;
            $hasThumbnail = true;
        }

        if (getSetting('media.use_blurred_previews_for_locked_posts') && $generateBlurredShot) {
            $blurredImg = Image::make($originalFileContent)->orientate(); // Ensure proper orientation
            // Downscale the image to speed up processing and reduce memory usage
            $blurredImg->resize(600, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            // Access the raw GD image resource
            $gdImage = $blurredImg->getCore();
            // Blur the image
            $blurredGdImage = multiStepBlur($gdImage);
            // Wrap the GD resource back into an Intervention Image instance
            $blurredImg = Image::make($blurredGdImage);
            // Encode the image as a web-optimized JPEG
            $blurredImg->encode('jpg', 85); // Adjust quality for faster load and reduced size
            $blurredPreviewPath = self::makeBlurredPreviewPath($directory);
            // Upload the blurred image to storage
            $outputs[$blurredPreviewPath] = (string) $blurredImg;
            $hasBlurredPreview = true;
            $blurredFilename = $blurredPreviewPath;
        }

        // Finish image processing before writing any output, and never accept failed writes.
        try {
            foreach ($outputs as $path => $bytes) {
                if ($bytes === false || !$storage->put($path, $bytes, 'public')) {
                    throw new \RuntimeException('The image could not be stored.');
                }
            }
        } catch (\Throwable $exception) {
            app(\Illuminate\Contracts\Bus\Dispatcher::class)->dispatch(new \App\Jobs\DeleteAttachmentFilesJob(
                config('filesystems.defaultFilesystemDriver'),
                array_keys($outputs)
            ));
            throw $exception;
        }

        return [
            'filePath' => $filePath,
            'hasBlurredPreview' => $hasBlurredPreview,
            'blurredFilename' => $blurredFilename,
            'hasThumbnail' => $hasThumbnail,
        ];

    }

    /**
     * Doesn't really encode such files at all, just uploads them onto storage driver.
     * @param $file
     * @param $directory
     * @param $fileId
     * @return array{filePath: string, hasBlurredPreview: false, hasThumbnail: false}
     */
    public static function encodeRegularFile($file, $directory, $fileId, ?string $extension = null)
    {
        $fileExtension = $extension ?: $file->guessExtension();
        $filePath = $directory.'/'.$fileId.'.'.$fileExtension;
        $storage = Storage::disk(config('filesystems.defaultFilesystemDriver'));
        self::putUploadedFileToDisk($storage, $filePath, $file, 'public');
        return [
            'filePath' => $filePath,
            'hasBlurredPreview' => false,
            'blurredFilename' => null,
            'hasThumbnail' => false,
        ];
    }

    public static function generateVideoBlurredPreview(
        string $newfilePath,
        string $directory,
        string $fileId,
        ?string $storageDiskName = null,
        ?string $destinationPath = null,
    ): ?string {
        $diskName = $storageDiskName ?: config('filesystems.defaultFilesystemDriver');
        $disk = Storage::disk($diskName);
        $tmpDisk = Storage::disk('tmp');
        $log = Log::channel('ffmpeg');

        $blurredThumbnailPath = $destinationPath ?? self::makeBlurredPreviewPath($directory);
        $disk->makeDirectory($directory.'/blurred');

        $blurredFileId = pathinfo($blurredThumbnailPath, PATHINFO_FILENAME);
        $tmpInputRel = "blur-src-{$blurredFileId}.mp4";
        $tmpOutputRel = "blur-{$blurredFileId}.jpg";

        try {
            $in = $disk->readStream($newfilePath);
            if (!is_resource($in)) {
                throw new \RuntimeException("readStream failed: {$newfilePath}");
            }
            $tmpDisk->put($tmpInputRel, $in);
            try { fclose($in); } catch (\Throwable $x) {}

            $inputAbs = $tmpDisk->path($tmpInputRel);
            $outputAbs = $tmpDisk->path($tmpOutputRel);

            $down = 3;
            $sigma = 10;
            $steps = 2;

            $vf =
                "scale=trunc(iw/{$down}/2)*2:trunc(ih/{$down}/2)*2:flags=lanczos,"
                ."gblur=sigma={$sigma}:steps={$steps},"
                ."scale=trunc(iw*{$down}/2)*2:trunc(ih*{$down}/2)*2:flags=lanczos";

            $ffmpeg = getSetting('media.ffmpeg_path') ?: 'ffmpeg';

            $result = Process::run([
                $ffmpeg,
                '-y',
                '-hide_banner',
                '-loglevel', 'error',
                '-ss', '1',
                '-i', $inputAbs,
                '-frames:v', '1',
                '-vf', $vf,
                '-q:v', '1',
                $outputAbs,
            ]);

            if (!$result->successful()) {
                $log->warning('Blur preview ffmpeg failed', [
                    'disk' => $diskName,
                    'stderr' => $result->errorOutput(),
                    'exit_code' => $result->exitCode(),
                ]);
                throw new \RuntimeException('ffmpeg blur failed');
            }

            $out = $tmpDisk->readStream($tmpOutputRel);
            if (!is_resource($out)) {
                throw new \RuntimeException("readStream failed: {$tmpOutputRel}");
            }

            $disk->put($blurredThumbnailPath, $out, 'public');
            try { fclose($out); } catch (\Throwable $x) {}

            return $blurredThumbnailPath;

        } catch (\Throwable $e) {
            $log->info('Blur preview fallback to GD', [
                'disk' => $diskName,
                'reason' => $e->getMessage(),
            ]);

            try {
                $thumbnailPath = $directory.'/thumbnails/'.$fileId.'.jpg';

                $thumbStream = $disk->readStream($thumbnailPath);
                if (!is_resource($thumbStream)) {
                    throw new \RuntimeException("readStream failed: {$thumbnailPath}");
                }

                $thumbnailImage = Image::make($thumbStream)->orientate();
                try { fclose($thumbStream); } catch (\Throwable $x) {}

                $gdImage = $thumbnailImage->getCore();
                $blurredGdImage = multiStepBlur($gdImage, 4, 40, 25);

                $blurredThumbnailImage = Image::make($blurredGdImage)->encode('jpg', 80);
                $disk->put($blurredThumbnailPath, (string) $blurredThumbnailImage, 'public');

                return $blurredThumbnailPath;

            } catch (\Throwable $e2) {
                $log->warning('Blur preview failed (ffmpeg + GD)', [
                    'disk' => $diskName,
                    'error' => $e2->getMessage(),
                ]);
                return null;
            }

        } finally {
            try { $tmpDisk->delete($tmpInputRel); } catch (\Throwable $x) {}
            try { $tmpDisk->delete($tmpOutputRel); } catch (\Throwable $x) {}
        }
    }

    public static function pickVideoEncoder(?string $requested = null): string
    {
        $requested = $requested ?? (getSetting('media.ffmpeg_video_encoder') ?: 'libx264');
        $requested = trim((string) $requested);

        // Always allow CPU fallback
        if ($requested === '' || $requested === 'libx264') {
            return 'libx264';
        }

        if (!in_array($requested, self::SUPPORTED_FFMPEG_VIDEO_ENCODERS, true)) {
            Log::warning("Unknown FFmpeg encoder '{$requested}' requested; falling back to libx264.");

            return 'libx264';
        }

        if (self::ffmpegSupportsEncoder($requested)) {
            return $requested;
        }

        Log::warning("FFmpeg encoder '{$requested}' not available on this host; falling back to libx264.");

        return 'libx264';
    }

    protected static function ffmpegSupportsEncoder(string $encoder): bool
    {
        $encoders = self::getFfmpegEncoders();
        return isset($encoders[$encoder]);
    }

    protected static function getFfmpegEncoders(): array
    {
        $ffmpeg = getSetting('media.ffmpeg_path') ?: 'ffmpeg';
        $cacheKey = 'ffmpeg:encoders:'.sha1($ffmpeg);

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($ffmpeg) {
            try {
                $result = Process::run([$ffmpeg, '-hide_banner', '-encoders']);

                if (!$result->successful()) {
                    Log::warning('FFmpeg encoder detection failed: '.$result->errorOutput());
                    return [];
                }

                $out = $result->output()."\n".$result->errorOutput();
                $encoders = [];

                foreach (preg_split("/\R/", $out) as $line) {
                    // Example: " V....D h264_nvenc           NVIDIA NVENC H.264 encoder (codec h264)"
                    if (preg_match('/^\s*[A-Z\.]{6}\s+(\S+)\s+/', $line, $m)) {
                        $encoders[$m[1]] = true;
                    }
                }

                return $encoders;
            } catch (\Throwable $e) {
                Log::warning('FFmpeg encoder detection exception: '.$e->getMessage());
                return [];
            }
        });
    }

    protected static function makeVideoFormat(string $videoEncoder, string $audioCodec): X264|H264Nvenc|H264Qsv|H264Amf
    {
        return match ($videoEncoder) {
            'h264_nvenc' => new H264Nvenc($audioCodec),
            'h264_qsv' => new H264Qsv($audioCodec),
            'h264_amf' => new H264Amf($audioCodec),
            default => new X264($audioCodec, 'libx264'),
        };
    }

    protected static function coerceVideoEncoderPreset(string $videoEncoder, string $preset): string
    {
        $presets = match (true) {
            str_ends_with($videoEncoder, '_nvenc') => [
                'default' => 'p5',
                'options' => ['p1', 'p2', 'p3', 'p4', 'p5', 'p6', 'p7'],
            ],
            str_ends_with($videoEncoder, '_qsv') => [
                'default' => 'veryfast',
                'options' => ['veryfast', 'faster', 'fast', 'medium', 'slow'],
            ],
            str_ends_with($videoEncoder, '_amf') => [
                'default' => 'balanced',
                'options' => ['speed', 'balanced', 'quality'],
            ],
            default => [
                'default' => 'veryfast',
                'options' => ['ultrafast', 'superfast', 'veryfast', 'faster', 'fast', 'medium'],
            ],
        };

        return in_array($preset, $presets['options'], true)
            ? $preset
            : $presets['default'];
    }

    protected static function applyVideoEncoderPreset($video, string $videoEncoder, string $preset): void
    {
        if ($videoEncoder === 'libx264' || str_ends_with($videoEncoder, '_nvenc') || str_ends_with($videoEncoder, '_qsv')) {
            $video->addFilter(['-preset', $preset]);

            return;
        }

        if (str_ends_with($videoEncoder, '_amf')) {
            $video->addFilter(['-quality', $preset]);
        }
    }

    // TODO: Move this ot attachments provider?
    protected static function putUploadedFileToDisk($disk, string $path, $file, string $visibility = 'public'): void
    {
        // $file is usually an UploadedFile (Symfony), but keep it generic.
        $stream = null;
        try {
            // Prefer stream from real path (most memory efficient)
            if (method_exists($file, 'getRealPath') && $file->getRealPath()) {
                $stream = fopen($file->getRealPath(), 'rb');
            }

            // Fallback: try readStream if $file is a filesystem path/string
            if (!$stream && is_string($file)) {
                $stream = fopen($file, 'rb');
            }

            if ($stream) {
                if (!$disk->put($path, $stream, $visibility)) {
                    throw new \RuntimeException('The uploaded file could not be stored.');
                }
                fclose($stream);
                return;
            }
        } catch (\Throwable $e) {
            // stream failed => fallback below
            try { if (is_resource($stream)) fclose($stream); } catch (\Throwable $x) {}
        }

        // Final fallback keeps old behavior (in-memory)
        if (is_string($file)) {
            $bytes = file_get_contents($file);
        } else {
            $realPath = method_exists($file, 'getRealPath') ? $file->getRealPath() : null;

            if (!is_string($realPath) || $realPath === '') {
                throw new \InvalidArgumentException('Unable to read uploaded file contents.');
            }

            $bytes = file_get_contents($realPath);
        }

        if ($bytes === false || !$disk->put($path, $bytes, $visibility)) {
            throw new \RuntimeException('The uploaded file could not be stored.');
        }
    }
}
