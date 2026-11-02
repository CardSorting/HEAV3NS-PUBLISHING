<?php

namespace App\Providers;

use App\Model\User;
use Illuminate\Support\ServiceProvider;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Balance;
use Stripe\Payout;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Stripe\Transfer;

class StripeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    private static function createStripeClient(): StripeClient {
        return new StripeClient(getSetting('payments.stripe_secret_key'));
    }

    public static function createStripeCustomAccount(User $user): Account
    {
        return self::createStripeClient()->accounts->create([
            'country' => $user->userCountry->country_code,
            'type' => 'custom',
            'email' => $user->email,
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
            'settings' => [
                'payouts' => [
                    'schedule' => [
                        'interval' => 'manual',
                    ],
                ],
            ],
        ]);
    }

    public static function retrieveStripeCustomAccount(string $accountId): Account {
        return self::createStripeClient()->accounts->retrieve($accountId, []);
    }

    /**
     * The account link represents the place where users go through the verification / onboarding process
     * (Stripe hosted UI).
     */
    public static function createStripeAccountLink(string $accountId, string $onboardingType): AccountLink {
        return self::createStripeClient()->accountLinks->create([
            'account' => $accountId,
            'refresh_url' => route('withdrawals.onboarding'),
            'return_url' => route('my.settings', ['type' => 'wallet', 'active' => 'withdraw']),
            'type' => $onboardingType,
        ]);
    }

    public static function createConnectedAccountTransfer($withdrawal, string $accountId = null): Transfer {
        return self::createStripeClient()->transfers->create([
            'amount' => ($withdrawal->amount - $withdrawal->fee) * 100,
            'currency' => getSetting('payments.currency_code'),
            'destination' => $accountId,
            'transfer_group' => $withdrawal->id,
        ]);
    }

    public static function createManualPayout(string $accountId = null): Payout {
        $stripeClient = self::createStripeClient();
        // fetch available amount and currency as there might be the case the connected account have a different currency
        // for his linked card so the amount we transfer to his account might be different as it gets automatically
        // converted to the connected account default currency
        $balanceTransaction = $stripeClient->balance->retrieve([], ['stripe_account' => $accountId]);
        $availableBalance = self::getAvailableStripeBalance($balanceTransaction);
        if(empty($availableBalance)) {
            throw new \Exception("This user does not have any available balance to payout");
        }

        return self::createStripeClient()->payouts->create(
            [
                'amount' => $availableBalance['amount'],
                'currency' => $availableBalance['currency'],
            ],
            ['stripe_account' => $accountId]
        );
    }

    private static function getAvailableStripeBalance(Balance $balance): array|StripeObject {
        $available = [];
        if($balance->available) {
            foreach ($balance->available as $availableBalance) {
                if($availableBalance['amount'] > 0) {
                    return $availableBalance;
                }
            }
        }

        return $available;
    }
}
