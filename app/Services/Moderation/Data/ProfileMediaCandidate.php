<?php

namespace App\Services\Moderation\Data;

use Carbon\CarbonInterface;

class ProfileMediaCandidate
{
    public function __construct(
        public readonly int $userId,
        public readonly string $sourceType,
        public readonly string $path,
        public readonly string $disk,
        public readonly ?CarbonInterface $updatedAt,
    ) {
    }

    public function sourceId(): string
    {
        return (string) $this->userId;
    }

    public function filename(): string
    {
        return basename($this->path);
    }

    public function fingerprint(): string
    {
        return hash('sha256', implode('|', [
            $this->sourceType,
            $this->sourceId(),
            $this->disk,
            $this->path,
        ]));
    }
}
