<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SecuritySettings extends Settings
{
    public bool $enable_2fa;

    public bool $default_2fa_on_register;

    public bool $allow_users_2fa_switch;

    public bool $enforce_app_ssl;

    public bool $allow_geo_blocking;

    public bool $enforce_email_valid_check;

    public ?string $abstract_api_key;

    public ?string $email_abstract_api_key;

    public ?string $email_domain_policy = 'allow_all';

    public array $email_allowedlist_domains = [];

    public array $email_blocklist_domains = [];

    public bool $enable_feature_rate_limits = false;

    public bool $rate_limit_posts_save_enabled = false;

    public int $rate_limit_posts_save_max_attempts = 10;

    public int $rate_limit_posts_save_decay_seconds = 60;

    public bool $rate_limit_posts_comments_add_enabled = false;

    public int $rate_limit_posts_comments_add_max_attempts = 20;

    public int $rate_limit_posts_comments_add_decay_seconds = 60;

    public bool $rate_limit_stories_store_enabled = false;

    public int $rate_limit_stories_store_max_attempts = 10;

    public int $rate_limit_stories_store_decay_seconds = 60;

    public bool $rate_limit_reels_store_enabled = false;

    public int $rate_limit_reels_store_max_attempts = 10;

    public int $rate_limit_reels_store_decay_seconds = 60;

    public bool $rate_limit_reels_comments_add_enabled = false;

    public int $rate_limit_reels_comments_add_max_attempts = 20;

    public int $rate_limit_reels_comments_add_decay_seconds = 60;

    public bool $rate_limit_streams_init_enabled = false;

    public int $rate_limit_streams_init_max_attempts = 5;

    public int $rate_limit_streams_init_decay_seconds = 60;

    public bool $rate_limit_stream_comments_add_enabled = false;

    public int $rate_limit_stream_comments_add_max_attempts = 20;

    public int $rate_limit_stream_comments_add_decay_seconds = 30;

    public bool $rate_limit_suggestions_generate_enabled = false;

    public int $rate_limit_suggestions_generate_max_attempts = 5;

    public int $rate_limit_suggestions_generate_decay_seconds = 60;

    public bool $rate_limit_profile_asset_generate_enabled = false;

    public int $rate_limit_profile_asset_generate_max_attempts = 3;

    public int $rate_limit_profile_asset_generate_decay_seconds = 60;

    public bool $rate_limit_messenger_send_enabled = false;

    public int $rate_limit_messenger_send_max_attempts = 20;

    public int $rate_limit_messenger_send_decay_seconds = 60;

    public string $captcha_driver;

    public ?string $recaptcha_site_key;

    public ?string $recaptcha_site_secret_key;

    public ?string $turnstile_site_key;

    public ?string $turnstile_site_secret_key;

    public ?string $hcaptcha_site_key;

    public ?string $hcaptcha_site_secret_key;

    public ?string $domain_policy = null;

    public array $allowedlist_domains = [];

    public array $blocklist_domains = [];

    public static function group(): string
    {
        return 'security';
    }
}
