<?php

namespace App\Filament\Resources\PublicPages\Pages;

use App\Filament\Resources\PublicPages\PublicPageResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPublicPage extends ViewRecord
{
    protected static string $resource = PublicPageResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make(),
            ...PublicPageResource::getRecordActionsForDisplay($this, $this->getRecord()),
        ];
    }
}
