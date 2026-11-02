<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\Reward;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class RewardPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Reward');
    }

    public function view(AuthUser $authUser, Reward $reward): bool
    {
        return $authUser->can('View:Reward');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Reward');
    }

    public function update(AuthUser $authUser, Reward $reward): bool
    {
        return $authUser->can('Update:Reward');
    }

    public function delete(AuthUser $authUser, Reward $reward): bool
    {
        return $authUser->can('Delete:Reward');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Reward');
    }
}
