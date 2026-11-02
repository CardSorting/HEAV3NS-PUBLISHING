<?php

namespace App\Filament\Resources\StreamMessages\Pages;

use App\Filament\Resources\StreamMessages\StreamMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStreamMessages extends ListRecords
{
    protected static string $resource = StreamMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
