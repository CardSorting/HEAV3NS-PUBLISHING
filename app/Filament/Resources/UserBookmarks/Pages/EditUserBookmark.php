<?php

namespace App\Filament\Resources\UserBookmarks\Pages;

use App\Filament\Resources\UserBookmarks\UserBookmarkResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserBookmark extends EditRecord
{
    protected static string $resource = UserBookmarkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
