<?php

namespace App\Filament\Resources\FeaturedUsers\Pages;

use App\Filament\Resources\FeaturedUsers\FeaturedUserResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFeaturedUser extends ViewRecord
{
    protected static string $resource = FeaturedUserResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make(),
            ...FeaturedUserResource::getRecordActionsForDisplay($this, $this->getRecord()),
        ];
    }
}
