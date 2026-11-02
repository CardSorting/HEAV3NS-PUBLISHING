<?php

namespace App\Filament\Resources\UserLists\Pages;

use App\Filament\Resources\UserLists\UserListResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUserList extends CreateRecord
{
    protected static string $resource = UserListResource::class;
}
