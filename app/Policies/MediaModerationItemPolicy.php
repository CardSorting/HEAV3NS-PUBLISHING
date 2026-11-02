<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\MediaModerationItem;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class MediaModerationItemPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $user): bool
    {
        return $user->can('ViewAny:MediaModerationItem');
    }

    public function view(AuthUser $user, MediaModerationItem $item): bool
    {
        return $user->can('View:MediaModerationItem');
    }

    public function update(AuthUser $user, MediaModerationItem $item): bool
    {
        return $user->can('Update:MediaModerationItem');
    }
}
