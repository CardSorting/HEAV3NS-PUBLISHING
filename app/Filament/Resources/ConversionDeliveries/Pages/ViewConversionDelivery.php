<?php

namespace App\Filament\Resources\ConversionDeliveries\Pages;

use App\Filament\Resources\ConversionDeliveries\ConversionDeliveryResource;
use Filament\Resources\Pages\ViewRecord;

class ViewConversionDelivery extends ViewRecord
{
    protected static string $resource = ConversionDeliveryResource::class;

    protected function getHeaderActions(): array { return []; }
}
