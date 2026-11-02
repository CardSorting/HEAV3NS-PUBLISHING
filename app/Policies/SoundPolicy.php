<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\Sound;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SoundPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Sound');
    }

    public function view(AuthUser $authUser, Sound $sound): bool
    {
        return $authUser->can('View:Sound');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Sound');
    }

    public function update(AuthUser $authUser, Sound $sound): bool
    {
        return $authUser->can('Update:Sound');
    }

    public function delete(AuthUser $authUser, Sound $sound): bool
    {
        return $authUser->can('Delete:Sound');
    }
}
