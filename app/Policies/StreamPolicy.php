<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\Stream;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class StreamPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Stream');
    }

    public function view(AuthUser $authUser, Stream $stream): bool
    {
        return $authUser->can('View:Stream');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Stream');
    }

    public function update(AuthUser $authUser, Stream $stream): bool
    {
        return $authUser->can('Update:Stream');
    }

    public function delete(AuthUser $authUser, Stream $stream): bool
    {
        return $authUser->can('Delete:Stream');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Stream');
    }
}
