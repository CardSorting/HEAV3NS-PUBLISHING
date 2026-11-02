<?php

declare(strict_types=1);

namespace App\Policies;

use App\Model\PaymentRequest;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PaymentRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PaymentRequest');
    }

    public function view(AuthUser $authUser, PaymentRequest $paymentRequest): bool
    {
        return $authUser->can('View:PaymentRequest');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PaymentRequest');
    }

    public function update(AuthUser $authUser, PaymentRequest $paymentRequest): bool
    {
        return $authUser->can('Update:PaymentRequest');
    }

    public function delete(AuthUser $authUser, PaymentRequest $paymentRequest): bool
    {
        return $authUser->can('Delete:PaymentRequest');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:PaymentRequest');
    }
}
