<?php

namespace App\Filament\Resources\Reels\Pages;

use App\Filament\Resources\Reels\ReelResource;
use Filament\Resources\Pages\ViewRecord;

class ViewReel extends ViewRecord
{
    protected static string $resource = ReelResource::class;

    protected function getActions(): array
    {
        return [
            ...ReelResource::getRecordActionsForDisplay($this, $this->getRecord()),
        ];
    }
}
