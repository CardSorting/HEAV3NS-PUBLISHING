<?php

namespace App\Filament\Resources\TextModerationItems\Pages;

use App\Filament\Resources\TextModerationItems\TextModerationItemResource;
use Filament\Resources\Pages\ViewRecord;

class ViewTextModerationItem extends ViewRecord
{
    protected static string $resource = TextModerationItemResource::class;

    protected function getHeaderActions(): array
    {
        return TextModerationItemResource::headerActions();
    }
}
