<?php

namespace App\Filament\Resources\UserDeletionRequests\Pages;

use App\Filament\Resources\UserDeletionRequests\UserDeletionRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUserDeletionRequest extends ViewRecord
{
    protected static string $resource = UserDeletionRequestResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make(),
            ...UserDeletionRequestResource::getRecordActionsForDisplay($this, $this->getRecord()),
        ];
    }
}
