<?php

namespace App\Filament\Resources\PublicPages\Pages;

use App\Filament\Resources\PublicPages\PublicPageResource;
use Filament\Resources\Pages\EditRecord;

class EditPublicPage extends EditRecord
{
    protected static string $resource = PublicPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...PublicPageResource::getRecordActionsForDisplay($this, $this->getRecord()),
        ];
    }
}
