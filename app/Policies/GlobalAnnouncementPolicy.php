<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\GlobalAnnouncement;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class GlobalAnnouncementPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:GlobalAnnouncement');
    }

    public function view(AuthUser $authUser, GlobalAnnouncement $globalAnnouncement): bool
    {
        return $authUser->can('View:GlobalAnnouncement');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:GlobalAnnouncement');
    }

    public function update(AuthUser $authUser, GlobalAnnouncement $globalAnnouncement): bool
    {
        return $authUser->can('Update:GlobalAnnouncement');
    }

    public function delete(AuthUser $authUser, GlobalAnnouncement $globalAnnouncement): bool
    {
        return $authUser->can('Delete:GlobalAnnouncement');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:GlobalAnnouncement');
    }
}
