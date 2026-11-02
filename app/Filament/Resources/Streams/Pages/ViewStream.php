<?php

namespace App\Filament\Resources\Streams\Pages;

use App\Filament\Resources\Streams\StreamResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStream extends ViewRecord
{
    protected static string $resource = StreamResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make(),
            ...StreamResource::getRecordActionsForDisplay($this, $this->getRecord()),
        ];
    }
}
