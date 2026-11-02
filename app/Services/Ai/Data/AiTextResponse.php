<?php

namespace App\Services\Ai\Data;

class AiTextResponse
{
    public function __construct(
        public string $text,
        public array $raw = [],
    ) {
    }
}
