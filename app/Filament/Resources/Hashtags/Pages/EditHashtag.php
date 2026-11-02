<?php

namespace App\Filament\Resources\Hashtags\Pages;

use App\Filament\Resources\Hashtags\HashtagResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHashtag extends EditRecord
{
    protected static string $resource = HashtagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
