<?php

namespace App\Filament\Resources\Sounds\Pages;

use App\Filament\Resources\Sounds\SoundResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListSounds extends ListRecords
{
    protected static string $resource = SoundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
