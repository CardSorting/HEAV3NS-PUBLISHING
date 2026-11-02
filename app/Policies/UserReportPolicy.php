<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\UserReport;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class UserReportPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:UserReport');
    }

    public function view(AuthUser $authUser, UserReport $userReport): bool
    {
        return $authUser->can('View:UserReport');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:UserReport');
    }

    public function update(AuthUser $authUser, UserReport $userReport): bool
    {
        return $authUser->can('Update:UserReport');
    }

    public function delete(AuthUser $authUser, UserReport $userReport): bool
    {
        return $authUser->can('Delete:UserReport');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:UserReport');
    }
}
