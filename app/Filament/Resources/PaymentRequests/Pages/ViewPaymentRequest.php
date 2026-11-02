<?php

namespace App\Filament\Resources\PaymentRequests\Pages;

use App\Filament\Resources\PaymentRequests\PaymentRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPaymentRequest extends ViewRecord
{
    protected static string $resource = PaymentRequestResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make(),
            ...PaymentRequestResource::getRecordActionsForDisplay($this, $this->getRecord()),
        ];
    }
}
