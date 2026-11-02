<?php

namespace App\Services\Moderation\Contracts;

use App\Services\Moderation\Data\ModerationResult;

interface TextModerationProvider
{
    public function key(): string;

    public function estimateOperations(string $text, array $models, array $policy): int;

    public function scan(string $text, array $models, array $policy): ModerationResult;

    public function normalizeStored(array $response, array $policy): ModerationResult;
}
