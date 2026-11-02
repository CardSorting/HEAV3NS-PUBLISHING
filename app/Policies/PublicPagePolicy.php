<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\PublicPage;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PublicPagePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PublicPage');
    }

    public function view(AuthUser $authUser, PublicPage $publicPage): bool
    {
        return $authUser->can('View:PublicPage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PublicPage');
    }

    public function update(AuthUser $authUser, PublicPage $publicPage): bool
    {
        return $authUser->can('Update:PublicPage');
    }

    public function delete(AuthUser $authUser, PublicPage $publicPage): bool
    {
        if ($publicPage->is_tos || $publicPage->is_privacy) {
            return false;
        }
        return $authUser->can('Delete:PublicPage');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:PublicPage');
    }
}
