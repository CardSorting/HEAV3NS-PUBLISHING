<?php

namespace App\Services\Moderation\Data;

use Carbon\CarbonInterface;

class StreamPosterCandidate
{
    public function __construct(
        public readonly int $streamId,
        public readonly int $userId,
        public readonly string $path,
        public readonly string $disk,
        public readonly ?CarbonInterface $updatedAt,
    ) {
    }

    public function sourceId(): string
    {
        return (string) $this->streamId;
    }

    public function filename(): string
    {
        return basename($this->path);
    }

    public function fingerprint(): string
    {
        return hash('sha256', implode('|', [
            'stream_poster',
            $this->sourceId(),
            $this->disk,
            $this->path,
        ]));
    }
}
