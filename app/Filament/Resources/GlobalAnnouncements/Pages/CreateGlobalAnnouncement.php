<?php

namespace App\Filament\Resources\GlobalAnnouncements\Pages;

use App\Filament\Resources\GlobalAnnouncements\GlobalAnnouncementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGlobalAnnouncement extends CreateRecord
{
    protected static string $resource = GlobalAnnouncementResource::class;
}
