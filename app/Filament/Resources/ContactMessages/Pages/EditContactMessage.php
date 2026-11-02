<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use Filament\Resources\Pages\EditRecord;

class EditContactMessage extends EditRecord
{
    protected static string $resource = ContactMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ContactMessageResource::getReplyViaEmailAction()->record($this->getRecord()),
            ...ContactMessageResource::getRecordActionsForDisplay($this, $this->getRecord()),
        ];
    }
}
