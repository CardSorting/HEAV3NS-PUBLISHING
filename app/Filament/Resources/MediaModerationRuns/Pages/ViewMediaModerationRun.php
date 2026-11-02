<?php

namespace App\Filament\Resources\MediaModerationRuns\Pages;

use App\Filament\Resources\MediaModerationRuns\MediaModerationRunResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMediaModerationRun extends ViewRecord
{
    protected static string $resource = MediaModerationRunResource::class;

    protected function getHeaderActions(): array
    {
        return MediaModerationRunResource::headerActions();
    }
}
