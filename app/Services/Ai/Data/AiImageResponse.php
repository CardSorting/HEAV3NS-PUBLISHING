<?php

namespace App\Services\Ai\Data;

class AiImageResponse
{
    public function __construct(
        public string $base64,
        public array $raw = [],
    ) {
    }
}
