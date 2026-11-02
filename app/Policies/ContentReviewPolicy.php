<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\ContentReview;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ContentReviewPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $user): bool
    {
        return $user->can('ViewAny:ContentReview');
    }

    public function view(AuthUser $user, ContentReview $review): bool
    {
        return $user->can('View:ContentReview');
    }

    public function update(AuthUser $user, ContentReview $review): bool
    {
        return $user->can('Update:ContentReview');
    }
}
