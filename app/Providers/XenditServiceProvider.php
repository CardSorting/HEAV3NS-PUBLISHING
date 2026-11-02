<?php

namespace App\Providers;

use App\Model\Transaction;
use App\Model\User;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class XenditServiceProvider extends ServiceProvider
{
    private const API_BASE_URI = 'https://api.xendit.co/';

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
                getSetting('payments.xendit_secret_key'),
                '',
            ],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public static function createPaymentSessionByTransaction(Transaction $transaction, string $paymentToken): array
    {
        $sender = $transaction->sender ?? User::query()->find($transaction->sender_user_id);
        $description = PaymentsServiceProvider::getPaymentDescriptionByTransaction($transaction);
        $currency = 'IDR';
        $countryCode = self::resolveCountryCode();
        $amount = self::normalizeAmount((float) $transaction->amount, $currency);
        [$givenNames, $surname] = self::splitName($sender->name ?? $sender->email ?? 'Customer');

        $response = self::createHttpClient()->request('POST', 'sessions', [
            'json' => [
                'reference_id' => $paymentToken,
                'session_type' => 'PAY',
                'mode' => 'PAYMENT_LINK',
                'allow_save_payment_method' => 'DISABLED',
                'capture_method' => 'AUTOMATIC',
                'amount' => $amount,
                'currency' => $currency,
                'country' => $countryCode,
                'customer' => array_filter([
                    'reference_id' => 'user_'.Str::uuid(),
                    'type' => 'INDIVIDUAL',
                    'email' => $sender->email,
                    'individual_detail' => array_filter([
                        'given_names' => $givenNames,
                        'surname' => $surname,
                    ]),
                ]),
                'items' => [
                    [
                        'reference_id' => $paymentToken.'_item',
                        'type' => 'DIGITAL_SERVICE',
                        'category' => self::resolveItemCategory($transaction),
                        'name' => $description,
                        'net_unit_amount' => $amount,
                        'quantity' => 1,
                        'currency' => $currency,
                        'description' => $description,
                    ],
                ],
                'success_return_url' => route('payment.checkXenditPaymentStatus').'?token='.$paymentToken,
                'cancel_return_url' => route('payment.checkXenditPaymentStatus').'?token='.$paymentToken,
                'description' => $description,
                'metadata' => [
                    'payment_token' => $paymentToken,
                    'transaction_type' => $transaction->type,
                    'sender_user_id' => (string) $transaction->sender_user_id,
                    'recipient_user_id' => (string) $transaction->recipient_user_id,
                    'sender_email' => $sender?->email,
                ],
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true) ?: [];
    }

    public static function getSessionData(string $sessionId): array
    {
        $response = self::createHttpClient()->request('GET', 'sessions/'.$sessionId);

        return json_decode($response->getBody()->getContents(), true) ?: [];
    }

    private static function resolveCountryCode(): string
    {
        return 'ID';
    }

    private static function resolveItemCategory(Transaction $transaction): string
    {
        return match ($transaction->type) {
            Transaction::DEPOSIT_TYPE => 'WALLET_TOP_UP',
            Transaction::TIP_TYPE,
            Transaction::CHAT_TIP_TYPE => 'TIP',
            Transaction::POST_UNLOCK,
            Transaction::MESSAGE_UNLOCK,
            Transaction::STREAM_ACCESS => 'DIGITAL_CONTENT',
            default => 'DIGITAL_SERVICE',
        };
    }

    /**
     * @return int|float
     */
    private static function normalizeAmount(float $amount, string $currency): int|float
    {
        return in_array($currency, ['IDR', 'VND'], true)
            ? (int) round($amount)
            : round($amount, 2);
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $givenNames = array_shift($parts) ?: 'Customer';
        $surname = count($parts) ? implode(' ', $parts) : null;

        return [$givenNames, $surname];
    }
}
