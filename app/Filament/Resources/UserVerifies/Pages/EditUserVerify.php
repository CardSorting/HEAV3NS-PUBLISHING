<?php

namespace App\Filament\Resources\UserVerifies\Pages;

use App\Filament\Resources\UserVerifies\UserVerifyResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;

class EditUserVerify extends EditRecord
{
    protected static string $resource = UserVerifyResource::class;

    protected ?Alignment $headerActionsAlignment = Alignment::End;

    protected function getHeaderActions(): array
    {
        return UserVerifyResource::getReviewHeaderActions();
    }

    protected function afterSave(): void
    {
        // The form may load the new owner's ID relation before saving the ownership change.
        $this->getRecord()->unsetRelation('user');
    }
}
