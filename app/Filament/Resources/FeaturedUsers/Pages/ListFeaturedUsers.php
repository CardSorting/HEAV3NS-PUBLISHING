<?php

namespace App\Filament\Resources\FeaturedUsers\Pages;

use App\Filament\Resources\FeaturedUsers\FeaturedUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFeaturedUsers extends ListRecords
{
    protected static string $resource = FeaturedUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
