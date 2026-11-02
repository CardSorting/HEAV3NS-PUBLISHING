<?php

namespace App\Services\Moderation\Data;

class ModerationResult
{
    public function __construct(
        public readonly array $scores,
        public readonly float $maxScore,
        public readonly ?string $matchedClass,
        public readonly ?int $matchedAtMs,
        public readonly int $operations,
        public readonly ?string $requestId,
        public readonly ?string $mediaId,
        public readonly array $raw,
    ) {
    }
}
