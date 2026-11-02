<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\UserTax;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class UserTaxPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:UserTax');
    }

    public function view(AuthUser $authUser, UserTax $userTax): bool
    {
        return $authUser->can('View:UserTax');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:UserTax');
    }

    public function update(AuthUser $authUser, UserTax $userTax): bool
    {
        return $authUser->can('Update:UserTax');
    }

    public function delete(AuthUser $authUser, UserTax $userTax): bool
    {
        return $authUser->can('Delete:UserTax');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:UserTax');
    }
}
