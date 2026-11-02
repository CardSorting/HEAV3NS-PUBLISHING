<?php

namespace App\Filament\Resources\UserDeletionRequests\Pages;

use App\Filament\Resources\UserDeletionRequests\UserDeletionRequestResource;
use Filament\Resources\Pages\EditRecord;

class EditUserDeletionRequest extends EditRecord
{
    protected static string $resource = UserDeletionRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...UserDeletionRequestResource::getRecordActionsForDisplay($this, $this->getRecord()),
        ];
    }
}
