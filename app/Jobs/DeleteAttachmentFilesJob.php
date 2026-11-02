<?php

namespace App\Jobs;

use App\Services\Media\PrivateFileStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteAttachmentFilesJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 10;

    public int $timeout = 120;

    // Kept only so previously queued payloads deserialize without dynamic properties.
    public ?string $privateLocationFingerprint = null;

    public ?string $storageScope = null;

    // Only scalar storage identity is serialized: the attachment may no longer exist.
    public bool $isFileAttachment = false;

    public function __construct(public string $disk, public array $keys, public ?string $coconutJobId = null, public ?string $coconutRegion = null, bool $isFileAttachment = false)
    {
        $this->isFileAttachment = $isFileAttachment;
        $this->onConnection('attachment_cleanup')->onQueue('attachment-cleanup')->beforeCommit();
    }

    public function backoff(): array
    {
        return [60, 300, 900, 3600, 21600];
    }

    public function handle(): void
    {
        if ($this->coconutJobId && !app(\App\Services\Media\CoconutJobStatusService::class)->isFinished($this->coconutJobId, $this->coconutRegion)) {
            throw new RuntimeException('Waiting for the remote encoder to finish before purging its output files.');
        }
        foreach ($this->keys as $key) {
            // Prefixes also identify private files in older queued payloads.
            $storage = $this->isFileAttachment || PrivateFileStorage::isPrivateKey($key)
                ? app(PrivateFileStorage::class)->disk($this->disk, $key)
                : Storage::disk($this->disk);
            if (!$storage->delete($key) && $storage->exists($key)) {
                throw new RuntimeException('Could not remove attachment storage object: '.$key);
            }
        }
    }
}
