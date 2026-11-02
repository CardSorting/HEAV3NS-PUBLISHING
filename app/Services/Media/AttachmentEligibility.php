<?php

namespace App\Services\Media;

use App\Model\Attachment;
use App\Model\ReleaseForm;
use App\Model\UserVerify;

class AttachmentEligibility
{
    public function protectedFilenames(): array
    {
        return collect([UserVerify::class, ReleaseForm::class])->flatMap(function ($model) {
            return $model::query()->pluck('files')->flatMap(function ($files) {
                return is_array($files) ? $files : (json_decode((string) $files, true) ?: []);
            });
        })->filter(fn ($file) => is_string($file) && $file !== '')->unique()->values()->all();
    }

    public function isProtected(Attachment $attachment, ?array $filenames = null, bool $includeOperationalPaths = true): bool
    {
        $path = ltrim((string) $attachment->filename, '/');
        return $attachment->sound_id !== null
            || preg_match('~^users/(verifications|release-forms)/~', $path)
            || ($includeOperationalPaths && preg_match('~^(payment-request|sounds)/~', $path))
            || in_array($attachment->filename, $filenames ?? $this->protectedFilenames(), true);
    }
}
