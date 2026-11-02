<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Traits\SyncsUserRole;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use SyncsUserRole;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...UserResource::getRecordActionsForDisplay($this, $this->getRecord()),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('avatar_upload', $data)) {
            $data['avatar'] = $data['avatar_upload'];
            unset($data['avatar_upload']);
        }

        return $data;
    }

    protected function afterSave(): void
    {

        $this->syncRoleAndLegacy($this->record);
    }
}
