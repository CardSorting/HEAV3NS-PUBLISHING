<?php

namespace App\Providers;

use App\Model\Transaction;
use App\Model\User;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

class MollieServiceProvider extends ServiceProvider
{
    private const API_BASE_URI = 'https://api.mollie.com/v2/';

    public function register()
    {
        //
    }

    public function boot()
    {
        //
    }

    private static function createHttpClient(): Client
    {
        return new Client([
            'base_uri' => self::API_BASE_URI,
            'headers' => [
                'Authorization' => 'Bearer '.getSetting('payments.mollie_api_key'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/hal+json',
            ],
        ]);
    }

    public static function createPaymentByTransaction(Transaction $transaction, string $paymentToken): array
    {
        $sender = $transaction->sender ?? User::query()->find($transaction->sender_user_id);

        $response = self::createHttpClient()->request('POST', 'payments', [
            'json' => [
                'amount' => [
                    'currency' => strtoupper((string) $transaction->currency),
                    'value' => self::formatAmount((float) $transaction->amount),
                ],
                'description' => PaymentsServiceProvider::getPaymentDescriptionByTransaction($transaction),
                'redirectUrl' => route('payment.checkMolliePaymentStatus').'?token='.$paymentToken,
                'cancelUrl' => route('payment.checkMolliePaymentStatus').'?token='.$paymentToken,
                'webhookUrl' => route('mollie.payment.update'),
                'metadata' => [
                    'payment_token' => $paymentToken,
                    'transaction_type' => $transaction->type,
                    'sender_user_id' => $transaction->sender_user_id,
                    'recipient_user_id' => $transaction->recipient_user_id,
                    'sender_email' => $sender?->email,
                ],
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true) ?: [];
    }

    public static function getPaymentData(string $paymentId): array
    {
        $response = self::createHttpClient()->request('GET', 'payments/'.$paymentId);

        return json_decode($response->getBody()->getContents(), true) ?: [];
    }

    private static function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
