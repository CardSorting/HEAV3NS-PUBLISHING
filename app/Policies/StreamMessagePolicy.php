<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\StreamMessage;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class StreamMessagePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:StreamMessage');
    }

    public function view(AuthUser $authUser, StreamMessage $streamMessage): bool
    {
        return $authUser->can('View:StreamMessage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:StreamMessage');
    }

    public function update(AuthUser $authUser, StreamMessage $streamMessage): bool
    {
        return $authUser->can('Update:StreamMessage');
    }

    public function delete(AuthUser $authUser, StreamMessage $streamMessage): bool
    {
        return $authUser->can('Delete:StreamMessage');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:StreamMessage');
    }
}
