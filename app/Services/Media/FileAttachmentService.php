<?php

namespace App\Services\Media;

use App\Model\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class FileAttachmentService
{
    public static function supportedFormats(): array
    {
        return ['pdf' => 'PDF', 'docx' => 'Word (DOCX)', 'xlsx' => 'Excel (XLSX)', 'pptx' => 'PowerPoint (PPTX)',
            'txt' => 'Text (TXT)', 'csv' => 'CSV', 'md' => 'Markdown (MD)', 'zip' => 'ZIP'];
    }

    public static function isFile(Attachment $attachment): bool
    {
        return (bool) $attachment->getAttribute('is_file_attachment');
    }

    public function enabled(): bool
    {
        return (bool) getSetting('media.file_attachments_enabled');
    }

    public function allowedFormats(): array
    {
        return array_values(array_intersect((array) getSetting('media.file_attachment_formats'), array_keys(self::supportedFormats())));
    }

    public function canSelect(Attachment $attachment): bool
    {
        return $this->enabled() && in_array($attachment->type, $this->allowedFormats(), true);
    }

    public function assertSelectable(Attachment $attachment): void
    {
        if (!$this->canSelect($attachment)) {
            $this->invalid(__('This file format is currently disabled. Remove the file or contact the site administrator.'));
        }
    }

    public static function supportsContext(?string $context): bool
    {
        return in_array($context, ['post', 'message', 'message-template', 'vault'], true);
    }

    public static function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = trim($name, " .\t\n\r\0\x0B");

        $name = $name ?: 'file';
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $suffix = $extension !== '' && strlen($extension) <= 16 ? '.'.$extension : '';
        $stem = $suffix !== '' ? substr($name, 0, -strlen($suffix)) : $name;

        return Str::limit($stem, 180 - strlen($suffix), '').$suffix;
    }

    public function validate(UploadedFile $file, ?string $context): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (!self::supportsContext($context) || !$this->enabled() || !in_array($extension, $this->allowedFormats(), true)) {
            $this->invalid(__('This file format is not allowed here.'));
        }
        if (!$file->isValid() || !$file->getSize() || $file->getSize() > (int) getSetting('media.max_file_upload_size') * 1024 * 1024) {
            $this->invalid(__('The uploaded file is empty or exceeds the upload limit.'));
        }
        $mime = (string) $file->getMimeType();
        $path = $file->getPathname();
        if ($extension === 'pdf') {
            $stream = fopen($path, 'rb');
            $magic = $stream ? fread($stream, 5) : false;
            if ($stream) fclose($stream);
            if ($mime !== 'application/pdf' || $magic !== '%PDF-') $this->invalid();
        } elseif (in_array($extension, ['zip', 'docx', 'xlsx', 'pptx'], true)) {
            if (!class_exists(ZipArchive::class)) {
                $this->invalid(__('ZIP and Office uploads require the PHP ZIP extension. Contact the site administrator.'));
            }
            $expectedMime = ['docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'];
            if (!in_array($mime, ['application/zip', 'application/x-zip', 'application/x-zip-compressed', $expectedMime[$extension] ?? 'application/zip'], true)) $this->invalid();
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::CHECKCONS) !== true) $this->invalid();
            try {
                if ($extension !== 'zip') {
                    $entry = ['docx' => 'word/document.xml', 'xlsx' => 'xl/workbook.xml', 'pptx' => 'ppt/presentation.xml'][$extension];
                    if ($zip->locateName('[Content_Types].xml') === false || $zip->locateName($entry) === false) $this->invalid();
                    $typesStat = $zip->statName('[Content_Types].xml');
                    $mainStat = $zip->statName($entry);
                    if (!$typesStat || $typesStat['size'] > 262144 || !$mainStat || $mainStat['size'] === 0
                        || ($mainStat['encryption_method'] ?? 0) !== 0) $this->invalid();
                    $xml = $zip->getFromName('[Content_Types].xml', 262144);
                    if (!is_string($xml) || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) $this->invalid();
                    $previousErrors = libxml_use_internal_errors(true);
                    try {
                        $document = new \DOMDocument;
                        if (!$document->loadXML($xml, LIBXML_NONET) || $document->documentElement?->namespaceURI !== 'http://schemas.openxmlformats.org/package/2006/content-types') $this->invalid();
                        $validMain = false;
                        foreach ($document->getElementsByTagNameNS('http://schemas.openxmlformats.org/package/2006/content-types', 'Override') as $override) {
                            if ($override->getAttribute('PartName') === '/'.$entry
                                && $override->getAttribute('ContentType') === $expectedMime[$extension].'.main+xml') {
                                $validMain = true;
                            }
                        }
                        if (!$validMain) $this->invalid();
                    } finally {
                        libxml_clear_errors();
                        libxml_use_internal_errors($previousErrors);
                    }
                    for ($index = 0; $index < $zip->numFiles; $index++) {
                        if (preg_match('~(?:vbaproject|activex|embeddings)/?|\.(?:exe|dll|js|vbs)$~i', $zip->getNameIndex($index))) $this->invalid();
                    }
                }
            } finally {
                $zip->close();
            }
        } else {
            if (!in_array($mime, ['text/plain', 'text/csv', 'text/markdown', 'text/x-markdown', 'application/csv'], true)) $this->invalid();
            $stream = fopen($path, 'rb');
            if (!$stream) $this->invalid();
            try {
                while (!feof($stream)) {
                    $chunk = fread($stream, 65536);
                    if ($chunk === false || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $chunk)) $this->invalid();
                }
            } finally {
                fclose($stream);
            }
        }
    }

    public function create(UploadedFile $file, string $context, int $ownerId): Attachment
    {
        $this->validate($file, $context);
        $storage = app(PrivateFileStorage::class);
        $facts = $storage->store($file->getPathname(), strtolower($file->getClientOriginalExtension()));
        try {
            $attachment = new Attachment;
            $attachment->forceFill(array_merge($facts, ['id' => (string) Str::uuid(), 'user_id' => $ownerId,
                'type' => strtolower($file->getClientOriginalExtension()), 'original_name' => self::safeName($file->getClientOriginalName()),
                'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'vaulted_at' => now(),
                'has_thumbnail' => false, 'has_blurred_preview' => false]));
            $attachment->save();

            return $attachment;
        } catch (\Throwable $exception) {
            $storage->cleanup(\App\Providers\AttachmentServiceProvider::getStorageProviderName($facts['driver']), $facts['filename']);
            throw $exception;
        }
    }

    public function payload(Attachment $attachment, ?string $downloadUrl = null): array
    {
        if ($downloadUrl === null && (int) Auth::id() === (int) $attachment->user_id && Auth::id() !== null) {
            $downloadUrl = route('file-attachments.owner', ['attachment' => $attachment->id]);
        }
        $size = (int) $attachment->size;

        return ['id' => (string) $attachment->id, 'attachmentID' => (string) $attachment->id, 'type' => 'file',
            'attachmentType' => 'file', 'extension' => $attachment->type, 'original_name' => self::safeName((string) $attachment->original_name),
            'mime_type' => $attachment->mime_type, 'size' => $size,
            'size_label' => $size >= 1048576 ? round($size / 1048576, 1).' MB' : max(1, (int) ceil($size / 1024)).' KB',
            'download_url' => $downloadUrl, 'path' => null, 'thumbnail' => null, 'has_thumbnail' => false,
            'blurred' => null, 'can_select' => $this->canSelect($attachment), 'transcoding_status' => 'completed',
            'can_retry_transcoding' => false, 'transcoding_message' => null, 'transcoding_progress' => null,
            'transcoding_driver' => null, 'length' => null];
    }

    protected function invalid(?string $message = null): never
    {
        throw ValidationException::withMessages(['file' => $message ?? __('The file contents do not match a supported format.')]);
    }
}
