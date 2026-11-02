<?php

namespace App\Filament\Resources\GlobalAnnouncements\Pages;

use App\Filament\Resources\GlobalAnnouncements\GlobalAnnouncementResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewGlobalAnnouncement extends ViewRecord
{
    protected static string $resource = GlobalAnnouncementResource::class;

    protected function getActions(): array
    {
        return [EditAction::make()];
    }
}
