<?php

namespace App\Filament\Resources\Stories\Pages;

use App\Filament\Resources\Stories\StoryResource;
use Filament\Resources\Pages\ViewRecord;

class ViewStory extends ViewRecord
{
    protected static string $resource = StoryResource::class;

    protected function getActions(): array
    {
        return [
            ...StoryResource::getRecordActionsForDisplay($this, $this->getRecord()),
        ];
    }
}
