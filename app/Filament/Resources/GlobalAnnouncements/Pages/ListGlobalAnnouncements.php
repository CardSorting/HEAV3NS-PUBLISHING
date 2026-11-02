<?php

namespace App\Filament\Resources\GlobalAnnouncements\Pages;

use App\Filament\Resources\GlobalAnnouncements\GlobalAnnouncementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGlobalAnnouncements extends ListRecords
{
    protected static string $resource = GlobalAnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
