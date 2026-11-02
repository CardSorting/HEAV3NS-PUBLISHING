<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\Story;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class StoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Story');
    }

    public function view(AuthUser $authUser, Story $story): bool
    {
        return $authUser->can('View:Story');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Story');
    }

    public function update(AuthUser $authUser, Story $story): bool
    {
        return $authUser->can('Update:Story');
    }

    public function delete(AuthUser $authUser, Story $story): bool
    {
        return $authUser->can('Delete:Story');
    }
}
