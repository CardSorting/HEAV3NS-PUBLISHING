<?php

namespace App\Providers;

use App\Model\Transaction;
use App\Model\Subscription;
use App\Model\User;
use Illuminate\Support\ServiceProvider;

class ProfileMonetizationServiceProvider extends ServiceProvider
{
    public const MODE_MIXED = 'mixed';
    public const MODE_PAID_ONLY = 'paid_only';
    public const MODE_FREE_ONLY = 'free_only';

    public static function mode(): string
    {
        $mode = (string) getSetting('profiles.profile_monetization_mode');

        return in_array($mode, self::modes(), true) ? $mode : self::MODE_MIXED;
    }

    public static function modes(): array
    {
        return [
            self::MODE_MIXED,
            self::MODE_PAID_ONLY,
            self::MODE_FREE_ONLY,
        ];
    }

    public static function subscriptionTypes(): array
    {
        return [
            Transaction::ONE_MONTH_SUBSCRIPTION,
            Transaction::THREE_MONTHS_SUBSCRIPTION,
            Transaction::SIX_MONTHS_SUBSCRIPTION,
            Transaction::YEARLY_SUBSCRIPTION,
        ];
    }

    public static function isPaidOnly(): bool
    {
        return self::mode() === self::MODE_PAID_ONLY;
    }

    public static function isFreeOnly(): bool
    {
        return self::mode() === self::MODE_FREE_ONLY;
    }

    public static function isMixed(): bool
    {
        return self::mode() === self::MODE_MIXED;
    }

    public static function openProfilesAllowed(): bool
    {
        return !self::isPaidOnly() && (bool) getSetting('profiles.allow_users_enabling_open_profiles');
    }

    public static function userHasOpenProfile(User $user): bool
    {
        return self::openProfilesAllowed() && (bool) $user->open_profile;
    }

    public static function userHasPaidProfile(User $user): bool
    {
        return (bool) $user->paid_profile;
    }

    public static function userHasFreeProfile(User $user): bool
    {
        return !self::userHasPaidProfile($user) || self::userHasOpenProfile($user);
    }

    public static function canSetPaidProfile(bool $value): bool
    {
        return self::isMixed();
    }

    public static function normalizeProfileFlagsForNewUser(array $data): array
    {
        if (self::isPaidOnly()) {
            $data['paid_profile'] = true;
            $data['open_profile'] = false;
        }

        if (self::isFreeOnly()) {
            $data['paid_profile'] = false;
        }

        if (!self::openProfilesAllowed()) {
            $data['open_profile'] = false;
        }

        return $data;
    }

    public static function canReceiveProfileSubscriptions(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if (!self::userHasPaidProfile($user)) {
            return false;
        }

        return !self::userHasOpenProfile($user);
    }

    public static function shouldShowSubscriptionsForUser(?User $user): bool
    {
        if (!self::isFreeOnly()) {
            return true;
        }

        if (!$user) {
            return false;
        }

        return Subscription::query()
            ->where('sender_user_id', $user->id)
            ->orWhere('recipient_user_id', $user->id)
            ->exists();
    }

    public static function shouldShowRatesForUser(?User $user): bool
    {
        if (!self::isFreeOnly()) {
            return true;
        }

        return $user && self::userHasPaidProfile($user);
    }
}
