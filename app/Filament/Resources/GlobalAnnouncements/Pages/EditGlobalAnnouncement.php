<?php

namespace App\Filament\Resources\GlobalAnnouncements\Pages;

use App\Filament\Resources\GlobalAnnouncements\GlobalAnnouncementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGlobalAnnouncement extends EditRecord
{
    protected static string $resource = GlobalAnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
