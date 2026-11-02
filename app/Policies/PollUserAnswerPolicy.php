<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\PollUserAnswer;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PollUserAnswerPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PollUserAnswer');
    }

    public function view(AuthUser $authUser, PollUserAnswer $pollUserAnswer): bool
    {
        return $authUser->can('View:PollUserAnswer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PollUserAnswer');
    }

    public function update(AuthUser $authUser, PollUserAnswer $pollUserAnswer): bool
    {
        return $authUser->can('Update:PollUserAnswer');
    }

    public function delete(AuthUser $authUser, PollUserAnswer $pollUserAnswer): bool
    {
        return $authUser->can('Delete:PollUserAnswer');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:PollUserAnswer');
    }
}
