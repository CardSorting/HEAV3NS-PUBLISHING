<?php

namespace App\Filament\Resources\UserBookmarks\Pages;

use App\Filament\Resources\UserBookmarks\UserBookmarkResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserBookmarks extends ListRecords
{
    protected static string $resource = UserBookmarkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
