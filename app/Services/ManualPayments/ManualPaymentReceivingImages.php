<?php

namespace App\Services\ManualPayments;

use App\Providers\AttachmentServiceProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\ImageManagerStatic as Image;

/** Site-owned receiving instructions, separate from customer payment evidence. */
class ManualPaymentReceivingImages
{
    public const MAX_SIZE_KB = 2048;
    public const MAX_DIMENSION = 3000;
    private const PURPOSE = 'manual-payment-receiving-image-v1';
    private const DIRECTORY = 'assets/manual-payment-methods';

    public static function maxSizeKb(): int
    {
        $siteLimit = (float) getSetting('media.max_file_upload_size', 2);

        return min(self::MAX_SIZE_KB, $siteLimit > 0 ? max(1, (int) ($siteLimit * 1024)) : self::MAX_SIZE_KB);
    }

    public function upload(UploadedFile $file): string
    {
        Gate::authorize('View:ManagePaymentsSettings');
        $extension = strtolower($file->getClientOriginalExtension());
        $image = @getimagesize($file->getPathname());
        $mime = $file->getMimeType();
        $formats = ['png' => ['image/png', IMAGETYPE_PNG], 'jpg' => ['image/jpeg', IMAGETYPE_JPEG],
            'jpeg' => ['image/jpeg', IMAGETYPE_JPEG], 'webp' => ['image/webp', IMAGETYPE_WEBP]];
        if (!$file->isValid() || !$file->getSize() || $file->getSize() > self::maxSizeKb() * 1024
            || !isset($formats[$extension]) || !$image || $mime !== $formats[$extension][0] || $image[2] !== $formats[$extension][1]
            || $image[0] > self::MAX_DIMENSION || $image[1] > self::MAX_DIMENSION) {
            throw ValidationException::withMessages(['receiving_details' => __('Choose a PNG, JPEG or WebP image within the upload size limit and 3000 × 3000 pixels.')]);
        }

        try {
            // Re-encode without resizing/cropping: strip metadata and preserve QR readability.
            $decoded = Image::make($file->getPathname());
            try {
                $bytes = (string) $decoded->encode('png');
            } finally {
                $decoded->destroy();
            }
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages(['receiving_details' => __('The receiving image could not be read. Choose another image.')]);
        }
        if (strlen($bytes) > self::MAX_SIZE_KB * 1024) {
            throw ValidationException::withMessages(['receiving_details' => __('The normalized image exceeds 2 MB. Choose a smaller image.')]);
        }

        $disk = Storage::getDefaultDriver();
        $path = self::DIRECTORY.'/'.Str::uuid().'.png';
        if (!Storage::disk($disk)->put($path, $bytes, ['visibility' => AttachmentServiceProvider::getAdminFileUploadVisibility()])) {
            throw new \RuntimeException('Unable to store the receiving image.');
        }

        return Crypt::encryptString(json_encode([
            'purpose' => self::PURPOSE, 'disk' => $disk, 'path' => $path, 'mime' => 'image/png', 'size' => strlen($bytes),
        ], JSON_THROW_ON_ERROR));
    }

    /** Only this upload service can issue a reference to a stored receiving image. */
    public static function metadata(?string $reference): ?array
    {
        if (!$reference || strlen($reference) > 2000) return null;
        try {
            $data = json_decode(Crypt::decryptString($reference), true, 16, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            return null;
        }
        if (!is_array($data) || ($data['purpose'] ?? null) !== self::PURPOSE || ($data['mime'] ?? null) !== 'image/png'
            || !is_string($data['disk'] ?? null) || !preg_match('/^[a-z0-9_-]{1,64}$/D', $data['disk'])
            || !is_array(config('filesystems.disks.'.$data['disk']))
            || !is_string($data['path'] ?? null)
            || !preg_match('~^'.self::DIRECTORY.'/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\.png$~D', $data['path'])
            || !is_int($data['size'] ?? null) || $data['size'] < 1 || $data['size'] > self::MAX_SIZE_KB * 1024) {
            return null;
        }

        return $data;
    }

    public static function url(string $reference, ?int $methodId = null, ?int $paymentRequestId = null): string
    {
        return route('manual-payment-receiving-images.show', array_filter([
            'image' => $reference, 'method' => $methodId, 'payment_request' => $paymentRequestId,
        ], fn ($value) => $value !== null));
    }

    public static function contains(array $details, string $reference): bool
    {
        foreach ($details as $detail) {
            if (is_array($detail) && ($detail['type'] ?? 'text') === 'image' && ($detail['value'] ?? null) === $reference) return true;
        }

        return false;
    }
}
