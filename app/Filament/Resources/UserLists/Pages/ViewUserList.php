<?php

namespace App\Filament\Resources\UserLists\Pages;

use App\Filament\Resources\UserLists\UserListResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUserList extends ViewRecord
{
    protected static string $resource = UserListResource::class;

    protected function getActions(): array
    {
        return [EditAction::make()];
    }
}
