<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\ContactMessage;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ContactMessagePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ContactMessage');
    }

    public function view(AuthUser $authUser, ContactMessage $contactMessage): bool
    {
        return $authUser->can('View:ContactMessage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ContactMessage');
    }

    public function update(AuthUser $authUser, ContactMessage $contactMessage): bool
    {
        return $authUser->can('Update:ContactMessage');
    }

    public function delete(AuthUser $authUser, ContactMessage $contactMessage): bool
    {
        return $authUser->can('Delete:ContactMessage');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:ContactMessage');
    }
}
