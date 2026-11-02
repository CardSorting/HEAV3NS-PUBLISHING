<?php

namespace App\Services;

use App\Model\User;
use Illuminate\Database\Eloquent\Collection;

class UserStaffBadge
{
    public function forUser(?User $user): ?string
    {
        if ($user === null || !$this->enabled()) {
            return null;
        }

        if (config('settings.admin_version') !== 'v2') {
            return (int) $user->role_id === 1 ? 'admin' : null;
        }

        // Public responses expose the badge, never the underlying role assignments.
        $user->makeHidden('roles')->loadMissing('roles');
        $roles = $user->roles->where('guard_name', 'web');

        return $roles->contains('name', 'admin') ? 'admin' : null;
    }

    /** Load role assignments once for a list of public authors instead of once per badge. */
    public function preload(iterable $users): void
    {
        if (!$this->enabled() || config('settings.admin_version') !== 'v2') {
            return;
        }

        $models = [];
        foreach ($users as $user) {
            if ($user instanceof User) {
                $user->makeHidden('roles');
                $models[] = $user;
            }
        }

        (new Collection($models))->loadMissing('roles');
    }

    protected function enabled(): bool
    {
        return (bool) getSetting('admin.show_staff_badges', true);
    }
}
