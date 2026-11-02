<?php

namespace App\Filament\Resources\MediaModerationItems\Pages;

use App\Filament\Resources\MediaModerationItems\MediaModerationItemResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMediaModerationItem extends ViewRecord
{
    protected static string $resource = MediaModerationItemResource::class;

    protected function getHeaderActions(): array
    {
        return MediaModerationItemResource::headerActions();
    }
}
