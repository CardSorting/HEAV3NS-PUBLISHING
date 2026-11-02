<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\TextModerationItem;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TextModerationItemPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $user): bool
    {
        return $user->can('ViewAny:TextModerationItem');
    }

    public function view(AuthUser $user, TextModerationItem $item): bool
    {
        return $user->can('View:TextModerationItem');
    }

    public function update(AuthUser $user, TextModerationItem $item): bool
    {
        return $user->can('Update:TextModerationItem');
    }
}
