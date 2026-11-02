<?php

namespace App\Services;

use App\Model\User;
use App\Providers\AuthServiceProvider;
use Illuminate\Support\Facades\Session;

class TwoFactorAuthenticationService
{
    /**
     * Initializes the current login session's two-factor authentication state.
     */
    public function initialize(User $user): bool
    {
        $requiresTwoFactorAuthentication = $this->requiresVerification($user);

        if ($requiresTwoFactorAuthentication) {
            $this->generateVerificationCode();
            $this->addCurrentDevice($user);
        }

        $this->storeSessionRequirement($requiresTwoFactorAuthentication);

        return $requiresTwoFactorAuthentication;
    }

    public function requiresVerification(User $user): bool
    {
        return $this->siteRequiresTwoFactorAuthentication()
            && $user->enable_2fa
            && !$this->isCurrentDeviceVerified($user);
    }

    protected function siteRequiresTwoFactorAuthentication(): bool
    {
        return (bool) getSetting('security.enable_2fa');
    }

    protected function isCurrentDeviceVerified(User $user): bool
    {
        return in_array(
            AuthServiceProvider::generate2FaDeviceSignature(),
            AuthServiceProvider::getUserDevices($user->id),
            true
        );
    }

    protected function generateVerificationCode(): void
    {
        AuthServiceProvider::generate2FACode();
    }

    protected function addCurrentDevice(User $user): void
    {
        AuthServiceProvider::addNewUserDevice($user->id);
    }

    protected function storeSessionRequirement(bool $required): void
    {
        Session::put('force2fa', $required);
    }
}
