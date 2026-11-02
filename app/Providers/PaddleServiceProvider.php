<?php

namespace App\Providers;

use App\Model\Country;
use App\Model\Transaction;
use App\Model\User;
use App\Services\Payments\CheckoutBilling;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class PaddleServiceProvider extends ServiceProvider
{
    private const LIVE_API_BASE_URI = 'https://api.paddle.com/';
    private const SANDBOX_API_BASE_URI = 'https://sandbox-api.paddle.com/';

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
        $apiKey = (string) getSetting('payments.paddle_api_key');

        return new Client([
            'base_uri' => self::resolveApiBaseUri($apiKey),
            'headers' => [
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    public static function createTransactionByTransaction(Transaction $transaction, string $paymentToken): array
    {
        $sender = $transaction->sender ?? User::query()->find($transaction->sender_user_id);
        $description = PaymentsServiceProvider::getPaymentDescriptionByTransaction($transaction);
        $currency = strtoupper((string) $transaction->currency);
        $payload = [
            'collection_mode' => 'automatic',
            'currency_code' => $currency,
            'items' => [
                [
                    'quantity' => 1,
                    'price' => [
                        'name' => $description,
                        'description' => $description,
                        'unit_price' => [
                            'amount' => self::formatAmount($transaction->amount, $currency),
                            'currency_code' => $currency,
                        ],
                        'product' => [
                            'name' => $description,
                            'tax_category' => 'standard',
                            'description' => $description,
                        ],
                    ],
                ],
            ],
            'custom_data' => [
                'payment_token' => $paymentToken,
                'transaction_type' => $transaction->type,
                'sender_user_id' => $transaction->sender_user_id,
                'recipient_user_id' => $transaction->recipient_user_id,
                'sender_email' => $sender->email,
            ],
        ];

        $response = self::createHttpClient()->request('POST', 'transactions', [
            'json' => $payload,
        ]);

        return json_decode($response->getBody()->getContents(), true)['data'] ?? [];
    }

    public static function getTransactionData(string $transactionId): array
    {
        $response = self::createHttpClient()->request('GET', 'transactions/'.$transactionId);

        return json_decode($response->getBody()->getContents(), true)['data'] ?? [];
    }

    public static function generateHostedCheckoutUrl(Transaction $transaction, array $transactionData): string
    {
        $hostedCheckoutUrl = trim((string) getSetting('payments.paddle_hosted_checkout_url'));
        $transactionId = data_get($transactionData, 'id');

        if (!$hostedCheckoutUrl) {
            throw new \RuntimeException('Paddle hosted checkout URL is not configured.');
        }

        if (!$transactionId) {
            throw new \RuntimeException('Paddle transaction ID missing from create transaction response.');
        }

        $sender = $transaction->sender ?? User::query()->find($transaction->sender_user_id);
        $query = [
            'transaction_id' => $transactionId,
        ];

        $paymentToken = $transaction->paddle_transaction_token ?: data_get($transactionData, 'custom_data.payment_token');
        if ($paymentToken) {
            $query['token'] = (string) $paymentToken;
        }

        if (!empty($sender->email)) {
            $query['user_email'] = $sender->email;
        }

        if (CheckoutBilling::collectsDetails()) {
            $countryCode = self::resolveCountryCode($sender?->country);
            if ($countryCode) {
                $query['country_code'] = $countryCode;
            }

            $postalCode = trim((string) ($sender->postcode ?? ''));
            if ($postalCode !== '') {
                $query['postal_code'] = $postalCode;
            }
        }

        $separator = str_contains($hostedCheckoutUrl, '?') ? '&' : '?';

        return $hostedCheckoutUrl.$separator.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private static function resolveCountryCode(?string $country): ?string
    {
        if (!$country) {
            return null;
        }

        $trimmed = trim($country);
        if (strlen($trimmed) === 2) {
            return strtoupper($trimmed);
        }

        $countryCode = Country::query()
            ->where('name', $trimmed)
            ->value('country_code');

        return $countryCode ? strtoupper((string) $countryCode) : null;
    }

    private static function formatAmount(float $amount, string $currency): string
    {
        $zeroDecimalCurrencies = ['JPY', 'KRW', 'VND', 'IDR'];

        if (in_array($currency, $zeroDecimalCurrencies, true)) {
            return (string) (int) round($amount);
        }

        return (string) (int) round($amount * 100);
    }

    private static function resolveApiBaseUri(string $apiKey): string
    {
        return str_contains($apiKey, '_sdbx_')
            ? self::SANDBOX_API_BASE_URI
            : self::LIVE_API_BASE_URI;
    }
}
