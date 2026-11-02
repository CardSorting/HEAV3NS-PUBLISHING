<?php

namespace App\Filament\Resources\FeaturedUsers\Pages;

use App\Filament\Resources\FeaturedUsers\FeaturedUserResource;
use Filament\Resources\Pages\EditRecord;

class EditFeaturedUser extends EditRecord
{
    protected static string $resource = FeaturedUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...FeaturedUserResource::getRecordActionsForDisplay($this, $this->getRecord()),
        ];
    }
}
