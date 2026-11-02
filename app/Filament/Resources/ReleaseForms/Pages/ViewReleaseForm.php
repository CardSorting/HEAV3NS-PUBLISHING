<?php

namespace App\Filament\Resources\ReleaseForms\Pages;

use App\Filament\Resources\ReleaseForms\ReleaseFormResource;
use Filament\Resources\Pages\ViewRecord;

class ViewReleaseForm extends ViewRecord
{
    protected static string $resource = ReleaseFormResource::class;

    protected function getActions(): array
    {
        return [
            ...ReleaseFormResource::getRecordActionsForDisplay($this, $this->getRecord()),
        ];
    }
}
