<?php

namespace App\Filament\Resources\ManualPaymentMethods\Pages;

use App\Filament\Resources\ManualPaymentMethods\ManualPaymentMethodResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateManualPaymentMethod extends CreateRecord
{
    protected static string $resource = ManualPaymentMethodResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['key'] = 'method_'.Str::lower(Str::random(16));

        return $data;
    }
}
