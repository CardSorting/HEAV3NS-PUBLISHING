<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\Withdrawal;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class WithdrawalPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Withdrawal');
    }

    public function view(AuthUser $authUser, Withdrawal $withdrawal): bool
    {
        return $authUser->can('View:Withdrawal');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Withdrawal');
    }

    public function update(AuthUser $authUser, Withdrawal $withdrawal): bool
    {
        return $authUser->can('Update:Withdrawal');
    }

    public function delete(AuthUser $authUser, Withdrawal $withdrawal): bool
    {
        return $authUser->can('Delete:Withdrawal');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Withdrawal');
    }
}
