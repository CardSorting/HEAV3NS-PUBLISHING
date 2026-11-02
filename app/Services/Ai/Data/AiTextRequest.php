<?php

namespace App\Services\Ai\Data;

class AiTextRequest
{
    public function __construct(
        public string $prompt,
        public ?string $model = null,
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public array $meta = [],
    ) {
    }
}
