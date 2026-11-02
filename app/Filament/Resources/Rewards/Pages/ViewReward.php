<?php

namespace App\Filament\Resources\Rewards\Pages;

use App\Filament\Resources\Rewards\RewardResource;
use App\Model\ReferralCodeUsage;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewReward extends ViewRecord
{
    protected static string $resource = RewardResource::class;

    public function getTitle(): string | Htmlable
    {
        /** @var ReferralCodeUsage $record */
        $record = $this->getRecord();

        return (string) $record->id;
    }

    protected function getActions(): array
    {
        return [];
    }
}
