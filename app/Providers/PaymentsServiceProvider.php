<?php

namespace App\Providers;

use App\Model\ManualPaymentMethod;
use App\Model\Tax;
use App\Model\Transaction;
use App\Model\Withdrawal;
use App\Model\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Various Payments and withdrawals related actions.
 */
class PaymentsServiceProvider extends ServiceProvider
{
    public const WITHDRAWAL_METHOD_BANK_TRANSFER = 'bank_transfer';
    public const WITHDRAWAL_METHOD_MANUAL = 'manual';
    public const WITHDRAWAL_METHOD_PAYPAL = 'paypal';
    public const WITHDRAWAL_METHOD_CRYPTO = 'crypto';
    public const WITHDRAWAL_METHOD_PIX = 'pix';
    public const WITHDRAWAL_METHOD_CUSTOM = 'custom';
    public const WITHDRAWAL_METHOD_STRIPE_CONNECT = 'stripe_connect';

    /**
     * Get subscription monthly interval.
     *
     * @param $transactionType
     * @return int
     */
    public static function getSubscriptionMonthlyIntervalByTransactionType($transactionType)
    {
        $interval = 1;
        if ($transactionType != null) {
            switch ($transactionType) {
                case Transaction::YEARLY_SUBSCRIPTION:
                    $interval = 12;
                    break;
                case Transaction::THREE_MONTHS_SUBSCRIPTION:
                    $interval = 3;
                    break;
                case Transaction::SIX_MONTHS_SUBSCRIPTION:
                    $interval = 6;
                    break;
                default:
                    $interval = 1;
                    break;
            }
        }

        return $interval;
    }

    /**
     * Get withdrawal limit amounts.
     * @return string
     */
    public static function getWithdrawalAmountLimitations()
    {
        $withdrawalsMinAmount = SettingsServiceProvider::getWebsiteFormattedAmount('20');
        if (getSetting('payments.withdrawal_min_amount') != null && getSetting('payments.withdrawal_min_amount') > 0) {
            $withdrawalsMinAmount = SettingsServiceProvider::getWebsiteFormattedAmount(getSetting('payments.withdrawal_min_amount'));
        }
        $withdrawalsMaxAmount = SettingsServiceProvider::getWebsiteFormattedAmount('500');
        if (getSetting('payments.withdrawal_max_amount') != null && getSetting('payments.withdrawal_max_amount') > 0) {
            $withdrawalsMaxAmount = SettingsServiceProvider::getWebsiteFormattedAmount(getSetting('payments.withdrawal_max_amount'));
        }

        return __('Amount').' ('.$withdrawalsMinAmount.' min, '.$withdrawalsMaxAmount.' max)';
    }

    /**
     * Get deposit limit amounts.
     * @return string
     */
    public static function getDepositLimitAmounts()
    {
        $depositMinAmount = SettingsServiceProvider::getWebsiteFormattedAmount('5');
        if (getSetting('payments.deposit_min_amount') != null && getSetting('payments.deposit_min_amount') > 0) {
            $depositMinAmount = SettingsServiceProvider::getWebsiteFormattedAmount(getSetting('payments.deposit_min_amount'));
        }
        $depositMaxAmount = SettingsServiceProvider::getWebsiteFormattedAmount('500');
        if (getSetting('payments.deposit_max_amount') != null && getSetting('payments.deposit_max_amount') > 0) {
            $depositMaxAmount = SettingsServiceProvider::getWebsiteFormattedAmount(getSetting('payments.deposit_max_amount'));
        }

        return __('Amount').' ('.$depositMinAmount.' min, '.$depositMaxAmount.' max)';
    }

    /**
     * Get withdrawals minimum amount.
     * @return \Illuminate\Config\Repository|int|mixed|null
     */
    public static function getWithdrawalMinimumAmount() {
        return
            getSetting('payments.withdrawal_min_amount') != null
            && getSetting('payments.withdrawal_min_amount') > 0
                ? getSetting('payments.withdrawal_min_amount') : 20;
    }

    /**
     * Get withdrawals maximum amount.
     * @return \Illuminate\Config\Repository|int|mixed|null
     */
    public static function getWithdrawalMaximumAmount() {
        return
            getSetting('payments.withdrawal_max_amount') != null
            && getSetting('payments.withdrawal_max_amount') > 0
                ? getSetting('payments.withdrawal_max_amount') : 500;
    }

    /**
     * Get deposit minimum amount.
     * @return \Illuminate\Config\Repository|int|mixed|null
     */
    public static function getDepositMinimumAmount() {
        return
            getSetting('payments.deposit_min_amount') != null
            && getSetting('payments.deposit_min_amount') > 0
                ? getSetting('payments.deposit_min_amount') : 5;
    }

    /**
     * Get deposit maximum amount.
     * @return \Illuminate\Config\Repository|int|mixed|null
     */
    public static function getDepositMaximumAmount() {
        return
            getSetting('payments.deposit_max_amount') != null
            && getSetting('payments.deposit_max_amount') > 0
                ? getSetting('payments.deposit_max_amount') : 500;
    }

    /**
     * Creates transaction for an approved withdrawal.
     * @param $withdrawal
     */
    public static function createTransactionForWithdrawal($withdrawal) {
        try{
            if($withdrawal->status === Withdrawal::APPROVED_STATUS){
                $data = [];
                $data['recipient_user_id'] = $withdrawal->user_id;
                $data['sender_user_id'] = $withdrawal->user_id;
                $data['type'] = Transaction::WITHDRAWAL_TYPE;
                $data['amount'] = $withdrawal->amount - $withdrawal->fee;
                $data['payment_provider'] = $withdrawal->payment_method;
                $data['currency'] = SettingsServiceProvider::getAppCurrencyCode();
                $data['status'] = Transaction::APPROVED_STATUS;

                Transaction::create($data);
            }
        } catch (\Exception $e){
            Log::channel('withdrawals')->error($e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch withdrawals allowed payment methods from admin panel.
     * @return array
     */
    public static function getWithdrawalsAllowedPaymentMethods() {
        return array_values(self::getWithdrawalMethodOptions());
    }

    public static function getAvailableWithdrawalManualMethodOptions(): array
    {
        return [
            self::WITHDRAWAL_METHOD_BANK_TRANSFER => __('Bank transfer'),
            self::WITHDRAWAL_METHOD_PAYPAL => __('PayPal'),
            self::WITHDRAWAL_METHOD_CRYPTO => __('Crypto'),
            self::WITHDRAWAL_METHOD_PIX => __('Pix'),
            self::WITHDRAWAL_METHOD_CUSTOM => __('Custom'),
        ];
    }

    public static function normalizeWithdrawalManualMethodKeys(array|string|null $methods): array
    {
        $values = is_array($methods)
            ? $methods
            : collect(explode(',', (string) $methods))
                ->map(fn ($value) => trim($value))
                ->filter()
                ->values()
                ->all();

        $allowedKeys = array_keys(self::getAvailableWithdrawalManualMethodOptions());

        return collect($values)
            ->map(fn ($value) => self::getWithdrawalMethodKey($value))
            ->filter(fn ($value) => in_array($value, $allowedKeys, true))
            ->unique()
            ->values()
            ->all();
    }

    public static function getConfiguredWithdrawalManualMethodKeys(): array
    {
        $configuredMethods = self::normalizeWithdrawalManualMethodKeys(getSetting('payments.withdrawal_payment_methods'));

        return $configuredMethods ?: [self::WITHDRAWAL_METHOD_CUSTOM];
    }

    public static function getWithdrawalMethodOptions(): array
    {
        $options = ManualPaymentMethod::enabledFor(ManualPaymentMethod::WITHDRAWAL)
            ->pluck('name', 'id')->all();

        if (getSetting('payments.withdrawal_enable_stripe_connect')) {
            $options[self::WITHDRAWAL_METHOD_STRIPE_CONNECT] = self::getWithdrawalMethodLabel(self::WITHDRAWAL_METHOD_STRIPE_CONNECT);
        }

        return $options;
    }

    public static function getWithdrawalMethodLabel(?string $method): string
    {
        // Read existing withdrawal records that used a prefixed selector value.
        if (preg_match('/^manual_([1-9][0-9]*)$/D', (string) $method, $matches)) {
            return ManualPaymentMethod::findForDirection($matches[1], ManualPaymentMethod::WITHDRAWAL, false)->name ?? __('Manual payout');
        }

        return match (self::getWithdrawalMethodKey($method)) {
            self::WITHDRAWAL_METHOD_MANUAL => __('Manual payout'),
            self::WITHDRAWAL_METHOD_BANK_TRANSFER => __('Bank transfer'),
            self::WITHDRAWAL_METHOD_PAYPAL => __('PayPal'),
            self::WITHDRAWAL_METHOD_CRYPTO => __('Crypto'),
            self::WITHDRAWAL_METHOD_PIX => __('Pix'),
            self::WITHDRAWAL_METHOD_STRIPE_CONNECT => __('Stripe Connect'),
            self::WITHDRAWAL_METHOD_CUSTOM => __('Custom'),
            default => __('Custom'),
        };
    }

    public static function getWithdrawalMethodKey(?string $method): string
    {
        $normalized = Str::of((string) $method)->trim()->lower()->replace(['-', ' '], '_')->value();

        if (preg_match('/^manual_[1-9][0-9]*$/', $normalized)) {
            return $normalized;
        }

        return match ($normalized) {
            'manual' => self::WITHDRAWAL_METHOD_MANUAL,
            'bank_transfer', 'bank', 'wire_transfer' => self::WITHDRAWAL_METHOD_BANK_TRANSFER,
            'paypal', 'pay_pal' => self::WITHDRAWAL_METHOD_PAYPAL,
            'crypto', 'cryptocurrency' => self::WITHDRAWAL_METHOD_CRYPTO,
            'pix' => self::WITHDRAWAL_METHOD_PIX,
            'stripe_connect', 'stripe' => self::WITHDRAWAL_METHOD_STRIPE_CONNECT,
            'other', 'custom', '' => self::WITHDRAWAL_METHOD_CUSTOM,
            default => self::WITHDRAWAL_METHOD_CUSTOM,
        };
    }

    public static function isBankTransferMethod(?string $method): bool
    {
        return self::getWithdrawalMethodKey($method) === self::WITHDRAWAL_METHOD_BANK_TRANSFER;
    }

    public static function isStripeConnectWithdrawalMethod(?string $method): bool
    {
        return self::getWithdrawalMethodKey($method) === self::WITHDRAWAL_METHOD_STRIPE_CONNECT
            || $method === Withdrawal::STRIPE_CONNECT_METHOD;
    }

    /**
     * Checks if CCBill keys are provided in admin panel.
     * @return bool
     */
    public static function ccbillCredentialsProvided() {
        return getSetting('payments.ccbill_account_number') && (getSetting('payments.ccbill_subaccount_number_recurring')
                || getSetting('payments.ccbill_subaccount_number_one_time'))
            && getSetting('payments.ccbill_flex_form_id') && getSetting('payments.ccbill_salt_key') && !getSetting('payments.ccbill_checkout_disabled');
    }

    /**
     * Calculate taxes for transaction.
     * @param $transaction
     * @return float[]
     */
    public static function calculateTaxesForTransaction($transaction): array
    {
        $totals = [
            'inclusiveTaxesAmount' => 0.00,
            'exclusiveTaxesAmount' => 0.00,
            'fixedTaxesAmount' => 0.00,
        ];

        if (!$transaction) {
            return $totals;
        }

        // Accept: Eloquent model ($transaction->taxes) or array-access ($transaction['taxes'])
        $rawTaxes = $transaction->taxes ?? ($transaction['taxes'] ?? null);

        // Handle legacy JSON string OR new array-cast
        if (is_string($rawTaxes)) {
            $rawTaxes = json_decode($rawTaxes, true);
        }

        if (!is_array($rawTaxes)) {
            return $totals;
        }

        $lines = $rawTaxes['data'] ?? null;
        if (!is_array($lines)) {
            return $totals;
        }

        foreach ($lines as $tax) {
            if (!is_array($tax)) {
                continue;
            }

            $type = $tax['taxType'] ?? null;
            $amount = $tax['taxAmount'] ?? null;

            if (!$type || $amount === null) {
                continue;
            }

            $amountFloat = (float) $amount;

            switch ($type) {
                case Tax::INCLUSIVE_TYPE:
                    $totals['inclusiveTaxesAmount'] += $amountFloat;
                    break;
                case Tax::EXCLUSIVE_TYPE:
                    $totals['exclusiveTaxesAmount'] += $amountFloat;
                    break;
                case Tax::FIXED_TYPE:
                    $totals['fixedTaxesAmount'] += $amountFloat;
                    break;
            }
        }

        // Normalize to 2dp to avoid float noise
        foreach ($totals as $k => $v) {
            $totals[$k] = round($v, 2);
        }

        return $totals;
    }

    /**
     * @param $transaction
     * @return float
     */
    public static function getTransactionAmountWithTaxesDeducted($transaction): float
    {
        if (!$transaction) {
            return 0.0;
        }

        $rawTaxes = $transaction->taxes ?? ($transaction['taxes'] ?? null);
        if (is_string($rawTaxes)) {
            $rawTaxes = json_decode($rawTaxes, true);
        }

        // Prefer netSubtotal if stored (new format)
        if (is_array($rawTaxes) && isset($rawTaxes['netSubtotal'])) {
            return max(0.0, round((float) $rawTaxes['netSubtotal'], 2));
        }

        // Fallback for old format
        $amount = (float) ($transaction->amount ?? 0);
        $taxTotals = self::calculateTaxesForTransaction($transaction);

        $amount -= $taxTotals['inclusiveTaxesAmount'];
        $amount -= $taxTotals['exclusiveTaxesAmount'];
        $amount -= $taxTotals['fixedTaxesAmount'];

        return max(0.0, round($amount, 2));
    }

    public static function getPaymentDescriptionByTransaction($transaction): string
    {
        if (!$transaction) {
            return 'Default payment description';
        }

        $amount = SettingsServiceProvider::getWebsiteFormattedAmount((float) $transaction->amount);

        $recipientName = null;
        if (!empty($transaction->recipient_user_id)) {
            $recipientName = User::query()
                ->where('id', $transaction->recipient_user_id)
                ->value('name'); // single column, no model hydration
        }

        // Subscriptions
        if (self::isSubscriptionPayment($transaction->type)) {
            $recipient = $recipientName ?: __('creator');
            return $recipient.' '.__('for').' '.$amount;
        }

        // One-time / other types
        return match ($transaction->type) {
            Transaction::DEPOSIT_TYPE => $amount.' '.__('wallet top-up'),

            Transaction::TIP_TYPE, Transaction::CHAT_TIP_TYPE => (
                $recipientName
                ? $amount.' '.__('tip').' '.__('for').' '.$recipientName
                : $amount.' '.__('tip')
            ),

            Transaction::POST_UNLOCK => __('Unlock post for').' '.$amount,

            Transaction::STREAM_ACCESS => __('Join streaming for').' '.$amount,

            Transaction::MESSAGE_UNLOCK => __('Unlock message for').' '.$amount,

            default => 'Default payment description',
        };
    }

    public static function isSubscriptionPayment($transactionType): bool
    {
        return $transactionType != null
            && ($transactionType === Transaction::SIX_MONTHS_SUBSCRIPTION
                || $transactionType === Transaction::THREE_MONTHS_SUBSCRIPTION
                || $transactionType === Transaction::ONE_MONTH_SUBSCRIPTION
                || $transactionType === Transaction::YEARLY_SUBSCRIPTION);
    }
}
