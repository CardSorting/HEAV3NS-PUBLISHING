<?php

namespace App\Services\Media;

use App\Model\Attachment;
use App\Providers\AttachmentServiceProvider;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateFileStorage
{
    public const PREFIX = 'attachments/';
    private const LEGACY_PREFIX = 'file-attachments/private/';

    public function provider(): string
    {
        $provider = (string) getSetting('storage.driver');
        if (!in_array($provider, ['public', 's3', 'wasabi', 'do_spaces', 'minio', 'pushr', 'r2', 'b2'], true)) {
            throw new RuntimeException(__('The configured storage provider does not support private file attachments.'));
        }

        return $provider;
    }

    protected function configuration(string $provider): array
    {
        if ($provider === 'public') {
            return ['driver' => 'local', 'root' => storage_path('app/private'), 'visibility' => 'private', 'throw' => true];
        }
        $config = config('filesystems.disks.'.$provider);
        if (!is_array($config) || ($config['driver'] ?? null) !== 's3' || empty($config['bucket'])) {
            throw new RuntimeException(__('Configure the selected storage provider before uploading file attachments.'));
        }
        unset($config['url']);
        $config['visibility'] = 'private';
        $config['throw'] = true;

        return $config;
    }

    public static function isPrivateKey(string $key): bool
    {
        return str_starts_with($key, self::PREFIX) || str_starts_with($key, self::LEGACY_PREFIX);
    }

    public function disk(string $provider, ?string $key = null): FilesystemAdapter
    {
        $config = $this->configuration($provider);
        if ($provider === 'public' && $key !== null && str_starts_with($key, self::LEGACY_PREFIX)) {
            $config['root'] = storage_path('app/private/file-attachments');
        }

        $disk = Storage::build($config);
        if (!$disk instanceof FilesystemAdapter) {
            throw new RuntimeException(__('The configured storage provider does not support private file attachments.'));
        }

        return $disk;
    }

    public function store(string $source, string $extension): array
    {
        $provider = $this->provider();
        $key = self::PREFIX.Str::uuid().'.'.$extension;
        $disk = $this->disk($provider);
        $stream = fopen($source, 'rb');
        if ($stream === false) {
            throw new RuntimeException(__('Could not read the uploaded file.'));
        }
        try {
            if (!$disk->put($key, $stream, ['visibility' => 'private', 'ContentType' => 'application/octet-stream'])) {
                throw new RuntimeException('Private file upload failed.');
            }
        } catch (\Throwable $exception) {
            $this->cleanup($provider, $key);
            throw $exception;
        } finally {
            fclose($stream);
        }

        return ['filename' => $key, 'driver' => AttachmentServiceProvider::getStorageProviderID($provider),
            'is_file_attachment' => true];
    }

    public function cleanup(string $provider, string $key): void
    {
        try {
            $disk = $this->disk($provider, $key);
            if (!$disk->delete($key) && $disk->exists($key)) {
                throw new RuntimeException('Private file cleanup failed.');
            }
        } catch (\Throwable $exception) {
            app(\Illuminate\Contracts\Bus\Dispatcher::class)->dispatch(new \App\Jobs\DeleteAttachmentFilesJob($provider, [$key], null, null, true));
        }
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        abort_unless(FileAttachmentService::isFile($attachment), 404);
        $disk = $this->disk(AttachmentServiceProvider::getStorageProviderName($attachment->driver), (string) $attachment->filename);
        abort_unless($disk->exists($attachment->filename), 404);

        return $disk->download($attachment->filename, FileAttachmentService::safeName((string) $attachment->original_name), [
            'Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "sandbox; default-src 'none'",
        ]);
    }
}
