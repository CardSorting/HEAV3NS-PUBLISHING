<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\Subscription;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SubscriptionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Subscription');
    }

    public function view(AuthUser $authUser, Subscription $subscription): bool
    {
        return $authUser->can('View:Subscription');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Subscription');
    }

    public function update(AuthUser $authUser, Subscription $subscription): bool
    {
        return $authUser->can('Update:Subscription');
    }

    public function delete(AuthUser $authUser, Subscription $subscription): bool
    {
        return $authUser->can('Delete:Subscription');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Subscription');
    }
}
