<?php

namespace App\Services\Moderation\Data;

use Carbon\CarbonImmutable;

class QuotaReservation
{
    public function __construct(
        public readonly string $runId,
        public readonly int $itemId,
        public readonly string $provider,
        public readonly CarbonImmutable $usageDate,
        public readonly int $operations,
        public readonly string $itemType = 'media',
    ) {
    }
}
