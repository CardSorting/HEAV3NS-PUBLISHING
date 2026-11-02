<?php

namespace App\Filament\Resources\UserTaxes\Pages;

use App\Filament\Resources\UserTaxes\UserTaxResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUserTax extends CreateRecord
{
    protected static string $resource = UserTaxResource::class;
}
