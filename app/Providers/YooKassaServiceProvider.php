<?php

namespace App\Providers;

use App\Model\Transaction;
use App\Model\User;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class YooKassaServiceProvider extends ServiceProvider
{
    private const API_BASE_URI = 'https://api.yookassa.ru/v3/';

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
            'auth' => [
                getSetting('payments.yookassa_shop_id'),
                getSetting('payments.yookassa_secret_key'),
            ],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public static function createPaymentByTransaction(Transaction $transaction, string $paymentToken): array
    {
        $sender = $transaction->sender ?? User::query()->find($transaction->sender_user_id);

        $response = self::createHttpClient()->request('POST', 'payments', [
            'headers' => [
                'Idempotence-Key' => (string) Str::uuid(),
            ],
            'json' => [
                'amount' => [
                    'value' => number_format((float) $transaction->amount, 2, '.', ''),
                    'currency' => $transaction->currency,
                ],
                'confirmation' => [
                    'type' => 'redirect',
                    'return_url' => route('payment.checkYooKassaPaymentStatus').'?token='.$paymentToken,
                ],
                'capture' => true,
                'description' => PaymentsServiceProvider::getPaymentDescriptionByTransaction($transaction),
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
}
