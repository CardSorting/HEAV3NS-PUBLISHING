<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\Country;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CountryPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Country');
    }

    public function view(AuthUser $authUser, Country $country): bool
    {
        return $authUser->can('View:Country');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Country');
    }

    public function update(AuthUser $authUser, Country $country): bool
    {
        return $authUser->can('Update:Country');
    }

    public function delete(AuthUser $authUser, Country $country): bool
    {
        return $authUser->can('Delete:Country');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Country');
    }
}
