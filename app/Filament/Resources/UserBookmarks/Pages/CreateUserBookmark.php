<?php

namespace App\Filament\Resources\UserBookmarks\Pages;

use App\Filament\Resources\UserBookmarks\UserBookmarkResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUserBookmark extends CreateRecord
{
    protected static string $resource = UserBookmarkResource::class;
}
