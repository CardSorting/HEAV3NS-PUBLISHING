<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\MediaModerationRun;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class MediaModerationRunPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $user): bool
    {
        return $user->can('ViewAny:MediaModerationRun');
    }

    public function view(AuthUser $user, MediaModerationRun $run): bool
    {
        return $user->can('View:MediaModerationRun');
    }

    public function create(AuthUser $user): bool
    {
        return $user->can('Create:MediaModerationRun');
    }

    public function update(AuthUser $user, MediaModerationRun $run): bool
    {
        return $user->can('Update:MediaModerationRun');
    }

    public function delete(AuthUser $user, MediaModerationRun $run): bool
    {
        return $user->can('Delete:MediaModerationRun');
    }

    public function deleteAny(AuthUser $user): bool
    {
        return $user->can('Delete:MediaModerationRun');
    }
}
