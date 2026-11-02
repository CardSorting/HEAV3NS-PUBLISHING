<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\ReleaseForm;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ReleaseFormPolicy
{
    use HandlesAuthorization;

    public function before(AuthUser $authUser): ?bool
    {
        return (int) ($authUser->role_id ?? 0) === 1 ? true : null;
    }

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ReleaseForm');
    }

    public function view(AuthUser $authUser, ReleaseForm $releaseForm): bool
    {
        return $authUser->can('View:ReleaseForm');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ReleaseForm');
    }

    public function update(AuthUser $authUser, ReleaseForm $releaseForm): bool
    {
        return $authUser->can('Update:ReleaseForm');
    }

    public function delete(AuthUser $authUser, ReleaseForm $releaseForm): bool
    {
        return $authUser->can('Delete:ReleaseForm');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:ReleaseForm');
    }
}
