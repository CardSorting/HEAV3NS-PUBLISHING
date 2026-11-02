<?php

namespace App\Filament\Resources\UserReports\Pages;

use App\Filament\Resources\UserReports\UserReportResource;
use Filament\Resources\Pages\EditRecord;

class EditUserReport extends EditRecord
{
    protected static string $resource = UserReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...UserReportResource::getRecordActionsForDisplay($this, $this->getRecord()),
        ];
    }
}
