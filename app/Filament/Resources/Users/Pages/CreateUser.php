<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Traits\SyncsUserRole;
use App\Providers\ProfileMonetizationServiceProvider;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use SyncsUserRole;

    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (array_key_exists('avatar_upload', $data)) {
            $data['avatar'] = $data['avatar_upload'];
            unset($data['avatar_upload']);
        }

        return ProfileMonetizationServiceProvider::normalizeProfileFlagsForNewUser($data);
    }

    protected function afterCreate(): void
    {
        $this->syncRoleAndLegacy($this->record);
    }
}
