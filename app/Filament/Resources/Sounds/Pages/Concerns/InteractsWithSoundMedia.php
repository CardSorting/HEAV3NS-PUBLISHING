<?php

namespace App\Filament\Resources\Sounds\Pages\Concerns;

use App\Model\Sound;
use App\Services\SoundMediaService;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait InteractsWithSoundMedia
{
    protected ?TemporaryUploadedFile $coverUpload = null;

    protected ?TemporaryUploadedFile $audioUpload = null;

    protected bool $activateAfterMediaSync = false;

    protected function prepareSoundData(array $data): array
    {
        $this->coverUpload = $this->extractTemporaryUpload($data['cover_upload'] ?? null);
        $this->audioUpload = $this->extractTemporaryUpload($data['audio_upload'] ?? null);
        $this->activateAfterMediaSync = (bool) ($data['is_active'] ?? false);

        unset($data['cover_upload'], $data['audio_upload']);

        // A sound is exposed only after both media records are safely synchronized.
        $data['is_active'] = false;

        return $data;
    }

    protected function hydrateSoundMediaData(array $data, Sound $sound): array
    {
        $data['cover_upload'] = $sound->coverAttachment()->value('filename');
        $data['audio_upload'] = $sound->audioAttachment()->value('filename');

        return $data;
    }

    protected function synchronizeSoundMedia(Sound $sound): void
    {
        $service = app(SoundMediaService::class);
        $service->sync($sound, $this->coverUpload, $this->audioUpload);

        $errors = [];

        if (!$sound->coverAttachment()->exists()) {
            $errors['data.cover_upload'] = __('admin.resources.sound.validation.cover_required');
        }

        if (!$sound->audioAttachment()->exists()) {
            $errors['data.audio_upload'] = __('admin.resources.sound.validation.audio_required');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $sound->update(['is_active' => $this->activateAfterMediaSync]);
    }

    private function extractTemporaryUpload(mixed $state): ?TemporaryUploadedFile
    {
        if ($state instanceof TemporaryUploadedFile) {
            return $state;
        }

        if (!is_array($state)) {
            return null;
        }

        foreach ($state as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                return $file;
            }
        }

        return null;
    }
}
