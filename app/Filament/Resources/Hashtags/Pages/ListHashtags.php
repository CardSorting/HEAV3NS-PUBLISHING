<?php

namespace App\Filament\Resources\Hashtags\Pages;

use App\Filament\Resources\Hashtags\HashtagResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHashtags extends ListRecords
{
    protected static string $resource = HashtagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
