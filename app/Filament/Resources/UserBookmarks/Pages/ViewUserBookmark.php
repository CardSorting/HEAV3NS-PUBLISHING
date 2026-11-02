<?php

namespace App\Filament\Resources\UserBookmarks\Pages;

use App\Filament\Resources\UserBookmarks\UserBookmarkResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUserBookmark extends ViewRecord
{
    protected static string $resource = UserBookmarkResource::class;

    protected function getActions(): array
    {
        return [EditAction::make()];
    }
}
