<?php

namespace App\Services\Ai\Data;

class AiImageRequest
{
    public function __construct(
        public string $prompt,
        public ?string $model = null,
        public string $size = '1024x1024',
        public array $meta = [],
    ) {
    }
}
