<?php

namespace App\Filament\Resources\ReleaseForms\Pages;

use App\Filament\Resources\ReleaseForms\ReleaseFormResource;
use Filament\Resources\Pages\EditRecord;

class EditReleaseForm extends EditRecord
{
    protected static string $resource = ReleaseFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...ReleaseFormResource::getRecordActionsForDisplay($this, $this->getRecord(), false),
        ];
    }
}
