<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\UserList;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class UserListPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:UserList');
    }

    public function view(AuthUser $authUser, UserList $userList): bool
    {
        return $authUser->can('View:UserList');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:UserList');
    }

    public function update(AuthUser $authUser, UserList $userList): bool
    {
        return $authUser->can('Update:UserList');
    }

    public function delete(AuthUser $authUser, UserList $userList): bool
    {
        return $authUser->can('Delete:UserList');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:UserList');
    }
}
