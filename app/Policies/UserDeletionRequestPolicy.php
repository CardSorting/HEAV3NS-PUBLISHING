<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\UserDeletionRequest;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class UserDeletionRequestPolicy
{
    use HandlesAuthorization;

    public function before(AuthUser $authUser): ?bool
    {
        return (int) ($authUser->role_id ?? 0) === 1 ? true : null;
    }

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:UserDeletionRequest');
    }

    public function view(AuthUser $authUser, UserDeletionRequest $userDeletionRequest): bool
    {
        return $authUser->can('View:UserDeletionRequest');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:UserDeletionRequest');
    }

    public function update(AuthUser $authUser, UserDeletionRequest $userDeletionRequest): bool
    {
        return $authUser->can('Update:UserDeletionRequest');
    }

    public function delete(AuthUser $authUser, UserDeletionRequest $userDeletionRequest): bool
    {
        return $authUser->can('Delete:UserDeletionRequest');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:UserDeletionRequest');
    }
}
