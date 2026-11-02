<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\Transaction;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TransactionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Transaction');
    }

    public function view(AuthUser $authUser, Transaction $transaction): bool
    {
        return $authUser->can('View:Transaction');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Transaction');
    }

    public function update(AuthUser $authUser, Transaction $transaction): bool
    {
        return $authUser->can('Update:Transaction');
    }

    public function delete(AuthUser $authUser, Transaction $transaction): bool
    {
        return $authUser->can('Delete:Transaction');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Transaction');
    }
}
