<?php

namespace App\Filament\Resources\UserVerifies\Pages;

use App\Filament\Resources\UserVerifies\UserVerifyResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Alignment;

class ViewUserVerify extends ViewRecord
{
    protected static string $resource = UserVerifyResource::class;

    protected ?Alignment $headerActionsAlignment = Alignment::End;

    protected function getHeaderActions(): array
    {
        return UserVerifyResource::getReviewHeaderActions(includeEdit: true);
    }
}
