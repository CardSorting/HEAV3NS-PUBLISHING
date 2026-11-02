<?php

namespace App\Filament\Resources\ReleaseForms\Pages;

use App\Filament\Resources\ReleaseForms\ReleaseFormResource;
use App\Providers\AttachmentServiceProvider;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CreateReleaseForm extends CreateRecord
{
    protected static string $resource = ReleaseFormResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $uploads = $data['files'] ?? [];
        $uploads = is_array($uploads) ? $uploads : [$uploads];
        $userId = (int) $data['user_id'];

        $data['files'] = collect($uploads)
            ->map(function ($upload) use ($userId): ?string {
                if ($upload instanceof TemporaryUploadedFile) {
                    return AttachmentServiceProvider::createAttachment(
                        $upload,
                        'users/release-forms',
                        false,
                        false,
                        false,
                        $userId
                    )->filename;
                }

                return is_string($upload) ? $upload : null;
            })
            ->filter()
            ->values()
            ->all();

        return $data;
    }
}
