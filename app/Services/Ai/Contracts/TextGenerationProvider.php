<?php

namespace App\Services\Ai\Contracts;

use App\Model\User;
use App\Services\Ai\Data\AiTextRequest;
use App\Services\Ai\Data\AiTextResponse;

interface TextGenerationProvider
{
    public function generateText(AiTextRequest $request, ?User $user = null): AiTextResponse;

    public function listTextModels(): array;
}
