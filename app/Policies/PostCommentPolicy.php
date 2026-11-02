<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\PostComment;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PostCommentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PostComment');
    }

    public function view(AuthUser $authUser, PostComment $postComment): bool
    {
        return $authUser->can('View:PostComment');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PostComment');
    }

    public function update(AuthUser $authUser, PostComment $postComment): bool
    {
        return $authUser->can('Update:PostComment');
    }

    public function delete(AuthUser $authUser, PostComment $postComment): bool
    {
        return $authUser->can('Delete:PostComment');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:PostComment');
    }
}
