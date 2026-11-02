<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\Reel;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ReelPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Reel');
    }

    public function view(AuthUser $authUser, Reel $reel): bool
    {
        return $authUser->can('View:Reel');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Reel');
    }

    public function update(AuthUser $authUser, Reel $reel): bool
    {
        return $authUser->can('Update:Reel');
    }

    public function delete(AuthUser $authUser, Reel $reel): bool
    {
        return $authUser->can('Delete:Reel');
    }
}
