<?php

namespace App\Services\Moderation\Data;

class ModerationPendingResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?float $progress,
        public readonly int $operations,
        public readonly ?string $requestId,
        public readonly ?string $mediaId,
        public readonly array $raw,
    ) {
    }
}
