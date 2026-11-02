<?php

namespace App\Filament\Resources\Polls\Pages;

use App\Filament\Resources\Polls\PollResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPoll extends ViewRecord
{
    protected static string $resource = PollResource::class;

    protected function getActions(): array
    {
        return [EditAction::make()];
    }
}
