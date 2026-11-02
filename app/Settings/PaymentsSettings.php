<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class PaymentsSettings extends Settings
{
    // === Invoices & General ===
    public string $billing_details_collection = 'full';

    public ?string $invoices_enabled;

    public string $invoices_date_format = 'Y-m-d';

    public ?string $invoices_sender_name;

    public ?string $invoices_sender_country_name;

    public ?string $invoices_sender_street_address;

    public ?string $invoices_sender_state_name;

    public ?string $invoices_sender_city_name;

    public ?string $invoices_sender_postcode;

    public ?string $invoices_sender_company_number;

    public ?string $invoices_prefix;

    public ?string $currency_code;

    public ?string $currency_symbol;

    public ?string $currency_position;

    public ?float $default_subscription_price;

    public ?float $minimum_subscription_price;

    public ?float $maximum_subscription_price;

    public ?float $deposit_min_amount;

    public ?float $deposit_max_amount;

    public ?float $min_tip_value;

    public ?float $max_tip_value;

    public ?float $min_ppv_post_price;

    public ?float $max_ppv_post_price;

    public ?float $min_ppv_message_price;

    public ?float $max_ppv_message_price;

    public ?float $min_ppv_stream_price;

    public ?float $max_ppv_stream_price;

    // === Stripe ===
    public ?string $stripe_public_key;

    public ?string $stripe_secret_key;

    public ?string $stripe_webhooks_secret;

    public ?string $stripe_checkout_disabled;

    public ?string $stripe_recurring_disabled;

    public ?string $stripe_oxxo_provider_enabled;

    public ?string $stripe_ideal_provider_enabled;

    public ?string $stripe_blik_provider_enabled;

    public ?string $stripe_bancontact_provider_enabled;

    public ?string $stripe_eps_provider_enabled;

    public ?string $stripe_giropay_provider_enabled;

    public ?string $stripe_przelewy_provider_enabled;

    // === PayPal ===
    public ?string $paypal_client_id;

    public ?string $paypal_secret;

    public ?string $paypal_webhook_id = '';

    public ?string $paypal_live_mode;

    public ?string $paypal_checkout_disabled;

    public ?string $paypal_recurring_disabled;

    // === NowPayments ===
    public ?string $nowpayments_api_key;

    public ?string $nowpayments_ipn_secret_key;

    public ?string $nowpayments_checkout_disabled;

    // === CCBill ===
    public ?string $ccbill_account_number;

    public ?string $ccbill_subaccount_number_recurring;

    public ?string $ccbill_subaccount_number_one_time;

    public ?string $ccbill_flex_form_id;

    public ?string $ccbill_salt_key;

    public ?string $ccbill_datalink_username;

    public ?string $ccbill_datalink_password;

    public ?string $ccbill_checkout_disabled;

    public ?string $ccbill_recurring_disabled;

    public ?string $ccbill_skip_subaccount_from_cancellations;

    // === Verotel ===
    public ?string $verotel_merchant_id;

    public ?string $verotel_shop_id;

    public ?string $verotel_signature_key;

    public ?string $verotel_control_center_api_user;

    public ?string $verotel_control_center_api_password;

    public ?string $verotel_checkout_disabled;

    public ?string $verotel_recurring_disabled;

    // === Paystack ===
    public ?string $paystack_secret_key;

    public ?string $paystack_checkout_disabled;

    // === YooKassa ===
    public ?string $yookassa_shop_id = null;

    public ?string $yookassa_secret_key = null;

    public ?string $yookassa_checkout_disabled = null;

    // === Mollie ===
    public ?string $mollie_api_key = null;

    public ?string $mollie_checkout_disabled = null;

    // === Flutterwave ===
    public ?string $flutterwave_secret_key = null;

    public ?string $flutterwave_webhook_secret_hash = null;

    public ?string $flutterwave_checkout_disabled = null;

    // === CoinGate ===
    public ?string $coingate_api_token = null;

    public ?string $coingate_mode = null;

    public ?string $coingate_checkout_disabled = null;

    // === Xendit ===
    public ?string $xendit_secret_key = null;

    public ?string $xendit_webhook_token = null;

    public ?string $xendit_checkout_disabled = null;

    // === Paddle ===
    public ?string $paddle_api_key = null;

    public ?string $paddle_hosted_checkout_url = null;

    public ?string $paddle_webhooks_secret = null;

    public ?string $paddle_checkout_disabled = null;

    // === Crypto.com ===
    public ?string $cryptocom_secret_key = null;

    public ?string $cryptocom_webhooks_secret = null;

    public ?string $cryptocom_checkout_disabled = null;

    // === Mercado ===
    public ?string $mercado_access_token;

    public ?string $mercado_checkout_disabled;

    // === RazorPay ===
    public ?string $razorpay_api_key = null;

    public ?string $razorpay_api_secret = null;

    public ?string $razorpay_webhooks_secret = null;

    public ?string $razorpay_checkout_disabled = null;

    // === Withdrawals / Deposits ===
    public ?string $withdrawal_default_fee_percentage;

    public ?string $withdrawal_allow_fees;

    public ?string $withdrawal_allow_only_for_verified;

    public ?string $withdrawal_enable_stripe_connect;

    public ?string $withdrawal_stripe_connect_webhooks_secret;

    public ?string $withdrawal_min_amount;

    public ?string $withdrawal_max_amount;

    public ?string $withdrawal_payment_methods;

    public ?string $withdrawal_custom_message_box;

    // === Offline Payments ===
    public ?string $offline_payments_owner;

    public ?string $offline_payments_account_number;

    public ?string $offline_payments_bank_name;

    public ?string $offline_payments_routing_number;

    public ?string $offline_payments_iban;

    public ?string $offline_payments_swift;

    public ?string $offline_payments_custom_message_box;

    public ?string $offline_payments_make_notes_field_mandatory;

    public ?string $offline_payments_minimum_attachments_required;

    // === Referrals ===
    public bool $referrals_enabled = false;

    public int $referrals_fee_percentage = 0;

    public int $referrals_apply_for_months = 0;

    public int $referrals_fee_limit = 0;

    public string $referrals_default_link_page = 'profile';

    public bool $referrals_disable_for_non_verified = false;

    public bool $referrals_auto_follow_the_user = true;

    // === Purchase conversion tracking ===
    public bool $conversion_tracking_enabled = false;

    public string $conversion_tracking_method = 'browser';

    public string $conversion_tracking_counting_mode = 'first_site';

    // Safe fallback until the purchase-tracking expansion migration seeds this setting.
    public array $conversion_tracking_purchase_types = ['subscription'];

    public string $conversion_tracking_creator_scope = 'all';

    public array $conversion_tracking_creator_ids = [];

    public bool $conversion_tracking_include_renewals = false;

    public string $conversion_tracking_browser_code = '';

    public string $conversion_tracking_postback_url = '';

    public string $conversion_tracking_click_id_parameter = 'click_id';

    public int $conversion_tracking_attribution_days = 30;

    public bool $conversion_tracking_test_mode = true;

    // Migration-owned boundary for recognizing legacy purchases, never editable in the form.
    public string $conversion_tracking_history_cutoff = '';

    public string $conversion_tracking_purchase_history_cutoff = '';

    // === Extra ===
    public ?string $allow_manual_payments;

    public ?string $disable_local_wallet_for_subscriptions;

    public static function group(): string
    {
        return 'payments';
    }
}
