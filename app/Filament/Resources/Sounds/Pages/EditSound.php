<?php

namespace App\Filament\Resources\Sounds\Pages;

use App\Filament\Resources\Sounds\Pages\Concerns\InteractsWithSoundMedia;
use App\Filament\Resources\Sounds\SoundResource;
use App\Model\Sound;
use Filament\Resources\Pages\EditRecord;

class EditSound extends EditRecord
{
    use InteractsWithSoundMedia;

    protected static string $resource = SoundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...SoundResource::getRecordActionsForDisplay($this, $this->getRecord()),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->prepareSoundData($data);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Sound $sound */
        $sound = $this->record;

        return $this->hydrateSoundMediaData($data, $sound);
    }

    protected function afterSave(): void
    {
        /** @var Sound $sound */
        $sound = $this->record;

        $this->synchronizeSoundMedia($sound);
    }
}
