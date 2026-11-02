<?php

namespace App\Services\Media;

use App\Model\Attachment;
use App\Providers\AttachmentServiceProvider;
use App\Providers\MediaEncoderServiceProvider;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class SharedAttachmentPreviewService
{
    public function ensure(Attachment $attachment): void
    {
        if (FileAttachmentService::isFile($attachment)) return;
        if (!getSetting('media.use_blurred_previews_for_locked_posts') || $attachment->has_blurred_preview) {
            return;
        }
        $type = AttachmentServiceProvider::getAttachmentType($attachment->type);
        if (!in_array($type, ['image', 'video'], true)) {
            return;
        }
        try {
            $diskName = AttachmentServiceProvider::getStorageProviderName($attachment->driver);
            $disk = Storage::disk($diskName);
            $directory = dirname($attachment->filename);
            $path = $directory.'/blurred/shared-'.$attachment->id.'.jpg';
            if (!$disk->exists($path)) {
                if ($type === 'video') {
                    if (!MediaEncoderServiceProvider::generateVideoBlurredPreview($attachment->filename, $directory, $attachment->id, $diskName, $path)) {
                        throw new \RuntimeException('Video preview generation failed.');
                    }
                } else {
                    $image = Image::make($disk->get($attachment->filename))->orientate();
                    $image->resize(600, null, fn ($constraint) => $constraint->aspectRatio());
                    $blurred = Image::make(multiStepBlur($image->getCore()))->encode('jpg', 85);
                    if (!$disk->put($path, (string) $blurred, 'public')) {
                        throw new \RuntimeException('Image preview could not be stored.');
                    }
                }
            }
            if (!$disk->exists($path)) {
                throw new \RuntimeException('Preview is missing.');
            }
        } catch (\Throwable $exception) {
            report($exception);
            // Locked views use a generic placeholder when no blurred preview exists.
            return;
        }

        $attachment->has_blurred_preview = true;
        $attachment->blurred_filename = $path;
        $attachment->timestamps = false;
        $attachment->save();
    }
}
