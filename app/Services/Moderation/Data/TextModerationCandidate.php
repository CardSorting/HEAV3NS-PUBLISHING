<?php

namespace App\Services\Moderation\Data;

use Carbon\CarbonInterface;

class TextModerationCandidate
{
    public function __construct(
        public readonly string $sourceType,
        public readonly string $sourceId,
        public readonly string $sourceField,
        public readonly ?int $ownerUserId,
        public readonly string $subjectType,
        public readonly ?int $subjectId,
        public readonly string $text,
        public readonly ?CarbonInterface $updatedAt,
    ) {
    }

    public function hash(): string
    {
        return hash('sha256', $this->text);
    }
}
