<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\ConversionDelivery;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ConversionDeliveryPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $user): bool { return $user->can('ViewAny:ConversionDelivery'); }

    public function view(AuthUser $user, ConversionDelivery $delivery): bool { return $user->can('View:ConversionDelivery'); }

    // Never allow an audit record to be edited or removed through resource actions.
    public function create(AuthUser $user): bool { return false; }

    public function update(AuthUser $user, ConversionDelivery $delivery): bool { return false; }

    public function delete(AuthUser $user, ConversionDelivery $delivery): bool { return false; }

    public function deleteAny(AuthUser $user): bool { return false; }
}
