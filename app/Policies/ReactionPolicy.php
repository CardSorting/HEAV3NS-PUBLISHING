<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\Reaction;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ReactionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Reaction');
    }

    public function view(AuthUser $authUser, Reaction $reaction): bool
    {
        return $authUser->can('View:Reaction');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Reaction');
    }

    public function update(AuthUser $authUser, Reaction $reaction): bool
    {
        return $authUser->can('Update:Reaction');
    }

    public function delete(AuthUser $authUser, Reaction $reaction): bool
    {
        return $authUser->can('Delete:Reaction');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Reaction');
    }
}
