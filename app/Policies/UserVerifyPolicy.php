<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\UserVerify;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class UserVerifyPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:UserVerify');
    }

    public function view(AuthUser $authUser, UserVerify $userVerify): bool
    {
        return $authUser->can('View:UserVerify');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:UserVerify');
    }

    public function update(AuthUser $authUser, UserVerify $userVerify): bool
    {
        return $authUser->can('Update:UserVerify');
    }

    public function delete(AuthUser $authUser, UserVerify $userVerify): bool
    {
        return $authUser->can('Delete:UserVerify');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:UserVerify');
    }
}
