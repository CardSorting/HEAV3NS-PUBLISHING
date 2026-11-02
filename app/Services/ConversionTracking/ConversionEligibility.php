<?php

namespace App\Services\ConversionTracking;

use App\Model\Transaction;

class ConversionEligibility
{
    public const SUBSCRIPTION_TYPES = [
        Transaction::ONE_MONTH_SUBSCRIPTION,
        Transaction::THREE_MONTHS_SUBSCRIPTION,
        Transaction::SIX_MONTHS_SUBSCRIPTION,
        Transaction::YEARLY_SUBSCRIPTION,
    ];
    public const NON_SUBSCRIPTION_TYPES = [
        Transaction::POST_UNLOCK, Transaction::MESSAGE_UNLOCK, Transaction::STREAM_ACCESS,
        Transaction::TIP_TYPE, Transaction::CHAT_TIP_TYPE,
    ];
    public const PURCHASE_TYPES = [...self::SUBSCRIPTION_TYPES, ...self::NON_SUBSCRIPTION_TYPES];
    public const PROVIDERS = [Transaction::CREDIT_PROVIDER, ...Transaction::ALLOWED_PAYMENT_PROVIDERS];
    public const SUBSCRIPTION_PROVIDERS = [
        Transaction::CREDIT_PROVIDER, Transaction::STRIPE_PROVIDER, Transaction::PAYPAL_PROVIDER,
        Transaction::CCBILL_PROVIDER, Transaction::VEROTEL_PROVIDER,
    ];

    public static function purchaseType(string $transactionType): ?string
    {
        if (in_array($transactionType, [...self::SUBSCRIPTION_TYPES, Transaction::SUBSCRIPTION_RENEWAL], true)) {
            return 'subscription';
        }
        return match ($transactionType) {
            Transaction::POST_UNLOCK => 'post_unlock',
            Transaction::MESSAGE_UNLOCK => 'message_unlock',
            Transaction::STREAM_ACCESS => 'stream_access',
            Transaction::TIP_TYPE, Transaction::CHAT_TIP_TYPE => 'tip',
            default => null,
        };
    }

    public static function isPaidPurchase(Transaction $transaction): bool
    {
        $amount = (float) $transaction->amount;
        $category = self::purchaseType((string) $transaction->type);
        $hasTarget = match ($category) {
            'subscription' => (int) $transaction->subscription_id > 0
                && in_array($transaction->payment_provider, self::SUBSCRIPTION_PROVIDERS, true),
            'post_unlock' => (int) $transaction->post_id > 0,
            'message_unlock' => (int) $transaction->user_message_id > 0,
            'stream_access' => (int) $transaction->stream_id > 0,
            'tip' => true,
            default => false,
        };

        return $transaction->status === Transaction::APPROVED_STATUS && is_finite($amount) && $amount > 0
            && (bool) $transaction->conversion_checkout && $hasTarget
            && (int) $transaction->sender_user_id > 0 && (int) $transaction->recipient_user_id > 0
            && (int) $transaction->sender_user_id !== (int) $transaction->recipient_user_id
            && in_array($transaction->payment_provider, self::PROVIDERS, true)
            && in_array($transaction->type, [...self::PURCHASE_TYPES, Transaction::SUBSCRIPTION_RENEWAL], true);
    }

    public static function permits(Transaction $transaction, ConversionConfiguration $configuration, bool $hasPriorPurchase): bool
    {
        if (!self::isPaidPurchase($transaction) || !$configuration->enabled() || $configuration->method() === ''
            || !preg_match('/\A[A-Z]{3}\z/', strtoupper((string) $transaction->currency))
            || !in_array(self::purchaseType($transaction->type), $configuration->selectedPurchaseTypes(), true)
            || !$configuration->creatorAllowed((int) $transaction->recipient_user_id)) {
            return false;
        }

        if ($transaction->type === Transaction::SUBSCRIPTION_RENEWAL && !$configuration->includesRenewals()) {
            return false;
        }

        return match ($configuration->countingMode()) {
            'first_site', 'first_creator' => !$hasPriorPurchase,
            'every_purchase' => true,
            default => false,
        };
    }

    public static function providerEventKey(Transaction $transaction, ?string $providerEventId = null): ?string
    {
        $renewal = $transaction->type === Transaction::SUBSCRIPTION_RENEWAL;
        $identity = $providerEventId;
        if ($identity === null || $identity === '') {
            $identity = match ($transaction->payment_provider) {
                Transaction::STRIPE_PROVIDER, Transaction::OXXO_PROVIDER => $transaction->stripe_transaction_id ?: ($renewal ? null : $transaction->stripe_session_id),
                Transaction::PAYPAL_PROVIDER => $transaction->paypal_transaction_id ?: ($renewal ? null : $transaction->paypal_transaction_token),
                Transaction::CCBILL_PROVIDER => $renewal ? null : $transaction->ccbill_transaction_id,
                Transaction::VEROTEL_PROVIDER => $renewal ? null : $transaction->verotel_sale_id,
                Transaction::YOOKASSA_PROVIDER => $transaction->yookassa_payment_id,
                Transaction::MOLLIE_PROVIDER => $transaction->mollie_payment_id,
                Transaction::FLUTTERWAVE_PROVIDER => $transaction->flutterwave_payment_id,
                Transaction::COINGATE_PROVIDER => $transaction->coingate_order_id,
                Transaction::XENDIT_PROVIDER => $transaction->xendit_payment_id,
                Transaction::PADDLE_PROVIDER => $transaction->paddle_transaction_id,
                Transaction::CRYPTOCOM_PROVIDER => $transaction->cryptocom_payment_id,
                Transaction::NOWPAYMENTS_PROVIDER => $transaction->nowpayments_payment_id,
                Transaction::PAYSTACK_PROVIDER => $transaction->paystack_payment_token,
                Transaction::MERCADO_PROVIDER => $transaction->mercado_payment_id,
                Transaction::RAZORPAY_PROVIDER => $transaction->razorpay_payment_id,
                default => null,
            };
        }

        // Some recurring gateways store a subscription ID, not a unique charge ID.
        // Callers must supply a charge/period identity; otherwise do not report that renewal.
        if (($identity === null || $identity === '') && ($renewal || $transaction->payment_provider !== Transaction::CREDIT_PROVIDER)) {
            return null;
        }
        $identity = $identity ?: 'local-transaction:'.$transaction->getKey();
        $provider = $transaction->payment_provider === Transaction::OXXO_PROVIDER ? Transaction::STRIPE_PROVIDER : $transaction->payment_provider;

        return hash('sha256', $provider.'|'.$identity);
    }
}
