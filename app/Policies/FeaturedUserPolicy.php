<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\FeaturedUser;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class FeaturedUserPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FeaturedUser');
    }

    public function view(AuthUser $authUser, FeaturedUser $featuredUser): bool
    {
        return $authUser->can('View:FeaturedUser');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FeaturedUser');
    }

    public function update(AuthUser $authUser, FeaturedUser $featuredUser): bool
    {
        return $authUser->can('Update:FeaturedUser');
    }

    public function delete(AuthUser $authUser, FeaturedUser $featuredUser): bool
    {
        return $authUser->can('Delete:FeaturedUser');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:FeaturedUser');
    }
}
