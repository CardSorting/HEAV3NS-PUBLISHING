<?php

namespace App\Filament\Resources\Sounds\Pages;

use App\Filament\Resources\Sounds\Pages\Concerns\InteractsWithSoundMedia;
use App\Filament\Resources\Sounds\SoundResource;
use App\Model\Sound;
use App\Services\SoundMediaService;
use Filament\Resources\Pages\CreateRecord;
use Throwable;

class CreateSound extends CreateRecord
{
    use InteractsWithSoundMedia;

    protected static string $resource = SoundResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareSoundData($data);
    }

    protected function afterCreate(): void
    {
        /** @var Sound $sound */
        $sound = $this->record;

        try {
            $this->synchronizeSoundMedia($sound);
        } catch (Throwable $exception) {
            app(SoundMediaService::class)->deleteAll($sound);
            $sound->delete();

            throw $exception;
        }
    }
}
