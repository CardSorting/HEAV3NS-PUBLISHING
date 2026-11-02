<?php

namespace App\Filament\Resources\ManualPaymentMethods\Pages;

use App\Filament\Resources\ManualPaymentMethods\ManualPaymentMethodResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditManualPaymentMethod extends EditRecord
{
    protected static string $resource = ManualPaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->authorizationTooltip()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['direction'] = $this->getRecord()->getAttribute('direction');

        return $data;
    }
}
