<?php

namespace App\Providers;

use App\Model\Transaction;
use Illuminate\Support\ServiceProvider;
use Razorpay\Api\Api;
use Razorpay\Api\Payment;

class RazorPayServiceProvider extends ServiceProvider
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

    private static function createApiClient(): Api {
        return new Api(
            key: getSetting('payments.razorpay_api_key'),
            secret: getSetting('payments.razorpay_api_secret')
        );
    }

    public static function createPaymentLinkByTransaction(Transaction $transaction, string $paymentToken): string
    {
        /** @phpstan-ignore-next-line */
        $paymentLink = self::createApiClient()->paymentLink->create([
            'amount' => intval($transaction->amount * 100),
            'currency' => $transaction['currency'],
            'accept_partial' => false,
            'reference_id' => $paymentToken,
            'description' => PaymentsServiceProvider::getPaymentDescriptionByTransaction($transaction),
            'customer' => [
                'name' => $transaction->sender->name ?? $transaction->sender->email,
                'email' => $transaction->sender->email,
            ],
            'notify' => [
                'sms' => false,
                'email' => false,
            ],
            'callback_url' => route('payment.checkRazorPayPaymentStatus'),
            'callback_method' => 'get',
        ]);

        return $paymentLink['short_url'];
    }

    public static function getPaymentData(string $paymentId): ?Payment {
        /* @phpstan-ignore-next-line */
        return self::createApiClient()->payment->fetch($paymentId);
    }

    public static function verifyWebhookSignature(string $payload, string $signature, string $secret): void
    {
        /* @phpstan-ignore-next-line */
        self::createApiClient()->utility->verifyWebhookSignature($payload, $signature, $secret);
    }
}
