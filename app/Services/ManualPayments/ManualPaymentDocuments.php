<?php

namespace App\Services\ManualPayments;

use App\Model\ManualPaymentDocument;
use App\Model\ManualPaymentMethod;
use App\Model\PaymentRequest;
use App\Model\User;
use App\Model\Withdrawal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class ManualPaymentDocuments
{
    public const DISK = 'manual-payment-documents';

    public static function maxSizeKb(array $field): int
    {
        return min(
            (int) ($field['max_size_kb'] ?? ManualPaymentFields::MAX_FILE_SIZE_KB),
            ManualPaymentFields::MAX_FILE_SIZE_KB,
            max(1, (int) (getSetting('media.max_file_upload_size') * 1024))
        );
    }

    public function upload(int $userId, int $methodId, string $fieldKey, UploadedFile $file): ManualPaymentDocument
    {
        $path = null;
        try {
            return DB::transaction(function () use ($userId, $methodId, $fieldKey, $file, &$path) {
                // Lock the owner before the method, matching account saves and request creation.
                User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
                $method = ManualPaymentMethod::query()->whereKey($methodId)->where('enabled', true)->lockForUpdate()->first();
                if (!$method || ($method->direction === ManualPaymentMethod::DEPOSIT && !getSetting('payments.allow_manual_payments'))) {
                    throw ValidationException::withMessages(['manual_method_id' => __('This payment method is unavailable.')]);
                }
                $field = collect($method->request_fields)->first(fn ($field) => $field['key'] === $fieldKey && $field['type'] === 'file');
                if (!$field) {
                    throw ValidationException::withMessages(['field_key' => __('This upload field is unavailable.')]);
                }
                $pending = ManualPaymentDocument::query()->where('user_id', $userId)->whereNull('submitted_at');
                if ((clone $pending)->count() >= 150 || (clone $pending)->sum('size') + $file->getSize() > 100 * 1024 * 1024) {
                    throw ValidationException::withMessages(['file' => __('Too many pending uploads. Remove unused files or try again later.')]);
                }
                $extension = strtolower($file->getClientOriginalExtension());
                $mime = $this->validateFile($file, $field, $extension);
                $path = now()->format('Y/m/d').'/'.Str::uuid().'.'.$extension;
                if (!Storage::disk(self::DISK)->putFileAs(dirname($path), $file, basename($path), ['visibility' => 'private'])) {
                    throw new \RuntimeException('Unable to store the request document.');
                }

                return ManualPaymentDocument::create([
                    'user_id' => $userId, 'manual_method_id' => $methodId, 'field_key' => $fieldKey,
                    'field_label' => $field['label'], 'original_name' => self::safeName($file->getClientOriginalName()),
                    'path' => $path, 'extension' => $extension, 'mime_type' => $mime, 'size' => $file->getSize(),
                ]);
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk(self::DISK)->delete($path);
            }
            throw $exception;
        }
    }

    private function validateFile(UploadedFile $file, array $field, string $extension): string
    {
        if (!$file->isValid() || !$file->getSize() || $file->getSize() > self::maxSizeKb($field) * 1024
            || !in_array($extension, $field['allowed_extensions'], true)) {
            throw ValidationException::withMessages(['file' => __('Choose an allowed file within the upload size limit.')]);
        }
        $mime = $file->getMimeType();
        $valid = false;
        if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            $image = @getimagesize($file->getPathname());
            $expected = $extension === 'png' ? IMAGETYPE_PNG : IMAGETYPE_JPEG;
            $valid = $image && $image[2] === $expected && $image[0] * $image[1] <= 40000000
                && $mime === ($expected === IMAGETYPE_PNG ? 'image/png' : 'image/jpeg');
        } elseif ($extension === 'pdf') {
            $stream = fopen($file->getPathname(), 'rb');
            $header = fread($stream, 8);
            fclose($stream);
            $valid = $mime === 'application/pdf' && preg_match('/^%PDF-[12]\.[0-9]/', $header);
        } elseif ($extension === 'xlsx') {
            $valid = in_array($mime, ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], true)
                && $this->isSpreadsheet($file->getPathname());
            $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }
        if (!$valid) {
            throw ValidationException::withMessages(['file' => __('The file content does not match an allowed format.')]);
        }

        return $mime;
    }

    private function isSpreadsheet(string $path): bool
    {
        if (!class_exists(ZipArchive::class)) {
            return false;
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return false;
        }
        try {
            if ($zip->numFiles > 1000 || $zip->locateName('xl/workbook.xml') === false || $zip->locateName('_rels/.rels') === false) {
                return false;
            }
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $total += $entry['size'];
                $unknownBinary = str_ends_with(strtolower($entry['name']), '.bin')
                    && !preg_match('~^xl/printerSettings/printerSettings[0-9]+\.bin$~i', $entry['name']);
                if ($total > 50 * 1024 * 1024 || ($entry['encryption_method'] ?? 0) !== 0 || $unknownBinary
                    || preg_match('~(^/|\\\\|(^|/)\.\.(/|$)|vbaproject|macrosheets|activex|(^|/)embeddings/)~i', $entry['name'])) {
                    return false;
                }
            }
            $typesInfo = $zip->statName('[Content_Types].xml');
            if (!$typesInfo || $typesInfo['size'] > 1024 * 1024) {
                return false;
            }
            $types = $zip->getFromName('[Content_Types].xml');
            if ($types === false || strlen($types) > 1024 * 1024 || stripos($types, '<!DOCTYPE') !== false) {
                return false;
            }
            $xml = new \DOMDocument;
            if (!@$xml->loadXML($types, LIBXML_NONET)) {
                return false;
            }
            $workbook = false;
            foreach ($xml->getElementsByTagName('*') as $override) {
                $type = $override->getAttribute('ContentType');
                if (preg_match('/macro|vba|activex|oleobject/i', $type)) {
                    return false;
                }
                if ($override->getAttribute('PartName') === '/xl/workbook.xml'
                    && $type === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml') {
                    $workbook = true;
                }
            }

            return $workbook;
        } finally {
            $zip->close();
        }
    }

    public static function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';

        return mb_substr($name ?: 'document', 0, 180);
    }

    public function validateSubmission(ManualPaymentMethod $method, array $values, int $userId, bool $lock = false): array
    {
        $values = ManualPaymentFields::validate($method->request_fields ?? [], $values, 'submitted_fields', true);
        $seen = [];
        foreach ($method->request_fields ?? [] as $field) {
            if ($field['type'] !== 'file') {
                continue;
            }
            foreach ($values[$field['key']] as $id) {
                $document = ManualPaymentDocument::query()->when($lock, fn ($query) => $query->lockForUpdate())->find($id);
                if (!$document || isset($seen[$id]) || (int) $document->user_id !== $userId
                    || (int) $document->manual_method_id !== (int) $method->id || $document->field_key !== $field['key']
                    || $document->submitted_at || $document->payment_request_id || $document->withdrawal_id
                    || $document->created_at->lte(now()->subDay())
                    || !in_array($document->extension, $field['allowed_extensions'], true)
                    || $document->size > self::maxSizeKb($field) * 1024
                    || !Storage::disk(self::DISK)->exists($document->path)) {
                    throw ValidationException::withMessages(['submitted_fields.'.$field['key'] => __('This upload is unavailable. Please upload the file again.')]);
                }
                $seen[$id] = true;
            }
        }

        return $values;
    }

    /** Count only configured file fields after validateSubmission has checked every document. */
    public static function countSubmittedFiles(ManualPaymentMethod $method, array $values): int
    {
        $ids = [];
        foreach ($method->request_fields ?? [] as $field) {
            if ($field['type'] === 'file') {
                $ids = array_merge($ids, $values[$field['key']] ?? []);
            }
        }

        return count(array_unique($ids));
    }

    /** Must run inside the transaction creating the request. Revalidate under row locks. */
    public function bind(ManualPaymentMethod $method, array $values, int $userId, PaymentRequest|Withdrawal $request): void
    {
        if (!DB::transactionLevel()) {
            throw new \LogicException('Request documents must be bound inside the request transaction.');
        }
        if ((int) $request->user_id !== $userId || $method->direction !== ($request instanceof PaymentRequest ? ManualPaymentMethod::DEPOSIT : ManualPaymentMethod::WITHDRAWAL)) {
            throw ValidationException::withMessages(['submitted_fields' => __('Invalid request document destination.')]);
        }
        $values = $this->validateSubmission($method, $values, $userId, true);
        foreach ($method->request_fields ?? [] as $field) {
            if ($field['type'] === 'file' && $values[$field['key']]) {
                ManualPaymentDocument::query()->whereIn('id', $values[$field['key']])->update([
                    $request instanceof PaymentRequest ? 'payment_request_id' : 'withdrawal_id' => $request->getKey(),
                    'field_label' => $field['label'], 'submitted_at' => now(),
                ]);
            }
        }
    }

    public function canDownload(User $user, ManualPaymentDocument $document): bool
    {
        $parent = $document->paymentRequest ?? $document->withdrawal;
        if ($document->submitted_at && !$parent) {
            return false;
        }
        if ((int) $document->user_id === (int) $user->id) {
            return $document->submitted_at || $document->created_at->gt(now()->subDay());
        }

        return $parent && (Gate::forUser($user)->allows('view', $parent) || Gate::forUser($user)->allows('update', $parent));
    }

    public function removePending(int $id, int $userId): void
    {
        DB::transaction(function () use ($id, $userId) {
            $document = ManualPaymentDocument::query()->whereKey($id)->where('user_id', $userId)->lockForUpdate()->firstOrFail();
            abort_if($document->submitted_at || $document->payment_request_id || $document->withdrawal_id, 403);
            $this->deleteFileAndRecord($document);
        }, 3);
    }

    public function prune(): int
    {
        $count = 0;
        ManualPaymentDocument::query()->where(function ($query) {
            $query->whereNull('user_id')->orWhereNull('manual_method_id')
                ->orWhere(fn ($query) => $query->whereNull('submitted_at')->where('created_at', '<=', now()->subDay()))
                ->orWhere(fn ($query) => $query->whereNotNull('submitted_at')->whereNull('payment_request_id')->whereNull('withdrawal_id'));
        })->select('id')->chunkById(100, function ($documents) use (&$count) {
            foreach ($documents as $item) {
                $removed = DB::transaction(function () use ($item) {
                    $document = ManualPaymentDocument::query()->lockForUpdate()->find($item->id);
                    if (!$document) {
                        return false;
                    }
                    // Submission and cleanup can race. Never delete live request evidence.
                    if ($document->user_id && $document->manual_method_id &&
                        ($document->payment_request_id || $document->withdrawal_id || (!$document->submitted_at && $document->created_at->gt(now()->subDay())))) {
                        return false;
                    }
                    $this->deleteFileAndRecord($document);
                    return true;
                }, 3);
                $count += (int) $removed;
            }
        });

        return $count;
    }

    private function deleteFileAndRecord(ManualPaymentDocument $document): void
    {
        Storage::disk(self::DISK)->delete($document->path);
        $document->delete();
    }
}
