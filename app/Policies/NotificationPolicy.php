<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\Notification;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class NotificationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Notification');
    }

    public function view(AuthUser $authUser, Notification $notification): bool
    {
        return $authUser->can('View:Notification');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Notification');
    }

    public function update(AuthUser $authUser, Notification $notification): bool
    {
        return $authUser->can('Update:Notification');
    }

    public function delete(AuthUser $authUser, Notification $notification): bool
    {
        return $authUser->can('Delete:Notification');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Notification');
    }
}
