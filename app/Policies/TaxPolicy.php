<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\Tax;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TaxPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Tax');
    }

    public function view(AuthUser $authUser, Tax $tax): bool
    {
        return $authUser->can('View:Tax');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Tax');
    }

    public function update(AuthUser $authUser, Tax $tax): bool
    {
        return $authUser->can('Update:Tax');
    }

    public function delete(AuthUser $authUser, Tax $tax): bool
    {
        return $authUser->can('Delete:Tax');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Tax');
    }
}
