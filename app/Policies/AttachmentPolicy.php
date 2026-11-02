<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\Attachment;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AttachmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Attachment');
    }

    public function view(AuthUser $authUser, Attachment $attachment): bool
    {
        return $authUser->can('View:Attachment');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Attachment');
    }

    public function update(AuthUser $authUser, Attachment $attachment): bool
    {
        return $authUser->can('Update:Attachment');
    }

    public function delete(AuthUser $authUser, Attachment $attachment): bool
    {
        return $authUser->can('Delete:Attachment');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Attachment');
    }
}
