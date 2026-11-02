<?php

namespace App\Services\Moderation\Data;

class ModerationInput
{
    public function __construct(
        public readonly string $mediaType,
        public readonly string $filename,
        public readonly mixed $stream,
        public readonly ?int $durationSeconds = null,
    ) {
    }
}
