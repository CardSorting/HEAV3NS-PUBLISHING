<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach ($this->getSettings() as $spatieKey => $meta) {
            $voyagerKey = $meta['voyager_key'];
            $value = DB::table('settings')->where('key', $voyagerKey)->value('value');

            if (is_null($value)) {
                $value = $meta['default'] ?? null;
            }

            $this->migrator->add("payments.$spatieKey", $value);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->getSettings()) as $key) {
            $this->migrator->delete("payments.$key");
        }
    }

    protected function getSettings(): array
    {
        return [
            // General config
            'invoices_enabled' => ['voyager_key' => 'payments.invoices_enabled'],
            'invoices_sender_name' => ['voyager_key' => 'payments.invoices_sender_name'],
            'invoices_sender_country_name' => ['voyager_key' => 'payments.invoices_sender_country_name'],
            'invoices_sender_street_address' => ['voyager_key' => 'payments.invoices_sender_street_address'],
            'invoices_sender_state_name' => ['voyager_key' => 'payments.invoices_sender_state_name'],
            'invoices_sender_city_name' => ['voyager_key' => 'payments.invoices_sender_city_name'],
            'invoices_sender_postcode' => ['voyager_key' => 'payments.invoices_sender_postcode'],
            'invoices_sender_company_number' => ['voyager_key' => 'payments.invoices_sender_company_number'],
            'invoices_prefix' => ['voyager_key' => 'payments.invoices_prefix'],

            // Currencies
            'currency_code' => ['voyager_key' => 'payments.currency_code'],
            'currency_symbol' => ['voyager_key' => 'payments.currency_symbol'],
            'currency_position' => ['voyager_key' => 'payments.currency_position'],

            // Stripe
            'stripe_public_key' => ['voyager_key' => 'payments.stripe_public_key'],
            'stripe_secret_key' => ['voyager_key' => 'payments.stripe_secret_key'],
            'stripe_webhooks_secret' => ['voyager_key' => 'payments.stripe_webhooks_secret'],
            'stripe_checkout_disabled' => ['voyager_key' => 'payments.stripe_checkout_disabled'],
            'stripe_recurring_disabled' => ['voyager_key' => 'payments.stripe_recurring_disabled'],
            'stripe_oxxo_provider_enabled' => ['voyager_key' => 'payments.stripe_oxxo_provider_enabled'],
            'stripe_ideal_provider_enabled' => ['voyager_key' => 'payments.stripe_ideal_provider_enabled'],
            'stripe_blik_provider_enabled' => ['voyager_key' => 'payments.stripe_blik_provider_enabled'],
            'stripe_bancontact_provider_enabled' => ['voyager_key' => 'payments.stripe_bancontact_provider_enabled'],
            'stripe_eps_provider_enabled' => ['voyager_key' => 'payments.stripe_eps_provider_enabled'],
            'stripe_giropay_provider_enabled' => ['voyager_key' => 'payments.stripe_giropay_provider_enabled'],
            'stripe_przelewy_provider_enabled' => ['voyager_key' => 'payments.stripe_przelewy_provider_enabled'],

            // PayPal
            'paypal_client_id' => ['voyager_key' => 'payments.paypal_client_id'],
            'paypal_secret' => ['voyager_key' => 'payments.paypal_secret'],
            'paypal_live_mode' => ['voyager_key' => 'payments.paypal_live_mode'],
            'paypal_checkout_disabled' => ['voyager_key' => 'payments.paypal_checkout_disabled'],
            'paypal_recurring_disabled' => ['voyager_key' => 'payments.paypal_recurring_disabled'],

            // Coinbase
            'coinbase_api_key' => ['voyager_key' => 'payments.coinbase_api_key'],
            'coinbase_webhook_key' => ['voyager_key' => 'payments.coinbase_webhook_key'],
            'coinbase_checkout_disabled' => ['voyager_key' => 'payments.coinbase_checkout_disabled'],

            // NowPayments
            'nowpayments_api_key' => ['voyager_key' => 'payments.nowpayments_api_key'],
            'nowpayments_ipn_secret_key' => ['voyager_key' => 'payments.nowpayments_ipn_secret_key'],
            'nowpayments_checkout_disabled' => ['voyager_key' => 'payments.nowpayments_checkout_disabled'],

            // CCBill
            'ccbill_account_number' => ['voyager_key' => 'payments.ccbill_account_number'],
            'ccbill_subaccount_number_recurring' => ['voyager_key' => 'payments.ccbill_subaccount_number_recurring'],
            'ccbill_subaccount_number_one_time' => ['voyager_key' => 'payments.ccbill_subaccount_number_one_time'],
            'ccbill_flex_form_id' => ['voyager_key' => 'payments.ccbill_flex_form_id'],
            'ccbill_salt_key' => ['voyager_key' => 'payments.ccbill_salt_key'],
            'ccbill_datalink_username' => ['voyager_key' => 'payments.ccbill_datalink_username'],
            'ccbill_datalink_password' => ['voyager_key' => 'payments.ccbill_datalink_password'],
            'ccbill_checkout_disabled' => ['voyager_key' => 'payments.ccbill_checkout_disabled'],
            'ccbill_recurring_disabled' => ['voyager_key' => 'payments.ccbill_recurring_disabled'],
            'ccbill_skip_subaccount_from_cancellations' => ['voyager_key' => 'payments.ccbill_skip_subaccount_from_cancellations'],

            // Verotel
            'verotel_merchant_id' => ['voyager_key' => 'payments.verotel_merchant_id'],
            'verotel_shop_id' => ['voyager_key' => 'payments.verotel_shop_id'],
            'verotel_signature_key' => ['voyager_key' => 'payments.verotel_signature_key'],
            'verotel_control_center_api_user' => ['voyager_key' => 'payments.verotel_control_center_api_user'],
            'verotel_control_center_api_password' => ['voyager_key' => 'payments.verotel_control_center_api_password'],
            'verotel_checkout_disabled' => ['voyager_key' => 'payments.verotel_checkout_disabled'],
            'verotel_recurring_disabled' => ['voyager_key' => 'payments.verotel_recurring_disabled'],

            // Paystack
            'paystack_secret_key' => ['voyager_key' => 'payments.paystack_secret_key'],
            'paystack_checkout_disabled' => ['voyager_key' => 'payments.paystack_checkout_disabled'],

            // Mercado
            'mercado_access_token' => ['voyager_key' => 'payments.mercado_access_token'],
            'mercado_checkout_disabled' => ['voyager_key' => 'payments.mercado_checkout_disabled'],

            // Withdrawal / Deposit
            'withdrawal_default_fee_percentage' => ['voyager_key' => 'payments.withdrawal_default_fee_percentage'],
            'withdrawal_allow_fees' => ['voyager_key' => 'payments.withdrawal_allow_fees'],
            'withdrawal_allow_only_for_verified' => ['voyager_key' => 'payments.withdrawal_allow_only_for_verified'],
            'withdrawal_enable_stripe_connect' => ['voyager_key' => 'payments.withdrawal_enable_stripe_connect'],
            'withdrawal_stripe_connect_webhooks_secret' => ['voyager_key' => 'payments.withdrawal_stripe_connect_webhooks_secret'],
            'withdrawal_min_amount' => ['voyager_key' => 'payments.withdrawal_min_amount'],
            'withdrawal_max_amount' => ['voyager_key' => 'payments.withdrawal_max_amount'],
            'withdrawal_payment_methods' => ['voyager_key' => 'payments.withdrawal_payment_methods'],
            'withdrawal_custom_message_box' => ['voyager_key' => 'payments.withdrawal_custom_message_box'],

            'deposit_min_amount' => ['voyager_key' => 'payments.deposit_min_amount'],
            'deposit_max_amount' => ['voyager_key' => 'payments.deposit_max_amount'],

            // Pricing
            'default_subscription_price' => ['voyager_key' => 'payments.default_subscription_price'],
            'minimum_subscription_price' => ['voyager_key' => 'payments.minimum_subscription_price'],
            'maximum_subscription_price' => ['voyager_key' => 'payments.maximum_subscription_price'],
            'min_tip_value' => ['voyager_key' => 'payments.min_tip_value'],
            'max_tip_value' => ['voyager_key' => 'payments.max_tip_value'],
            'min_ppv_post_price' => ['voyager_key' => 'payments.min_ppv_post_price'],
            'max_ppv_post_price' => ['voyager_key' => 'payments.max_ppv_post_price'],
            'min_ppv_message_price' => ['voyager_key' => 'payments.min_ppv_message_price'],
            'max_ppv_message_price' => ['voyager_key' => 'payments.max_ppv_message_price'],
            'min_ppv_stream_price' => ['voyager_key' => 'payments.min_ppv_stream_price'],
            'max_ppv_stream_price' => ['voyager_key' => 'payments.max_ppv_stream_price'],

            // Offline
            'offline_payments_owner' => ['voyager_key' => 'payments.offline_payments_owner'],
            'offline_payments_account_number' => ['voyager_key' => 'payments.offline_payments_account_number'],
            'offline_payments_bank_name' => ['voyager_key' => 'payments.offline_payments_bank_name'],
            'offline_payments_routing_number' => ['voyager_key' => 'payments.offline_payments_routing_number'],
            'offline_payments_iban' => ['voyager_key' => 'payments.offline_payments_iban'],
            'offline_payments_swift' => ['voyager_key' => 'payments.offline_payments_swift'],
            'offline_payments_custom_message_box' => ['voyager_key' => 'payments.offline_payments_custom_message_box'],
            'offline_payments_make_notes_field_mandatory' => ['voyager_key' => 'payments.offline_payments_make_notes_field_mandatory'],
            'offline_payments_minimum_attachments_required' => ['voyager_key' => 'payments.offline_payments_minimum_attachments_required'],

            // Extra
            'allow_manual_payments' => ['voyager_key' => 'payments.allow_manual_payments'],
            'disable_local_wallet_for_subscriptions' => ['voyager_key' => 'payments.disable_local_wallet_for_subscriptions'],
            'tax_info_dac7_enabled' => ['voyager_key' => 'payments.tax_info_dac7_enabled'],
            'tax_info_dac7_withdrawals_enforced' => ['voyager_key' => 'payments.tax_info_dac7_withdrawals_enforced'],
        ];
    }
};
