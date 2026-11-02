<?php

namespace App\Providers;

use App\Model\Transaction;
use App\Model\User;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

class FlutterwaveServiceProvider extends ServiceProvider
{
    private const API_BASE_URI = 'https://api.flutterwave.com/v3/';

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
                'Authorization' => 'Bearer '.getSetting('payments.flutterwave_secret_key'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    public static function createPaymentByTransaction(Transaction $transaction, string $paymentToken): array
    {
        $sender = $transaction->sender ?? User::query()->find($transaction->sender_user_id);
        $description = PaymentsServiceProvider::getPaymentDescriptionByTransaction($transaction);

        $response = self::createHttpClient()->request('POST', 'payments', [
            'json' => [
                'tx_ref' => $paymentToken,
                'amount' => (float) $transaction->amount,
                'currency' => strtoupper((string) $transaction->currency),
                'redirect_url' => route('payment.checkFlutterwavePaymentStatus'),
                'customer' => array_filter([
                    'email' => $sender?->email,
                    'name' => $sender?->name,
                ], static fn ($value) => !empty($value)),
                'customizations' => [
                    'title' => config('app.name'),
                    'description' => $description,
                ],
                'meta' => [
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

    public static function verifyTransaction(string $transactionId): array
    {
        $response = self::createHttpClient()->request('GET', 'transactions/'.$transactionId.'/verify');

        return json_decode($response->getBody()->getContents(), true) ?: [];
    }
}
