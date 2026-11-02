<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\UserMessage;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class UserMessagePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:UserMessage');
    }

    public function view(AuthUser $authUser, UserMessage $userMessage): bool
    {
        return $authUser->can('View:UserMessage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:UserMessage');
    }

    public function update(AuthUser $authUser, UserMessage $userMessage): bool
    {
        return !$userMessage->tip_transaction_id && $authUser->can('Update:UserMessage');
    }

    public function delete(AuthUser $authUser, UserMessage $userMessage): bool
    {
        return !$userMessage->tip_transaction_id && $authUser->can('Delete:UserMessage');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:UserMessage');
    }
}
