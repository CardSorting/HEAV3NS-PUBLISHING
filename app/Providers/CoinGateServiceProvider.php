<?php

namespace App\Providers;

use App\Model\Transaction;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

class CoinGateServiceProvider extends ServiceProvider
{
    private const LIVE_API_BASE_URI = 'https://api.coingate.com/api/v2/';
    private const SANDBOX_API_BASE_URI = 'https://api-sandbox.coingate.com/api/v2/';

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
            'base_uri' => self::resolveApiBaseUri(),
            'headers' => [
                'Authorization' => 'Token '.getSetting('payments.coingate_api_token'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public static function createOrderByTransaction(Transaction $transaction, string $paymentToken): array
    {
        $description = PaymentsServiceProvider::getPaymentDescriptionByTransaction($transaction);

        $response = self::createHttpClient()->request('POST', 'orders', [
            'json' => [
                'order_id' => $paymentToken,
                'price_amount' => (float) $transaction->amount,
                'price_currency' => strtoupper((string) $transaction->currency),
                'title' => self::truncate($description, 150),
                'description' => self::truncate($description, 500),
                'callback_url' => route('coingate.payment.update'),
                'cancel_url' => route('payment.checkCoinGatePaymentStatus').'?token='.$paymentToken,
                'success_url' => route('payment.checkCoinGatePaymentStatus').'?token='.$paymentToken,
                'token' => $paymentToken,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true) ?: [];
    }

    public static function getOrderData(string $orderId): array
    {
        $response = self::createHttpClient()->request('GET', 'orders/'.$orderId);

        return json_decode($response->getBody()->getContents(), true) ?: [];
    }

    private static function resolveApiBaseUri(): string
    {
        return getSetting('payments.coingate_mode') === 'sandbox'
            ? self::SANDBOX_API_BASE_URI
            : self::LIVE_API_BASE_URI;
    }

    private static function truncate(string $value, int $length): string
    {
        return mb_strlen($value) <= $length
            ? $value
            : rtrim(mb_substr($value, 0, $length - 3)).'...';
    }
}
