<?php

namespace App\Filament\Resources\UserMessages\Pages;

use App\Filament\Resources\UserMessages\UserMessageResource;
use App\Filament\Support\GifContent;
use Filament\Resources\Pages\CreateRecord;

class CreateUserMessage extends CreateRecord
{
    protected static string $resource = UserMessageResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return GifContent::prepare($data);
    }
}
