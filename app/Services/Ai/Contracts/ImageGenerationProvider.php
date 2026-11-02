<?php

namespace App\Services\Ai\Contracts;

use App\Model\User;
use App\Services\Ai\Data\AiImageRequest;
use App\Services\Ai\Data\AiImageResponse;

interface ImageGenerationProvider
{
    public function generateImage(AiImageRequest $request, ?User $user = null): AiImageResponse;

    public function listImageModels(): array;
}
