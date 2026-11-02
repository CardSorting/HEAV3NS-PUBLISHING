<?php

namespace App\Providers;

use App\Model\Transaction;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

class CryptoComServiceProvider extends ServiceProvider
{
    private const API_BASE_URI = 'https://pay.crypto.com/api/';

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
                getSetting('payments.cryptocom_secret_key'),
                '',
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    public static function createPaymentByTransaction(Transaction $transaction, string $paymentToken): array
    {
        $description = PaymentsServiceProvider::getPaymentDescriptionByTransaction($transaction);
        $currency = strtoupper((string) $transaction->currency);
        $amount = self::normalizeAmount((float) $transaction->amount, $currency);

        $response = self::createHttpClient()->request('POST', 'payments', [
            'form_params' => array_filter([
                'amount' => $amount,
                'currency' => $currency,
                'description' => $description,
                'return_url' => route('payment.checkCryptoComPaymentStatus').'?token='.$paymentToken,
                'cancel_url' => route('payment.checkCryptoComPaymentStatus').'?token='.$paymentToken,
                'notification_url' => route('cryptocom.payment.update'),
                'order_id' => $paymentToken,
            ], static fn ($value) => $value !== ''),
        ]);

        return json_decode($response->getBody()->getContents(), true) ?: [];
    }

    public static function getPaymentData(string $paymentId): array
    {
        $response = self::createHttpClient()->request('GET', 'payments/'.$paymentId);

        return json_decode($response->getBody()->getContents(), true) ?: [];
    }

    private static function normalizeAmount(float $amount, string $currency): int
    {
        $zeroDecimalCurrencies = ['JPY', 'KRW', 'VND', 'IDR'];

        return in_array($currency, $zeroDecimalCurrencies, true)
            ? (int) round($amount)
            : (int) round($amount * 100);
    }
}
