<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ComplianceSettings extends Settings
{
    public bool $enable_cookies_box;

    public bool $enable_age_verification_dialog;

    public ?string $age_verification_cancel_url;

    public ?int $admin_approved_posts_limit;

    public ?int $minimum_posts_until_creator;

    public ?int $minimum_posts_deletion_limit;

    public ?int $monthly_posts_before_inactive;

    public bool $disable_creators_ppv_delete;

    public bool $allow_text_only_ppv;

    public bool $allow_user_email_changes = true;

    public bool $enable_release_forms = false;

    public bool $release_forms_verified_users_only = false;

    public ?string $release_forms_custom_message_box = null;

    public bool $enforce_tos_check_on_id_verify;

    public bool $enforce_media_agreement_on_id_verify;

    public bool $delete_verification_assets_after_review = false;

    public ?string $id_verify_custom_message_box = null;

    public ?string $tax_info_dac7_enabled = null;

    public ?string $tax_info_dac7_withdrawals_enforced = null;

    public ?string $tax_info_dac7_earnings_limit_before_enforced = "0";

    public bool $account_deletion_enabled = false;

    public string $account_deletion_mode = 'manual_review_with_cooldown';

    public int $account_deletion_cooldown_days = 14;

    public bool $account_deletion_allow_user_cancel = true;

    public bool $account_deletion_enforce_wallet_blocker = true;

    public bool $account_deletion_enforce_subscription_blocker = true;

    public bool $account_deletion_enforce_moderation_blocker = true;

    public bool $age_gate_enabled = false;

    public string $age_gate_driver = 'none';

    public string $age_gate_mode = 'checker';

    public ?string $age_gate_ageverif_public_key = null;

    public ?string $age_gate_ageverif_oauth_client_id = null;

    public ?string $age_gate_ageverif_oauth_client_secret = null;

    public string|array|null $age_gate_ageverif_challenges = [];

    public string $age_gate_country_detection_driver = 'none';

    public ?string $age_gate_abstract_api_key = null;

    public string $age_gate_countries_mode = 'everyone';

    public array $age_gate_countries = [];

    public bool $age_gate_require_unknown_country = true;

    public int $age_gate_minimum_age = 18;

    public int $age_gate_cookie_lifetime_days = 90;

    public static function group(): string
    {
        return 'compliance';
    }
}
