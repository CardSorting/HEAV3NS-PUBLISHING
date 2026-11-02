<?php

namespace App\Filament\Resources\Reactions\Pages;

use App\Filament\Resources\Reactions\ReactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReaction extends EditRecord
{
    protected static string $resource = ReactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
