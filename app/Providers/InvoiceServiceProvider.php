<?php

namespace App\Providers;

use App\Model\Invoice;
use App\Model\Transaction;
use App\Model\User;
use App\Services\Payments\CheckoutBilling;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class InvoiceServiceProvider extends ServiceProvider
{
    public const DEFAULT_DATE_FORMAT = 'Y-m-d';

    public static function getDateFormatOptions(): array
    {
        return [
            'Y-m-d' => '2026-11-23 (YYYY-MM-DD)',
            'd/m/Y' => '23/11/2026 (DD/MM/YYYY)',
            'm/d/Y' => '11/23/2026 (MM/DD/YYYY)',
            'd.m.Y' => '23.11.2026 (DD.MM.YYYY)',
            'd-m-Y' => '23-11-2026 (DD-MM-YYYY)',
            'd M Y' => '23 Nov 2026',
            'F j, Y' => 'November 23, 2026',
        ];
    }

    public static function getDateFormat(): string
    {
        $format = getSetting('payments.invoices_date_format', self::DEFAULT_DATE_FORMAT);

        return is_string($format) && array_key_exists($format, self::getDateFormatOptions())
            ? $format
            : self::DEFAULT_DATE_FORMAT;
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     * @return void
     */
    public function boot()
    {
    }

    public static function createInvoiceByTransaction($transaction)
    {
        if (!getSetting('payments.invoices_enabled') || !CheckoutBilling::collectsDetails()) {
            return null;
        }

        $data = [];
        $invoice = null;
        if ($transaction != null) {
            if($transaction->invoice_id != null){
                return null;
            }

            $senderUser = User::query()->where(['id' => $transaction->sender_user_id])->first();

            if ($senderUser != null) {
                foreach (CheckoutBilling::FIELDS as $field) {
                    if (!is_string($senderUser->$field) || trim($senderUser->$field) === '') {
                        Log::channel('payments')->warning('Skipped invoice generation: buyer billing details are incomplete.', [
                            'transaction_id' => $transaction->id,
                        ]);

                        return null;
                    }
                }

                $billingDetails = [];
                $billingDetails['senderName'] = getSetting('payments.invoices_sender_name');
                $billingDetails['senderAddress'] = getSetting('payments.invoices_sender_country_name');
                $billingDetails['senderCountry'] = getSetting('payments.invoices_sender_street_address');
                $billingDetails['senderState'] = getSetting('payments.invoices_sender_state_name');
                $billingDetails['senderPostcode'] = getSetting('payments.invoices_sender_city_name');
                $billingDetails['senderCity'] = getSetting('payments.invoices_sender_postcode');
                $billingDetails['senderCompanyNumber'] = getSetting('payments.invoices_sender_company_number');
                $billingDetails['receiverFirstName'] = $senderUser->first_name;
                $billingDetails['receiverLastName'] = $senderUser->last_name;
                $billingDetails['receiverCountryName'] = $senderUser->country;
                $billingDetails['receiverState'] = $senderUser->state;
                $billingDetails['receiverCity'] = $senderUser->city;
                $billingDetails['receiverPostcode'] = $senderUser->postcode;
                $billingDetails['receiverBillingAddress'] = $senderUser->billing_address;
                $data['subtotal'] = $transaction->amount;
                $data['taxesTotalAmount'] = 0.00;
                if ($transaction->taxes != null) {
                    $taxes = $transaction->taxes;
                    $data['taxes'] = $taxes;
                    $data['subtotal'] = $taxes['subtotal'];
                    $data['taxesTotalAmount'] = $taxes['taxesTotalAmount'];
                }
                $data['billingDetails'] = $billingDetails;
                $data['totalAmount'] = $transaction->amount;
                $data['dueDate'] = $transaction->created_at;
                $data['invoicePrefix'] = getSetting('payments.invoices_prefix');

                $latestInvoice = Invoice::orderBy('id', 'desc')->first();
                if ($latestInvoice != null) {
                    $invoiceId = intval($latestInvoice->invoice_id) + 1;
                } else {
                    $invoiceId = 1;
                }

                $invoice = Invoice::create([
                    'invoice_id' => $invoiceId,
                    'data' => json_encode($data),
                ]);
            }
        }

        return $invoice;
    }

    /**
     * Handles invoice payment description by transaction type.
     *
     * @param $transaction
     * @return array|\Illuminate\Contracts\Translation\Translator|string|null
     */
    public static function getInvoiceDescriptionByTransaction($transaction)
    {
        $description = __('One month subscription to access username profile');

        if ($transaction != null && $transaction->type != null) {
            $transactionType = $transaction->type;
            if ($transactionType === Transaction::ONE_MONTH_SUBSCRIPTION
                || $transactionType === Transaction::THREE_MONTHS_SUBSCRIPTION
                || $transactionType === Transaction::SIX_MONTHS_SUBSCRIPTION
                || $transactionType === Transaction::YEARLY_SUBSCRIPTION) {
                $subscriptionMonthlyInterval = PaymentsServiceProvider::getSubscriptionMonthlyIntervalByTransactionType($transactionType);
                $subscriptionInterval = trans_choice('months', $subscriptionMonthlyInterval, ['number' => $subscriptionMonthlyInterval]);
                $description = __(
                    ':subscriptionInterval subscription to access :username profile',
                    [
                        'subscriptionInterval' => $subscriptionInterval,
                        'username' => $transaction->receiver->name,
                    ]
                );
            }
            else{
                $description = ucfirst(__($transaction->type));
            }
        }

        return $description;
    }
}
