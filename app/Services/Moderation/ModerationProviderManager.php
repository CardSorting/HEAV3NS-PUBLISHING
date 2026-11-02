<?php

namespace App\Services\Moderation;

use App\Services\Moderation\Contracts\MediaModerationProvider;
use App\Services\Moderation\Contracts\TextModerationProvider;
use App\Services\Moderation\Providers\SightengineProvider;
use App\Services\Moderation\Providers\SightengineTextProvider;
use InvalidArgumentException;

class ModerationProviderManager
{
    public function __construct(
        protected SightengineProvider $sightengine,
        protected SightengineTextProvider $sightengineText,
    ) {
    }

    public function provider(string $provider): MediaModerationProvider
    {
        return match ($provider) {
            'sightengine' => $this->sightengine,
            default => throw new InvalidArgumentException("Unsupported media moderation provider [{$provider}]."),
        };
    }

    public function textProvider(string $provider): TextModerationProvider
    {
        return match ($provider) {
            'sightengine' => $this->sightengineText,
            default => throw new InvalidArgumentException("Unsupported text moderation provider [{$provider}]."),
        };
    }
}
