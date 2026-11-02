<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\PollAnswer;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PollAnswerPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PollAnswer');
    }

    public function view(AuthUser $authUser, PollAnswer $pollAnswer): bool
    {
        return $authUser->can('View:PollAnswer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PollAnswer');
    }

    public function update(AuthUser $authUser, PollAnswer $pollAnswer): bool
    {
        return $authUser->can('Update:PollAnswer');
    }

    public function delete(AuthUser $authUser, PollAnswer $pollAnswer): bool
    {
        return $authUser->can('Delete:PollAnswer');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:PollAnswer');
    }
}
