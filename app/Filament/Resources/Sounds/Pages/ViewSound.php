<?php

namespace App\Filament\Resources\Sounds\Pages;

use App\Filament\Resources\Sounds\Pages\Concerns\InteractsWithSoundMedia;
use App\Filament\Resources\Sounds\SoundResource;
use App\Model\Sound;
use Filament\Resources\Pages\ViewRecord;

class ViewSound extends ViewRecord
{
    use InteractsWithSoundMedia;

    protected static string $resource = SoundResource::class;

    protected function getActions(): array
    {
        return [
            ...SoundResource::getRecordActionsForDisplay($this, $this->getRecord()),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Sound $sound */
        $sound = $this->record;

        return $this->hydrateSoundMediaData($data, $sound);
    }
}
