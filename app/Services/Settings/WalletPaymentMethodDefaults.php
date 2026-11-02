<?php

namespace App\Services\Settings;

use App\Model\ManualPaymentMethod;
use App\Model\Transaction;
use App\Model\Withdrawal;
use App\Providers\PaymentsServiceProvider;

class WalletPaymentMethodDefaults
{
    public function lastWithdrawalMethod(int $userId): ?string
    {
        $withdrawal = Withdrawal::query()->where('user_id', $userId)
            ->latest('created_at')->latest('id')
            ->first(['manual_method_id', 'payout_method_key', 'payment_method']);

        if (!$withdrawal) {
            return null;
        }
        if ($withdrawal->manual_method_id) {
            return (string) $withdrawal->manual_method_id;
        }

        // Historical requests may use a prefixed ID or an original template key.
        $method = $withdrawal->payout_method_key ?: $withdrawal->payment_method;
        if (preg_match('/^manual_([1-9][0-9]*)$/D', (string) $method, $matches)) {
            return $matches[1];
        }
        if (PaymentsServiceProvider::isStripeConnectWithdrawalMethod($method)) {
            return PaymentsServiceProvider::WITHDRAWAL_METHOD_STRIPE_CONNECT;
        }

        $methodKey = PaymentsServiceProvider::getWithdrawalMethodKey($method);
        if (!in_array($methodKey, ['bank_transfer', 'paypal', 'crypto', 'pix', 'custom'], true)) {
            return null;
        }

        $manualMethod = ManualPaymentMethod::enabledFor(ManualPaymentMethod::WITHDRAWAL)->firstWhere('key', $methodKey);

        return $manualMethod ? (string) $manualMethod->id : null;
    }

    public function lastDepositMethod(int $userId): ?array
    {
        $transaction = Transaction::query()->where('sender_user_id', $userId)
            ->where('type', Transaction::DEPOSIT_TYPE)
            ->latest('created_at')->latest('id')
            ->first(['payment_provider', 'manual_method_id']);

        return $transaction ? [
            'provider' => $transaction->payment_provider,
            'manual_method_id' => $transaction->manual_method_id,
        ] : null;
    }

    public function depositOptionId(array $enabledMethods, ?array $preferred): ?string
    {
        if (!$preferred) {
            return null;
        }

        foreach ($enabledMethods as $method) {
            if ($method['value'] === 'payment-'.$preferred['provider']
                && (!array_key_exists('manual_method_id', $method)
                    || (string) $method['manual_method_id'] === (string) ($preferred['manual_method_id'] ?? null))) {
                return $method['id'];
            }
        }

        return null;
    }
}
