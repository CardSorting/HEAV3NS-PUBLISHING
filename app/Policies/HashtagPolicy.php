<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Model\Hashtag;
use Illuminate\Auth\Access\HandlesAuthorization;

class HashtagPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Hashtag');
    }

    public function view(AuthUser $authUser, Hashtag $hashtag): bool
    {
        return $authUser->can('View:Hashtag');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Hashtag');
    }

    public function update(AuthUser $authUser, Hashtag $hashtag): bool
    {
        return $authUser->can('Update:Hashtag');
    }

    public function delete(AuthUser $authUser, Hashtag $hashtag): bool
    {
        return $authUser->can('Delete:Hashtag');
    }
}
