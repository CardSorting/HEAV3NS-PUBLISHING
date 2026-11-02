<?php

namespace App\Filament\Traits;

use App\Model\User;
use Illuminate\Database\Eloquent\Model as EloquentModel;

trait SyncsUserRole
{
    private function syncRoleAndLegacy(EloquentModel $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Get the Spatie role id that was just synced
        $spatieRoleId = $user->refresh()->roles()->value('id'); // integer|null

        // Persist to users.role_id (silent)
        $user->forceFill(['role_id' => $spatieRoleId])->saveQuietly();
        // or, if fillable: $user->updateQuietly(['role_id' => $spatieRoleId]);
    }
}
