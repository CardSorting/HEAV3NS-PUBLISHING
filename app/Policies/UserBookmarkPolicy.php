<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\UserBookmark;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class UserBookmarkPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:UserBookmark');
    }

    public function view(AuthUser $authUser, UserBookmark $userBookmark): bool
    {
        return $authUser->can('View:UserBookmark');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:UserBookmark');
    }

    public function update(AuthUser $authUser, UserBookmark $userBookmark): bool
    {
        return $authUser->can('Update:UserBookmark');
    }

    public function delete(AuthUser $authUser, UserBookmark $userBookmark): bool
    {
        return $authUser->can('Delete:UserBookmark');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:UserBookmark');
    }
}
