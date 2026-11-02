<?php

namespace App\Services\Moderation\Contracts;

use App\Services\Moderation\Data\ModerationInput;
use App\Services\Moderation\Data\ModerationPendingResult;
use App\Services\Moderation\Data\ModerationResult;

interface MediaModerationProvider
{
    public function key(): string;

    public function supports(string $mediaType): bool;

    public function estimateOperations(ModerationInput $input, array $models, array $policy): int;

    public function processingMode(ModerationInput $input, array $policy): string;

    public function scan(
        ModerationInput $input,
        array $models,
        array $policy,
        ?string $callbackUrl = null,
    ): ModerationResult|ModerationPendingResult;

    public function poll(string $mediaId, array $policy): ModerationResult|ModerationPendingResult;

    public function normalizeCallback(array $response, array $policy): ModerationResult|ModerationPendingResult;

    public function normalizeStored(array $response, array $policy): ModerationResult;
}
