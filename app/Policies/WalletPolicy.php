<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\Wallet;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class WalletPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Wallet');
    }

    public function view(AuthUser $authUser, Wallet $wallet): bool
    {
        return $authUser->can('View:Wallet');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Wallet');
    }

    public function update(AuthUser $authUser, Wallet $wallet): bool
    {
        return $authUser->can('Update:Wallet');
    }

    public function delete(AuthUser $authUser, Wallet $wallet): bool
    {
        return $authUser->can('Delete:Wallet');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Wallet');
    }
}
