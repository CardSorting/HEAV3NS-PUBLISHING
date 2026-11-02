<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\Poll;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PollPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Poll');
    }

    public function view(AuthUser $authUser, Poll $poll): bool
    {
        return $authUser->can('View:Poll');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Poll');
    }

    public function update(AuthUser $authUser, Poll $poll): bool
    {
        return $authUser->can('Update:Poll');
    }

    public function delete(AuthUser $authUser, Poll $poll): bool
    {
        return $authUser->can('Delete:Poll');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Poll');
    }
}
